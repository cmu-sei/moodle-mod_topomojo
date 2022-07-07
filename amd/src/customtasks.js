/**
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE
MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO
WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER
INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR
MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT
TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release
and unlimited distribution.  Please see Copyright notice for non-US Government
use and distribution.
This Software includes and/or makes use of the following Third-Party Software
subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas
DM20-0196
 */

define(['jquery', 'core/config', 'core/log'], function($, config, log) {

    var timeout;
    var view_id;

    return {
        init: function(info) {

            view_id = info.view;

            get_results();

            timeout = setInterval(function() {
                get_results();
            }, 5000);

            var tasks = document.getElementsByClassName('exec-task');
            $.each(tasks, function(index, value) {
                var id = value.id;
                var button = document.getElementById(id);
                if (button) {
                    // TODO replace this check
                    if (button.innerHTML === "Run Task") {
                        button.onclick = function() {
                            exec_task_mdl(id);
                        };
                        console.log('set event for button ' + id);
                    }
                }
            });
        }
    };

    function get_results() {
        $.ajax({
            url: config.wwwroot + '/mod/topomojo/getresults.php',
            dataType: 'json',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'time': $.now(),
                'id': view_id
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function(response) {
                console.log(response);
                response.parsed.sort(function(a, b) {
                    return (a.statusDate > b.statusDate) ? 1 : -1;
                });

                $.each(response.parsed, function(index, value) {
                    var result = document.getElementById('result-' + value.taskId);
                    if (result) {
                        result.innerHTML = value.status;
                    }
                });
            },
            error: function(response) {
                console.log('error');
                console.log(response);
                clearTimeout(timeout);
            }
        });
    }

    function exec_task_mdl(id) {
        console.log('exec task for ' + id);

        $.ajax({
            url: config.wwwroot + '/mod/topomojo/runcustomtask.php',
            dataType: 'json',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'time': $.now(),
                'id': id
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function(result) {
                console.log(result);
            },
            error: function(request) {
                console.log("topomojo task failed");
                console.log(request);
                log.debug('moodle-mod_topomojo-runtask: ' . request);
            }
        });
    }
});
