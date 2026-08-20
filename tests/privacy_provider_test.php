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

namespace local_autobrowsertimezone;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use local_autobrowsertimezone\privacy\provider;

/**
 * Tests for the Privacy API provider.
 *
 * The plugin creates no plugin-owned personal-data tables and sends no data
 * externally; it only causes an existing core_user field to be updated. This
 * is a regression test for the metadata declaration and for Moodle Plugin
 * privacy registry compliance (Issue #14) -- it does not replace running
 * Moodle's own Privacy API validation (moodle-plugin-ci validate, and the
 * site administration Data registry / Plugin privacy registry pages) against
 * a real site, see docs/RELEASE_QA.md.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class privacy_provider_test extends \advanced_testcase {
    /**
     * The provider must declare exactly one core_user subsystem link
     * (no plugin-owned tables) with a timezone field mapping and summary.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_get_metadata_declares_core_user_subsystem_link(): void {
        $this->resetAfterTest();

        $collection = provider::get_metadata(new collection('local_autobrowsertimezone'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);

        $link = $items[0];
        $this->assertInstanceOf(\core_privacy\local\metadata\types\subsystem_link::class, $link);
        $this->assertSame('core_user', $link->get_name());
        $this->assertSame(
            ['timezone' => 'privacy:metadata:core_user:timezone'],
            $link->get_privacy_fields()
        );
        $this->assertSame('privacy:metadata:core_user', $link->get_summary());

        // The lang strings referenced by the metadata must actually exist,
        // otherwise Moodle's Privacy API rendering (e.g. the Data registry
        // page) would show a broken/untranslated identifier.
        $this->assertTrue(get_string_manager()->string_exists('privacy:metadata:core_user', 'local_autobrowsertimezone'));
        $this->assertTrue(
            get_string_manager()->string_exists('privacy:metadata:core_user:timezone', 'local_autobrowsertimezone')
        );
    }

    /**
     * This is the exact regression for Issue #14: Moodle's own privacy manager, not just
     * class_implements(), must consider the component registry-compliant.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_component_is_compliant_with_privacy_registry(): void {
        $this->resetAfterTest();

        $manager = new \core_privacy\manager();

        $this->assertTrue($manager->component_is_compliant('local_autobrowsertimezone'));
    }

    /**
     * The provider must implement the concrete plugin\provider contract (which satisfies
     * data_provider transitively) and must not use null_provider, which would misrepresent
     * a plugin that does process/write personal data through core_user. It must also
     * implement core_userlist_provider: Moodle's Plugin privacy registry
     * (tool_dataprivacy\metadata_registry) separately flags "Userlist provider missing" for
     * any core_user_data_provider descendant (which plugin\provider is) that does not also
     * implement core_userlist_provider, independently of component_is_compliant().
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_provider_uses_plugin_provider_contract_not_null_provider(): void {
        $rc = new \ReflectionClass(provider::class);

        $this->assertTrue($rc->implementsInterface(\core_privacy\local\request\plugin\provider::class));
        $this->assertTrue($rc->implementsInterface(\core_privacy\local\request\data_provider::class));
        $this->assertTrue($rc->implementsInterface(\core_privacy\local\request\core_userlist_provider::class));
        $this->assertFalse($rc->implementsInterface(\core_privacy\local\metadata\null_provider::class));
    }

    /**
     * The plugin owns no independently retrievable personal-data record, so it must not
     * falsely report a context for a user merely because core_user has data for them.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_get_contexts_for_userid_reports_no_independently_owned_contexts(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['timezone' => 'Europe/London']);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(0, $contextlist);
    }

    /**
     * export_user_data() has nothing independently owned to export. Exercise it directly
     * (Moodle's manager would not otherwise call it, since get_contexts_for_userid() returns
     * no contexts) and prove the user's timezone and an unrelated profile field are both left
     * untouched -- core_user's own provider remains solely responsible for exporting them.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_export_user_data_does_not_mutate_user_profile(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'timezone' => 'Europe/London',
            'city' => 'privacy-export-sentinel',
        ]);
        $usercontext = \context_user::instance((int) $user->id);

        $approvedcontextlist = new approved_contextlist(
            \core_user::get_user((int) $user->id),
            'local_autobrowsertimezone',
            [$usercontext->id]
        );

        provider::export_user_data($approvedcontextlist);

        $after = \core_user::get_user((int) $user->id, '*', MUST_EXIST);
        $this->assertSame('Europe/London', $after->timezone);
        $this->assertSame('privacy-export-sentinel', $after->city);
    }

    /**
     * delete_data_for_user() has nothing independently owned to delete. Prove it leaves the
     * user's timezone and an unrelated profile field unchanged.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_delete_data_for_user_does_not_mutate_user_profile(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'timezone' => 'Australia/Sydney',
            'city' => 'privacy-delete-sentinel',
        ]);
        $usercontext = \context_user::instance((int) $user->id);

        $approvedcontextlist = new approved_contextlist(
            \core_user::get_user((int) $user->id),
            'local_autobrowsertimezone',
            [$usercontext->id]
        );

        provider::delete_data_for_user($approvedcontextlist);

        $after = \core_user::get_user((int) $user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $after->timezone);
        $this->assertSame('privacy-delete-sentinel', $after->city);
    }

    /**
     * delete_data_for_all_users_in_context() has nothing independently owned to delete. Prove
     * it leaves the user's timezone and an unrelated profile field unchanged when called for
     * that user's own context.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_delete_data_for_all_users_in_context_does_not_mutate_user_profile(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'timezone' => 'Pacific/Auckland',
            'city' => 'privacy-context-delete-sentinel',
        ]);
        $usercontext = \context_user::instance((int) $user->id);

        provider::delete_data_for_all_users_in_context($usercontext);

        $after = \core_user::get_user((int) $user->id, '*', MUST_EXIST);
        $this->assertSame('Pacific/Auckland', $after->timezone);
        $this->assertSame('privacy-context-delete-sentinel', $after->city);
    }

    /**
     * The plugin owns no independently retrievable users of its own within any context, so it
     * must not falsely add a user to the userlist merely because core_user has a timezone for
     * them.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_get_users_in_context_reports_no_independently_owned_users(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']);
        $usercontext = \context_user::instance((int) $user->id);

        $userlist = new userlist($usercontext, 'local_autobrowsertimezone');
        provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist);
    }

    /**
     * delete_data_for_users() has nothing independently owned to delete. Prove it leaves the
     * users' timezones and an unrelated profile field unchanged.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_delete_data_for_users_does_not_mutate_user_profile(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user([
            'timezone' => 'Europe/Berlin',
            'city' => 'privacy-userlist-delete-sentinel-1',
        ]);
        $user2 = $this->getDataGenerator()->create_user([
            'timezone' => 'America/Chicago',
            'city' => 'privacy-userlist-delete-sentinel-2',
        ]);
        $usercontext1 = \context_user::instance((int) $user1->id);

        $approveduserlist = new approved_userlist(
            $usercontext1,
            'local_autobrowsertimezone',
            [$user1->id, $user2->id]
        );

        provider::delete_data_for_users($approveduserlist);

        $after1 = \core_user::get_user((int) $user1->id, '*', MUST_EXIST);
        $after2 = \core_user::get_user((int) $user2->id, '*', MUST_EXIST);
        $this->assertSame('Europe/Berlin', $after1->timezone);
        $this->assertSame('privacy-userlist-delete-sentinel-1', $after1->city);
        $this->assertSame('America/Chicago', $after2->timezone);
        $this->assertSame('privacy-userlist-delete-sentinel-2', $after2->city);
        $this->assertFalse((bool) $after1->deleted);
        $this->assertFalse((bool) $after2->deleted);
    }

    /**
     * This is the exact regression for the "Userlist provider missing" staging finding: Moodle's
     * own Plugin privacy registry data (tool_dataprivacy\metadata_registry::get_registry_metadata(),
     * the same source the Plugin privacy registry page renders from) must not flag
     * 'userlistnoncompliance' for this component now that core_userlist_provider is implemented.
     * This exercises the real registry logic rather than duplicating it, without depending on
     * HTML rendering.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_registry_metadata_does_not_flag_userlist_noncompliance(): void {
        $this->resetAfterTest();

        $registry = new \tool_dataprivacy\metadata_registry();
        $data = $registry->get_registry_metadata();

        // get_registry_metadata() returns a numerically-indexed list of plugin-type branches,
        // each with a 'plugins' list keyed the same way (not by component name) -- mirror the
        // reduction Moodle's own admin/tool/dataprivacy/tests/metadata_registry_test.php uses
        // to look up a single component's entry.
        $entry = null;
        foreach ($data as $branch) {
            if ($branch['plugin_type_raw'] !== 'local') {
                continue;
            }
            foreach ($branch['plugins'] as $plugindata) {
                if ($plugindata['raw_component'] === 'local_autobrowsertimezone') {
                    $entry = $plugindata;
                    break 2;
                }
            }
        }

        $this->assertNotNull($entry, 'local_autobrowsertimezone was not found in the Plugin privacy registry data.');
        $this->assertTrue($entry['compliant']);
        $this->assertArrayNotHasKey('userlistnoncompliance', $entry);
    }
}
