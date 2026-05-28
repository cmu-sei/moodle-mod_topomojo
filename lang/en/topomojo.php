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

/*
TopoMojo Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1175
*/

/**
 * Language file.
 *
 * @package   mod_topomojo
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'TopoMojo';
$string['modulename_help'] = 'Displays TopoMojo labs and VM consoles';
$string['modulename_link'] = 'mod/topomojo/view';
$string['modulenameplural'] = 'TopoMojos';
$string['pluginname'] = 'TopoMojo';

// Plugin settings
$string['topomojoapiurl'] = 'TopoMojo API Base URL';
$string['topomojoapiurl_help'] = 'The base URL of your TopoMojo API endpoint, typically ending in /api. For example: https://topomojo.example.com/api. Trailing slashes will be removed automatically.';
$string['topomojobaseurl'] = 'TopoMojo Base URL';
$string['topomojobaseurl_help'] = 'The base URL of your TopoMojo web interface. For example: https://topomojo.example.com. This is used for generating invitation links. Trailing slashes will be removed automatically.';
$string['autocomplete'] = 'Workspace Selection Method';
$string['workspace'] = 'Workspace';
$string['selectname'] = 'Search for a Workspace by name';
$string['showfailed'] = 'Show Failed';
$string['displaylink'] = 'Display Link to TopoMojo';
$string['embed'] = 'Display Mode';
$string['embedlab'] = 'Embed Lab inside Moodle';
$string['theattempt'] = 'The attempt';
$string['theattempt_help'] = 'Whether the student can review the attempt at all.';
$string['configtopomojobaseurl'] = 'Base URL for TopoMojo (e.g., https://topomojo.example.com). Trailing slashes will be automatically removed.';
$string['configissuerid'] = 'OAuth2 issuer for authentication with TopoMojo API. The issuer must be configured in Site administration > Server > OAuth 2 services.';
$string['configembed'] = 'This determines whether the lab is embedded or whether a link to TopoMojo is displayed';
$string['configtopomojoapiurl'] = 'Base URL for TopoMojo API (e.g., https://topomojo.example.com/api). Trailing slashes will be automatically removed.';
$string['configworkspace'] = 'Workspace GUID to be launched.';
$string['configautocomplete'] = 'Display list of Workspaces in a dropdown or a searchable text box.';
$string['configshowfailed'] = 'Show failed Events in the history table.';
$string['apikey'] = 'API Key';
$string['apikey_help'] = 'The API key provided by your TopoMojo administrator. This key is sent in the x-api-key header with every API request. Only required when using API key authentication (not OAuth2).';
$string['configapikey'] = 'The value included with TopoMojo API requests under the x-api-key header. Assigned by the TopoMojo administrator.';
$string['managername'] = 'Manager Name';
$string['managername_help'] = 'The manager account name associated with your API key or OAuth2 credentials. This is used to filter gamespaces and enforce workspace limits. Contact your TopoMojo administrator for this value.';
$string['configmanagername'] = 'The Manager Name associated with the API key. Used to filter gamespaces.';
$string['enableapikey'] = 'Enable External API Key';
$string['enableapikey_help'] = 'Enable this to use API key authentication instead of OAuth2. You must provide an API key below when this is enabled. API key and OAuth2 authentication are mutually exclusive.';
$string['enablemanagername'] = 'Enable External Manager User';
$string['enablemanagername_help'] = 'Enable this if your TopoMojo integration requires a manager name for filtering gamespaces and enforcing workspace limits.';
$string['tagimport'] = 'Import Tags';
$string['tagimport_help'] = 'When enabled, tags from TopoMojo labs will be imported and associated with Moodle activities. This allows you to organize and search activities by TopoMojo tags.';
$string['configtagimport'] = 'Import Tags from TopoMojo Lab.';
$string['tagcreate'] = 'Create Tags';
$string['tagcreate_help'] = 'When enabled, new tags will be created in Moodle if they don\'t already exist. Requires "Import Tags" to be enabled.';
$string['configtagcreate'] = 'Create Tags from TopoMojo Lab in Moodle.';
$string['tagmap'] = 'Map Tags';
$string['tagmap_help'] = 'When enabled, imported tags will be mapped to Moodle activities, allowing tag-based filtering and organization. Requires "Import Tags" to be enabled.';
$string['configtagmap'] = 'Maps Tags from TopoMojo Lab to Moodle Activities.';
$string['tagcollection'] = 'Tag Collection';
$string['tagcollection_help'] = 'Select which Moodle tag collection should be used to organize imported TopoMojo tags. Tag collections help group related tags together.';
$string['configtagcollection'] = 'This determines which Tag Collection should be used to group tags imported';
$string['importchallenge'] = 'Import Challenge';
$string['importchallenge_help'] = 'Whether challenge questions should be imported from TopoMojo.';
$string['endlab'] = 'End Lab';
$string['endlab_help'] = 'Whether quiz submission should end TopoMojo lab. Will set submissions to 1.';
$string['maxattemptlabel'] = 'Maximum Challenge Submissions';
$string['maxattemptdesc'] = 'Set the maximum number of challenge submissions allowed per lab attempt. Set to 0 for unlimited submissions.';
$string['maxdeployedlabel'] = 'Maximum Active Labs';
$string['maxdeployedlabel_help'] = 'The maximum number of labs a student can have running at the same time. This helps manage resource usage. Set to 0 for unlimited active labs.';
$string['maxdeployedlabsdesc'] = 'Set the maximum number of active labs a student can have at one time. Set to 0 for unlimited active labs.';
$string['deploytimeout'] = 'Lab Deployment Timeout';
$string['deploytimeout_help'] = 'Maximum time in seconds to wait for VM deployment before showing a timeout message. Typical deployments take 30-60 seconds. Increase this if labs consistently timeout during launch. Default: 120 seconds.';
$string['configdeploytimeout'] = 'Maximum time in seconds to wait for VM deployment (default: 120). Increase if labs consistently timeout during launch.';
$string['timeout_title'] = 'Lab Deployment Taking Longer Than Expected';
$string['timeout_description'] = 'Your lab deployment is still in progress but has exceeded the configured timeout. The lab may still be deploying in the background. Please click the button below to check if your lab is ready.';
$string['refresh_page'] = 'Refresh Page';
$string['maxgamespacesreached'] = 'This lab could not be deployed because the system account has reached the maximum limit of deployed namespaces. Please contact support to resolve this issue.';
$string['endlabmessage'] = 'Notice: Submitting your answers will end the lab and your responses will be graded.';
$string['configenableapikey'] = 'Enable API key-based integration with TopoMojo.';
$string['configenablemanagername'] = 'Enable external manager for integration with TopoMojo.';
$string['issuerid'] = 'Issuer Id';
$string['issuerid_help'] = 'Select the OAuth2 issuer to use for authenticating with the TopoMojo API. The issuer must first be created in Site administration > Server > OAuth 2 services. This is required when OAuth authentication is enabled.';
$string['configissuerid'] = 'OAuth2 issuer for authentication with TopoMojo API. The issuer must be configured in Site administration > Server > OAuth 2 services.';
$string['enableoauth'] = 'Enable Oauth2 System Account';
$string['enableoauth_help'] = 'When enabled, Moodle will authenticate with TopoMojo using OAuth2 instead of an API key. You must configure an OAuth2 issuer in Site administration > Server > OAuth 2 services before enabling this option.';
$string['configenableoauth'] = 'Enable Oauth2 System Account for integration with TopoMojo.';

// Activity settings
$string['embed_help'] = 'This determines whether the lab is emebeded in an iframe or whether a link to TopoMojo is displayed';
$string['workspace_help'] = 'This is the Workspace GUID in TopoMojo.';
$string['workspace'] = 'TopoMojo Workspace';
$string['filterbyaudience'] = 'Filter by Audience';
$string['pluginadministration'] = 'TopoMojo administration';
$string['playerlinktext'] = 'Open lab in new tab';
$string['clock'] = 'Clock';
$string['configclock'] = 'Style for clock.';
$string['clock_help'] = 'Display no clock, a countup timer, or a countdown timer.';
$string['firstattempt'] = 'First attempt';
$string['lastattempt'] = 'Last completed attempt';
$string['highestattempt'] = 'Highest attempt';
$string['attemptaverage'] = 'Average of all attempts';
$string['grade'] = 'Grade';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['grademethoddesc'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['extendevent'] = 'Extend Lab';
$string['extendevent_help'] = 'Setting this allows the user to extend the lab by one hour increments.';
$string['grade_help'] = 'This setting specifies the maximum grade for the lab. If set to 0, the lesson does not appear in the grades pages.';
$string['duration'] = 'Duration';
$string['duration_help'] = 'This is the duration of the lab in minutes. Set to 0 to use worspace default from TopoMojo';
$string['overallfeedback'] = 'Overall feedback';
$string['overallfeedback_help'] = 'Overall feedback is text that is shown after a  has been attempted. By specifying additional grade boundaries (as a percentage or as a number), the text shown can depend on the grade obtained.';
$string['everythingon'] = 'Everything on';
$string['manualcomment'] = 'Manual Comment';
$string['manualcomment_help'] = 'The comment that instructors can add when grading an attempt';
$string['shufflewithin'] = 'Shuffle within questions';
$string['shufflewithin_help'] = 'If enabled, the parts making up each question will be randomly shuffled each time a student attempts the quiz, provided the option is also enabled in the question settings. This setting only applies to questions that have multiple parts, such as multiple choice or matching questions.';
$string['configshufflewithin'] = 'If you enable this option, then the parts making up the individual questions will be randomly shuffled each time a student starts an attempt at this quiz, provided the option is also enabled in the question settings.';
$string['questionbehaviour'] = 'Question behaviour';
$string['marks'] = 'Marks';
$string['marks_help'] = 'The numerical marks for each question, and the overall attempt score.';
$string['variant'] = 'Variant';
$string['variant_help'] = 'The variant of the lab deployment on TopoMojo. Select "Random" to assign different variants to each student automatically, or select a specific variant number for all students to use the same variant.';
$string['variant_random'] = 'Random (assign different variants to each student)';
$string['variant_number'] = 'Variant {$a}';
$string['variantlockedhasattempts'] = 'Locked: attempts exist';
$string['workspacelockedhasattempts'] = 'Locked: attempts exist';
$string['wrongvariant'] = 'This question is for Variant {$a->question_variant}, but this activity uses Variant {$a->activity_variant}';
$string['wrongvariantadd'] = 'Cannot add question from Variant {$a->question_variant} to an activity configured for Variant {$a->activity_variant}';
$string['attemptsallowed'] = 'Attempts allowed';
$string['deploymentsallowed'] = 'Deployments allowed';
$string['completionminattemptserror'] = 'Minimum number of attempts must be lower or equal to attempts allowed.';
$string['submissionsallowed'] = 'Submissions allowed';
$string['completionminaubmissionserror'] = 'Minimum number of submissions must be lower or equal to submissions allowed.';
$string['attemptsallowed_help'] = 'Attempts allowed for the lab activity. The number of times the lab can be launched. Set to 0 for unlimited attempts.';
$string['submissionsallowed_help'] = 'Submissions allowed for the challenge/quiz. Set to 0 for unlimited submissions.';
$string['maxattemptsreached'] = 'You have reached the maximum number of attempts allowed for this activity.';
$string['maxdeploysreached'] = 'You have reached the maximum number of deployments allowed.';
$string['finalattempt'] = "Notice: This is your final submission for this challenge.";
$string['currentlydeployedlabs'] = 'Currently Deployed Labs';
$string['contentlicense'] = 'Content License';
$string['contentlicense_help'] = 'Select the appropriate content license associated with this content or lab from the dropdown menu.';
$string['showcontentlicense'] = 'Display Content License to Students';
$string['showcontentlicense_help'] = 'If checked, the content license text will be visible to students on the activity page.';


// Time options
$string['timing'] = 'Timing';
$string['eventopen'] = 'Open the activity';
$string['eventclose'] = 'Close the activity';
$string['eventopen_help'] = 'The actitity will not be available until this date.';
$string['eventclose_help'] = 'The activity will not be available after this date';

// History table
$string['id'] = 'TopoMojo Gamespace GUID';
$string['status'] = 'Status';
$string['launchdate'] = 'Launch Date';
$string['enddate'] = 'End Date';
$string['historycaption'] = 'History';

// Attempt table
$string['eventid'] = 'TopoMojo Gamespace GUID';
$string['state'] = 'State';
$string['timestart'] = 'Time Started';
$string['timefinish'] = 'Time Finished';
$string['tasks'] = 'Tasks';
$string['score'] = 'Score';
$string['username'] = 'Username';

// Events
$string['eventattemptstarted'] = 'Attempt started';
$string['eventattemptended'] = 'Attempt ended';

// View
$string['eventwithoutattempt'] = 'Gamespace exists but attempt does not exist in moodle db.';
$string['courseorinstanceid'] = 'Either a course id or an instance must be given.';
$string['attemptalreadyexists'] = 'An open attempt already exists for this event';
$string['overallgrade'] = 'Overall Grade: ';
$string['extendevent'] = 'Extend Lab';
$string['reviewtext'] = 'Review Activity Attempts';
$string['durationtext'] = 'Scheduled Duration';
$string['challengetext'] = 'Challenge';
$string['attemptscore'] = 'Attempt Grade: ';
$string['invitelink'] = 'Generate Invite';
$string['supportcode'] = 'Support Code: ';
$string['copyinvite'] = 'Copy Invitation Link';
$string['stoplab'] = 'End Lab';
$string['endlab'] = 'End Lab?';
$string['startlab'] = 'Start Lab?';
$string['start_attempt_confirm'] = 'Are you sure you want to start the attempt? This will deploy the lab on TopoMojo. Your page will refresh once the lab is ready.';
$string['stop_attempt_confirm'] = 'Are you sure you want to stop the attempt? This will destroy the lab on TopoMojo.';
$string['attemptscompleted'] = 'Attempts completed';
$string['launchedlabs'] = 'Launched Labs';
$string['nomoreattempts'] = 'No more attempts can be made.';
$string['nomorelabs'] = 'No more labs can be deployed at the moment.';
$string['challengeinstructions'] = 'Challenge Instructions';

// Review
$string['returntext'] = 'Return to Lab';
$string['savequestion'] = 'Save question';
$string['noreview'] = 'You are not able to review the quiz attempt at this time.';
$string['nochallenge'] = 'There are no challenge questions to review.';

// Review options
$string['review'] = 'Review';
$string['reviewafter'] = 'Allow review after lab is closed';
$string['reviewalways'] = 'Allow review at any time';
$string['reviewattempt'] = 'Review attempt';
$string['reviewbefore'] = 'Allow review while lab is open';
$string['reviewclosed'] = 'After the lab is closed';
$string['reviewduring'] = 'During the attempt';
$string['reviewimmediately'] = 'Immediately after the attempt';
$string['reviewnever'] = 'Never allow review';
$string['reviewofattempt'] = 'Review of attempt {$a}';
$string['reviewofpreview'] = 'Review of preview';
$string['reviewofquestion'] = 'Review of question {$a->question} in {$a->topomojo} by {$a->user}';
$string['reviewopen'] = 'While the lab is open';
$string['previewlab'] = 'Preview Lab';
$string['previewmode'] = 'Preview Mode';
$string['previewmodewarning'] = 'This is a preview session for instructional purposes. This attempt will not be recorded in the gradebook or count toward attempt limits.';
$string['reviewoptions'] = 'Students may review';
$string['reviewoptionsheading'] = 'Review options';
$string['reviewoptionsheading_help'] = 'These options control what information students can see when they review a lab attempt or look at the lab reports.

**During the attempt** settings are only relevant for some behaviours, like \'interactive with multiple tries\', which may display feedback during the attempt.

**Immediately after the attempt** settings apply for the first two minutes after \'Submit all and finish\' is clicked.

**Later, while the lab is still open** settings apply after this, and before the lab close date.

**After the lab is closed** settings apply after the lab close date has passed. If the lab does not have a close date, this state is never reached.';
$string['reviewoverallfeedback'] = 'Overall feedback';
$string['reviewoverallfeedback_help'] = 'The feedback given at the end of the attempt, depending on the student\'s total mark.';
$string['reviewresponse'] = 'Review response';
$string['reviewresponsetoq'] = 'Review response (question {$a})';
$string['reviewthisattempt'] = 'Review your responses to this attempt';


// Roles
$string['topomojo:manage'] = 'Manage TopoMojo activities';
$string['topomojo:view'] = 'View TopoMojo activity information';
$string['topomojo:addinstance'] = 'Add a new TopoMojo activties';
$string['topomojo:bulkdeploy'] = 'Bulk-deploy a TopoMojo lab for all enrolled users';

// Questions
$string['noquestions'] = 'No questions have been added yet';
$string['questions'] = 'Questions';

// Task
$string['taskcloseattempt'] = 'Close Expired TopoMojo Attempts';

// Edit
$string['questionlist'] = 'Question List';
$string['cannoteditafterattempts'] = 'You cannot add or remove questions because this lab has been attempted.';
$string['addtotopomojo'] = 'Add to lab';
$string['addselectedquestionstotopomojo'] = 'Add selected questions to the lab';
$string['addselectedtotopomojo'] = 'Add selected to lab';
$string['question'] = 'Question ';
$string['points'] = 'Question Points';
$string['points_help'] = 'The number of points you\'d like this question to be worth';
$string['addquestion'] = 'Add question';
$string['questiondelete'] = 'Delete question {$a}';
$string['questionedit'] = 'Edit question';
$string['qdeletesucess'] = 'Successfully deleted question';
$string['qdeleteerror'] = 'Couldn\'t delete question';
$string['qaddsuccess'] = 'Successfully added question';
$string['qadderror'] = 'Couldn\'t add question';
$string['importtopo'] = 'Questions synced from TopoMojo lab cannot be removed';
$string['importsuccess'] = 'Successfully imported questions from TopoMojo';
$string['importprevious'] = 'Questions from TopoMojo have aleady been added';
$string['invalid_point'] = 'Invalid point value entered for question';
$string['questionmovedown'] = 'Move question {$a} down';
$string['questionmoveup'] = 'Move question {$a} up';
$string['invalid_points'] = 'Invalid point value';

// Privacy
$string['privacy:metadata'] = 'The TopoMojo activity plugin shows data stored in Moodle although the privacy API has not yet been implemented';
$string['challengeaccessnotice'] = 'Notice: Please access the Challenge questions in a new tab.';
$string['responsesnotsaved'] = 'Notice: Your responses will not be saved if you navigate away from this page.';

// Global Search
$string['search:activity'] = 'TopoMojo - activity information';
$string['interestslist'] = 'list test';
$string['deleteallattempts'] = 'Delete all attempts and grades';
$string['deleteall'] = 'Delete all attempts and grades?';
$string['deleteallattempts_confirm'] = 'Are you sure you want to delete all attempts and grades for this activity? This action cannot be undone.';
$string['attemptsdeleted'] = 'All attempts and grades have been deleted.';
$string['messageprovider:notification'] = 'TopoMojo question mismatch notification';
$string['questionsynced'] = 'The question list has been successfully updated to match TopoMojo.';
$string['questionaddfailed'] = 'Failed to update the question list.';
$string['task_rotate_labofday'] = 'Rotate Lab of the Day link';

// Labs Display
$string['labslist']     = 'All TopoMojo Labs';
$string['col_course']   = 'Course';
$string['col_activity'] = 'Activity';
$string['nolabs']       = 'No TopoMojo activities found.';

// TopoMojo Middle Page
$string['topomojo']     = 'TopoMojo';
$string['topomojo_viewall'] = 'View all TopoMojo Labs';
$string['topomojo_viewall_desc'] = 'Browse all TopoMojo labs across the site.';
$string['topomojo_lod'] = 'Lab of the Day';
$string['topomojo_lod_desc'] = 'See today\'s featured lab.';

// Lab of Day
$string['featuredlab'] = 'Eligible for Lab of the Day';
$string['featuredlab_help'] = 'Include this activity in the “Lab of the Day” rotation pool. The scheduled task will cycle through all eligible labs.';
$string['viewcourse'] = 'View course';
$string['noactivitiesmapped'] = 'No activities were found on the site.';

$string['usingconsoleforge'] = 'Using ConsoleForge?';
$string['configusingconsoleforge'] = 'Enable if you are using a new version of TopoMojo.';

$string['labcontent'] = 'Lab Content';
$string['labcontent_help'] = 'The content/description of this lab. When you select a workspace, the markdown content from TopoMojo will be automatically fetched and displayed here. You can add additional notes or instructions, and the TopoMojo content will be appended. This content is used for AI competency classification.';

// Overview page editing
$string['overview_edit_header'] = 'Edit TopoMojo Overview Page';
$string['overview_content'] = 'Overview Content';
$string['overview_content_help'] = 'The content displayed on the TopoMojo overview page. You can use headings, lists, bold text, and other HTML formatting to organize the information.';
$string['overview_saved'] = 'Overview page content has been updated successfully.';
$string['configure_overview'] = 'Configure TopoMojo Overview';
$string['overview_default_content'] = <<<EOT
<p style="margin:0 0 12px;">
TopoMojo is the platform behind hands-on cybersecurity labs. Instructors build labs in <em>workspaces</em>; when you launch from Moodle you get an isolated <em>gamespace</em>, so nothing you do changes the original.
</p>

<h3 style="margin:14px 0 6px;font-size:16px;color:#0f172a;">On this page</h3>
<ul style="margin:0 0 12px 18px;padding:0;">
<li><strong>View all TopoMojo Labs</strong> to browse everything you have access to.</li>
<li>Jump into the rotating <strong>Lab of the Day</strong> for a quick, curated pick.</li>
<li>Open a lab’s Moodle activity and click <strong>Launch Lab</strong> to start your gamespace and follow the built-in guide.</li>
</ul>

<h3 style="margin:14px 0 6px;font-size:16px;color:#0f172a;">While in a lab</h3>
<ul style="margin:0 0 12px 18px;padding:0;">
<li><strong>Timer</strong> counts down your session; use <strong>Extend Lab</strong> if enabled.</li>
<li><strong>End Lab</strong> cleanly shuts the lab down when you’re finished.</li>
<li><strong>Generate Invite</strong> lets a collaborator join your current run.</li>
<li>The <strong>Challenge</strong> tab contains graded questions; <strong>Review Activity Attempts</strong> shows history and scores.</li>
</ul>

<h3 style="margin:14px 0 6px;font-size:16px;color:#0f172a;">Tips</h3>
<ul style="margin:0 8px 0 18px;padding:0;">
<li>Read the guide first to understand the topology and goals.</li>
<li>Watch the timer and extend before it hits zero (if allowed).</li>
<li>End the lab when finished to free resources.</li>
</ul>
EOT;

// Health check strings.
$string['healthchecksuccess'] = 'TopoMojo API is healthy (version: {$a})';
$string['healthcheckfailed'] = 'TopoMojo API health check failed (HTTP code: {$a})';
$string['healthcheckexception'] = 'TopoMojo API health check error: {$a}';
$string['healthcheckunknown'] = 'TopoMojo API health status unknown';
$string['healthcheckapinotconfigured'] = 'TopoMojo API URL is not configured';
$string['healthcheckauthfailed'] = 'TopoMojo API authentication failed';
$string['healthalert'] = 'Labs are currently unavailable. Please contact your administrator.';
$string['healthwarning'] = 'TopoMojo service may be experiencing issues.';

// Lab launching strings.
$string['launching'] = 'Launching lab';
$string['launching_description'] = 'Please wait while your lab environment is being prepared. This may take up to 2 minutes.';

// Workspace not found strings.
$string['workspacenotfound'] = 'Lab workspace not found in TopoMojo (ID: {$a}). This activity cannot be launched. Please contact your instructor.';
$string['workspacenotfound_instructor'] = 'Lab workspace not found in TopoMojo (ID: {$a}). Check that the workspace ID is correct and exists in your TopoMojo instance before students can access this activity.';

// Bulk-deploy strings.
$string['bulkdeploy_button'] = 'Deploy for all enrolled users';
$string['bulkdeploy_pageheading'] = 'Bulk-deploy lab';
$string['bulkdeploy_rolefilter'] = 'Roles to include';
$string['bulkdeploy_rolefilter_help'] = 'Choose which course roles to deploy labs for. Only users with active enrollment in one of the selected roles will receive a gamespace. Leave empty to deploy for everyone enrolled.';
$string['bulkdeploy_rolefilter_all'] = 'All roles';
$string['bulkdeploy_batchsize'] = 'Batch size';
$string['bulkdeploy_batchsize_help'] = 'How many gamespaces to deploy in parallel. Larger batches finish faster but put more load on TopoMojo. The deploy waits for every gamespace in a batch to finish starting before the next batch begins. Default: 5.';
$string['bulkdeploy_submit'] = 'Queue bulk deploy';
$string['bulkdeploy_no_workspace'] = 'This activity has no TopoMojo workspace configured; cannot bulk-deploy.';
$string['bulkdeploy_no_users_match'] = 'No active enrolled users matched the selected roles.';
$string['bulkdeploy_batchsize_invalid'] = 'Batch size must be between 1 and 50.';
$string['bulkdeploy_waittimeout'] = 'Bulk-deploy per-batch wait timeout (seconds)';
$string['configbulkdeploy_waittimeout'] = 'Maximum time to wait for one batch of gamespaces to start (have active VMs) before failing them. The bulk deploy waits for every gamespace in a batch to be ready before starting the next batch. Increase if your TopoMojo deployment is slow to start VMs.';
$string['bulkdeploy_batchsize'] = 'Batch size';
$string['configbulkdeploy_batchsize'] = 'Maximum number of gamespaces to deploy concurrently. When deploying to multiple users, this many gamespaces will start at once. Once a batch completes, the next batch starts. Lower values reduce load on TopoMojo infrastructure.';
$string['bulkdeploy_batchsize_desc'] = 'Deploy this many gamespaces at once (maximum: {$a})';
$string['deploy_confirm_message'] = 'Deploy gamespaces for selected users?';
$string['schedule_confirm_message'] = 'Choose when to start the deployment for selected users:';
$string['cleanup_gamespaces_task'] = 'Clean up expired TopoMojo gamespaces';
$string['bulkdeploy_status_pageheading'] = 'Bulk-deploy status';
$string['bulkdeploy_cancel'] = 'Cancel deploy';

// Manage deployments strings.
$string['manage_pageheading'] = 'Manage Deployments';
$string['manage_button'] = 'Manage Deployments';
$string['deploy_options'] = 'Deployment Options';
$string['deploynow'] = 'Deploy immediately';
$string['scheduledeploy'] = 'Schedule for later';
$string['scheduledfor'] = 'Scheduled time';
$string['schedule_past_error'] = 'Scheduled time must be in the future';
$string['active_jobs'] = 'Active Jobs';
$string['scheduled_jobs'] = 'Scheduled Jobs';
$string['deploy_selected'] = 'Deploy selected ({$a})';
$string['end_selected'] = 'End selected ({$a})';
$string['action_launch'] = 'Launch';
$string['action_end'] = 'End';
$string['action_deploy'] = 'Deploy';
$string['action_cancel'] = 'Cancel';
$string['action_view'] = 'View';
$string['viewattempt'] = 'View Attempt';
$string['job_scheduled_for'] = 'Scheduled for {$a}';
$string['job_running_progress'] = '{$a->ready}/{$a->total} ready, {$a->failed} failed';
$string['confirm_deploy_selected'] = 'Deploy gamespaces for {$a} selected users?';
$string['confirm_end_selected'] = 'End active attempts for {$a} selected users?';
$string['confirm_cancel_job'] = 'Cancel this job? Pending users will be skipped.';
$string['page_moved'] = 'This page has moved to the unified management interface.';
$string['view_job_in_manage'] = 'View all deployments in the Manage Deployments page.';
$string['select_all'] = 'Select All';
$string['select_all_help'] = 'Select all users in the table';
$string['deselect_all'] = 'Deselect All';
$string['deselect_all_help'] = 'Clear all selections';
$string['deploy_selected_now'] = 'Deploy Selected Now';
$string['deploy_selected_help'] = 'Immediately deploy gamespaces for selected users';
$string['schedule_selected'] = 'Schedule Selected...';
$string['schedule_selected_help'] = 'Schedule gamespace deployment for selected users at a future time';
$string['schedule_deployment'] = 'Schedule Deployment';
$string['cancel_selected'] = 'Cancel Selected';
$string['cancel_selected_help'] = 'Cancel pending/queued deployments for selected users';
$string['end_selected'] = 'End Selected';
$string['end_selected_help'] = 'End active gamespace attempts for selected users';
$string['no_users_selected'] = 'No users selected';
$string['deployment_queued'] = 'Deployment queued for {$a} user(s)';
$string['deployment_scheduled'] = 'Deployment scheduled for {$a} user(s)';
$string['deployments_cancelled'] = 'Cancelled {$a} deployment(s)';
$string['attempts_ended'] = 'Ended {$a} attempt(s)';
$string['status'] = 'Status';
$string['status_help'] = 'Status values used in this table:

* **None**: no deployment or attempt for this user.
* **Scheduled**: deployment is queued for a future time.
* **Pending**: deployment is queued and waiting to launch.
* **Launched**: deployment has started and the gamespace is being built.
* **Failed**: deployment failed (hover the cell for the error).
* **Cancelled**: deployment was cancelled before completing.
* **Not Started**: an attempt exists but the user has not begun.
* **Active**: the gamespace is deployed and ready.
* **Abandoned**: user left the attempt without finishing.
* **Finished**: user completed the attempt.';
$string['status_started_at'] = 'Started at: {$a}';
$string['status_ended_at'] = 'Ended at: {$a}';
$string['manage_deploy_running_summary'] = 'Deployments running ({$a->progress}). {$a->link}';
$string['manage_deploy_running_link'] = 'View adhoc task details';
$string['randomvariantinfo'] = 'Random variant mode: Questions from all variants have been imported. Each student will be randomly assigned a variant when they first access the activity.';
$string['cannotaddvariantquestionrandom'] = 'Cannot add variant-specific questions in random variant mode. Only manually created questions (True/False, etc.) can be added.';
$string['questionsnotimported_teacher'] = 'Questions have not been imported yet. <a href="{$a}">Visit the Questions page</a> or update activity settings to trigger import.';
$string['questionsnotimported_student'] = 'This activity is not ready yet. Please contact your instructor.';
$string['nochallengequestions'] = 'This activity has no graded questions.';
