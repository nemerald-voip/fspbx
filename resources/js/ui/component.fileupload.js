/**
* Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
* Author: Coderthemes
* Component: Dragula component
*/


import select2 from 'select2';
select2(window, jQuery);

import 'bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js';

import Dropzone from 'dropzone';

!function ($) {
    "use strict";

    var FileUpload = function () {
        this.$body = $("body")
    };


    /* Initializing */
    FileUpload.prototype.init = function () {
        // Disable auto discovery

        Dropzone.autoDiscover = false;



        $('[data-plugin="dropzone"]').each(function () {
            var actionUrl = $(this).attr('action')
            var previewContainer = $(this).data('previewsContainer');

            var csrfToken = $('meta[name="csrf-token"]').attr('content'); // Retrieve CSRF token from the meta tag

            var opts = { url: actionUrl, headers: { 'X-CSRF-TOKEN': csrfToken } }; // Add CSRF token to headers

            if (previewContainer) {
                opts['previewsContainer'] = previewContainer;
            }

            var uploadPreviewTemplate = $(this).data("uploadPreviewTemplate");
            if (uploadPreviewTemplate) {
                opts['previewTemplate'] = $(uploadPreviewTemplate).html();
            }

            var autoProcessQueue = $(this).data("autoProcessQueue");
            if (autoProcessQueue) {
                opts['autoProcessQueue'] = true; // Explicitly convert the string to a boolean
            } else {
                opts['autoProcessQueue'] = false; // Explicitly convert the string to a boolean
            }

            var uploadMultiple = $(this).data("uploadMultiple");
            if (uploadMultiple) {
                opts['uploadMultiple'] = true; // Explicitly convert the string to a boolean
            } else {
                opts['uploadMultiple'] = false; // Explicitly convert the string to a boolean
            }

            var parallelUploads = $(this).data("parallelUploads");
            if (parallelUploads) {
                opts['parallelUploads'] = parallelUploads;
            }

            var maxFilesize = $(this).data("maxFilesize");
            if (maxFilesize) {
                opts['maxFilesize'] = maxFilesize;
            }

            var maxFiles = $(this).data("maxFiles");
            if (maxFiles) {
                opts['maxFiles'] = maxFiles;
            }

            var thumbnailWidth = $(this).data("thumbnailWidth");
            if (thumbnailWidth) {
                opts['thumbnailWidth'] = thumbnailWidth;
            }

            var acceptedFiles = $(this).data("acceptedFiles");
            if (acceptedFiles) {
                opts['acceptedFiles'] = acceptedFiles;
            }

            // Add the init function to the options array
            opts['init'] = function () {
                var myDropzone = this;

                var submitButton = document.getElementById('dropzoneSubmit');

                if (submitButton) {
                    submitButton.addEventListener("click", function (e) {
                        // Make sure that the form isn't actually being sent.
                        e.preventDefault();
                        e.stopPropagation();
                        myDropzone.processQueue();
                    });
                }

                myDropzone.on("sending", function (file, xhr, formData) {
                    // Find the form that contains the Dropzone
                    var form = this.element.closest('form');

                    // Iterate over all form elements
                    Array.from(form.elements).forEach(function (element) {
                        // Check if the element has a name and value
                        if (element.type === 'checkbox') {
                            formData.append(element.name, element.checked ? 'true' : 'false');
                        } else {
                            formData.append(element.name, element.value);
                        }
                    });

                });

                myDropzone.on("success", function () {
                    this.removeAllFiles(true);

                    // Dispatch a custom event for success
                    var successEvent = new Event('dropzoneSuccessEvent');
                    document.dispatchEvent(successEvent);

                });
                myDropzone.on("error", function (files, message) {
                    this.removeAllFiles(true);
                    if(message.error) {
                        var error = message.error;
                    } else {
                        var error = message;
                    }

                    // Dispatch a custom event for error
                    var errorEvent = new CustomEvent('dropzoneErrorEvent', { detail: { errorMessage: error } });
                    document.dispatchEvent(errorEvent);
                });

            };


            var dropzoneEl = $(this).dropzone(opts);

        });
    },

        //init fileupload
        $.FileUpload = new FileUpload, $.FileUpload.Constructor = FileUpload

}(window.jQuery),

    //initializing FileUpload
    function ($) {
        "use strict";
        $.FileUpload.init()
    }(window.jQuery);
