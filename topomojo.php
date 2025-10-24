<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/topomojo/topomojo.php'));
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('topomojo', 'mod_topomojo'));
$PAGE->set_heading(format_string($SITE->fullname));

// Breadcrumbs: Home / TopoMojo.
$PAGE->navbar->add(get_string('topomojo', 'mod_topomojo'), $PAGE->url);

$context = [
    'all_url' => (new moodle_url('/mod/topomojo/labs.php'))->out(false),
    'lod_url' => (new moodle_url('/mod/topomojo/labofday.php'))->out(false),
    'icon'    => $OUTPUT->image_url('icon', 'mod_topomojo')->out(false), // mod/topomojo/pix/icon.svg|png
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_topomojo/overview', $context);
echo $OUTPUT->footer();
