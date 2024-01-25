!function ($) {
    "use strict";

    const Devices = function () {
        this.$body = $("body")
        this.$restartDeviceBtn = $(".btn-restart-device")
        this.$deleteDeviceBtn = $(".btn-delete-device")
        this.$actionCheckbox = $(".action_checkbox")
        this.$restartSelectedDevices = $(".btn-restart-selected-devices")
        this.$restartAllDevices = $('.btn-restart-all-devices')
        this.$selectallCheckbox = $('#selectallCheckbox')
        //this.$restartedWrapper = '<div id="restartedWrapper" class="jq-toast-wrap bottom-right">' +
        //    '<div class="jq-toast-single"><h2 class="jq-toast-heading">Restarting devices</h2>' +
        //    '<span id="restartedCount"></span></div></div>'
    };

    Devices.prototype.init = function () {
        var $this = this;
        this.$restartDeviceBtn.on("click", function (e) {
            e.preventDefault();
            $this.sendRequest(e.currentTarget.href.replace(':id', e.currentTarget.dataset.extensionId));
            return false;
        });
        this.$restartSelectedDevices.on("click", function (e) {
            e.preventDefault();
            //$this.removeRestartedWrapper(false)
            let devices = $this.$actionCheckbox.filter(':checked');
            //let devicesTotal = devices.length;
            //$('#restartedCount').text(`0 of ${devicesTotal}`)
            //$('body').append($this.$restartedWrapper);

            let extensionIds = [];
            devices.each(function(key, val) {
                extensionIds.push($(this).data('extension-uuid'));
            });
            $this.sendRequest(e.currentTarget.dataset.restartUrl, extensionIds)

            return false;
        });
        this.$deleteDeviceBtn.on("click", function (e) {
            e.preventDefault();
            let dataObj = {};
            dataObj.url = e.currentTarget.href;
            dataObj.setting_id = e.currentTarget.dataset.id;
            $('#confirmDeleteModal').data(dataObj).modal('show');
            //$this.sendRequest(e.currentTarget.href, null, 'DELETE');
            return false;
        });
        this.$restartAllDevices.on('click', function (e) {
            e.preventDefault();
            //$this.removeRestartedWrapper(false)
            //$('#restartedCount').text(`0 of ${e.currentTarget.dataset.totalDevicesCount}`)
            //$('body').append($this.$restartedWrapper);
            $this.sendRequest(e.currentTarget.dataset.restartUrl)

            return false;
        })
        this.$restartSelectedDevices.on('click', function (e) {
            e.preventDefault();

        })
        this.$actionCheckbox.on('click', function (e) {
            if ($this.$actionCheckbox.filter(':checked').length > 0) {
                $this.enableRestartSelectedDevices()
            } else {
                $this.disableRestartSelectedDevices()
            }
        })
        this.$selectallCheckbox.on('click', function () {
            if ($(this).prop('checked')) {
                $this.enableRestartSelectedDevices()
            } else {
                $this.disableRestartSelectedDevices()
            }
        })
    }

    Devices.prototype.enableRestartSelectedDevices = function () {
        this.$restartSelectedDevices.removeClass('disabled');
    }

    Devices.prototype.disableRestartSelectedDevices = function () {
        this.$restartSelectedDevices.addClass('disabled');
    }

    Devices.prototype.removeRestartedWrapper = function (fadeOut = true) {
        if(fadeOut) {
            $('body').find('#restartedWrapper').fadeOut(function () {
                $(this).remove();
            });
        } else {
            $('body').find('#restartedWrapper').remove()
        }
    }

    Devices.prototype.sendRequest = function (url, devicesData = null, hideNotifyOnSuccess = false) {
        let ajaxData = {
            type: 'POST',
            url: url,
            cache: false,
            data: {
                extensionIds: devicesData
            }
        };
        $.ajax(ajaxData).done(function (response) {
            //$('.loading').hide();
            if (response.error) {
                if (response.message) {
                    $.NotificationApp.send(
                        "Warning",
                        response.message,
                        "top-right",
                        "#ff5b5b",
                        "error"
                    );
                }
                if (response.error.message) {
                    $.NotificationApp.send(
                        "Warning",
                        response.error.message,
                        "top-right",
                        "#ff5b5b",
                        "error"
                    );
                }
            } else {
                if (response.success && response.success.message) {
                    if (!hideNotifyOnSuccess) {
                        $.NotificationApp.send(
                            "Success",
                            response.success.message,
                            "top-right",
                            "#10c469",
                            "success"
                        );
                    }
                }
            }
        }).fail(function (jqXHR, testStatus, error) {
            $('.loading').hide();
            printErrorMsg(error);
        });
    }

    $.Devices = new Devices
    $.Devices.Constructor = Devices

    $.Devices.init()

}(window.jQuery);

