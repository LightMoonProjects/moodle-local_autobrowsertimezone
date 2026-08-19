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
        .catch(Notification.exception);
};
