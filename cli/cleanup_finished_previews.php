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
attempts (preview = 1). Use --legacy-email-domain to explicitly include
legacy instructor previews that were recorded before the preview flag existed.

The command checks every selected gamespace through the TopoMojo API. It
deletes only previews whose gamespaces are inactive or missing. Active and
unreachable gamespaces are reported and skipped.

Options:
    -h, --help                         Print this help.
    -x, --execute                      Delete eligible previews. Dry run if omitted.
    -y, --confirm                      Required with --execute.
    -t, --topomojoid=ID                Limit processing to one TopoMojo activity.
    -l, --legacy-email-domain=DOMAIN   Include finished preview=0 attempts for
                                       users ending in @DOMAIN. May be a
                                       comma-separated list.
    -n, --no-current-previews          Do not include preview=1 attempts.
    -s, --sync-questions               After deleting the final eligible
                                       preview for an activity, sync its
                                       configured TopoMojo questions. This
                                       runs only with --execute --confirm.

Examples:
    php mod/topomojo/cli/cleanup_finished_previews.php
    php mod/topomojo/cli/cleanup_finished_previews.php --topomojoid=13
    php mod/topomojo/cli/cleanup_finished_previews.php \\
        --legacy-email-domain=sei.cmu.edu
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

$where = ['a.state = :finished'];
$params = ['finished' => topomojo_attempt::FINISHED];
$selectors = [];

if (!$options['no-current-previews']) {
    $selectors[] = 'a.preview = 1';
}

foreach ($domains as $index => $domain) {
    $param = "legacydomain{$index}";
    $selectors[] = "a.preview = 0 AND LOWER(u.username) LIKE :{$param}";
    $params[$param] = '%@' . $domain;
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
 * @param curl $client TopoMojo API client.
 * @param string $eventid Gamespace identifier.
 * @return string One of active, inactive, missing, or unknown.
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
 * Delete one finished preview and its question usage.
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
            'state' => topomojo_attempt::FINISHED,
            'preview' => $attempt->preview,
        ]
    );
    $transaction->allow_commit();
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

cli_heading($options['execute'] ? 'Deleting finished TopoMojo previews' : 'Finished TopoMojo preview dry run');

$summary = [
    'selected' => 0,
    'active' => 0,
    'inactive' => 0,
    'missing' => 0,
    'unknown' => 0,
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
    if ($state === 'inactive' || $state === 'missing') {
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
        '%s attempt=%d activity="%s" user=%s event=%s gamespace=%s action=%s',
        $legacy,
        $attempt->id,
        $attempt->activityname,
        $attempt->username,
        $attempt->eventid ?: '-',
        $state,
        $action
    ));
}

cli_separator();
cli_writeln("Selected: {$summary['selected']}");
cli_writeln("Inactive: {$summary['inactive']}");
cli_writeln("Missing: {$summary['missing']}");
cli_writeln("Active (skipped): {$summary['active']}");
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
