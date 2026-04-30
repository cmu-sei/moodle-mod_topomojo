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
 * Auto-refresh page while VMs are being provisioned.
 *
 * @module mod_topomojo/launching
 */
define(['jquery', 'core/log'], function($, log) {
    "use strict";

    return {
        /**
         * Initialize the launching page auto-refresh.
         *
         * @param {Object} params - Configuration parameters
         * @param {number} params.refreshInterval - Seconds between refresh attempts (default 5)
         * @param {number} params.maxAttempts - Maximum refresh attempts before stopping (default 24 = 2 minutes at 5s interval)
         */
        init: function(params) {
            var refreshInterval = params.refreshInterval || 5;
            var maxAttempts = params.maxAttempts || 24;
            var attemptCount = 0;

            log.debug('mod_topomojo/launching: Starting auto-refresh (interval: ' + refreshInterval + 's, max attempts: ' + maxAttempts + ')');

            var refreshTimer = setInterval(function() {
                attemptCount++;
                log.debug('mod_topomojo/launching: Refresh attempt ' + attemptCount + ' of ' + maxAttempts);

                // Reload the page to check VM status
                window.location.reload();

                if (attemptCount >= maxAttempts) {
                    clearInterval(refreshTimer);
                    log.debug('mod_topomojo/launching: Max attempts reached, stopping auto-refresh');
                }
            }, refreshInterval * 1000);
        }
    };
});
