<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the JSON payload used to POST a new gamespace to TopoMojo.
 *
 * Mirrors the logic in locallib.php's start_event() but accepts an explicit
 * $user object so single-user and bulk-deploy callers can both share one
 * implementation.
 */
class payload_builder {
    public static function build(string $workspaceid, \stdClass $topomojo, \stdClass $user): \stdClass {
        $payload = new \stdClass();
        $payload->resourceId = $workspaceid;
        $payload->startGamespace = true;
        $payload->allowPreview = false;
        $payload->allowReset = false;
        $payload->maxAttempts = (int) $topomojo->submissions;
        $payload->maxMinutes = (int) ($topomojo->duration / 60);
        $payload->points = (int) $topomojo->grade;

        // Pass variant directly - TopoMojo handles variant=0 as random
        $payload->variant = (int) $topomojo->variant;

        $email = (string) ($user->email ?? '');
        $atpos = strpos($email, '@');
        $subjectid = $atpos === false ? $email : substr($email, 0, $atpos);

        $player = new \stdClass();
        $player->subjectId = $subjectid;
        $player->subjectName = (string) ($user->username ?? '');

        $payload->players = [$player];
        return $payload;
    }
}
