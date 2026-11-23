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
 * Event observers for the TopoMojo activity.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Observers that keep the lab of the day link in step with activity changes.
 */
class observer {
    /**
     * Queues a lab of the day rotation when the deleted activity is the one the front page links to.
     *
     * @param \core\event\course_module_deleted $event The event.
     * @return void
     */
    public static function cm_deleted(\core\event\course_module_deleted $event): void {
        if (($event->other['modulename'] ?? '') !== 'topomojo') {
            return;
        }

        $deletedcmid = $event->objectid ?? ($event->other['cmid'] ?? null);
        if (!$deletedcmid) {
            return;
        }

        if (self::frontpage_current_cmid_equals((int)$deletedcmid)) {
            // Run scheduled task logic immediately.
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Queues a lab of the day rotation when the activity the front page links to is hidden.
     *
     * @param \core\event\course_module_visibility_updated $event The event.
     * @return void
     */
    public static function cm_visibility_updated(\core\event\course_module_visibility_updated $event): void {
        global $DB;

        $cm = $event->get_record_snapshot('course_modules', $event->objectid);
        if (!$cm) {
            return;
        }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        if (!$module || $module->name !== 'topomojo') {
            return;
        }

        // If it is now hidden, and the front page link currently points to this cmid, run task.
        if ((int)$cm->visible === 0 && self::frontpage_current_cmid_equals((int)$cm->id)) {
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Queues a lab of the day rotation when the activity the front page links to is updated and is hidden.
     *
     * @param \core\event\course_module_updated $event The event.
     * @return void
     */
    public static function cm_updated(\core\event\course_module_updated $event): void {
        global $DB;

        $cm = $event->get_record_snapshot('course_modules', $event->objectid);
        if (!$cm) {
            return;
        }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        if (!$module || $module->name !== 'topomojo') {
            return;
        }

        if ((int)$cm->visible === 0 && self::frontpage_current_cmid_equals((int)$cm->id)) {
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true); // True = dedupe bursts.
        }
    }


    /**
     * Reads the front-page HTML block(s), extracts the current id used by
     * the marked link (view.php?id=### with data-labofday="1"), and checks
     * if it equals the given $cmid.
     */
    private static function frontpage_current_cmid_equals(int $cmid): bool {
        global $DB;

        $frontcontext = \context_course::instance(SITEID);
        $blocks = $DB->get_records('block_instances', [
            'blockname'       => 'html',
            'parentcontextid' => $frontcontext->id,
        ]);
        if (!$blocks) {
            return false;
        }

        $refindmarkedhref = '~href\s*=\s*["\'][^"\']*/mod/topomojo/view\.php\?id=(\d+)~i';
        $rehasmarker      = '~data-labofday\s*=\s*(?:"1"|\'1\'|1)~i';

        foreach ($blocks as $bi) {
            if (empty($bi->configdata)) {
                continue;
            }
            $config = @unserialize(base64_decode($bi->configdata));
            if (empty($config) || empty($config->text)) {
                continue;
            }

            $text = $config->text;
            if (!preg_match($rehasmarker, $text)) {
                continue;
            }
            if (preg_match($refindmarkedhref, $text, $m)) {
                $current = (int)$m[1];
                if ($current === $cmid) {
                    return true;
                }
            }
        }
        return false;
    }
}
