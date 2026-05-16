<?php
namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

/**
 * Scheduled task to clean up expired TopoMojo gamespaces.
 *
 * @package    mod_topomojo
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_gamespaces extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('cleanup_gamespaces_task', 'mod_topomojo');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        $auth = setup();
        if (!$auth) {
            mtrace('Could not initialize TopoMojo API');
            return;
        }

        $cleaned = 0;

        // Find finished/abandoned attempts with active gamespaces
        $attempts = $DB->get_records_sql(
            "SELECT id, topomojoid, userid, eventid, state, timefinish
               FROM {topomojo_attempts}
              WHERE eventid IS NOT NULL
                AND state IN (20, 30)
                AND timefinish > 0
                AND timefinish < :cutoff",
            ['cutoff' => time() - 300] // Finished > 5 minutes ago
        );

        foreach ($attempts as $attempt) {
            try {
                $gamespace = get_event($auth, $attempt->eventid);
                if (!empty($gamespace->id) && $gamespace->isActive) {
                    mtrace("Cleaning up gamespace {$attempt->eventid} for finished attempt {$attempt->id}");
                    stop_event($auth, $attempt->eventid);
                    $cleaned++;
                }
            } catch (\Exception $e) {
                mtrace("Failed to clean up gamespace {$attempt->eventid}: " . $e->getMessage());
            }
        }

        // Find deployments marked as expired but not cleaned up
        $deployments = $DB->get_records_select(
            'topomojo_bulkdeploy_user',
            "status = 'expired' AND gamespaceid IS NOT NULL",
            null,
            '',
            'id, gamespaceid'
        );

        foreach ($deployments as $deploy) {
            try {
                $gamespace = get_event($auth, $deploy->gamespaceid);
                if (!empty($gamespace->id) && $gamespace->isActive) {
                    mtrace("Cleaning up expired deployment gamespace {$deploy->gamespaceid}");
                    stop_event($auth, $deploy->gamespaceid);
                    $cleaned++;
                }
            } catch (\Exception $e) {
                // Already cleaned up or doesn't exist - ignore
            }
        }

        mtrace("Cleaned up {$cleaned} gamespace(s)");
    }
}
