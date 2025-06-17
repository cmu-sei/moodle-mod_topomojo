<?php
// mod/topomojo/db/messages.php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'notification' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
];

