/*
* Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
* Author: Coderthemes
* Component: Chat init js
*/



!function ($) {
    "use strict";

    var ChatApp = function () {
        this.$body = $("body"),
            this.$chatInput = $('.chat-input'),
            this.$chatList = $('.conversation-list'),
            this.$chatSendBtn = $('.chat-send'),
            this.$chatForm = $("#chat-form")
    };

    ChatApp.prototype.save = function () {
        var chatText = this.$chatInput.val();
        var chatTime = moment().format("h:mm");
        if (chatText == "") {
            this.$chatInput.focus();
            return false;
        } else {
            $('<li class="clearfix odd"><div class="chat-avatar"><img src="/images/users/avatar-1.jpg" alt="male"><i>' + chatTime + '</i></div><div class="conversation-text"><div class="ctext-wrap"><i>Dominic</i><p>' + chatText + '</p></div></div></li>').appendTo('.conversation-list');
            this.$chatInput.focus();
            this.$chatList.animate({ scrollTop: this.$chatList.prop("scrollHeight") }, 1000);
            return true;
        }
    }

    // init
    ChatApp.prototype.init = function () {
        var $this = this;
        //binding keypress event on chat input box - on enter we are adding the chat into chat list - 
        $this.$chatInput.keypress(function (ev) {
            var p = ev.which;
            if (p == 13) {
                $this.save();
                return false;
            }
        });


        //binding send button click
        $this.$chatForm.on('submit', function (ev) {
            ev.preventDefault();
            $this.save();
            $this.$chatForm.removeClass('was-validated');
            $this.$chatInput.val('');
            return false;
        });
    },
        //init ChatApp
        $.ChatApp = new ChatApp, $.ChatApp.Constructor = ChatApp

}(window.jQuery),

    //initializing main application module
    function ($) {
        "use strict";
        $.ChatApp.init();
    }(window.jQuery);