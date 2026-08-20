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

/**
 * Deterministic auth_plugin_base test double.
 *
 * Moodle's real user_update()-capable plugins (auth_ldap, auth_db) can only
 * be forced to reject an update by reaching a live external server, which is
 * not available in this plugin's PHPUnit environment. This double exercises
 * the exact contract boundary manager::apply_timezone_change() relies on
 * (the return value of auth_plugin_base::user_update($olduser, $newuser))
 * without any network or filesystem dependency.
 *
 * @package    local_autobrowsertimezone
 * @copyright  2026 LightMoonProjects
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_autobrowsertimezone_fake_auth_plugin extends auth_plugin_base {
    /** @var bool Value user_update() should return. */
    protected $acceptupdate;

    /** @var stdClass|null Old user object received by the last user_update() call. */
    public $lastolduser = null;

    /** @var stdClass|null New user object received by the last user_update() call. */
    public $lastnewuser = null;

    /**
     * Create the deterministic authentication-plugin test double.
     *
     * @param bool $acceptupdate Whether user_update() should accept (true) or reject (false) the change.
     */
    public function __construct(bool $acceptupdate) {
        $this->authtype = 'fake';
        $this->config = new stdClass();
        $this->acceptupdate = $acceptupdate;
    }

    /**
     * Record the proposed old/new user pair and return the configured outcome.
     *
     * @param stdClass $olduser Existing user record.
     * @param stdClass $newuser Proposed updated user record.
     * @return bool Whether the update is accepted.
     */
    public function user_update($olduser, $newuser) {
        $this->lastolduser = $olduser;
        $this->lastnewuser = $newuser;

        return $this->acceptupdate;
    }
}
