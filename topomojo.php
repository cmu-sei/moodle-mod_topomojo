<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/edit_overview_form.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/topomojo/topomojo.php'));
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('topomojo', 'mod_topomojo'));
$PAGE->set_heading(format_string($SITE->fullname));

// Breadcrumbs: Home / TopoMojo.
$PAGE->navbar->add(get_string('topomojo', 'mod_topomojo'), $PAGE->url);

// Check if user has capability to edit.
$systemcontext = context_system::instance();
$canedit = has_capability('moodle/site:config', $systemcontext);

// Get edit mode parameter for the form.
$edit = optional_param('edit', false, PARAM_BOOL);

if ($canedit && $edit) {
    $PAGE->set_url(new moodle_url('/mod/topomojo/topomojo.php', ['edit' => 1]));
}

/**
 * Get default overview content.
 *
 * @return string Default HTML content for the overview page.
 */
function get_default_overview_content()
{
    return get_string('overview_default_content', 'mod_topomojo');
}

// Initialize form - only if user can edit and edit parameter is set.
$mform = null;
if ($canedit && $edit) {
    $formurl = new moodle_url('/mod/topomojo/topomojo.php', ['edit' => 1]);
    $mform = new \mod_topomojo\form\edit_overview_form($formurl->out(false));

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/topomojo/topomojo.php'));
    } else if ($data = $mform->get_data()) {
        $contenttext = $data->content_text['text'];

        set_config('overview_content', $contenttext, 'topomojo');

        redirect(
            new moodle_url('/mod/topomojo/topomojo.php'),
            get_string('overview_saved', 'mod_topomojo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $savedcontent = get_config('topomojo', 'overview_content');
    $currentdata = [
        'content_text' => [
            'text' => !empty($savedcontent) ? $savedcontent : get_default_overview_content(),
            'format' => FORMAT_HTML
        ],
    ];

    $mform->set_data($currentdata);
}

// If in edit mode, show the form.
if ($canedit && $edit && $mform) {
    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
} else {
    $showedit = $canedit && $PAGE->user_is_editing();

    $actionmenu = '';
    if ($showedit) {
        $menu = new action_menu();
        $menu->set_kebab_trigger(get_string('actions'));

        $editurl = new moodle_url('/mod/topomojo/topomojo.php', ['edit' => 1]);
        $editaction = new action_menu_link_secondary(
            $editurl,
            new pix_icon('t/edit', ''),
            get_string('configure_overview', 'mod_topomojo')
        );
        $menu->add($editaction);

        $actionmenu = $OUTPUT->render($menu);
    }

    $savedcontent = get_config('topomojo', 'overview_content');
    $context = [
        'all_url' => (new moodle_url('/mod/topomojo/labs.php'))->out(false),
        'lod_url' => (new moodle_url('/mod/topomojo/labofday.php'))->out(false),
        'icon'    => $OUTPUT->image_url('icon', 'mod_topomojo')->out(false),
        'content' => !empty($savedcontent) ? $savedcontent : get_default_overview_content(),
        'actionmenu' => $actionmenu,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_topomojo/overview', $context);
    echo $OUTPUT->footer();
}
