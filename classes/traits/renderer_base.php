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
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

namespace mod_topomojo\traits;

/**
 *
 * @package   mod_topomojo
 * @copyright 2014 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait renderer_base {


    /** @var array $pagevars Includes other page information needed for rendering functions */
    protected $pagevars;

    /** @var \moodle_url $pageurl easy access to the pageurl */
    protected $pageurl;

    /** @var \mod_topomojo\topomojo $topomojo */
    protected $topomojo;

    /** @var array|null $pagemessage Message details with type and content. */
    protected $pagemessage;

    /**
     * Sets a page message to display when the page is loaded into view
     *
     * base_header() must be called for the message to appear
     *
     * @param string $type
     * @param string $message
     */
    public function setMessage($type, $message) {
        $this->pagemessage = [$type, $message];
    }

    /**
     * Base header function to do basic header rendering
     *
     * @param string $tab the current tab to show as group
     */
    public function base_header($tab = 'view') {
        echo $this->output->header();
        //echo topomojo_view_tabs($this->topomojo, $tab);
        $this->showMessage(); // shows a message if there is one
    }

    /**
     * Base footer function to do basic footer rendering
     *
     */
    public function base_footer() {
        echo $this->output->footer();
    }

    /**
     * shows a message if there is one
     *
     */
    public function showMessage() {

        if ( empty($this->pagemessage) ) {
            return; // return if there is no message
        }

        if ( !is_array($this->pagemessage) ) {
            return; // return if it's not an array
        }

        switch ($this->pagemessage[0]) {
            case 'error':
                echo $this->output->notification($this->pagemessage[1], 'error');
                break;
            case 'success':
                echo $this->output->notification($this->pagemessage[1], 'success');
                break;
            case 'info':
                echo $this->output->notification($this->pagemessage[1], 'info');
                break;
            case 'warning':
                echo $this->output->notification($this->pagemessage[1], 'warning');
                break;
            default:
                // unrecognized notification type
                break;
        }
    }

    /**
     * Shows an error message with the popup layout
     *
     * @param string $message
     */
    public function render_popup_error($message) {

        $this->setMessage('error', $message);
        echo $this->output->header();
        $this->showMessage();
        $this->base_footer();
    }

    /**
     * Initialize the renderer with some variables
     *
     * @param \mod_topomojo\topomojo $topomojo
     * @param \moodle_url $pageurl Always require the page url
     * @param array $pagevars (optional)
     */
    public function init($topomojo, $pageurl, $pagevars = []) {
        $this->pagevars = $pagevars;
        $this->pageurl = $pageurl;
        $this->topomojo = $topomojo;
    }

}
