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
     * Invoke the moodle/user:editownprofile eligibility check without the
     * request-level CLI guard, for the same reason as
     * auth_plugin_allows_timezone_update() above.
     *
     * @return bool
     */
    private function can_edit_own_profile(): bool {
        $method = new \ReflectionMethod(manager::class, 'can_edit_own_profile');

        return (bool)$method->invoke(null);
    }

    /**
     * Invoke should_run()'s eligibility policy without the request-level
     * CLI guard, for the same reason as the helpers above: Moodle PHPUnit
     * defines CLI_SCRIPT, so should_run() itself is unconditionally false
     * and cannot otherwise prove these branches behave correctly.
     *
     * @return bool
     */
    private function is_eligible_for_sync(): bool {
        $method = new \ReflectionMethod(manager::class, 'is_eligible_for_sync');

        return (bool)$method->invoke(null);
    }

    /**
     * Invoke the field-lock/can_edit_profile() policy against a specific
     * auth_plugin_base instance, bypassing the real get_auth_plugin() lookup.
     *
     * @param \auth_plugin_base $authplugin
     * @param string $currenttimezone
     * @return bool
     */
    private function auth_plugin_permits_timezone_edit(\auth_plugin_base $authplugin, string $currenttimezone): bool {
        $method = new \ReflectionMethod(manager::class, 'auth_plugin_permits_timezone_edit');

        return (bool)$method->invoke(null, $authplugin, $currenttimezone);
    }

    /**
     * Invoke the validation/mutation policy directly, bypassing
     * should_run(): Moodle PHPUnit defines CLI_SCRIPT, so
     * update_current_user_timezone() is unconditionally 'disabled' there and
     * never reaches this logic.
     *
     * @param string $timezone
     * @return array{changed: bool, timezone: string, reason: string}
     */
    private function apply_validated_timezone_request(string $timezone): array {
        $method = new \ReflectionMethod(manager::class, 'apply_validated_timezone_request');

        return $method->invoke(null, $timezone);
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

    /**
     * A default authenticated user has moodle/user:editownprofile by role
     * archetype and remains eligible on this check.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_can_edit_own_profile_true_for_default_user(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertTrue($this->can_edit_own_profile());
    }

    /**
     * A user whose role has moodle/user:editownprofile explicitly prohibited
     * at system context must not be eligible for synchronisation, mirroring
     * the capability update_timezone::execute() enforces independently.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_can_edit_own_profile_false_when_capability_prohibited(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
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

        $this->assertFalse($this->can_edit_own_profile());
    }

    /**
     * should_run() must still be false under PHPUnit's CLI_SCRIPT even for an
     * otherwise fully-eligible, fully-capable user: adding the
     * moodle/user:editownprofile check must not weaken the existing CLI
     * safeguard, and no other guard should have been loosened to compensate.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_should_run_remains_false_under_cli_for_capable_user(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        $this->assertTrue($this->can_edit_own_profile());
        $this->assertFalse(manager::should_run());
    }

    /**
     * A fully-configured, enabled, capable user is eligible.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_true_for_default_user(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertTrue($this->is_eligible_for_sync());
    }

    /**
     * The plugin's own enable/disable setting must gate eligibility.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_when_plugin_disabled(): void {
        $this->resetAfterTest();

        set_config('enabled', 0, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * Guests must never be eligible.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_for_guest(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $this->setUser(guest_user());

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * A deleted user's session must never be eligible.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_for_deleted_user(): void {
        global $USER;

        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $USER->deleted = 1;

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * A suspended user's session must never be eligible.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_for_suspended_user(): void {
        global $USER;

        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $USER->suspended = 1;

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * A "login as" session must never mutate the impersonated user's profile
     * using the operator's browser timezone.
     *
     * This also exercises the fix for a defect discovered while adding this
     * coverage: manager.php previously called a non-existent global
     * isloggedinas() function, which would throw a fatal error for any real
     * (non-CLI) request reaching this check. See the tracking issue
     * referenced in the accompanying pull request.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_when_logged_in_as(): void {
        global $USER;

        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // Mirrors the exact state \core\session\manager::is_loggedinas() reads.
        $USER->realuser = 1;

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * A remote MNet profile is managed by its home Moodle site and must not
     * be mutated by this plugin.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_for_mnet_remote_user(): void {
        global $CFG, $USER;

        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Config mnet_localhost_id is populated by every Moodle install; set a
        // host id guaranteed to differ from it, exactly what
        // is_mnet_remote_user() treats as a remote profile.
        $USER->mnethostid = (int)$CFG->mnet_localhost_id + 1;

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * A site-level forced timezone overrides individual profile timezone
     * settings, so automatic sync must not fight it.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_false_when_forced_timezone_set(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('forcetimezone', 'Europe/London');

        $this->assertFalse($this->is_eligible_for_sync());
    }

    /**
     * forcetimezone = 99 means "not forced" and must not block eligibility.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_is_eligible_for_sync_true_when_forced_timezone_is_not_forced(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_autobrowsertimezone');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('forcetimezone', '99');

        $this->assertTrue($this->is_eligible_for_sync());
    }

    /**
     * Baseline: the default auth_plugin_base::can_edit_profile() (true) with
     * no lock configured permits editing.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_auth_plugin_permits_timezone_edit_true_by_default(): void {
        $this->resetAfterTest();

        $authplugin = new \local_autobrowsertimezone_fake_auth_plugin(true);

        $this->assertTrue($this->auth_plugin_permits_timezone_edit($authplugin, ''));
    }

    /**
     * can_edit_profile() = false must block eligibility regardless of field
     * lock configuration. No core-shipped auth plugin overrides
     * can_edit_profile() to false without a live external dependency, so
     * this is exercised via the deterministic test double.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_auth_plugin_permits_timezone_edit_false_when_cannot_edit_profile(): void {
        $this->resetAfterTest();

        $authplugin = new \local_autobrowsertimezone_fake_auth_plugin(true, false);

        $this->assertFalse($this->auth_plugin_permits_timezone_edit($authplugin, ''));
    }

    /**
     * An unsupported browser timezone must be rejected with
     * invalid_parameter_exception at the actual production mutation
     * boundary (not merely is_supported_timezone() in isolation), and must
     * not persist anything.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_apply_validated_timezone_request_rejects_invalid_timezone(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => '99',
        ]);
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);

        try {
            $this->apply_validated_timezone_request('Not/A_Timezone');
        } finally {
            $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
            $this->assertSame('99', $dbuser->timezone);
        }
    }

    /**
     * Requesting the timezone the user already has must be a no-op: no
     * profile write, no auth-plugin dispatch, and no user_updated event.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_apply_validated_timezone_request_unchanged_is_noop(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => 'Australia/Sydney',
        ]);
        $this->setUser($user);

        $sink = $this->redirectEvents();
        $result = $this->apply_validated_timezone_request('Australia/Sydney');
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($result['changed']);
        $this->assertSame('unchanged', $result['reason']);
        $this->assertSame('Australia/Sydney', $result['timezone']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $dbuser->timezone);

        $updatedevents = array_filter($events, function ($event) {
            return $event instanceof \core\event\user_updated;
        });
        $this->assertCount(0, $updatedevents);
    }

    /**
     * A successful request through the full validation/mutation policy (not
     * just apply_timezone_change() in isolation) persists the new timezone.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_apply_validated_timezone_request_successful_update(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => '99',
        ]);
        $this->setUser($user);

        $result = $this->apply_validated_timezone_request('Australia/Sydney');

        $this->assertTrue($result['changed']);
        $this->assertSame('updated', $result['reason']);
        $this->assertSame('Australia/Sydney', $result['timezone']);

        $dbuser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $this->assertSame('Australia/Sydney', $dbuser->timezone);

        global $USER;
        $this->assertSame('Australia/Sydney', $USER->timezone);
    }

    /**
     * A successful persisted timezone change must trigger Moodle's standard
     * core\event\user_updated event, exactly as user_update_user() does for
     * any other profile update, so consuming code (logging, sync, caches)
     * behaves consistently.
     *
     * @return void
     */
    // phpcs:ignore moodle.PHPUnit.TestCaseCovers.Missing
    public function test_successful_persistence_emits_user_updated_event(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'timezone' => 'Europe/London',
        ]);
        $olduser = \core_user::get_user($user->id, '*', MUST_EXIST);
        $authplugin = new \local_autobrowsertimezone_fake_auth_plugin(true);

        $sink = $this->redirectEvents();
        $result = manager::apply_timezone_change($olduser, 'Australia/Sydney', $authplugin);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['changed']);

        $updatedevents = array_values(array_filter($events, function ($event) {
            return $event instanceof \core\event\user_updated;
        }));
        $this->assertCount(1, $updatedevents);
        $this->assertSame((int)$user->id, (int)$updatedevents[0]->objectid);
        $this->assertSame((int)$user->id, (int)$updatedevents[0]->relateduserid);
        $this->assertSame('core', $updatedevents[0]->component);
    }
}
