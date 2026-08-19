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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Web service declarations for the Automatic browser timezone plugin.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_autobrowsertimezone_update_timezone' => [
        'classname' => 'local_autobrowsertimezone\external\update_timezone',
        'description' => 'Update the current user profile timezone from a validated browser timezone.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/user:editownprofile',
    ],
];
