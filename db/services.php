<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_autobrowsertimezone_update_timezone' => [
        'classname' => 'local_autobrowsertimezone\external\update_timezone',
        'description' => 'Update the current user profile timezone from a validated browser timezone.',
        'type' => 'write',
        'ajax' => true,
    ],
];
