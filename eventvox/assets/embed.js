/* EventVOX in an iframe (class-embeds.php): the frame grows with the page in it, and comes into view when that page
   asks (another page of the shop, a dialog). Only messages from the frame itself, from the EventVOX address. */
(function () {
    'use strict';
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.source !== 'eventvox-shop') return;
        var frames = document.querySelectorAll('iframe[data-eventvox-origin]');
        for (var i = 0; i < frames.length; i++) {
            var frame = frames[i];
            if (frame.contentWindow !== event.source || frame.getAttribute('data-eventvox-origin') !== event.origin) continue;
            if (data.type === 'height' && typeof data.height === 'number' && data.height > 0) {
                frame.style.minHeight = '0';
                frame.style.height = Math.min(Math.ceil(data.height), 20000) + 'px';
            } else if (data.type === 'top') {
                var top = frame.getBoundingClientRect().top;
                if (top < 0) window.scrollBy(0, top - 16);
            }
            return;
        }
    });
})();
