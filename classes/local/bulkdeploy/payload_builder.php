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

        // Auto-detect random mode (variant=0) and pick random variant
        $variant = (int) $topomojo->variant;
        if ($variant === 0) {
            // Random mode - get variant count from TopoMojo and pick one
            require_once(__DIR__ . '/../../../locallib.php');
            $auth = setup();
            $challenge = get_challenge($auth, $workspaceid);
            if ($challenge && !empty($challenge->variants)) {
                $variant_count = count($challenge->variants);
                $variant = rand(1, $variant_count);
                debugging("Random mode: selected variant $variant (out of $variant_count)", DEBUG_DEVELOPER);
            } else {
                $variant = 1; // Fallback
                debugging("Random mode: could not get variant count, defaulting to variant 1", DEBUG_DEVELOPER);
            }
        }
        $payload->variant = $variant;

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
