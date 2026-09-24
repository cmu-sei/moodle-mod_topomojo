<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
TopoMojo Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1175
*/

/**
 * Unit tests for the mod_topomojo mustache templates.
 *
 * @package    mod_topomojo
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the mod_topomojo mustache templates.
 *
 * @package    mod_topomojo
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class templates_test extends \basic_testcase {
    /**
     * Bootstrap emits every display utility with !important, so an element carrying one
     * cannot be hidden or shown again by an inline style. The AMD modules here toggle
     * several elements through element.style.display, which those classes silently
     * defeat: in mod_crucible, whose templates share this lineage, d-inline-flex
     * outranked both a style="display:none" and the hide call in its wait handler, and
     * the timer kept announcing "Your time has expired" over a lab that was deploying.
     *
     * @param string $template Path to a template in this plugin.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('template_provider')]
    public function test_inline_display_is_not_overridden_by_a_utility_class(string $template): void {
        $tags = self::get_tags_with_inline_display(file_get_contents($template));
        $name = basename($template);

        foreach ($tags as $tag) {
            $utility = self::get_display_utility_class($tag);
            $this->assertNull(
                $utility,
                "$name sets display inline on an element that also has the Bootstrap class "
                    . "'$utility'. Bootstrap marks display utilities !important, so neither the "
                    . "inline style nor any AMD module can control whether this element shows:\n$tag"
            );
        }
    }

    /**
     * Every template shipped by the plugin, so a new one is covered without being listed.
     *
     * @return array
     */
    public static function template_provider(): array {
        global $CFG;

        $templates = glob($CFG->dirroot . '/mod/topomojo/templates/*.mustache');
        $cases = [];
        foreach ($templates as $template) {
            $cases[basename($template)] = [$template];
        }

        return $cases;
    }

    /**
     * Opening tags that declare a display value in their own style attribute.
     *
     * @param string $content Template source.
     * @return string[]
     */
    private static function get_tags_with_inline_display(string $content): array {
        $pattern = '/<[a-z][a-z0-9]*\b[^>]*\bstyle\s*=\s*([\'"])[^\'"]*\bdisplay\s*:[^\'"]*\1[^>]*>/i';
        preg_match_all($pattern, $content, $matches);

        return $matches[0];
    }

    /**
     * The Bootstrap display utility on a tag, if it carries one.
     *
     * @param string $tag An opening tag.
     * @return string|null The class found, or null when the tag has none.
     */
    private static function get_display_utility_class(string $tag): ?string {
        if (!preg_match('/\bclass\s*=\s*([\'"])(.*?)\1/is', $tag, $matches)) {
            return null;
        }

        $breakpoints = 'sm|md|lg|xl|xxl';
        $values = 'none|inline-block|inline-flex|inline|block|flex|grid|table-row|table-cell|table';
        foreach (preg_split('/\s+/', trim($matches[2])) as $class) {
            if (preg_match("/^d-(?:(?:$breakpoints)-)?(?:$values)$/", $class)) {
                return $class;
            }
        }

        return null;
    }
}
