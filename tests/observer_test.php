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
 * Tests for the front page lab of the day check in the event observer.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the event observer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\observer::class)]
final class observer_test extends \advanced_testcase {
    /**
     * Adds an HTML block to the front page with the given raw configdata.
     *
     * @param string $configdata The value to store in block_instances.configdata.
     */
    private function add_frontpage_block(string $configdata): void {
        global $DB;
        $DB->insert_record('block_instances', (object) [
            'blockname' => 'html',
            'parentcontextid' => \context_course::instance(SITEID)->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'site-index',
            'subpagepattern' => null,
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => $configdata,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Calls the private check under test.
     *
     * @param int $cmid The course module ID to look for.
     * @return bool The result.
     */
    private function current_cmid_equals(int $cmid): bool {
        $method = new \ReflectionMethod(observer::class, 'frontpage_current_cmid_equals');
        return $method->invoke(null, $cmid);
    }

    /**
     * A marked link in a block's stored config is found.
     */
    public function test_marked_link_in_block_config_is_found(): void {
        $this->resetAfterTest();
        $text = '<a data-labofday="1" href="https://example.test/mod/topomojo/view.php?id=42">Lab</a>';
        $this->add_frontpage_block(base64_encode(serialize((object) ['text' => $text])));

        $this->assertTrue($this->current_cmid_equals(42));
        $this->assertFalse($this->current_cmid_equals(43));
    }

    /**
     * A link without the marker is ignored.
     */
    public function test_link_without_marker_is_ignored(): void {
        $this->resetAfterTest();
        $text = '<a href="https://example.test/mod/topomojo/view.php?id=42">Lab</a>';
        $this->add_frontpage_block(base64_encode(serialize((object) ['text' => $text])));

        $this->assertFalse($this->current_cmid_equals(42));
    }

    /**
     * Config that is not a serialized object reads as no match, without a notice.
     */
    public function test_invalid_block_config_is_no_match(): void {
        $this->resetAfterTest();
        $this->add_frontpage_block('not-base64-serialized-data');
        $this->add_frontpage_block(base64_encode(serialize(false)));

        $this->assertFalse($this->current_cmid_equals(42));
    }

    /**
     * A class other than stdClass in the stored config is not instantiated.
     */
    public function test_other_classes_in_block_config_are_not_instantiated(): void {
        $this->resetAfterTest();
        $this->add_frontpage_block(base64_encode(serialize(new \ArrayObject(['text' => 'x']))));

        $this->assertFalse($this->current_cmid_equals(42));
    }
}
