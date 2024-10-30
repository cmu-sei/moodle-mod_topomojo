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
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full 
terms.

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
$string['embed'] = 'Display Mode';
$string['topomojobaseurl'] = 'TopoMojo Base URL';
$string['autocomplete'] = 'Workspace Selection Method';
$string['workspace'] = 'Workspace';
$string['selectname'] = 'Search for a Workspace by name';
$string['showfailed'] = 'Show Failed';
$string['displaylink'] = 'Display Link to TopoMojo';
$string['embedlab'] = 'Embed Lab inside Moodle';
$string['theattempt'] = 'The attempt';
$string['theattempt_help'] = 'Whether the student can review the attempt at all.';
$string['configtopomojobaseurl'] = 'Base URL for TopoMojo without trailing /.';
$string['configissuerid'] = 'This is the integer value for the issuer.';
$string['configembed'] = 'This determines whether the lab is embedded or whether a link to TopoMojo is displayed';
$string['configtopomojoapiurl'] = 'Base URL for TopoMojo API instance without trailing /.';
$string['configworkspace'] = 'Workspace GUID to be launched.';
$string['configautocomplete'] = 'Display list of Workspaces in a dropdown or a searchable text box.';
$string['configshowfailed'] = 'Show failed Events in the history table.';
$string['apikey'] = 'API Key';
$string['configapikey'] = 'The value included with TopoMojo API requests under the x-api-key header. Assigned by the TopoMojo administrator.';
$string['managername'] = 'Manager Name';
$string['configmanagername'] = 'The Manager Name associated with the API key. Used to filter gamespaces.';
$string['tagimport'] = 'Import Tags';
$string['configtagimport'] = 'Import Tags from TopoMojo Lab.';
$string['tagcreate'] = 'Create Tags';
$string['configtagcreate'] = 'Create Tags from TopoMojo Lab in Moodle.';
$string['tagmap'] = 'Map Tags';
$string['configtagmap'] = 'Maps Tags from TopoMojo Lab to Moodle Activities.';
$string['tagcollection'] = 'Tag Collection';
$string['configtagcollection'] = 'This determines which Tag Collection should be used to group tags imported';
$string['importchallenge'] = 'Import Challenge';
$string['importchallenge_help'] = 'Whether challenge questions should be imported from TopoMojo.';
$string['endlab'] = 'End Lab';
$string['endlab_help'] = 'Whether quiz submission should end TopoMojo lab. Will set submissions to 1.';
$string['maxattemptlabel'] = 'Maximum Challenge Submissions';
$string['maxattemptdesc'] = 'Set the maximum number of challenge submissions allowed per lab attempt. Set to 0 for unlimited submissions.';

// Activity settings
$string['embed_help'] = 'This determines whether the lab is emebeded in an iframe or whether a link to TopoMojo is displayed';
$string['workspace_help'] = 'This is the Workspace GUID in TopoMojo.';
$string['workspace'] = 'TopoMojo Workspace';
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
$string['variant_help'] = 'The variant of the lab deployment on TopoMojo.';
$string['attemptsallowed'] = 'Attempts allowed';
$string['completionminattemptserror'] = 'Minimum number of attempts must be lower or equal to attempts allowed.';
$string['submissionsallowed'] = 'Submissions allowed';
$string['completionminaubmissionserror'] = 'Minimum number of submissions must be lower or equal to submissions allowed.';
$string['attemptsallowed_help'] = 'Attempts allowed for the lab activity. The number of times the lab can be launched.';
$string['submissionsallowed_help'] = 'Submissions allowed for the challenge/quiz.';

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

// Privacy
$string['privacy:metadata'] = 'The TopoMojo activity plugin shows data stored in Moodle although the privacy API has not yet been implemented';

// Global Search
$string['search:activity'] = 'TopoMojo - activity information';

$string['interestslist'] = 'list test';
