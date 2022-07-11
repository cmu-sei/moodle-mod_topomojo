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

define(['jquery', 'core/config', 'core/log', 'core/modal_factory'], function($, config, log, ModalFactory) {

    var eventId;

    return {
        init: function(info) {
            eventId = info.id;

            var copyButton = document.getElementById('copy_invite');
            if (copyButton) {
                copyButton.onclick = function() {

                    var text = document.getElementById("invitationlinkurl").textContent;
                    navigator.clipboard.writeText(text).then(function() {
                        ModalFactory.create({
                            type: ModalFactory.types.ALERT,
                            title: 'Invitation Link Copied',
                            body: text,
                            removeOnClose: true,
                        })
                        .then(function(modal) {
                            modal.show();
                            return modal;
                        })
                        .fail(function(err) {
                            log.debug(err);
                        });
                        return;
                    })
                    .catch(function(err) {
                        log.debug('Could not copy text: ', err);
                    });
                };
            }

            var generateButton = document.getElementById('generate_invite');
            if (generateButton) {
                generateButton.onclick = function() {

                    $.ajax({
                        url: config.wwwroot + '/mod/topomojo/generateinvite.php',
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
                            var text = document.getElementById("invitationlinkurl");
                            text.innerText = result.invitelinkurl;
                            var x = document.getElementById("copy_invite");
                            x.style.display = 'inline';
                        },
                        error: function(request) {
                            log.debug('moodle-mod_topomojo-generate_invite: ' + request);
                        }
                    });
                };
            }
        },
    };
});

