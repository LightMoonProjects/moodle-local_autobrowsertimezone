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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Privacy provider for the Automatic browser timezone plugin.
 *
 * The plugin creates no plugin-owned user data tables, preferences, file areas, cache
 * entries or external-service records. It causes the current user's existing core profile
 * timezone field to be updated, which is declared as a core_user subsystem link.
 *
 * core_privacy\manager::component_is_compliant() only accepts a component implementing
 * \core_privacy\local\metadata\provider (alone) if it either implements null_provider, or
 * also implements the \core_privacy\local\request\data_provider contract. null_provider is
 * not correct here because the plugin does process/write personal data (through core_user);
 * data_provider and its intermediate ancestor shared_data_provider/core_data_provider are
 * documented in Moodle core as marker interfaces not meant to be implemented directly. The
 * narrowest concrete Moodle-supported descendant for a plugin (not a subsystem or subplugin)
 * is \core_privacy\local\request\plugin\provider, which extends core_user_data_provider and
 * therefore satisfies data_provider transitively.
 *
 * The persistent timezone value itself belongs to core_user and is already exported and
 * deleted/anonymised by core_user's own privacy provider (see user/classes/privacy/provider.php)
 * as part of its own responsibility for the {user} table. This plugin owns no independently
 * retrievable personal-data record of its own, so its request-provider methods are no-ops:
 * get_contexts_for_userid() reports no contexts, and the export/delete methods do nothing,
 * rather than duplicating or interfering with core_user's export/deletion of the timezone
 * field or any other core profile data.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {
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

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * This plugin owns no independently retrievable personal-data record of its own, so it
     * reports no contexts; the user's timezone is exported through core_user's own provider.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * Nothing is independently owned by this plugin to export.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * Nothing is independently owned by this plugin to delete.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * Nothing is independently owned by this plugin to delete.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
