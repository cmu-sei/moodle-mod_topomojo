<?php
namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

class rotate_labofday extends \core\task\scheduled_task {
    public function get_name(): string {

        return get_string('task_rotate_labofday', 'mod_topomojo');

    }

    public function execute() {
        global $DB;

        $where  = "m.name = :modname
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
                   AND cm.visibleoncoursepage = 1";
        $params = ['modname' => 'topomojo'];

        $sql = "
          SELECT t.id AS instanceid, cm.id AS cmid, t.name
          FROM {course_modules} cm
          JOIN {modules} m   ON m.id = cm.module
          JOIN {topomojo} t  ON t.id = cm.instance
          WHERE {$where}
          ORDER BY t.name, cm.id";
        $rows = $DB->get_records_sql($sql, $params);
        if (!$rows) { return; }

        $instances = array_values($rows);

        // Find the featured lab
        $current = $DB->get_record('topomojo', ['isfeatured' => 1], 'id', IGNORE_MISSING);
        $nextinstanceid = $instances[0]->instanceid;

        if ($current) {
            $idx = array_search($current->id, array_map(fn($r) => $r->instanceid, $instances), true);
            if ($idx !== false) {
                $nextinstanceid = $instances[($idx + 1) % count($instances)]->instanceid;
            }
        }

        // Clear all, set next.
        $DB->execute("UPDATE {topomojo} SET isfeatured = 0 WHERE isfeatured <> 0");
        $DB->execute("UPDATE {topomojo} SET isfeatured = 1 WHERE id = ?", [$nextinstanceid]);
    }
}
