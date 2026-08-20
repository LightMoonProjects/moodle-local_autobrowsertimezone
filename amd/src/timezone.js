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
 * Prevent repeated failing calls for the same mismatch during a browser session.
 *
 * @param {string} key Storage key.
 * @returns {boolean}
 */
const markAttempt = (key) => {
    try {
        if (window.sessionStorage.getItem(key) === '1') {
            return false;
        }

        window.sessionStorage.setItem(key, '1');
    } catch {
        // Session storage may be unavailable; continue without the loop guard.
    }

    return true;
};

/**
 * Release a previously-set attempt marker so a later page load may retry the
 * same mismatch.
 *
 * @param {string} key Storage key.
 * @returns {void}
 */
const clearAttempt = (key) => {
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Session storage may be unavailable; nothing to release.
    }
};

/**
 * Whether an Ajax.call() rejection represents a deterministic server-side
 * outcome (Moodle exception) rather than a transient transport failure.
 *
 * core/ajax rejects in two distinct ways: a failed HTTP/transport request
 * (network drop, timeout, non-Moodle error) rejects with a plain string or
 * generic error that carries no `errorcode`; a request that reached Moodle
 * and was refused there (for example this plugin's own invalid_parameter_exception
 * for an unsupported browser timezone) rejects with the server's exception
 * object, which always has an `errorcode` string. Retrying the latter with
 * the same browser/profile timezone pair would fail identically, so the
 * attempt marker must stay set; only the former is safe to retry later.
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

    if (!browserTimezone || browserTimezone === currentTimezone) {
        return;
    }

    const attemptKey = [
        'local_autobrowsertimezone',
        currentTimezone,
        browserTimezone,
    ].join(':');

    if (!markAttempt(attemptKey)) {
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
            if (result.changed && safeConfig.reload) {
                window.location.reload();
            }
            return result;
        })
        .catch((error) => {
            if (!isPermanentServerOutcome(error)) {
                // A transient transport failure, not a deterministic server
                // outcome: allow a later page load to retry this mismatch.
                clearAttempt(attemptKey);
            }
            Notification.exception(error);
        });
};
