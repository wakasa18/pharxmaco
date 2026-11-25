/* Pharxmaco premium polish — tiny, passive, zero layout work.
   Adds `.is-scrolled` to the topbar once the page scrolls, so its shadow can
   fade in. The handler only touches the DOM when the state actually flips. */
(function () {
    'use strict';
    var topbar = document.querySelector('.topbar');
    if (!topbar) return;

    var scrolled = false;
    var queued = false;

    function update() {
        queued = false;
        var next = (window.scrollY || document.documentElement.scrollTop) > 6;
        if (next !== scrolled) {
            scrolled = next;
            topbar.classList.toggle('is-scrolled', scrolled);
        }
    }

    window.addEventListener('scroll', function () {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(update);
    }, { passive: true });

    update();
})();

/* Theme switch: a reveal animation from the toggle button (View Transitions). The style
   comes from the "Theme switch animation" setting (circle, diamond, wipe, curtain, rise,
   split, diagonal, fade, or shuffle) and lives in theme-transitions.js. Every other kind of
   switch (settings page, device change) and browsers without View Transitions get a light
   colour fade instead. Skipped under reduced motion.

   Why it is built this way:
   - The reveal is slow and eased in AND out. The old curve front-loaded the motion, so
     most of the screen had already flipped within ~150 ms and the rest looked like a jump.
   - While the reveal runs, `html.theme-vt` freezes every element's own CSS transitions
     (see premium.css). Otherwise hundreds of buttons, cards and rows fade on the main
     thread inside the live snapshot, which is what made the switch stutter.
   - Extra clicks during the reveal are ignored instead of flipping the theme mid-animation. */
(function () {
    'use strict';
    var root = document.documentElement;
    var toggle = document.getElementById('themeToggle');

    // Tuning knobs.
    var FADE_CLEAR_MS = 520;     // a bit longer than the .4s fade in premium.css

    var still = function () {
        return matchMedia('(prefers-reduced-motion: reduce)').matches || root.classList.contains('reduce-motion');
    };
    // Phones and touch tablets: a full-screen clip-path animation repaints the whole
    // viewport on the main thread every frame, and the colour fade restyles every element
    // at once. Both stutter on mid-range phones, so compact screens get an opacity-only
    // cross-fade (GPU composited) and no per-element fade.
    var compact = function () { return matchMedia('(max-width: 900px), (pointer: coarse)').matches; };
    var busy = false, replay = false;
    var wasDark = root.classList.contains('dark');
    var timer = 0;

    // Added in the same frame as the colour change, so every colour animates.
    function fade() {
        root.classList.add('theme-fade');
        clearTimeout(timer);
        timer = setTimeout(function () { root.classList.remove('theme-fade'); }, FADE_CLEAR_MS);
    }
    function clearFade() {
        clearTimeout(timer);
        root.classList.remove('theme-fade');
    }

    new MutationObserver(function () {
        var dark = root.classList.contains('dark');
        if (dark === wasDark) return;
        wasDark = dark;
        if (!busy && !still() && !compact()) fade();
    }).observe(root, { attributes: true, attributeFilter: ['class'] });

    var styles = window.PharxmacoThemeTransitions;
    if (!toggle || !styles || typeof document.startViewTransition !== 'function') return;

    // The saved style ("shuffle" until the user picks one).
    function chosenStyle() {
        try { return window.PharxmacoUiPreferences.read().themeTransition; }
        catch (_) { return 'shuffle'; }
    }

    document.addEventListener('click', function (e) {
        if (replay || e.button !== 0 || !e.target.closest || !e.target.closest('#themeToggle')) return;

        // A reveal is already running: swallow the click so the theme cannot flip halfway.
        if (busy) {
            e.stopImmediatePropagation();
            e.preventDefault();
            return;
        }
        if (still()) return;

        e.stopImmediatePropagation();
        e.preventDefault();

        var style = styles.pick(chosenStyle());
        var run = function () { replay = true; try { toggle.click(); } finally { replay = false; } };
        var done = function () { busy = false; root.classList.remove('theme-vt'); };

        busy = true;
        clearFade();                       // never let the fallback fade overlap a reveal
        root.classList.add('theme-vt');
        try {
            var vt = document.startViewTransition(run);
            vt.ready.then(function () {
                styles.play(root, style, toggle, compact());
            }, function () {});
            vt.finished.then(done, done);
        } catch (err) {
            run();
            done();
        }
    }, true);
})();
