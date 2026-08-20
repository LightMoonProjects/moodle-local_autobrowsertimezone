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
     * Create an eligible logged-in test user with the plugin enabled.
     *
     * @param array $userdata Overrides for the generated user record.
     * @return \stdClass
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    private function create_eligible_user(array $userdata = []): \stdClass {
        set_config('enabled', 1, 'local_autobrowsertimezone');

        $user = $this->getDataGenerator()->create_user(array_merge([
            'auth' => 'manual',
            'timezone' => '99',
        ], $userdata));

        $this->setUser($user);

        return $user;
    }

    /**
     * A successful update goes through the (default, no-op) authentication-plugin
     * contract exercised via the real Moodle get_auth_plugin() lookup, and the
     * local Moodle timezone is only committed after that contract succeeds.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_successful_update_persists_after_auth_plugin_accepts(): void {
        $this->resetAfterTest();

        $user = $this->create_eligible_user();

        $result = manager::update_current_user_timezone('Australia/Sydney');

        $this->assertTrue($result['changed']);
        $this->assertSame('Australia/Sydney', $result['timezone']);
        $this->assertSame('updated', $result['reason']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $dbuser->timezone);

        global $USER;
        $this->assertSame('Australia/Sydney', $USER->timezone);
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

        // The plugin must have been given the real old/new user contract.
        $this->assertSame('Europe/London', $authplugin->lastolduser->timezone);
        $this->assertSame('Australia/Sydney', $authplugin->lastnewuser->timezone);

        // No partial local commit: Moodle's own record is untouched.
        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Europe/London', $dbuser->timezone);
    }

    /**
     * field_lock_timezone = locked must continue to block the automatic update.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_locked_blocks_update(): void {
        $this->resetAfterTest();

        $user = $this->create_eligible_user(['timezone' => '99']);
        set_config('field_lock_timezone', 'locked', 'auth_manual');

        $result = manager::update_current_user_timezone('Australia/Sydney');

        $this->assertFalse($result['changed']);
        $this->assertSame('disabled', $result['reason']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('99', $dbuser->timezone);
    }

    /**
     * field_lock_timezone = unlockedifempty must block the update while the
     * current profile timezone is non-empty.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_unlockedifempty_blocks_when_not_empty(): void {
        $this->resetAfterTest();

        $user = $this->create_eligible_user(['timezone' => '99']);
        set_config('field_lock_timezone', 'unlockedifempty', 'auth_manual');

        $result = manager::update_current_user_timezone('Australia/Sydney');

        $this->assertFalse($result['changed']);
        $this->assertSame('disabled', $result['reason']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('99', $dbuser->timezone);
    }

    /**
     * field_lock_timezone = unlockedifempty must allow the update while the
     * current profile timezone is empty.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_field_lock_unlockedifempty_allows_when_empty(): void {
        $this->resetAfterTest();

        $user = $this->create_eligible_user(['timezone' => '']);
        set_config('field_lock_timezone', 'unlockedifempty', 'auth_manual');

        $result = manager::update_current_user_timezone('Australia/Sydney');

        $this->assertTrue($result['changed']);
        $this->assertSame('updated', $result['reason']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $dbuser->timezone);
    }

    /**
     * A request that already matches the authoritative database record must be a
     * no-op, so two near-simultaneous tabs applying the same change stay idempotent.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_unchanged_timezone_is_a_noop(): void {
        $this->resetAfterTest();

        $this->create_eligible_user(['timezone' => 'Australia/Sydney']);

        $result = manager::update_current_user_timezone('Australia/Sydney');

        $this->assertFalse($result['changed']);
        $this->assertSame('unchanged', $result['reason']);
    }

    /**
     * An unsupported timezone identifier must be rejected rather than persisted.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_invalid_timezone_is_rejected(): void {
        $this->resetAfterTest();

        $user = $this->create_eligible_user(['timezone' => '99']);

        $this->expectException(\invalid_parameter_exception::class);

        try {
            manager::update_current_user_timezone('Not/A_Timezone');
        } finally {
            $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
            $this->assertSame('99', $dbuser->timezone);
        }
    }
}
