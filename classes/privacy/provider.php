<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_autobrowsertimezone\privacy;

use core_privacy\local\metadata\null_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider.
 *
 * The plugin stores no user data in plugin-owned tables.
 *
 * @package local_autobrowsertimezone
 */
final class provider implements null_provider {
    /**
     * Reason no plugin-owned personal data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
