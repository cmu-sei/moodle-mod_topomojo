<?php
namespace mod_topomojo\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class bulkdeploy_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];
        $cmid = (int) ($customdata['cmid'] ?? 0);
        $roles = $customdata['roles'] ?? [];

        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $rolemenu = $mform->addElement(
            'select',
            'rolefilter',
            get_string('bulkdeploy_rolefilter', 'topomojo'),
            $roles,
            ['multiple' => 'multiple', 'size' => min(8, max(3, count($roles)))]
        );
        $rolemenu->setMultiple(true);
        $mform->setType('rolefilter', PARAM_INT);
        $mform->addHelpButton('rolefilter', 'bulkdeploy_rolefilter', 'topomojo');

        $mform->addElement('text', 'batchsize', get_string('bulkdeploy_batchsize', 'topomojo'));
        $mform->setType('batchsize', PARAM_INT);
        $mform->setDefault('batchsize', 5);
        $mform->addHelpButton('batchsize', 'bulkdeploy_batchsize', 'topomojo');

        $this->add_action_buttons(true, get_string('bulkdeploy_submit', 'topomojo'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $bs = (int) ($data['batchsize'] ?? 0);
        if ($bs < 1 || $bs > 50) {
            $errors['batchsize'] = get_string('bulkdeploy_batchsize_invalid', 'topomojo');
        }
        return $errors;
    }

    /** Test-only entry point; normal form callers go through Moodle's validation pipeline. */
    public function validation_for_test(array $data, array $files): array {
        return $this->validation($data, $files);
    }
}
