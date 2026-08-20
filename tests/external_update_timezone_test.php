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

use local_autobrowsertimezone\external\update_timezone;

/**
 * Tests for the update_timezone external function's Moodle External API contract.
 *
 * Moodle's PHPUnit environment defines CLI_SCRIPT, which manager::should_run()
 * deliberately treats as ineligible (browser timezone sync must never run for
 * CLI requests). That means execute() always reaches manager and gets back a
 * well-formed 'disabled' result in this test environment, regardless of the
 * caller's timezone value: it cannot demonstrate manager's own timezone
 * validation/persistence behaviour here (see tests/manager_test.php for that).
 * What these tests demonstrate instead is the thing issue #3 actually changed:
 * that removing require_login() did not weaken authentication, context, or
 * capability enforcement at the external-function boundary.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(update_timezone::class)]
final class external_update_timezone_test extends \advanced_testcase {
    /**
     * An authorised logged-in user reaches manager unimpeded: parameter
     * validation, context validation and capability enforcement all succeed,
     * and the response conforms to the declared external_single_structure.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_execute_succeeds_for_authorised_user(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        $result = update_timezone::execute('Australia/Sydney');

        // Proves the external return structure is still honoured.
        $clean = update_timezone::clean_returnvalue(update_timezone::execute_returns(), $result);
        $this->assertArrayHasKey('changed', $clean);
        $this->assertArrayHasKey('timezone', $clean);
        $this->assertArrayHasKey('reason', $clean);

        // CLI_SCRIPT makes manager::should_run() ineligible in PHPUnit; this
        // confirms execution reached manager (no auth/context/capability
        // exception was thrown for an authorised user) rather than that a
        // timezone change was actually persisted.
        $this->assertFalse($clean['changed']);
        $this->assertSame('disabled', $clean['reason']);
    }

    /**
     * A user whose role has moodle/user:editownprofile explicitly denied at
     * system context must be rejected server-side, regardless of client-side
     * eligibility checks or UI visibility.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_execute_rejects_capability_denied_user(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability(
            'moodle/user:editownprofile',
            CAP_PROHIBIT,
            $roleid,
            \context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);

        try {
            update_timezone::execute('Australia/Sydney');
        } finally {
            $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
            $this->assertSame('99', $dbuser->timezone);
        }
    }

    /**
     * The guest account is explicitly prohibited from moodle/user:editownprofile
     * by its role archetype; the external function must not let a guest session
     * reach manager.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_execute_rejects_guest_user(): void {
        $this->resetAfterTest();

        $this->setUser(guest_user());

        $this->expectException(\required_capability_exception::class);

        update_timezone::execute('Australia/Sydney');
    }

    /**
     * With no logged-in user at all, validate_context() must still enforce
     * login (via its own internal, AJAX-safe require_login() call) even
     * though update_timezone::execute() no longer calls require_login()
     * itself. This is the regression this issue guards against.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_execute_rejects_unauthenticated_caller(): void {
        $this->resetAfterTest();

        $this->setUser(0);

        $this->expectException(\require_login_exception::class);

        update_timezone::execute('Australia/Sydney');
    }

    /**
     * An unsupported/garbage timezone value must not bypass authorisation and
     * must not be persisted. Under CLI_SCRIPT this exercises the same
     * 'disabled' safe-result path as a valid value (manager's own PARAM_TIMEZONE
     * / supported-timezone validation is covered separately in
     * tests/manager_test.php); what matters here is that execute() still
     * requires a valid, capable, in-context caller and never persists.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_execute_does_not_persist_for_authorised_user_with_invalid_timezone(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        $result = update_timezone::execute('Not/A_Timezone');

        $this->assertFalse($result['changed']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('99', $dbuser->timezone);
    }
}
