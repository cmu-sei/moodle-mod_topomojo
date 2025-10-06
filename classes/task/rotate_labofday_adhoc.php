<?php
namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

class rotate_labofday_adhoc extends \core\task\adhoc_task {
    public function get_component() { return 'mod_topomojo'; }
    public function execute() {
        (new \mod_topomojo\task\rotate_labofday())->execute();
    }
}
