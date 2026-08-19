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

/**
 * Tests for timezone validation logic.
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
}
