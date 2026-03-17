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
 * Audience filter for TopoMojo workspace selection.
 *
 * @module     mod_topomojo/audience_filter
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        /**
         * Initialize the audience filter functionality.
         * Reads workspace audience data from data attribute on the filter element.
         */
        init: function() {
            $(document).ready(function() {
                var allWorkspaces = null;
                var workspaceAudiences = {};

                // Read workspace audiences from data attribute
                var audienceData = $('#id_audiencefilter').attr('data-workspace-audiences');
                if (audienceData) {
                    try {
                        workspaceAudiences = JSON.parse(audienceData);
                    } catch (e) {
                        console.error('Failed to parse workspace audience data:', e);
                        return;
                    }
                }

                /**
                 * Filter workspaces based on selected audience.
                 */
                function filterWorkspaces() {
                    var selectedAudience = $('#id_audiencefilter').val();
                    var workspaceSelect = $('#id_workspaceid');

                    // Save all options on first run
                    if (!allWorkspaces) {
                        allWorkspaces = [];
                        workspaceSelect.find('option').each(function() {
                            allWorkspaces.push({
                                value: $(this).val(),
                                text: $(this).text()
                            });
                        });
                    }

                    var currentValue = workspaceSelect.val();

                    // Clear options
                    workspaceSelect.empty();

                    // Rebuild options based on filter
                    allWorkspaces.forEach(function(ws) {
                        // Always show empty option
                        if (ws.value === '') {
                            workspaceSelect.append($('<option></option>').val(ws.value).text(ws.text));
                            return;
                        }

                        // Show all if no filter
                        if (!selectedAudience || selectedAudience === '') {
                            workspaceSelect.append($('<option></option>').val(ws.value).text(ws.text));
                            return;
                        }

                        // Check if workspace has selected audience
                        var wsAudience = workspaceAudiences[ws.value] || '';
                        if (wsAudience.toLowerCase().indexOf(selectedAudience.toLowerCase()) !== -1) {
                            workspaceSelect.append($('<option></option>').val(ws.value).text(ws.text));
                        }
                    });

                    // Restore selection if still available
                    if (workspaceSelect.find('option[value="' + currentValue + '"]').length > 0) {
                        workspaceSelect.val(currentValue);
                    }

                    workspaceSelect.trigger('change');
                }

                // Attach event handler
                $('#id_audiencefilter').on('change', filterWorkspaces);
            });
        }
    };
});
