<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => '\mod_topomojo\observer::cm_deleted',
        'priority'  => 999,
    ],
    [
        'eventname' => '\core\event\course_module_visibility_updated',
        'callback'  => '\mod_topomojo\observer::cm_visibility_updated',
        'priority'  => 999,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\mod_topomojo\observer::cm_updated',
        'priority'  => 999,
    ],
];