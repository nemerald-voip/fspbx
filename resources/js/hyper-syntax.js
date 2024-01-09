/**
 * Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
 * Author: Coderthemes
 * Module/App: Hightlight the syntax
 */


import hljs from 'highlightjs/highlight.pack.js'

import ClipboardJS from 'clipboard'
ClipboardJS;

(function ($) {

    'use strict';

    function initHighlight() {

        //syntax
        var entityMap = {
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;", "/": "&#x2F;",
        };

        $(document).ready(function () {
            document
                .querySelectorAll("pre span.escape")
                .forEach(function (element, n) {
                    if (element.classList.contains("escape")) {
                        var text = element.innerText;
                    } else {
                        var text = element.innerText;
                    }
                    text = text.replace(/^\n/, "").trimRight(); // goodbye starting whitespace
                    var to_kill = Infinity;
                    var lines = text.split("\n");
                    for (var i = 0; i < lines.length; i++) {
                        if (!lines[i].trim()) {
                            continue;
                        }
                        to_kill = Math.min(lines[i].search(/\S/), to_kill);
                    }
                    var out = [];
                    for (var i = 0; i < lines.length; i++) {
                        out.push(lines[i].replace(new RegExp("^ {" + to_kill + "}", "g"), ""));
                    }
                    element.innerText = out.join("\n");
                });

            document.querySelectorAll("pre span.escape").forEach(function (block) {
                hljs.highlightBlock(block);
            });
        });
    }

    function init() {
        initHighlight();
    }

    init();

})(jQuery)


var clipboard = new ClipboardJS('.btn-copy-clipboard', {
    target: function (trigger) {
        var highlight = trigger.closest('.tab-pane.active');

        const el = highlight.querySelector('.html.escape');

        return el;
    }
});

clipboard.on('success', function (e) {
    var originalLabel = e.trigger.innerHTML;
    e.trigger.innerHTML = "Copied";
    setTimeout(function () {
        e.trigger.innerHTML = originalLabel;
    }, 3000);
    e.clearSelection();
});

