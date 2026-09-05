<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Report or remove finished TopoMojo instructor previews.
 *
 * @package    mod_topomojo
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');
require_once($CFG->dirroot . '/mod/topomojo/lib.php');

use mod_topomojo\topomojo_attempt;

$help = <<<EOF
Report or remove finished TopoMojo instructor previews.

By default this command is a dry run. It only considers current preview
attempts (preview = 1) in the finished state. Use --legacy-email-domain to
explicitly include legacy instructor previews that were recorded before the
preview flag existed; a domain matches either the username or the email
address.

The command checks every selected gamespace through the TopoMojo API. It
deletes only previews whose gamespaces are inactive or missing. Active and
unreachable gamespaces are reported and skipped.

Note that topomojo_has_attempts() blocks question re-import while ANY attempt
row survives, whatever its state. Use --states to also remove abandoned and
notstarted rows when the goal is to unfreeze an activity's question import.

Options:
    -h, --help                         Print this help.
    -x, --execute                      Delete eligible previews. Dry run if omitted.
    -y, --confirm                      Required with --execute.
    -t, --topomojoid=ID                Limit processing to one TopoMojo activity.
    -l, --legacy-email-domain=DOMAIN   Include preview=0 attempts for users
                                       whose username or email ends in
                                       @DOMAIN. May be a comma-separated list.
    -n, --no-current-previews          Do not include preview=1 attempts.
    -s, --sync-questions               After deleting the final eligible
                                       preview for an activity, sync its
                                       configured TopoMojo questions. This
                                       runs only with --execute --confirm.
        --states=LIST                  Attempt states to consider, comma
                                       separated. Default finished. Valid
                                       values: notstarted, inprogress,
                                       abandoned, finished.
        --keep-scored                  Skip attempts with a score above zero.
        --expired-on-error             Also delete attempts whose gamespace call
                                       fails with a server error but whose
                                       recorded endtime has already passed.
                                       GET /gamespace/{id} throws a
                                       NullReferenceException in
                                       GamespaceService.MapChallengeView when the
                                       stored ChallengeSpec has variants set to
                                       null instead of an array, so the gamespace
                                       state cannot be read at all. The endtime
                                       TopoMojo returned when the attempt launched
                                       is proof it can no longer be live.

Examples:
    php mod/topomojo/cli/cleanup_finished_previews.php
    php mod/topomojo/cli/cleanup_finished_previews.php --topomojoid=13
    php mod/topomojo/cli/cleanup_finished_previews.php \\
        --legacy-email-domain=sei.cmu.edu,cert.org
    php mod/topomojo/cli/cleanup_finished_previews.php \\
        --legacy-email-domain=sei.cmu.edu,cert.org \\
        --states=finished,abandoned,notstarted --keep-scored
    php mod/topomojo/cli/cleanup_finished_previews.php \\
        --legacy-email-domain=sei.cmu.edu --execute --confirm
    php mod/topomojo/cli/cleanup_finished_previews.php \\
        --topomojoid=13 --sync-questions --execute --confirm

EOF;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'execute' => false,
        'confirm' => false,
        'topomojoid' => 0,
        'legacy-email-domain' => '',
        'no-current-previews' => false,
        'sync-questions' => false,
        'states' => topomojo_attempt::FINISHED,
        'keep-scored' => false,
        'expired-on-error' => false,
    ],
    [
        'h' => 'help',
        'x' => 'execute',
        'y' => 'confirm',
        't' => 'topomojoid',
        'l' => 'legacy-email-domain',
        'n' => 'no-current-previews',
        's' => 'sync-questions',
    ]
);

if ($options['help']) {
    echo $help;
    exit(0);
}

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['execute'] && !$options['confirm']) {
    cli_error('Refusing to delete previews without --confirm.');
}

$topomojoid = (int) $options['topomojoid'];
if ($topomojoid < 0) {
    cli_error('--topomojoid must be a positive activity ID.');
}

$domains = array_filter(array_map(
    static function(string $domain): string {
        return ltrim(strtolower(trim($domain)), '@');
    },
    explode(',', $options['legacy-email-domain'])
));

foreach ($domains as $domain) {
    if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
        cli_error("Invalid legacy email domain: {$domain}");
    }
}

$validstates = [
    topomojo_attempt::NOTSTARTED,
    topomojo_attempt::INPROGRESS,
    topomojo_attempt::ABANDONED,
    topomojo_attempt::FINISHED,
];

$states = array_values(array_unique(array_filter(array_map(
    static fn(string $state): string => strtolower(trim($state)),
    explode(',', (string) $options['states'])
))));

if (!$states) {
    cli_error('--states must name at least one attempt state.');
}

foreach ($states as $state) {
    if (!in_array($state, $validstates, true)) {
        cli_error("Invalid attempt state: {$state}. Valid states are " . implode(', ', $validstates) . '.');
    }
}

