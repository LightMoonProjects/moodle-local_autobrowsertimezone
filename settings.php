<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
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
