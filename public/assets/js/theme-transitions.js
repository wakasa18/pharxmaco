/* Theme switch animations, shared by the app (premium.js) and the sign-in page (login.js).

   Every style is a reveal: the new theme is drawn over the old one and uncovered by
   animating the clip-path (or opacity) of `::view-transition-new(root)`. Nothing here
   touches layout, and the per-element transition freeze in premium.css / login.css
   (`html.theme-vt`) still applies to all of them.

   Styles
     circle    grows from the toggle button (the original)
     diamond   a square that grows from the toggle, corners pointing up/down/left/right
     wipe      sweeps in from the left edge
     curtain   drops down from the top edge
     rise      lifts up from the bottom edge
     split     opens from the horizontal centre line outwards
     diagonal  sweeps in from the top-left corner
     fade      soft cross-fade with a tiny zoom settle (no clipping)

   "shuffle" picks one of the above at random and never repeats the previous pick.

   Phones and touch tablets always get the plain opacity cross-fade: clip-path animations
   repaint the whole viewport on the main thread every frame, which stutters on mid-range
   devices. */
(function () {
    'use strict';

    var REVEAL_MS = 850;
    var REVEAL_EASE = 'cubic-bezier(.4, 0, .2, 1)';
    var FADE_MS = 650;
    var COMPACT_MS = 300;
    var LAST_KEY = 'pharxmaco-last-theme-transition';

    var NAMES = ['circle', 'diamond', 'wipe', 'curtain', 'rise', 'split', 'diagonal', 'fade'];

    function clip(from, to) {
        return { keyframes: { clipPath: [from, to] }, duration: REVEAL_MS, easing: REVEAL_EASE };
    }

    // `o` is the geometry of the toggle: centre (x, y), the distance to the farthest
    // corner (radius) and the reach a diamond needs to cover the whole viewport.
    var recipes = {
        circle: function (o) {
            var at = ' at ' + o.x + 'px ' + o.y + 'px)';
            return clip('circle(0px' + at, 'circle(' + o.radius + 'px' + at);
        },
        diamond: function (o) {
            var x = o.x + 'px', y = o.y + 'px';
            var point = x + ' ' + y;
            return clip(
                'polygon(' + [point, point, point, point].join(',') + ')',
                'polygon(' + [
                    x + ' ' + (o.y - o.reach) + 'px',
                    (o.x + o.reach) + 'px ' + y,
                    x + ' ' + (o.y + o.reach) + 'px',
                    (o.x - o.reach) + 'px ' + y
                ].join(',') + ')'
            );
        },
        wipe:    function () { return clip('inset(0 100% 0 0)', 'inset(0 0 0 0)'); },
        curtain: function () { return clip('inset(0 0 100% 0)', 'inset(0 0 0 0)'); },
        rise:    function () { return clip('inset(100% 0 0 0)', 'inset(0 0 0 0)'); },
        split:   function () { return clip('inset(50% 0 50% 0)', 'inset(0 0 0 0)'); },
        diagonal: function () {
            // A triangle anchored in the top-left corner; 230% is enough to pass the far corner.
            return clip('polygon(0 0, 0 0, 0 0)', 'polygon(0 0, 230% 0, 0 230%)');
        },
        fade: function () {
            return {
                keyframes: { opacity: [0, 1], transform: ['scale(1.02)', 'scale(1)'] },
                duration: FADE_MS,
                easing: 'ease-out'
            };
        }
    };

    function geometry(origin) {
        var w = window.innerWidth, h = window.innerHeight;
        var x = w / 2, y = h / 2;
        if (origin && typeof origin.getBoundingClientRect === 'function') {
            var r = origin.getBoundingClientRect();
            x = r.left + r.width / 2;
            y = r.top + r.height / 2;
        }
        var far = Math.max(x, w - x), tall = Math.max(y, h - y);
        return { x: x, y: y, radius: Math.hypot(far, tall), reach: far + tall };
    }

    function remembered() {
        try { return sessionStorage.getItem(LAST_KEY) || ''; } catch (_) { return ''; }
    }
    function remember(name) {
        try { sessionStorage.setItem(LAST_KEY, name); } catch (_) { /* optional nicety */ }
    }

    // A chosen style is used as is. "shuffle" (and anything unrecognised) picks at random,
    // never the same style twice in a row, even across page loads in the same tab.
    function pick(choice) {
        if (NAMES.indexOf(choice) !== -1) return choice;
        var last = remembered();
        var pool = NAMES.filter(function (name) { return name !== last; });
        var name = pool[Math.floor(Math.random() * pool.length)];
        remember(name);
        return name;
    }

    // Call from `transition.ready`. Returns the Animation so callers can chain on it if needed.
    function play(root, name, origin, compact) {
        var target = '::view-transition-new(root)';
        if (compact) {
            return root.animate({ opacity: [0, 1] }, { duration: COMPACT_MS, easing: 'ease-out', pseudoElement: target });
        }
        var recipe = (recipes[name] || recipes.circle)(geometry(origin));
        return root.animate(recipe.keyframes, {
            duration: recipe.duration,
            easing: recipe.easing,
            pseudoElement: target
        });
    }

    window.PharxmacoThemeTransitions = { names: NAMES.slice(), pick: pick, play: play };
})();
