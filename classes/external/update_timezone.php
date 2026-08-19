<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_autobrowsertimezone\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_autobrowsertimezone\local\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX service used to update the current user's timezone.
 *
 * @package local_autobrowsertimezone
 */
final class update_timezone extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'timezone' => new external_value(PARAM_RAW_TRIMMED, 'Browser IANA timezone identifier'),
        ]);
    }

    /**
     * Update the current user's timezone.
     *
     * @param string $timezone Browser IANA timezone identifier.
     * @return array
     */
    public static function execute(string $timezone): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'timezone' => $timezone,
        ]);

        require_login();

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:editownprofile', $context);

        return manager::update_current_user_timezone($params['timezone']);
    }

    /**
     * Describe return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'changed' => new external_value(PARAM_BOOL, 'Whether the timezone changed'),
            'timezone' => new external_value(PARAM_RAW_TRIMMED, 'Resulting Moodle profile timezone'),
            'reason' => new external_value(PARAM_ALPHA, 'updated, unchanged, or disabled'),
        ]);
    }
}
