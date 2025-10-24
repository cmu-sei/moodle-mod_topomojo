<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use core_competency\api as comp_api;

global $DB, $OUTPUT, $SITE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/topomojo/labofday.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('featuredlab', 'mod_topomojo'));
$PAGE->set_heading(format_string($SITE->fullname));

// Breadcrumbs
$PAGE->navbar->add(get_string('topomojo', 'mod_topomojo'), new moodle_url('/mod/topomojo/topomojo.php'));
$PAGE->navbar->add(get_string('featuredlab', 'mod_topomojo'), $PAGE->url);

echo $OUTPUT->header();

$iconurl = $OUTPUT->image_url('icon', 'mod_topomojo')->out(false);

$where  = "m.name = :modname
           AND cm.deletioninprogress = 0
           AND cm.visible = 1
           AND cm.visibleoncoursepage = 1";
$params = ['modname' => 'topomojo'];

// Search for the featured tag
$sqlfeatured = "
  SELECT cm.id AS cmid, c.id AS courseid, c.fullname AS coursename,
         t.id AS instanceid, t.name AS activityname
  FROM {course_modules} cm
  JOIN {modules} m   ON m.id = cm.module
  JOIN {course}  c   ON c.id = cm.course
  JOIN {topomojo} t  ON t.id = cm.instance
  WHERE {$where} AND t.isfeatured = 1
  ORDER BY c.fullname, t.name, cm.id";
$record = $DB->get_record_sql($sqlfeatured, $params);

// If none are marked as featured, find the first eligible activity
if (!$record) {
    $sqlfirst = "
      SELECT cm.id AS cmid, c.id AS courseid, c.fullname AS coursename,
             t.id AS instanceid, t.name AS activityname
      FROM {course_modules} cm
      JOIN {modules} m   ON m.id = cm.module
      JOIN {course}  c   ON c.id = cm.course
      JOIN {topomojo} t  ON t.id = cm.instance
      WHERE {$where}
      ORDER BY c.fullname, t.name, cm.id";
    $record = $DB->get_record_sql($sqlfirst, $params);
}

$card = null;
if ($record) {
    // Tags.
    $tagnames = [];
    try {
        $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $record->cmid);
        foreach ($tags as $tag) {
            $tagnames[] = format_string($tag->get_display_name());
        }
        $tagnames = array_values(array_unique($tagnames));
    } catch (Throwable $e) {}

    // Competencies.
    $compitems = [];
    try {
        $modulecomps = comp_api::list_course_module_competencies($record->cmid);
        foreach ($modulecomps as $mc) {
            $comp = null;
            if (is_array($mc) && !empty($mc['competency']) && $mc['competency'] instanceof \core_competency\competency) {
                $comp = $mc['competency'];
            } elseif (is_array($mc) && !empty($mc['coursemodulecompetency']) &&
                      $mc['coursemodulecompetency'] instanceof \core_competency\course_module_competency) {
                $compid = (int)$mc['coursemodulecompetency']->get('competencyid');
                if ($compid) { $comp = \core_competency\competency::get_record(['id' => $compid]); }
            }
            if ($comp) {
                $label = (string)$comp->get('shortname') ?: (string)$comp->get('idnumber');
                if ($label === '') { $label = shorten_text(strip_tags((string)$comp->get('description')), 40); }
                $compitems[] = (object)['name' => format_string($label)];
            }
        }
    } catch (Throwable $e) {}

    $card = [
        'name'            => format_string($record->activityname),
        'url'             => (new moodle_url('/mod/topomojo/view.php', ['id' => $record->cmid]))->out(false),
        'iconurl'         => $iconurl,
        'coursename'      => format_string($record->coursename),
        'courseurl'       => (new moodle_url('/course/view.php', ['id' => $record->courseid]))->out(false),
        'hastags'         => !empty($tagnames),
        'tags'            => array_map(fn($n) => (object)['name' => $n], $tagnames),
        'hascompetencies' => (bool)count($compitems),
        'competencies'    => array_values($compitems),
    ];
}

echo $OUTPUT->render_from_template('mod_topomojo/labofday', [
    'title'    => get_string('featuredlab', 'mod_topomojo'),
    'hascards' => !empty($card),
    'cards'    => $card ? [$card] : [],
]);

echo $OUTPUT->footer();
