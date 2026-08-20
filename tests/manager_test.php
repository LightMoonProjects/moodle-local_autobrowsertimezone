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

defined('MOODLE_INTERNAL') || die();

use local_autobrowsertimezone\local\manager;

require_once(__DIR__ . '/fixtures/fake_auth_plugin.php');

/**
 * Tests for timezone validation logic and the authentication-plugin update contract.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class manager_test extends \advanced_testcase {
    /**
     * Invoke the authentication-plugin field policy without the request-level CLI guard.
     *
     * PHPUnit correctly defines CLI_SCRIPT, so manager::should_run() must remain
     * false in tests. This helper exercises the narrower lock/can-edit policy that
     * Issue #2 must preserve without weakening the production CLI safeguard.
     *
     * @return bool
     */
    private function auth_plugin_allows_timezone_update(): bool {
        $method = new \ReflectionMethod(manager::class, 'can_update_timezone_for_auth_plugin');

        return (bool)$method->invoke(null);
    }

    /**
     * Moodle timezone choices accept normal IANA zones and reject unknown values.
     *
     * @return void
     */
    // Moodle 4.5's CodeSniffer predates PHPUnit attribute-based coverage metadata.
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_supported_timezone_validation(): void {
        $this->assertTrue(manager::is_supported_timezone('Australia/Sydney'));
        $this->assertTrue(manager::is_supported_timezone('Europe/London'));
        $this->assertFalse(manager::is_supported_timezone(''));
        $this->assertFalse(manager::is_supported_timezone('99'));
        $this->assertFalse(manager::is_supported_timezone('Not/A_Timezone'));
    }

    /**
     * A successful authentication-plugin update is followed by the local Moodle commit.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_successful_update_persists_after_auth_plugin_accepts(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => 'Europe/London',
        ]);
        $olduser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $authplugin = new \local_autobrowsertimezone_fake_auth_plugin(true);

        $result = manager::apply_timezone_change($olduser, 'Australia/Sydney', $authplugin);

        $this->assertTrue($result['changed']);
        $this->assertSame('Australia/Sydney', $result['timezone']);
        $this->assertSame('updated', $result['reason']);
        $this->assertSame('Europe/London', $authplugin->lastolduser->timezone);
        $this->assertSame('Australia/Sydney', $authplugin->lastnewuser->timezone);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $dbuser->timezone);
    }

    /**
     * When the active authentication plugin rejects the proposed change, Moodle's
     * timezone must remain exactly as it was: no partial/local-only commit.
     *
     * A real network- or filesystem-backed auth plugin (auth_ldap, auth_db) cannot
     * be forced to reject an update inside plugin PHPUnit without a live external
     * server, so this exercises manager::apply_timezone_change() -- the exact
     * function update_current_user_timezone() delegates to -- against a minimal
     * auth_plugin_base double that implements the same
     * user_update($olduser, $newuser): bool contract. This demonstrates the
     * "reject means no commit" policy at the narrowest reliable boundary; it does
     * not demonstrate a specific vendor plugin's own rejection logic.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_auth_plugin_rejection_does_not_commit_locally(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => 'Europe/London',
        ]);

        $olduser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $authplugin = new \local_autobrowsertimezone_fake_auth_plugin(false);

        $result = manager::apply_timezone_change($olduser, 'Australia/Sydney', $authplugin);

        $this->assertFalse($result['changed']);
        $this->assertSame('Europe/London', $result['timezone']);
        $this->assertSame('authrejected', $result['reason']);
        $this->assertSame('Europe/London', $authplugin->lastolduser->timezone);
        $this->assertSame('Australia/Sydney', $authplugin->lastnewuser->timezone);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Europe/London', $dbuser->timezone);
    }

    /**
     * field_lock_timezone = locked must continue to block the automatic update policy.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_locked_blocks_update(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => '99',
        ]);
        $this->setUser($user);
        set_config('field_lock_timezone', 'locked', 'auth_manual');

        $this->assertFalse($this->auth_plugin_allows_timezone_update());
    }

    /**
     * field_lock_timezone = unlockedifempty blocks changes once the field has a value.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_unlockedifempty_blocks_when_not_empty(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => '99',
        ]);
        $this->setUser($user);
        set_config('field_lock_timezone', 'unlockedifempty', 'auth_manual');

        $this->assertFalse($this->auth_plugin_allows_timezone_update());
    }

    /**
     * field_lock_timezone = unlockedifempty allows changes while the field is empty.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_unlockedifempty_allows_when_empty(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => '',
        ]);
        $this->setUser($user);
        set_config('field_lock_timezone', 'unlockedifempty', 'auth_manual');

        $this->assertTrue($this->auth_plugin_allows_timezone_update());
    }
}
