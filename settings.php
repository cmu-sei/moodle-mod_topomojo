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
 * Settings for the TopoMojo plugin in Moodle.
 * @package   mod_topomojo
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/topomojo/locallib.php');
require_once("$CFG->dirroot/tag/lib.php");

// This is used for performance, we don't need to know about these settings on every page in Moodle, only when
// we are looking at the admin settings pages.
if ($ADMIN->fulltree) {
    // General settings
    $options = [get_string('displaylink', 'topomojo'), get_string('embedlab', 'topomojo')];
    $settings->add(new admin_setting_configselect('topomojo/embed',
        get_string('embed', 'topomojo'), get_string('configembed', 'topomojo'), 1, $options));

    $options = ['Dropdown', 'Searchable', 'Manual'];
    $settings->add(new admin_setting_configselect('topomojo/autocomplete',
        get_string('autocomplete', 'topomojo'), get_string('configautocomplete', 'topomojo'), 1, $options));

    $settings->add(new admin_setting_configtext('topomojo/topomojoapiurl',
        get_string('topomojoapiurl', 'topomojo'), get_string('configtopomojoapiurl', 'topomojo'), "", PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('topomojo/topomojobaseurl',
        get_string('topomojobaseurl', 'topomojo'), get_string('configtopomojobaseurl', 'topomojo'), "", PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('topomojo/apikey',
        get_string('apikey', 'topomojo'), get_string('configapikey', 'topomojo'), "", PARAM_ALPHANUMEXT, 60));

    $settings->add(new admin_setting_configtext('topomojo/managername',
        get_string('managername', 'topomojo'), get_string('managername', 'topomojo'), "", PARAM_TEXT, 60));

    $settings->add(new admin_setting_configcheckbox('topomojo/tagimport',
        get_string('tagimport', 'topomojo'), get_string('configtagimport', 'topomojo'), 0));

    $settings->add(new admin_setting_configcheckbox('topomojo/tagcreate',
        get_string('tagcreate', 'topomojo'), get_string('configtagcreate', 'topomojo'), 0));

    $settings->hide_if('topomojo/tagcreate', 'topomojo/tagimport', 'notchecked', 1);

    $tagcollections = core_tag_collection::get_collections();

    $collectionnames = [];

    if ($tagcollections != null) {
        foreach ($tagcollections as $collection) {
            $collectionid = $collection->id;
            $collectionnames[$collectionid] = $collection->name;
        }
    }

    $settings->add(new admin_setting_configselect('topomojo/tagcollection',
        get_string('tagcollection', 'topomojo'), get_string('configtagcollection', 'topomojo'), 1, $collectionnames));

    $settings->hide_if('topomojo/tagcollection', 'topomojo/tagcreate', 'notchecked', 1);
    $settings->hide_if('topomojo/tagcollection', 'topomojo/tagimport', 'notchecked', 1);

    $settings->add(new admin_setting_configcheckbox('topomojo/tagmap',
        get_string('tagmap', 'topomojo'), get_string('configtagmap', 'topomojo'), 0));

    $settings->hide_if('topomojo/tagmap', 'topomojo/tagcreate', 'notchecked', 1);
    $settings->hide_if('topomojo/tagmap', 'topomojo/tagimport', 'notchecked', 1);

    // Review options.
    $settings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'topomojo'), ''));
    foreach (mod_topomojo_admin_review_setting::fields() as $field => $name) {
        $default = mod_topomojo_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_topomojo_admin_review_setting::DURING;
            $forceduring = false;
        }
        $settings->add(new mod_topomojo_admin_review_setting('topomojo/review' . $field,
                $name, '', $default, $forceduring));
    }

}