[$stateselect, $params] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 'state');
$where = ["a.state {$stateselect}"];
$selectors = [];

if (!$options['no-current-previews']) {
    $selectors[] = 'a.preview = 1';
}

foreach ($domains as $index => $domain) {
    $userparam = "legacyuser{$index}";
    $mailparam = "legacymail{$index}";
    $selectors[] = "a.preview = 0
                    AND (LOWER(u.username) LIKE :{$userparam} OR LOWER(u.email) LIKE :{$mailparam})";
    $params[$userparam] = '%@' . $domain;
    $params[$mailparam] = '%@' . $domain;
}

if (!$selectors) {
    cli_error('Select current previews or provide --legacy-email-domain.');
}

$where[] = '(' . implode(' OR ', array_map(
    static fn(string $selector): string => "({$selector})",
    $selectors
)) . ')';

if ($topomojoid) {
    $where[] = 'a.topomojoid = :topomojoid';
    $params['topomojoid'] = $topomojoid;
}

if ($options['keep-scored']) {
    $where[] = '(a.score IS NULL OR a.score <= 0)';
}

$expiredonerror = (bool) $options['expired-on-error'];

$sql = "SELECT a.*, t.name AS activityname, u.username
          FROM {topomojo_attempts} a
          JOIN {topomojo} t ON t.id = a.topomojoid
          JOIN {user} u ON u.id = a.userid
         WHERE " . implode(' AND ', $where) . '
      ORDER BY t.name, a.timestart, a.id';
$attempts = $DB->get_records_sql($sql, $params);

if (!$attempts) {
    cli_writeln('No finished preview attempts matched the selected criteria.');
    exit(0);
}

$client = setup();
if (!$client) {
    cli_error('Unable to initialize the TopoMojo API client. No records were changed.');
}

/**
 * Check a preview gamespace without treating a transient API failure as safe to delete.
 *
 * TopoMojo reports an unknown gamespace as HTTP 400 with a sid validation error of
 * ResourceNotFound rather than 404, so that response is treated as missing. A 5xx is
 * reported as error: the gamespace record exists but its state cannot be read, which is
 * not by itself evidence that it is finished. In practice this happens when the stored
 * ChallengeSpec has variants set to null rather than an array, which makes
 * GamespaceService.MapChallengeView dereference a null collection.
 *
 * @param curl $client TopoMojo API client.
 * @param string $eventid Gamespace identifier.
 * @return string One of active, inactive, missing, error, or unknown.
 */
function topomojo_preview_gamespace_state($client, string $eventid): string {
    if ($eventid === '') {
        return 'unknown';
    }

    $url = get_topomojo_api_url() . '/gamespace/' . rawurlencode($eventid);
    $response = $client->get($url);
    $httpcode = (int) ($client->info['http_code'] ?? 0);

    if ($httpcode === 404) {
        return 'missing';
    }
    if ($httpcode === 400) {
        $problem = json_decode((string) $response);
        $siderrors = $problem->errors->sid ?? [];
        return in_array('ResourceNotFound', (array) $siderrors, true) ? 'missing' : 'unknown';
    }
    if ($httpcode >= 500) {
        return 'error';
    }
    if ($httpcode !== 200 || !$response) {
        return 'unknown';
    }

    $event = json_decode($response);
    if (!$event) {
        return 'unknown';
    }

    return !empty($event->isActive) ? 'active' : 'inactive';
}

/**
 * Delete one preview attempt, its question usage, and any gradebook entry it left behind.
 *
 * The gradebook is refreshed after the transaction commits, so a removed attempt does not
 * leave a stale grade with no attempt behind it.
 *
 * @param stdClass $attempt Selected TopoMojo attempt.
 * @return void
 */
function topomojo_delete_finished_preview_attempt(stdClass $attempt): void {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    if (!empty($attempt->questionusageid)
            && $DB->record_exists('question_usages', ['id' => $attempt->questionusageid])) {
        $qubaids = new qubaid_join(
            '{topomojo_attempts} topomojoa',
            'topomojoa.questionusageid',
            'topomojoa.id = :topomojoattemptid',
            ['topomojoattemptid' => $attempt->id]
        );
        question_engine::delete_questions_usage_by_activities($qubaids);
    }

    $DB->delete_records_select(
        'topomojo_attempts',
        'id = :id AND state = :state AND preview = :preview',
        [
            'id' => $attempt->id,
            'state' => $attempt->state,
            'preview' => $attempt->preview,
        ]
    );
    $transaction->allow_commit();

    // Deleting the row does not touch the gradebook, so refresh it for this user.
    $topomojo = $DB->get_record('topomojo', ['id' => $attempt->topomojoid]);
    if ($topomojo) {
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id, 0, false, IGNORE_MISSING);
        if ($cm) {
            $topomojo->cmidnumber = $cm->idnumber;
        }
        topomojo_update_grades($topomojo, $attempt->userid);
    }
}

