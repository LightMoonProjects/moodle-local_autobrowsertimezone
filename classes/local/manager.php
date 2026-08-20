<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_autobrowsertimezone\local;

/**
 * Runtime and profile update logic.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /**
     * Whether browser timezone synchronisation should run for this request.
     *
     * @return bool
     */
    public static function should_run(): bool {
        global $CFG, $USER;

        if (during_initial_install()) {
            return false;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return false;
        }

        if (!(bool)get_config('local_autobrowsertimezone', 'enabled')) {
            return false;
        }

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        if (empty($USER->id) || !empty($USER->deleted) || !empty($USER->suspended)) {
            return false;
        }

        // Never mutate the impersonated user's profile using the operator's browser timezone.
        if (isloggedinas()) {
            return false;
        }

        // Remote MNet profiles are managed by their home Moodle site.
        if (is_mnet_remote_user($USER)) {
            return false;
        }

        // Respect authentication-plugin ownership and locking of the core timezone profile field.
        if (!self::can_update_timezone_for_auth_plugin()) {
            return false;
        }

        // Moodle's forced timezone deliberately overrides individual profile timezone settings.
        if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
            return false;
        }

        return true;
    }

    /**
     * Whether the current authentication plugin allows this timezone field to be changed.
     *
     * This mirrors the lock handling used by Moodle's own user edit form.
     *
     * @return bool
     */
    private static function can_update_timezone_for_auth_plugin(): bool {
        global $USER;

        $authplugin = get_auth_plugin((string)($USER->auth ?? 'manual'));

        if (!$authplugin->can_edit_profile()) {
            return false;
        }

        $lock = (string)($authplugin->config->field_lock_timezone ?? 'unlocked');

        if ($lock === 'locked') {
            return false;
        }

        if ($lock === 'unlockedifempty' && (string)($USER->timezone ?? '') !== '') {
            return false;
        }

        return true;
    }

    /**
     * Queue the browser timezone detector on eligible pages.
     *
     * @return void
     */
    public static function queue_browser_timezone_check(): void {
        global $PAGE, $USER;

        if (!self::should_run()) {
            return;
        }

        $PAGE->requires->js_call_amd(
            'local_autobrowsertimezone/timezone',
            'init',
            [[
                'currentTimezone' => (string)($USER->timezone ?? '99'),
                'reload' => (bool)get_config('local_autobrowsertimezone', 'reload'),
            ]]
        );
    }

    /**
     * Check whether Moodle exposes a timezone as a supported profile choice.
     *
     * @param string $timezone IANA timezone ID.
     * @return bool
     */
    public static function is_supported_timezone(string $timezone): bool {
        if ($timezone === '' || $timezone === '99') {
            return false;
        }

        return array_key_exists($timezone, \core_date::get_list_of_timezones());
    }

    /**
     * Update the current user's timezone.
     *
     * @param string $timezone Browser-provided timezone.
     * @return array{changed: bool, timezone: string, reason: string}
     */
    public static function update_current_user_timezone(string $timezone): array {
        global $USER;

        if (!self::should_run()) {
            return [
                'changed' => false,
                'timezone' => (string)($USER->timezone ?? '99'),
                'reason' => 'disabled',
            ];
        }

        $timezone = (string)clean_param($timezone, PARAM_TIMEZONE);

        if (!self::is_supported_timezone($timezone)) {
            throw new \invalid_parameter_exception(
                get_string('invalidtimezone', 'local_autobrowsertimezone')
            );
        }

        // Load the authoritative pre-change record (rather than trusting the
        // cached $USER global) so a concurrent request that already applied
        // the same change is observed as a no-op, and so the active
        // authentication plugin below receives a genuine old/new pair.
        $olduser = \core_user::get_user((int)$USER->id, '*', MUST_EXIST);

        if ((string)($olduser->timezone ?? '99') === $timezone) {
            return [
                'changed' => false,
                'timezone' => $timezone,
                'reason' => 'unchanged',
            ];
        }

        $authplugin = get_auth_plugin((string)$olduser->auth);
        $result = self::apply_timezone_change($olduser, $timezone, $authplugin);

        if ($result['changed']) {
            // Keep the current request's user object consistent with the database.
            $USER->timezone = $result['timezone'];
        }

        return $result;
    }

    /**
     * Persist a validated timezone change, honouring the active
     * authentication plugin's user_update() contract.
     *
     * Mirrors the old/new user record pattern Moodle's own profile-edit
     * flow (user/edit.php) uses: the active authentication plugin is given
     * the normal opportunity to accept, propagate, or reject the change
     * before the local {user} record is touched. Moodle core's
     * user_update_user() never calls the authentication plugin itself, so
     * this step cannot be skipped without silently diverging from an
     * authoritative external source (see auth_plugin_base::user_update()).
     *
     * @param \stdClass $olduser Full pre-change user record.
     * @param string $timezone Already-validated target timezone.
     * @param \auth_plugin_base $authplugin Active authentication plugin for $olduser.
     * @return array{changed: bool, timezone: string, reason: string}
     */
    public static function apply_timezone_change(
        \stdClass $olduser,
        string $timezone,
        \auth_plugin_base $authplugin
    ): array {
        global $CFG;

        $newuser = clone $olduser;
        $newuser->timezone = $timezone;

        if (!$authplugin->user_update($olduser, $newuser)) {
            // The auth plugin rejected or failed to propagate the change upstream;
            // do not commit a local-only timezone that would diverge from it.
            return [
                'changed' => false,
                'timezone' => (string)($olduser->timezone ?? '99'),
                'reason' => 'authrejected',
            ];
        }

        require_once($CFG->dirroot . '/user/lib.php');

        // Do not touch the password; trigger Moodle's standard user_updated event.
        user_update_user($newuser, false, true);

        return [
            'changed' => true,
            'timezone' => $timezone,
            'reason' => 'updated',
        ];
    }
}
