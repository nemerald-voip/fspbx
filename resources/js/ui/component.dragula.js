/**
* Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
* Author: Coderthemes
* Component: Dragula component
*/

import dragula from 'dragula/dist/dragula.min.js';

!function ($) {
    "use strict";

    var Dragula = function () {
        this.$body = $("body")
    };


    /* Initializing */
    Dragula.prototype.init = function () {

        $('[data-plugin="dragula"]').each(function () {
            var containersIds = $(this).data("containers");
            var containers = [];
            if (containersIds) {
                for (var i = 0; i < containersIds.length; i++) {
                    containers.push($("#" + containersIds[i])[0]);
                }
            } else {
                containers = [$(this)[0]];
            }

            // if handle provided
            var handleClass = $(this).data("handleclass");

            // init dragula
            if (handleClass) {
                dragula(containers, {
                    moves: function (el, container, handle) {
                        return handle.classList.contains(handleClass);
                    }
                });
            } else {
                dragula(containers);
            }

        });
    },

        //init dragula
        $.Dragula = new Dragula, $.Dragula.Constructor = Dragula

}(window.jQuery),

//initializing Dragula
function ($) {
"use strict";
    $.Dragula.init()
}(window.jQuery);
