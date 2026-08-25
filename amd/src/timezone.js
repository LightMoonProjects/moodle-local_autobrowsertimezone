// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Detect the browser/device timezone and synchronise it with the Moodle user profile.
 *
 * @module     local_autobrowsertimezone/timezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

// Prevent duplicate requests for the same mismatch within this loaded document
// only; unlike sessionStorage, this disappears naturally on unload/navigation.
const inFlight = new Set();

/**
 * Resolve the browser's IANA timezone.
 *
 * @returns {string}
 */
const getBrowserTimezone = () => {
    try {
        if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
            return '';
        }

        return String(Intl.DateTimeFormat().resolvedOptions().timeZone || '').trim();
    } catch {
        return '';
    }
};

/**
 * Per-mismatch sessionStorage attempt states.
 *
 * Persisted state machine (see docs/RETRY_GUARD_QA.md for the full manual QA mapping):
 *
 *   (absent)  -- no settled cross-page state yet for this exact mismatch key.
 *      |
 *      | inFlight claims the key only in this loaded document, so a duplicate
 *      | init() cannot start a second concurrent request. No persistent
 *      | sessionStorage state is written merely because a request started.
 *      |
 *      | request resolves successfully or becomes unchanged -> clear any
 *      | prior retry marker (no further cross-page state required)
 *      v
 *   (absent)
 *
 *   (absent) -- request rejects with NO `errorcode` on a first attempt --> RETRY
 *
 *   RETRY -- a later page load calls init() again for the same mismatch -->
 *      exactly one more request:
 *        - resolves successfully -> (moot), as above;
 *        - rejects with an `errorcode` -> stays GUARDED (permanent);
 *        - resolves with changed: false for a deterministic application
 *          outcome such as authrejected/disabled -> GUARDED (permanent);
 *        - rejects again with no `errorcode` -> stays GUARDED (permanent) --
 *          the one-retry budget for this mismatch is now spent, so it will
 *          NOT be downgraded back to RETRY a second time;
 *        - unload/navigation before settlement -> remains RETRY, because no
 *          persistent pre-request state is written or consumed.
 *
 * This bounds a persistent generic/transport failure (repeated proxy denial,
 * repeated HTTP 500, malformed response, etc.) to at most one retryable
 * attempt per mismatch per browser session, while a genuine one-off transient
 * failure still gets exactly one later chance to succeed.
 */
const ATTEMPT_STATE_GUARDED = 'guarded';
const ATTEMPT_STATE_RETRY = 'retry';

/**
 * Read the current attempt state for a mismatch key.
 *
 * @param {string} key Storage key.
 * @returns {string|null}
 */
const readAttemptState = (key) => {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
};

/**
 * Write the attempt state for a mismatch key.
 *
 * @param {string} key Storage key.
 * @param {string} state One of the ATTEMPT_STATE_* constants.
 * @returns {void}
 */
const writeAttemptState = (key, state) => {
    try {
        window.sessionStorage.setItem(key, state);
    } catch {
        // Session storage may be unavailable; continue without the loop guard.
    }
};

/**
 * Remove any persisted attempt state for a mismatch key.
 *
 * @param {string} key Storage key.
 * @returns {void}
 */
const clearAttemptState = (key) => {
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Session storage may be unavailable; continue without the loop guard.
    }
};

/**
 * Start an in-flight attempt for this exact mismatch, if one is currently permitted.
 *
 * Legacy sessionStorage values ('1') from a previous plugin version are
 * treated the same as ATTEMPT_STATE_GUARDED: already permanently guarded.
 *
 * @param {string} key Storage key.
 * @returns {{isRetry: boolean}|null} Null when no attempt is currently permitted.
 */
const beginAttempt = (key) => {
    const state = readAttemptState(key);

    if (state !== null && state !== ATTEMPT_STATE_RETRY) {
        // Either explicitly guarded, or an unrecognised/legacy value: treat
        // conservatively as already guarded rather than retrying forever.
        return null;
    }

    if (inFlight.has(key)) {
        return null;
    }

    const isRetry = state === ATTEMPT_STATE_RETRY;
    inFlight.add(key);

    return {isRetry};
};

