<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_autobrowsertimezone\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Runtime and profile update logic.
 *
 * @package local_autobrowsertimezone
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

        // Match Moodle core's forced-timezone precedence: anything other than 99 overrides user timezones.
        if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
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
     * @return array{changed:bool,timezone:string,reason:string}
     */
    public static function update_current_user_timezone(string $timezone): array {
        global $CFG, $USER;

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

        if ((string)($USER->timezone ?? '99') === $timezone) {
            return [
                'changed' => false,
                'timezone' => $timezone,
                'reason' => 'unchanged',
            ];
        }

        require_once($CFG->dirroot . '/user/lib.php');

        $update = (object)[
            'id' => (int)$USER->id,
            'timezone' => $timezone,
        ];

        // Do not touch the password; trigger the standard user_updated event.
        user_update_user($update, false, true);

        // Keep the current request's user object consistent with the database.
        $USER->timezone = $timezone;

        return [
            'changed' => true,
            'timezone' => $timezone,
            'reason' => 'updated',
        ];
    }
}
