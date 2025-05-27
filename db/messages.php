<?php
// mod/topomojo/db/messages.php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'notification' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
];

