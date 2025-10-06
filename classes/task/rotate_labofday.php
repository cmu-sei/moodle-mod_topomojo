<?php
namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

class rotate_labofday extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_rotate_labofday', 'mod_topomojo');
    }

    public function execute() {
        global $DB, $CFG;

        // Get a random cmid for mod_topomojo.
        $cmids = $DB->get_fieldset_sql("
            SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE m.name = :modname
               AND cm.deletioninprogress = 0
               AND cm.visible = 1
        ", ['modname' => 'topomojo']);

        if (empty($cmids)) {
            mtrace('[rotate_labofday] No TopoMojo activities found.');
            return;
        }

        $newcmid = $cmids[array_rand($cmids)];

        // Limit search to HTML blocks on the front page.
        $frontcontext = \context_course::instance(SITEID);
        $blocks = $DB->get_records('block_instances', [
            'blockname'       => 'html',
            'parentcontextid' => $frontcontext->id,
        ]);

        if (empty($blocks)) {
            mtrace('[rotate_labofday] No front page HTML blocks found.');
            return;
        }

        $countupdated    = 0;
        $countwithmarker = 0;

        foreach ($blocks as $bi) {
            if (empty($bi->configdata)) { continue; }

            $config = @unserialize(base64_decode($bi->configdata));
            if (empty($config) || empty($config->text)) { continue; }

            $text = $config->text;

            // Read current cmid
            $findCurrent = '~href\s*=\s*["\'][^"\']*/mod/topomojo/view\.php\?id=(\d+)~i';
            if (!preg_match($findCurrent, $text, $m)) {
                continue;
            }
            $countwithmarker++;
            $currentcmid = (int)$m[1];

            // If the random pick equals current, repick a few times
            if (count($cmids) > 1) {
                $tries = 0;
                while ($newcmid == $currentcmid && $tries < 10) {
                    $newcmid = $cmids[array_rand($cmids)];
                    $tries++;
                }
            }

            // Replace ONLY the link that has our marker data-labofday="1".
            $pattern = '~(<a[^>]*?(?=[^>]*data-labofday\s*=\s*(?:"1"|\'1\'|1))[^>]*?\bhref\s*=\s*["\'][^"\']*?\bid=)(\d+)(?=[^"\']*["\'][^>]*>)~i';
            $replacement = '${1}' . $newcmid;

            $updated = preg_replace($pattern, $replacement, $text, 1);

            if ($updated !== null && $updated !== $text) {
                $config->text   = $updated;
                $bi->configdata = base64_encode(serialize($config));
                $DB->update_record('block_instances', $bi);
                $countupdated++;
                mtrace("[rotate_labofday] Updated block {$bi->id} from cmid {$currentcmid} to {$newcmid}.");
            } else {
                mtrace("[rotate_labofday] Block {$bi->id} already at cmid {$currentcmid}; no change.");
            }
        }

        // Error messages
        if ($countwithmarker === 0) {
            mtrace('[rotate_labofday] Marker not found in any front page HTML block.');
        } elseif ($countupdated === 0) {
            mtrace('[rotate_labofday] Marker found but no updates were necessary.');
        }
    }
}
