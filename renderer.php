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
 * @package   mod_topomojo
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

defined('MOODLE_INTERNAL') || die();

class mod_topomojo_renderer extends plugin_renderer_base {

    function display_detail ($topomojo, $duration, $code = false) {
        $data = new stdClass();
        $data->name = $topomojo->name;
        $data->intro = $topomojo->intro;
        $data->code = $code;

        $data->durationtext = get_string('durationtext', 'mod_topomojo');
        $data->duration = $duration / 60;
        echo $this->render_from_template('mod_topomojo/detail', $data);
    }

    function display_startform($url, $workspace) {
        $data = new stdClass();
        $data->url = $url;
        $data->workspace = $workspace;
        echo $this->render_from_template('mod_topomojo/startform', $data);
    }

    function display_stopform($url, $workspace) {
        $data = new stdClass();
        $data->url = $url;
        $data->workspace = $workspace;
        echo $this->render_from_template('mod_topomojo/stopform', $data);
    }

    function display_return_form($url, $id) {
        $data = new stdClass();
        $data->url = $url;
        $data->id = $id;
        $data->returntext = get_string('returntext', 'mod_topomojo');;
        echo $this->render_from_template('mod_topomojo/returnform', $data);
    }

    function display_link_page($launchpointurl) {
        $data = new stdClass();
        $data->url =  $launchpointurl;
        $data->playerlinktext = get_string('playerlinktext', 'mod_topomojo');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/link', $data);

    }

    function display_embed_page($launchpointurl, $markdown, $vmlist) {
        $data = new stdClass();
        $data->url = $launchpointurl;
        //$data->fullscreen = get_string('fullscreen', 'mod_topomojo');

        $data->vmlist = $vmlist;

        $options['trusted'] = true;
        $options['noclean'] = true;
        $options['nocache'] = true;

        $data->markdown = format_text($markdown, FORMAT_MARKDOWN, $options);
        $data->markdown = str_replace("/docs/", "https://topomojo.cyberforce.site/docs/", $data->markdown, $i);

        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/embed', $data);

    }

    function display_grade($topomojo, $user = null) {
        global $USER;

        if (is_null($user)) {
            $user = $USER->id;
        }

        $usergrades = \mod_topomojo\utils\grade::get_user_grade($topomojo, $user);
        // should only be 1 grade, but we'll always get end just in case
        $usergrade = end($usergrades);
        $data = new stdClass();
        $data->overallgrade = get_string('overallgrade', 'topomojo');
        $data->grade = number_format($usergrade, 2);
        $data->maxgrade = $topomojo->grade;
        echo $this->render_from_template('mod_topomojo/grade', $data);
    }

    function display_score($attempt) {
        global $USER;
        global $DB;

        $rec = $DB->get_record("topomojo_attempts", array("id" => $attempt));

        $data = new stdClass();
        $data->score = $rec->score;
        $data->maxgrade = $DB->get_field("topomojo", "grade", array('id' => $rec->topomojoid));
        echo $this->render_from_template('mod_topomojo/score', $data);
    }

    function display_attempts($attempts, $showgrade, $showuser = false, $showdetail = false) {
        global $DB;
        $data = new stdClass();
        $data->tableheaders = new stdClass();
        $data->tabledata[] = array();

        if ($showuser) {
            $data->tableheaders->username = get_string('username', 'mod_topomojo');
            $data->tableheaders->eventguid = get_string('eventid', 'mod_topomojo');
        }
        if ($showdetail) {
            $data->tableheaders->name = get_string('workspace', 'mod_topomojo');
        }
        $data->tableheaders->timestart = get_string('timestart', 'mod_topomojo');
        $data->tableheaders->timefinish = get_string('timefinish', 'mod_topomojo');

        if ($showgrade) {
            $data->tableheaders->score = get_string('score', 'mod_topomojo');
        }

        if ($attempts) {
            foreach ($attempts as $attempt) {
                $rowdata = new stdClass();
                if ($showuser) {
                    $user = $DB->get_record("user", array('id' => $attempt->userid));
                    $rowdata->username = fullname($user);
                    if ($attempt->eventid) {
                        $rowdata->eventguid = $attempt->eventid;
                    } else {
                        $rowdata->eventguid = "-";
                    }
                }
                if ($showdetail) {
                    $topomojo = $DB->get_record("topomojo", array('id' => $attempt->topomojoid));
                    $rowdata->name= $topomojo->name;
                    $rowdata->moduleurl = new moodle_url('/mod/topomojo/view.php', array("c" => $topomojo->id));
                }
                $rowdata->timestart = userdate($attempt->timestart);
                if ($attempt->state == \mod_topomojo\topomojo_attempt::FINISHED) {
                    $rowdata->timefinish = userdate($attempt->timefinish);
                } else {
                    $rowdata->timefinish = null;
                }
                if ($showgrade) {
                    if ($attempt->score !== null) {
                        $rowdata->score = $attempt->score;
                        $rowdata->attempturl = new moodle_url('/mod/topomojo/viewattempt.php', array("a" => $attempt->id));
                    } else {
                        $rowdata->score = "-";
                    }
                }
                $data->tabledata[] = $rowdata;
            }
        }
        echo $this->render_from_template('mod_topomojo/history', $data);
    }

    function display_controls($starttime, $endtime, $extend = false) {

        $data = new stdClass();
        $data->starttime = $starttime;
        $data->endtime = $endtime;
        if ($extend) {
            $data->extend = get_string('extendevent', 'mod_topomojo');
        }

        echo $this->render_from_template('mod_topomojo/controls', $data);
    }

    /**
     * Initialize the renderer with some variables
     *
     * @param \mod_topomojo\topomojo $topomojo
     * @param moodle_url                 $pageurl Always require the page url
     * @param array                      $pagevars (optional)
     */
    public function init($topomojo, $pageurl, $pagevars = array()) {
        $this->pagevars = $pagevars;
        $this->pageurl = $pageurl;
        $this->topomojo = $topomojo;
    }

}


