/**
 * Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
 * Author: Coderthemes
 * Toastr js
 */

import 'jquery-toast-plugin'

// Notification


!function ($) {
    "use strict";

    var NotificationApp = function () { };

    NotificationApp.prototype.send = function (heading, body, position, loaderBgColor, icon, hideAfter, stack, showHideTransition) {
        // default
        if (!hideAfter)
            hideAfter = 3000;
        if (!stack)
            stack = 1;

        var options = {
            heading: heading,
            text: body,
            position: position,
            loaderBg: loaderBgColor,
            icon: icon,
            hideAfter: hideAfter,
            stack: stack
        };

        if (showHideTransition)
            options.showHideTransition = showHideTransition;
        else
            options.showHideTransition = 'fade';

        $.toast().reset('all');
        $.toast(options);
    }

    $.NotificationApp = new NotificationApp, $.NotificationApp.Constructor = NotificationApp
}(window.jQuery);