/*
{{--
        document.addEventListener('DOMContentLoaded', function() {
            $('#clearSearch').on('click', function () {
                $('#search').val('');
                var location = window.location.protocol + "//" + window.location.host + window.location.pathname;
                location += '?page=1';
                window.location.href = location;
            })

            {{-- // TODO: need to move on vite
            function sendEventNotify(url, extension_id = '') {
                //var setting_id = $("#confirmDeleteModal").data("setting_id");
                //$('#confirmDeleteModal').modal('hide');
                //$('.loading').show();

                if (extension_id == '') {
                    extension_id = [];
                    $('.action_checkbox').each(function(key, val) {
                        if ($(this).is(':checked')) {
                            extension_id.push($(this).val());
                        }
                    });
                }

                //Check if we received an array with multiple IDs
                if (Array.isArray(extension_id)) {
                    extension_id.forEach(function(item) {
                        // var url = $("#confirmDeleteModal").data("url");
                        url = url.replace(':id', item);
                        $.ajax({
                            type: 'POST',
                            url: url,
                            cache: false,
                        })
                            .done(function(response) {
                                //console.log(response);
                                //$('.loading').hide();

                                if (response.error) {
                                    if (response.message) {
                                        $.NotificationApp.send("Warning", response.message, "top-right", "#ff5b5b",
                                            "error");
                                    }
                                    if (response.error.message) {
                                        $.NotificationApp.send("Warning", response.error.message, "top-right",
                                            "#ff5b5b", "error");
                                    }

                                } else {
                                    if (response.message) {
                                        $.NotificationApp.send("Success", response.message, "top-right", "#10c469",
                                            "success");
                                    }

                                    if (response.success && response.success.message) {
                                        $.NotificationApp.send("Success", response.success.message, "top-right",
                                            "#10c469", "success");
                                    }

                                }
                            })
                            .fail(function(jqXHR, testStatus, error) {
                                $('.loading').hide();
                                printErrorMsg(error);
                            });
                    });

                } else {
                    //var url = $("#confirmDeleteModal").data("url");
                    url = url.replace(':id', extension_id);

                    $.ajax({
                        type: 'POST',
                        url: url,
                        cache: false,

                    })
                        .done(function(response) {
                            //$('.loading').hide();

                            if (response.error) {
                                if (response.message) {
                                    $.NotificationApp.send("Warning", response.message, "top-right", "#ff5b5b", "error");
                                }
                                if (response.error.message) {
                                    $.NotificationApp.send("Warning", response.error.message, "top-right", "#ff5b5b",
                                        "error");
                                }

                            } else {
                                if (response.message) {
                                    $.NotificationApp.send("Success", response.message, "top-right", "#10c469", "success");
                                }

                                if (response.success && response.success.message) {
                                    $.NotificationApp.send("Success", response.success.message, "top-right", "#10c469",
                                        "success");
                                }

                            }
                        })
                        .fail(function(jqXHR, testStatus, error) {
                            $('.loading').hide();
                            printErrorMsg(error);
                        });
                }
            }

        }); --}}
 */
