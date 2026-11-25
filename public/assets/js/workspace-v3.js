/* Pharxmaco v3 — navigation + submit feedback (about 1 KB, no dependencies).
   - A thin progress bar starts the moment a link or form is used.
   - The button that was pressed shows a spinner until the next page arrives.
   Nothing here changes what a form submits: buttons are never disabled. */
(function () {
    'use strict';
    if (!document.body || !document.body.classList.contains('is-workspace')) return;

    var bar = document.createElement('div');
    bar.className = 'ws-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    var spun = null, spinTimer = 0;

    function start() {
        stop();
        bar.classList.remove('run');
        void bar.offsetWidth;                   // restart the transition
        bar.classList.add('run');
        spinTimer = setTimeout(stop, 9000);
    }
    function stop() {
        bar.classList.remove('run');
        clearTimeout(spinTimer);
        if (spun) { spun.classList.remove('is-loading'); spun = null; }
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-progress')) return;
        var href = a.getAttribute('href') || '';
        if (/^(javascript|mailto|tel|#)/i.test(href)) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (_) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search) return;
        if (/export|download|\.pdf|\.csv|backup\/(download|create)/i.test(url.pathname + url.search)) return;
        start();
    }, true);

    // Bubble phase: by now every page script has had its chance to cancel the submit.
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var form = e.target;
        if (!form || form.target === '_blank' || form.hasAttribute('data-no-progress')) return;
        if (/export|download|pdf|csv|backup/i.test(form.getAttribute('action') || '')) return;
        start();
        var btn = e.submitter;
        if (btn && btn.classList && btn.classList.contains('btn')) {
            spun = btn;
            btn.classList.add('is-loading');
        }
    }, false);

    window.addEventListener('pageshow', stop);      // back/forward cache restores
    // The native unsaved-changes dialog pauses timers. If navigation is cancelled,
    // this runs when the dialog closes; accepted navigation unloads the page.
    window.addEventListener('beforeunload', function () { setTimeout(stop, 0); });
})();
