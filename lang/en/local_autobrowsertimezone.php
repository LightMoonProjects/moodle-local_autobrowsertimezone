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

/**
 * English language strings for the Automatic browser timezone plugin.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['enabled'] = 'Enable automatic browser timezone';
$string['enabled_desc'] = 'When enabled, the plugin compares a logged-in user\'s Moodle profile timezone with the IANA timezone reported by their browser/device and updates the Moodle profile when they differ. No GPS, IP geolocation or external timezone service is used.';
$string['invalidtimezone'] = 'The browser reported a timezone that Moodle does not support.';
$string['pluginname'] = 'Automatic browser timezone';
$string['privacy:metadata:core_user'] = 'The plugin updates the existing timezone field in the current user\'s Moodle profile. The field is stored and managed by the core user subsystem.';
$string['privacy:metadata:core_user:timezone'] = 'The browser-reported IANA timezone written to the current user\'s Moodle profile timezone field.';
$string['reload'] = 'Reload after timezone change';
$string['reload_desc'] = 'Reload the current page after the profile timezone changes so server-rendered dates immediately use the new timezone.';
