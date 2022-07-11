/**
Crucible Plugin for Moodle
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

    var eventid;
    var endtime;
    var starttime;

    return {

        init: function(start, end, id) {

            eventid = id;
            endtime = end;
            starttime = start;

            var button = document.getElementById('extend-event');
            if (button) {
                button.onclick = function() {
                    extendEvent();
                };
            }

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var remaining = endtime - timenow;
                if (remaining <= 0) {
                    var timer = document.getElementById('timer');
                    if (timer) {
                        timer.innerHTML = "Your time has expired";
                        timer.className = "alert alert-danger";
                    }
                }
            }, 1000);
        },

        countdown: function() {

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var remaining = endtime - timenow;
                if (remaining <= 0) {
                    return;
                }

                var hours = Math.floor(remaining % (60 * 60 * 24) / (60 * 60));
                var minutes = Math.floor(remaining % (60 * 60) / 60);
                var seconds = Math.floor(remaining % 60);

                var timer = document.getElementById('timer');
                if (timer) {
                    if (remaining < 1800) {
                        timer.className = "alert alert-warning";
                    } else if (remaining < 300) {
                        timer.className = "alert alert-danger";
                    } else {
                        timer.className = "alert alert-success";
                    }
                    timer.innerHTML = "Timer: " + hours.toString().padStart(2, '0') +
                            ":" + minutes.toString().padStart(2, '0') + ":" +
                            seconds.toString().padStart(2, '0');
                }
            }, 1000);
        },

        countup: function() {

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var running = timenow - starttime;

                var hours = Math.floor(running % (60 * 60 * 24) / (60 * 60));
                var minutes = Math.floor(running % (60 * 60) / 60);
                var seconds = Math.floor(running % 60);

                var timer = document.getElementById('timer');
                if (timer) {
                    timer.className = "alert alert-success";
                    timer.innerHTML = "Timer: " +  hours.toString().padStart(2, '0') +
                        ":" + minutes.toString().padStart(2, '0') + ":" +
                        seconds.toString().padStart(2, '0');
                }
            }, 1000);
        },
    };

    /**
     * Extend the duration of the lab by one hour
     *
     */
    function extendEvent() {
        $.ajax({
            url: config.wwwroot + '/mod/topomojo/extendevent.php',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'id': eventid
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function() {
                endtime += 3600;
            },
            error: function(request) {
                log.debug('moodle-mod_topomojo-extend-event: ' + request);
            }
        });

    }
});
