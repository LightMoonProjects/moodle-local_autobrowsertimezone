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

namespace local_autobrowsertimezone\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy metadata provider for the Automatic browser timezone plugin.
 *
 * The plugin does not create plugin-owned user data tables and does not send data to an
 * external service. It does, however, cause the current user's existing core profile
 * timezone field to be updated, so that processing is declared as a core_user subsystem link.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe personal data processed by this plugin.
     *
     * @param collection $collection Privacy metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link(
            'core_user',
            ['timezone' => 'privacy:metadata:core_user:timezone'],
            'privacy:metadata:core_user'
        );

        return $collection;
    }
}
