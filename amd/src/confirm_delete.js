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
 * Confirmation dialog for deleting all attempts
 *
 * @module     mod_topomojo/confirm_delete
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str'], function($, ModalFactory, ModalEvents, Str) {
    return {
        init: function(buttonSelector, confirmTitle, confirmBody) {
            $(document).ready(function() {
                // Find button within form or directly
                var $button = $(buttonSelector + ' input[type="submit"]').first();
                if ($button.length === 0) {
                    $button = $(buttonSelector).first();
                }

                if ($button.length === 0) {
                    window.console.error('Delete button not found with selector:', buttonSelector);
                    return;
                }

                var $form = $button.closest('form');

                // Intercept the button click
                $button.on('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Create and show the modal
                    Str.get_strings([
                        {key: 'delete', component: 'core'},
                        {key: 'cancel', component: 'core'}
                    ]).then(function(strings) {
                        return ModalFactory.create({
                            type: ModalFactory.types.SAVE_CANCEL,
                            title: confirmTitle,
                            body: confirmBody,
                        }).then(function(modal) {
                            // Set button text
                            modal.setSaveButtonText(strings[0]);

                            // Handle confirmation
                            modal.getRoot().on(ModalEvents.save, function() {
                                $form.off('submit');
                                $form.submit();
                            });

                            // Show the modal
                            modal.show();
                            return modal;
                        });
                    }).catch(function(error) {
                        window.console.error('Error creating modal:', error);
                    });

                    return false;
                });
            });
        }
    };
});
