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
 * Language file.
 *
 * @package   mod_topomojo
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN 'AS-IS' BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Topomojo';
$string['modulename_help'] = 'Displays Topomojo labs and VM consoles';
$string['modulename_link'] = 'mod/topomojo/view';
$string['modulenameplural'] = 'Topomojos';
$string['pluginname'] = 'Topomojo';

// plugin settings
$string['topomojoapiurl'] = 'Alloy API Base URL';
$string['vmapp'] = 'Display Mode';
$string['vmappurl'] = 'VM App Base URL';
$string['playerappurl'] = 'Player Base URL';
$string['steamfitterapiurl'] = 'SteamFitter API URL';
$string['issuerid'] = 'OAUTH2 Issuer';
$string['autocomplete'] = 'Workspace Selection Method';
$string['workspace'] = 'Workspace';
$string['selectname'] = 'Search for an Workspace by name';
$string['showfailed'] = 'Show Failed';

$string['configissuerid'] = 'This is the integer value for the issuer.';
$string['configvmapp'] = 'This determines whether the VM app is emebedd or whether a link to player is displayed';
$string['configvmappurl'] = 'Base URL for VM app instance without trailing /.';
$string['configplayerappurl'] = 'Base URL for Player instance without trailing /.';
$string['configsteamfitterapiurl'] = 'Base URL for SteamFitter API instance without trailing /.';
$string['configtopomojoapiurl'] = 'Base URL for Alloy API instance without trailing /.';
$string['configworkspace'] = 'Workspace GUID to be launched.';
$string['configautocomplete'] = 'Display list of Workspaces in a dropdown or a searchable text box.';
$string['configshowfailed'] = 'Show failed Events in the history table.';

$string['apikey'] = 'API Key';
$string['configapikey'] = 'The value included with TopoMojo API requests under the x-api-key header. Assigned by the TopoMojo administrator.';

// activity settings
$string['vmapp_help'] = 'This determines whether the VM app is emebeded in an iframe or whether a link to the player is displayed';
$string['workspace_help'] = 'This is the Workspace GUID in Topomojo.';
$string['workspace'] = 'Topomojo Workspace';
$string['pluginadministration'] = 'Topomojo administration';
$string['playerlinktext'] = 'Click here to open player in a new tab';
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
$string['extendeventsetting'] = 'Extend Event';
$string['extendeventsetting_help'] = 'Setting this allows the user to extend the lab by one hour increments.';
$string['grade_help'] = 'This setting specifies the maximum grade for the lab. If set to 0, the lesson does not appear in the grades pages.';
$string['duration'] = 'Duration';
$string['duration_help'] = 'This is the duration of the lab in minutes.';

// Time options
$string['timing'] = 'Timing';
$string['eventopen'] = 'Start Quiz';
$string['eventclose'] = 'Close the quiz';
$string['eventopen_help'] = 'The actitity will not be available until this date.';
$string['eventclose_help'] = 'The activity will not be available after this date';

// history table
$string['id'] = 'Topomojo Gamespace GUID';
$string['status'] = 'Status';
$string['launchdate'] = 'Launch Date';
$string['enddate'] = 'End Date';
$string['historycaption'] = 'History';

// attempt table
$string['eventid'] = 'Topomojo Event GUID';
$string['state'] = 'State';
$string['timestart'] = 'Time Started';
$string['timefinish'] = 'Time Finished';
$string['tasks'] = 'Tasks';
$string['score'] = 'Score';
$string['username'] = 'Username';

// events
$string['eventattemptstarted'] = 'Attempt started';
$string['eventattemptended'] = 'Attempt ended';

// tasks
$string['taskcaption'] = 'Tasks';
$string['taskid'] = 'Task ID';
$string['taskdesc'] = 'Task Description';
$string['taskname'] = 'Task Name';
$string['taskresult'] = 'Latest Task Result';
$string['taskaction'] = 'Action';
$string['taskexecute'] = 'Run Task';
$string['tasknoexecute'] = 'No Action';

// view
$string['eventwithoutattempt'] = 'Event exists but attempt does not exist in moodle db.';
$string['courseorinstanceid'] = 'Either a course id or an instance must be given.';
$string['attemptalreadyexists'] = 'An open attempt already exists for this event';
$string['overallgrade'] = 'Overall Grade: ';
$string['fullscreen'] = 'Fullscreen VM App';
$string['extendevent'] = 'Extend Event';
$string['reviewtext'] = 'Review Activity Attempts';
$string['managetext'] = 'Review Course Attempts';
$string['durationtext'] = 'Scheduled Duration';
$string['attemptscore'] = 'Attempt Grade: ';
$string['invitelink'] = 'Generate Invite';
$string['supportcode'] = 'Support Code: ';

// review
$string['returntext'] = 'Return to Lab';
$string['taskregrade'] = 'Edit Grade';
$string['vmname'] = 'VM Name';
$string['taskcomment'] = 'Comment';

// tasks
$string['managetasks'] = 'Manage Tasks';
$string['taskcloseattempt'] = 'Close expired Topomojo attempts';
$string['visible'] = 'Visible';
$string['gradable'] = 'Gradable';
$string['multiple'] = 'Multiple VMs';
$string['points'] = 'Points';

// roles
$string['topomojo:manage'] = 'Manage Topomojo activities';
$string['topomojo:view'] = 'View Topomojo activity information';
$string['topomojo:addinstance'] = 'Add a new Topomojo activties';

