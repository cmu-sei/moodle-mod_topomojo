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
 * Scheduled task that rotates the lab of the day.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that picks the lab of the day from the featured activities.
 */
class rotate_labofday extends \core\task\scheduled_task {
    /**
     * Gets the name of the task.
     *
     * @return string The task name.
     */
    public function get_name(): string {

        return get_string('task_rotate_labofday', 'mod_topomojo');
    }

    /**
     * Moves the lab of the day to the next visible, featured activity, or clears it if there is none.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Only select activities that are marked as eligible for Lab of the Day pool
        $where  = "m.name = :modname
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
                   AND cm.visibleoncoursepage = 1
                   AND t.isfeatured = 1";
        $params = ['modname' => 'topomojo'];

        $sql = "
          SELECT t.id AS instanceid, cm.id AS cmid, t.name
          FROM {course_modules} cm
          JOIN {modules} m   ON m.id = cm.module
          JOIN {topomojo} t  ON t.id = cm.instance
          WHERE {$where}
          ORDER BY t.name, cm.id";
        $rows = $DB->get_records_sql($sql, $params);
        if (!$rows) {
            // No eligible labs in the pool, clear current setting
            set_config('current_labofday_id', '', 'mod_topomojo');
            return;
        }

        $instances = array_values($rows);

        // Get the currently featured lab from plugin config
        $currentid = get_config('mod_topomojo', 'current_labofday_id');
        $nextinstanceid = $instances[0]->instanceid;

        if ($currentid) {
            // Find current lab in the pool and get the next one
            $idx = array_search($currentid, array_map(fn($r) => $r->instanceid, $instances), true);
            if ($idx !== false) {
                // Rotate to next lab in pool
                $nextinstanceid = $instances[($idx + 1) % count($instances)]->instanceid;
            }
            // If current lab is not in pool anymore, default to first lab
        }

        // Store the next featured lab ID in config
        set_config('current_labofday_id', $nextinstanceid, 'mod_topomojo');
    }
}