/**
 * Sync challenge questions for an activity with no remaining attempt records.
 *
 * @param int $topomojoid TopoMojo activity instance ID.
 * @return string Result status for reporting.
 */
function topomojo_sync_questions_after_preview_cleanup(int $topomojoid): string {
    global $DB;

    if ($DB->record_exists('topomojo_attempts', ['topomojoid' => $topomojoid])) {
        return 'skipped-attempts-remain';
    }

    $topomojo = $DB->get_record('topomojo', ['id' => $topomojoid], '*', MUST_EXIST);
    if (empty($topomojo->importchallenge)) {
        return 'skipped-import-disabled';
    }

    $cm = get_coursemodule_from_instance('topomojo', $topomojoid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    topomojo_auto_import_questions($topomojo, $context, $cm->id);

    return 'synced';
}

cli_heading($options['execute'] ? 'Deleting TopoMojo previews' : 'TopoMojo preview dry run');
cli_writeln('States: ' . implode(', ', $states)
    . ($domains ? ' | legacy domains: ' . implode(', ', $domains) : '')
    . ($options['keep-scored'] ? ' | keeping scored attempts' : '')
    . ($expiredonerror ? ' | deleting expired gamespaces that error' : ''));

$summary = [
    'selected' => 0,
    'active' => 0,
    'inactive' => 0,
    'missing' => 0,
    'error' => 0,
    'unknown' => 0,
    'expired' => 0,
    'deleted' => 0,
    'failed' => 0,
];
$deletablebyactivity = [];
$matchedactivityids = [];

foreach ($attempts as $attempt) {
    $summary['selected']++;
    $matchedactivityids[$attempt->topomojoid] = $attempt->activityname;
    $state = topomojo_preview_gamespace_state($client, (string) $attempt->eventid);
    $summary[$state]++;

    $legacy = $attempt->preview ? 'preview' : 'legacy-preview';
    $action = 'skip';

    // A gamespace whose recorded endtime has passed cannot still be live, even when the
    // API is too broken to say so. The endtime came from TopoMojo when the attempt launched.
    $endtime = (int) $attempt->endtime;
    $expired = $state === 'error' && $expiredonerror && $endtime > 0 && $endtime < time();
    if ($expired) {
        $summary['expired']++;
    }

    if ($state === 'inactive' || $state === 'missing' || $expired) {
        $deletablebyactivity[$attempt->topomojoid] = ($deletablebyactivity[$attempt->topomojoid] ?? 0) + 1;
        $action = $options['execute'] ? 'delete' : 'would-delete';
        if ($options['execute']) {
            try {
                topomojo_delete_finished_preview_attempt($attempt);
                $summary['deleted']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $action = 'failed: ' . $e->getMessage();
            }
        }
    }

    cli_writeln(sprintf(
        '%s attempt=%d activity="%s" user=%s state=%s score=%s event=%s gamespace=%s action=%s',
        $legacy,
        $attempt->id,
        $attempt->activityname,
        $attempt->username,
        $attempt->state,
        $attempt->score === null ? '-' : (string) (float) $attempt->score,
        $attempt->eventid ?: '-',
        $expired ? $state . '(expired)' : $state,
        $action
    ));
}

cli_separator();
cli_writeln("Selected: {$summary['selected']}");
cli_writeln("Inactive: {$summary['inactive']}");
cli_writeln("Missing: {$summary['missing']}");
cli_writeln("Active (skipped): {$summary['active']}");
cli_writeln("Server error: {$summary['error']}"
    . ($expiredonerror
        ? " (of which {$summary['expired']} past their recorded endtime)"
        : ' (all skipped)'));
cli_writeln("Unknown (skipped): {$summary['unknown']}");
if ($options['execute']) {
    cli_writeln("Deleted: {$summary['deleted']}");
    cli_writeln("Failed: {$summary['failed']}");
}

if ($deletablebyactivity) {
    cli_separator();
    $eligibleactivityids = [];
    cli_writeln($options['execute']
        ? 'Activities with no remaining attempts after cleanup:'
        : 'Activities that would have no remaining attempts after eligible cleanup:');
    foreach ($deletablebyactivity as $activityid => $count) {
        $remaining = (int) $DB->count_records('topomojo_attempts', ['topomojoid' => $activityid]);
        if (!$options['execute']) {
            $remaining -= $count;
        }
        if ($remaining === 0) {
            cli_writeln("{$activityid}\t{$matchedactivityids[$activityid]}");
            $eligibleactivityids[] = $activityid;
        }
    }

    if ($options['sync-questions'] && $eligibleactivityids) {
        cli_separator();
        cli_writeln('Question sync:');
        foreach ($eligibleactivityids as $activityid) {
            $status = $options['execute']
                ? topomojo_sync_questions_after_preview_cleanup($activityid)
                : 'would-sync';
            cli_writeln("{$activityid}\t{$matchedactivityids[$activityid]}\t{$status}");
        }
    }
}
