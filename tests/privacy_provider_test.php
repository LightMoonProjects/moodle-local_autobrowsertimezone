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
use local_autobrowsertimezone\privacy\provider;

/**
 * Tests for the Privacy API metadata provider.
 *
 * The plugin creates no plugin-owned personal-data tables and sends no data
 * externally; it only causes an existing core_user field to be updated. This
 * is a regression test for that metadata declaration -- it does not replace
 * running Moodle's own Privacy API validation (moodle-plugin-ci validate,
 * and the site administration Data registry) against a real site, see
 * docs/RELEASE_QA.md.
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
}
