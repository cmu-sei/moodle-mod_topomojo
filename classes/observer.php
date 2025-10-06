<?php
namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function cm_deleted(\core\event\course_module_deleted $event): void {
        if (($event->other['modulename'] ?? '') !== 'topomojo') { return; }

        $deletedcmid = $event->objectid ?? ($event->other['cmid'] ?? null);
        if (!$deletedcmid) { return; }

        if (self::frontpage_current_cmid_equals((int)$deletedcmid)) {
            // Run scheduled task logic immediately
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    public static function cm_visibility_updated(\core\event\course_module_visibility_updated $event): void {
        global $DB;

        $cm = $event->get_record_snapshot('course_modules', $event->objectid);
        if (!$cm) { return; }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        if (!$module || $module->name !== 'topomojo') { return; }

        // If it is now hidden, and the front page link currently points to this cmid, run task.
        if ((int)$cm->visible === 0 && self::frontpage_current_cmid_equals((int)$cm->id)) {
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    public static function cm_updated(\core\event\course_module_updated $event): void {
        global $DB;

        $cm = $event->get_record_snapshot('course_modules', $event->objectid);
        if (!$cm) { return; }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        if (!$module || $module->name !== 'topomojo') { return; }

        if ((int)$cm->visible === 0 && self::frontpage_current_cmid_equals((int)$cm->id)) {
            $task = new \mod_topomojo\task\rotate_labofday_adhoc();
            \core\task\manager::queue_adhoc_task($task, true); // true = dedupe bursts
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
        if (!$blocks) { return false; }

        $reFindMarkedHref = '~href\s*=\s*["\'][^"\']*/mod/topomojo/view\.php\?id=(\d+)~i';
        $reHasMarker      = '~data-labofday\s*=\s*(?:"1"|\'1\'|1)~i';

        foreach ($blocks as $bi) {
            if (empty($bi->configdata)) { continue; }
            $config = @unserialize(base64_decode($bi->configdata));
            if (empty($config) || empty($config->text)) { continue; }

            $text = $config->text;
            if (!preg_match($reHasMarker, $text)) { continue; }
            if (preg_match($reFindMarkedHref, $text, $m)) {
                $current = (int)$m[1];
                if ($current === $cmid) { return true; }
            }
        }
        return false;
    }
}
