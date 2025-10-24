<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use core_competency\api as comp_api;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/topomojo/labs.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('labslist', 'mod_topomojo'));
$PAGE->set_heading(format_string($SITE->fullname));

// Breadcrumbs
$PAGE->navbar->add(get_string('topomojo', 'mod_topomojo'), new moodle_url('/mod/topomojo/topomojo.php'));
$PAGE->navbar->add(get_string('labslist', 'mod_topomojo'), $PAGE->url);

echo $OUTPUT->header();

global $DB, $OUTPUT, $SITE;

$params = ['modname' => 'topomojo'];
$where  = "m.name = :modname
           AND cm.deletioninprogress = 0
           AND cm.visible = 1
           AND cm.visibleoncoursepage = 1";

$sql = "
  SELECT
      cm.id      AS cmid,
      c.id       AS courseid,
      c.fullname AS coursename,
      t.id       AS instanceid,
      t.name     AS activityname
  FROM {course_modules} cm
  JOIN {modules} m   ON m.id = cm.module
  JOIN {course}  c   ON c.id = cm.course
  JOIN {topomojo} t  ON t.id = cm.instance
  WHERE $where
  ORDER BY c.fullname, t.name";
$recs = $DB->get_records_sql($sql, $params);

$iconurl = $OUTPUT->image_url('icon', 'mod_topomojo')->out(false);

$cards = [];
foreach ($recs as $r) {
    // Tags (stored on core/course_modules).
    $tagnames = [];
    try {
        $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $r->cmid);
        foreach ($tags as $tag) {
            $tagnames[] = format_string($tag->get_display_name());
        }
        $tagnames = array_values(array_unique($tagnames));
    } catch (Throwable $e) {
        $tagnames = [];
    }

    // Competencies linked to this CM (matches your dump shape).
    $compitems = [];
    try {
        $modulecomps = comp_api::list_course_module_competencies($r->cmid);
        foreach ($modulecomps as $mc) {
            $comp = null;

            if (is_array($mc) && !empty($mc['competency']) && $mc['competency'] instanceof \core_competency\competency) {
                $comp = $mc['competency'];
            } elseif (is_array($mc) && !empty($mc['coursemodulecompetency']) &&
                      $mc['coursemodulecompetency'] instanceof \core_competency\course_module_competency) {
                $compid = (int)$mc['coursemodulecompetency']->get('competencyid');
                if ($compid) {
                    $comp = \core_competency\competency::get_record(['id' => $compid]);
                }
            }

            if (!$comp) { continue; }

            $shortname = (string)$comp->get('shortname');
            $idnumber  = (string)$comp->get('idnumber');
            $label = $shortname ?: $idnumber;
            if ($label === '') {
                $label = shorten_text(strip_tags((string)$comp->get('description')), 40);
            }

            $o = new stdClass();
            $o->name = format_string($label);
            $compitems[] = $o;
        }
    } catch (Throwable $e) {
        // ignore
    }

    $cards[] = [
        'name'            => format_string($r->activityname),
        'url'             => (new moodle_url('/mod/topomojo/view.php', ['id' => $r->cmid]))->out(false),
        'iconurl'         => $iconurl,
        'coursename'      => format_string($r->coursename),
        'courseurl'       => (new moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false),

        'hastags'         => !empty($tagnames),
        'tags'            => array_map(fn($n) => (object)['name' => $n], $tagnames),

        'hascompetencies' => (bool)count($compitems),
        'competencies'    => array_values($compitems),
    ];
}

$context = [
    'title'    => get_string('labslist', 'mod_topomojo'),
    'hascards' => !empty($cards),
    'cards'    => $cards,
];

echo $OUTPUT->render_from_template('mod_topomojo/labs', $context);
echo $OUTPUT->footer();
