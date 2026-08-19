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
 * Administration settings for the Automatic browser timezone plugin.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_autobrowsertimezone',
        get_string('pluginname', 'local_autobrowsertimezone')
    );
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_autobrowsertimezone/enabled',
            get_string('enabled', 'local_autobrowsertimezone'),
            get_string('enabled_desc', 'local_autobrowsertimezone'),
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_autobrowsertimezone/reload',
            get_string('reload', 'local_autobrowsertimezone'),
            get_string('reload_desc', 'local_autobrowsertimezone'),
            1
        ));
    }
}