/**
 * Finish the in-flight attempt for this loaded document.
 *
 * @param {string} key Storage key.
 * @returns {void}
 */
const finishAttempt = (key) => {
    inFlight.delete(key);
};

/**
 * Persist the settled outcome of a successful server response.
 *
 * changed:false results other than 'unchanged' are treated as deterministic
 * application/policy outcomes under the current server contract, including
 * 'authrejected' and 'disabled'. Those must remain guarded so they do not
 * repeat on every later page load.
 *
 * @param {string} key Storage key.
 * @param {Object} result Settled result returned by the external function.
 * @returns {void}
 */
const persistSuccessfulOutcome = (key, result) => {
    if (result.changed || result.reason === 'unchanged') {
        clearAttemptState(key);
        return;
    }

    writeAttemptState(key, ATTEMPT_STATE_GUARDED);
};

/**
 * Persist the settled outcome of a rejected server response.
 *
 * @param {string} key Storage key.
 * @param {boolean} isRetry Whether the rejected request was already the one bounded retry.
 * @param {*} error The rejection reason from Ajax.call().
 * @returns {void}
 */
const persistRejectedOutcome = (key, isRetry, error) => {
    if (isPermanentServerOutcome(error) || isRetry) {
        writeAttemptState(key, ATTEMPT_STATE_GUARDED);
        return;
    }

    writeAttemptState(key, ATTEMPT_STATE_RETRY);
};

/**
 * Whether an Ajax.call() rejection represents a deterministic server-side
 * outcome (Moodle exception) rather than a generic transport/request failure.
 *
 * core/ajax rejects in two distinct ways: a failed HTTP/transport request
 * (network drop, timeout, non-Moodle error) rejects with a plain string or
 * generic error that carries no `errorcode`; a request that reached Moodle
 * and was refused there (for example this plugin's own invalid_parameter_exception
 * for an unsupported browser timezone) rejects with the server's exception
 * object, which always has an `errorcode` string. Retrying the latter with
 * the same browser/profile timezone pair would fail identically, so it is
 * always treated as permanent; a rejection with no `errorcode` may be a
 * genuinely transient failure, but is only ever given one bounded retry (see
 * the state machine above) since it could equally be a persistent
 * transport-level problem that would otherwise generate a request on every
 * page load forever.
 *
 * @param {*} error The rejection reason from Ajax.call().
 * @returns {boolean}
 */
const isPermanentServerOutcome = (error) => {
    return Boolean(error) && typeof error.errorcode === 'string' && error.errorcode !== '';
};

/**
 * Initialise automatic browser timezone synchronisation.
 *
 * @param {Object} config Runtime config.
 * @returns {void}
 */
export const init = (config) => {
    const safeConfig = config || {};
    const browserTimezone = getBrowserTimezone();
    const currentTimezone = String(safeConfig.currentTimezone || '99');
    // Included in the guard key below so retry/loop state cannot leak
    // between different Moodle accounts sharing the same browser tab (Issue
    // #18): sessionStorage survives a logout/login in the same tab, but is
    // not itself scoped to the authenticated user.
    const userId = String(safeConfig.userid || '0');

    if (!browserTimezone || browserTimezone === currentTimezone) {
        return;
    }

    const attemptKey = [
        'local_autobrowsertimezone',
        userId,
        currentTimezone,
        browserTimezone,
    ].join(':');

    const attempt = beginAttempt(attemptKey);
    if (!attempt) {
        return;
    }

    const request = {
        methodname: 'local_autobrowsertimezone_update_timezone',
        args: {
            timezone: browserTimezone,
        },
    };

    Ajax.call([request])[0]
        .then((result) => {
            persistSuccessfulOutcome(attemptKey, result);
            finishAttempt(attemptKey);

            if (result.changed && safeConfig.reload) {
                window.location.reload();
            }

            return result;
        })
        .catch((error) => {
            // First generic/transport failure (not a Moodle exception, and
            // this was not already the bounded retry) gets exactly one later
            // page-load retry. A second generic failure, or any deterministic
            // Moodle/application outcome, remains guarded for the rest of the
            // session.
            persistRejectedOutcome(attemptKey, attempt.isRetry, error);
            finishAttempt(attemptKey);
            Notification.exception(error);

            return null;
        });
};
