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
 * Generic confirmation dialog for form submissions
 *
 * @module     mod_topomojo/confirm_action
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_save_cancel', 'core/modal_events', 'core/str', 'core/ajax'], function($, ModalSaveCancel, ModalEvents, Str, Ajax) {
    return {
        init: function(buttonSelector, confirmTitle, confirmBody, confirmFlagSelector, useAjax) {
            useAjax = useAjax || false;
            $(document).ready(function() {
                // Find the button
                var $button = $(buttonSelector).first();

                if ($button.length === 0) {
                    window.console.error('Button not found with selector:', buttonSelector);
                    return;
                }

                var $form = $button.closest('form');
                var $confirmFlag = $(confirmFlagSelector);
                var $previewField = $('#preview');
                var $previewButton = $('#preview_button');

                // Intercept the form submission
                $form.on('submit', function(e) {
                    // If already confirmed, allow submission
                    if ($confirmFlag.val() === 'yes') {
                        return true;
                    }

                    // Otherwise, prevent and show modal
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Create and show the modal
                    Str.get_strings([
                        {key: 'confirm', component: 'core'},
                        {key: 'cancel', component: 'core'}
                    ]).then(function(strings) {
                        return ModalSaveCancel.create({
                            title: confirmTitle,
                            body: confirmBody,
                        }).then(function(modal) {
                            modal.setSaveButtonText(strings[0]);

                            // Handle confirmation
                            modal.getRoot().on(ModalEvents.save, function() {
                                modal.hide();
                                $confirmFlag.val('yes');

                                // Determine which button to show spinner on based on preview field
                                var $targetButton = $button;
                                if ($previewField.length > 0 && $previewField.val() === '1' && $previewButton.length > 0) {
                                    $targetButton = $previewButton;
                                }

                                // Show loading state on the appropriate button
                                $targetButton.prop('disabled', true);
                                $targetButton.html('<span class="spinner"></span> Please wait, system processing');

                                if (useAjax) {
                                    // Replace page content with launching spinner
                                    $('.topomojo-content-area').html(
                                        '<div class="alert alert-info topomojo-launching">' +
                                        '<div class="d-flex align-items-center">' +
                                        '<div class="spinner-border text-primary mr-3" role="status">' +
                                        '<span class="sr-only">Launching Lab...</span>' +
                                        '</div>' +
                                        '<div>' +
                                        '<strong>Launching Lab...</strong>' +
                                        '<p class="mb-0">Please wait while your virtual machines are being deployed. This may take up to 60 seconds.</p>' +
                                        '</div>' +
                                        '</div>' +
                                        '</div>'
                                    );

                                    // Submit via AJAX
                                    var formData = new FormData($form[0]);
                                    $.ajax({
                                        url: $form.attr('action'),
                                        type: 'POST',
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            // Replace entire page content with response
                                            $('html').html(response);
                                        },
                                        error: function(xhr) {
                                            // Show error in content area
                                            $('.topomojo-content-area').html(
                                                '<div class="alert alert-danger">' +
                                                '<strong>Error</strong>' +
                                                '<p>' + (xhr.responseText || 'Failed to start lab. Please refresh and try again.') + '</p>' +
                                                '</div>'
                                            );
                                        }
                                    });
                                } else {
                                    $form.off('submit');
                                    $form[0].submit();
                                }
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
