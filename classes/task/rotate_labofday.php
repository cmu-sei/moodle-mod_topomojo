<?php
namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

class rotate_labofday extends \core\task\scheduled_task {
    public function get_name(): string {

        return get_string('task_rotate_labofday', 'mod_topomojo');

    }

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
