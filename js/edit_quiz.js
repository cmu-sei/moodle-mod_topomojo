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
 * Edit quiz javascript to implement drag and drop on the page
 *
 * @package    mod_topomojo
 * @author     John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright  2015 University of Wisconsin - Madison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

window.addEventListener('load', function () {

    console.log("loaded");
    topomojo.set('sesskey', window.topomojoinfo.sesskey);
    topomojo.set('siteroot', window.topomojoinfo.siteroot);
    topomojo.set('cmid', window.topomojoinfo.cmid)

    var questionList = document.getElementsByClassName('questionlist')[0];

    var sorted = Sortable.create(questionList, {
        handle: '.dragquestion',
        onSort: function (evt) {

            var questionList = document.getElementsByClassName('questionlist')[0];
            var questionOrder = [];
            for (var x = 0; x < questionList.childNodes.length; x++) {

                var questionID = questionList.childNodes[x].getAttribute('data-questionid');
                questionOrder.push(questionID);
            }

            var params = {
                'sesskey': topomojo.get('sesskey'),
                'cmid': topomojo.get('cmid'),
                'questionorder': questionOrder,
                'action': 'dragdrop'
            };

            topomojo.ajax.create_request('/mod/topomojo/edit.php', params, function (status, response) {

                var editStatus = document.getElementById('editstatus');
                editStatus.innerHTMl = '';

                if (status == 500) {

                    editStatus.classList.remove('topomojohiddenstatus');
                    editStatus.classList.add('topomojoerrorstatus');
                    editStatus.innerHTML = M.util.get_string('error', 'core');

                } else if (typeof response !== 'object') {

                    console.log(response);
                    editStatus.classList.remove('topomojohiddenstatus');
                    editStatus.classList.add('topomojoerrorstatus');
                    editStatus.innerHTML = response;

                } else {

                    editStatus.classList.remove('topomojohiddenstatus');
                    editStatus.classList.add('alert-success');
                    editStatus.classList.add('topomojosuccessstatus');
                    editStatus.innerHTML = M.util.get_string('success', 'core');

                }

                setTimeout(function () {
                    var editStatus = document.getElementById('editstatus');
                    editStatus.classList.remove('topomojosuccessstatus');
                    editStatus.classList.remove('topomojoerrorstatus');
                    editStatus.classList.add('topomojohiddenstatus');
                }, 2000);

            });

        }
    });


});
