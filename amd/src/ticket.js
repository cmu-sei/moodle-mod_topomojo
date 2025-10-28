/**
TopoMojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE
MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO
WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER
INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR
MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT
TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact
permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release
and unlimited distribution.  Please see Copyright notice for non-US Government
use and distribution.
This Software includes and/or makes use of the following Third-Party Software
subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas
DM20-0196
 */

define(['jquery', 'core/config', 'core/log'], function($, config, log) {

    var eventId;

    return {
        init: function(info) {
            eventId = info.id;


            // TODO listen for onlick on each of the vm buttons
            var consoleButtons = document.getElementsByClassName('console');
            if (consoleButtons) {
                // Console.log("we have buttons");
                consoleButtons.forEach(function(button) {
                    // Console.log("setting onclick function hook for " + button.id);
                    button.onclick = function() {
                        // Console.log('need to get ticket for button: ' + button.innerText);
                        $.ajax({
                            url: config.wwwroot + '/mod/topomojo/getticket.php',
                            type: 'POST',
                            data: {
                                'sesskey': config.sesskey,
                                'id': eventId
                            },
                            headers: {
                                'Cache-Control': 'no-cache',
                                'Expires': '-1'
                            },
                            success: function(result) {
                                var ticket = result.ticket;
                                var baseUrl = button.id;
                                var urlWithTicket;

                                if (baseUrl.indexOf('/mks/') !== -1) {
                                    urlWithTicket = baseUrl + '&t=' + encodeURIComponent(ticket);
                                } else {
                                    urlWithTicket = baseUrl + '&token=' + encodeURIComponent(ticket);
                                }

                                window.open(urlWithTicket, '_blank');
                            },
                            error: function(request) {
                                log.debug('moodle-mod_topomojo-generate_ticket: ' + request);
                            }
                        });
                    };
                });
            }
        },
    };
});

