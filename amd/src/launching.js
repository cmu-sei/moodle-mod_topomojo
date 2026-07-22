/**
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
 * Poll gamespace readiness while VMs are being provisioned.
 *
 * @module mod_topomojo/launching
 */
define(['jquery', 'core/log'], function($, log) {
    "use strict";

    return {
        /**
         * Initialize gamespace readiness polling.
         *
         * @param {Object} params - Configuration parameters
         * @param {string} params.statusUrl - Moodle endpoint used to retrieve gamespace status
         * @param {number} params.cmid - Course module ID
         * @param {string} params.gamespaceId - TopoMojo gamespace ID
         * @param {string} params.sesskey - Moodle session key
         * @param {number} params.pollInterval - Seconds between polls
         * @param {number} params.maxAttempts - Maximum status polls before the page reloads
         */
        init: function(params) {
            var pollInterval = params.pollInterval || 5;
            var maxAttempts = params.maxAttempts || 24;
            var attemptCount = 0;
            var timer = null;
            var reloading = false;
            var poll;

            var reloadPage = function() {
                if (reloading) {
                    return;
                }
                reloading = true;
                if (timer) {
                    window.clearTimeout(timer);
                }
                window.location.reload();
            };

            var schedulePoll = function() {
                timer = window.setTimeout(poll, pollInterval * 1000);
            };

            poll = function() {
                attemptCount++;
                log.debug('mod_topomojo/launching: Status poll ' + attemptCount + ' of ' + maxAttempts);

                $.ajax({
                    url: params.statusUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        cmid: params.cmid,
                        gamespaceid: params.gamespaceId,
                        sesskey: params.sesskey
                    }
                }).done(function(response) {
                    if (response.ready) {
                        reloadPage();
                    }
                }).fail(function(request, textStatus, errorThrown) {
                    log.debug('mod_topomojo/launching: Status poll failed: ' +
                        request.status + ' ' + textStatus + ' ' + errorThrown);
                }).always(function() {
                    if (reloading) {
                        return;
                    }
                    if (attemptCount >= maxAttempts) {
                        reloadPage();
                        return;
                    }
                    schedulePoll();
                });
            };

            schedulePoll();
        }
    };
});
