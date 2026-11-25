/* Navigation feedback: a thin progress bar the moment a link or form is used,
   so the app feels responsive while the next page loads. ~1 KB, no dependencies. */
(function () {
    'use strict';
    var bar = document.createElement('div');
    bar.className = 'nav-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    function start() {
        bar.classList.remove('run');
        void bar.offsetWidth;            // restart the transition
        bar.classList.add('run');
    }
    function stop() { bar.classList.remove('run'); }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (_) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search) return; // hash or same page
        if (/^(javascript|mailto|tel):/i.test(a.getAttribute('href'))) return;
        start();
    }, true);

    document.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) start();
    }, false);

    window.addEventListener('pageshow', stop);   // back/forward cache restores
})();
