<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Automatic browser timezone';
$string['enabled'] = 'Enable automatic browser timezone';
$string['enabled_desc'] = 'When enabled, logged-in users have their Moodle profile timezone aligned with the timezone reported by their browser/device.';
$string['reload'] = 'Reload after timezone change';
$string['reload_desc'] = 'Reload the current page after the profile timezone changes so server-rendered dates immediately use the new timezone.';
$string['invalidtimezone'] = 'The browser reported a timezone that Moodle does not support.';
$string['privacy:metadata'] = 'The Automatic browser timezone plugin does not store personal data in its own tables or send data to external services. It may update the current user\'s existing Moodle profile timezone field.';
