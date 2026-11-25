/* Dashboard premium behaviour: count-up figures, sliding tab/segment
   indicators, pointer spotlight on the stat tiles. No dependencies.
   Everything is skipped under reduced motion; the page works without it. */
(function () {
    'use strict';
    var page = document.querySelector('.dash-page');
    if (!page) return;

    var root = document.documentElement;
    var still = function () {
        return matchMedia('(prefers-reduced-motion: reduce)').matches || root.classList.contains('reduce-motion');
    };

    // 1. Figures count up once on load.
    if (!still()) {
        var fmt = new Intl.NumberFormat('en-US');
        page.querySelectorAll('[data-count]').forEach(function (el, i) {
            var to = Number(el.dataset.count);
            if (!isFinite(to) || to === 0) return;
            var start = performance.now() + 250 + i * 110;
            var dur = 1300;
            el.textContent = fmt.format(0);
            requestAnimationFrame(function tick(now) {
                var p = Math.min(1, Math.max(0, (now - start) / dur));
                el.textContent = fmt.format(Math.round(to * (p >= 1 ? 1 : 1 - Math.pow(2, -10 * p))));
                if (p < 1) requestAnimationFrame(tick);
            });
        });
    }

    // 2. One sliding indicator that follows the active tab / segment.
    function slider(host, itemSel, isOn, ink, cls) {
        var place = function () {
            var cur = Array.prototype.find.call(host.querySelectorAll(itemSel), isOn);
            if (!cur) return;
            ink.style.setProperty('--x', cur.offsetLeft + 'px');
            ink.style.setProperty('--w', cur.offsetWidth + 'px');
        };
        host.classList.add(cls);
        host.appendChild(ink);
        place();
        requestAnimationFrame(function () { requestAnimationFrame(function () { ink.classList.add('is-live'); }); });
        new MutationObserver(place).observe(host, { subtree: true, attributes: true, attributeFilter: ['aria-selected', 'aria-pressed', 'class'] });
        if (window.ResizeObserver) new ResizeObserver(place).observe(host);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
    }
    page.querySelectorAll('.dash-tabs').forEach(function (host) {
        var ink = document.createElement('span');
        ink.className = 'dash-tab-ink';
        ink.setAttribute('aria-hidden', 'true');
        slider(host, '[role="tab"]', function (t) { return t.getAttribute('aria-selected') === 'true'; }, ink, 'has-ink');
    });
    page.querySelectorAll('.dash-seg').forEach(function (host) {
        var ink = document.createElement('span');
        ink.className = 'dash-seg-thumb';
        ink.setAttribute('aria-hidden', 'true');
        slider(host, '.dash-seg-btn', function (b) { return b.getAttribute('aria-pressed') === 'true'; }, ink, 'has-thumb');
    });

    // 3. Pointer spotlight on the stat tiles (mouse devices only).
    if (matchMedia('(hover: hover)').matches) {
        page.querySelectorAll('.dash-stat').forEach(function (el) {
            var queued = false, x = 0, y = 0;
            el.addEventListener('pointermove', function (e) {
                x = e.clientX; y = e.clientY;
                if (queued) return;
                queued = true;
                requestAnimationFrame(function () {
                    queued = false;
                    var r = el.getBoundingClientRect();
                    el.style.setProperty('--mx', (x - r.left) + 'px');
                    el.style.setProperty('--my', (y - r.top) + 'px');
                });
            }, { passive: true });
        });
    }

    // 4. Less scrolling: fold panels, a floating jump bar, back to top.
    var LS = 'pm.dash.folded';
    var load = function () { try { return JSON.parse(localStorage.getItem(LS)) || []; } catch (e) { return []; } };
    var save = function (list) { try { localStorage.setItem(LS, JSON.stringify(list)); } catch (e) {} };
    var svg = function (d) { return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>'; };
    var saved = load();
    var panels = [];
    var persist = function () { save(panels.filter(function (p) { return p.shut(); }).map(function (p) { return p.key; })); };

    page.querySelectorAll('.dash-panel').forEach(function (panel, n) {
        var head = panel.querySelector('.dash-panel-head');
        if (!head) return;
        var title = head.querySelector('h2');
        var name = title ? title.textContent.trim() : 'section';
        var key = name.toLowerCase().replace(/\s+/g, '-');

        var fold = document.createElement('div');
        var inner = document.createElement('div');
        fold.className = 'dash-fold';
        fold.id = 'dashFold' + n;
        inner.className = 'dash-fold-in';
        while (head.nextSibling) inner.appendChild(head.nextSibling);
        fold.appendChild(inner);
        panel.appendChild(fold);

        var end = document.createElement('div');
        end.className = 'dash-head-end';
        while (head.children.length > 1) end.appendChild(head.children[1]);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dash-fold-btn';
        btn.setAttribute('aria-controls', fold.id);
        btn.innerHTML = svg('<path d="m6 9 6 6 6-6"/>');
        end.appendChild(btn);
        head.appendChild(end);

        var api = {
            key: key, panel: panel,
            shut: function () { return panel.classList.contains('is-folded'); },
            set: function (shut) {
                panel.classList.toggle('is-folded', shut);
                fold.inert = shut;
                btn.setAttribute('aria-expanded', String(!shut));
                btn.setAttribute('aria-label', (shut ? 'Expand ' : 'Collapse ') + name);
                btn.title = shut ? 'Expand' : 'Collapse';
            }
        };
        api.set(saved.indexOf(key) > -1);
        btn.addEventListener('click', function () { api.set(!api.shut()); persist(); sync(); });
        panels.push(api);
    });

    var sections = [['dashOverview', 'Today'], ['dashAttention', 'Attention'], ['dashTrends', 'Trends'], ['dashBranches', 'Branches'], ['dashActivity', 'Activity']]
        .filter(function (s) { return document.getElementById(s[0]); });
    var smooth = function () { return still() ? 'auto' : 'smooth'; };

    var dock = document.createElement('nav');
    dock.className = 'dash-dock';
    dock.setAttribute('aria-label', 'Jump to a section');
    var chips = document.createElement('div');
    chips.className = 'dash-dock-chips';
    sections.forEach(function (s, i) {
        var a = document.createElement('a');
        a.style.setProperty('--i', i);
        a.className = 'dash-dock-chip';
        a.href = '#' + s[0];
        a.dataset.target = s[0];
        a.textContent = s[1];
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.getElementById(s[0]);
            panels.forEach(function (p) { if (target.contains(p.panel)) p.set(false); });
            persist(); sync();
            target.scrollIntoView({ behavior: smooth(), block: 'start' });
            Array.prototype.forEach.call(target.matches('.dash-panel') ? [target] : target.querySelectorAll('.dash-panel'), function (el) {
                el.classList.remove('is-found');
                void el.offsetWidth;
                el.classList.add('is-found');
                el.addEventListener('animationend', function done(ev) {
                    if (ev.animationName !== 'd-found') return;
                    el.classList.remove('is-found');
                    el.removeEventListener('animationend', done);
                });
            });
        });
        chips.appendChild(a);
    });
    var thumb = document.createElement('span');
    thumb.className = 'dash-seg-thumb';
    thumb.setAttribute('aria-hidden', 'true');
    slider(chips, '.dash-dock-chip', function (c) { return c.classList.contains('is-active'); }, thumb, 'has-thumb');

    var foldAll = document.createElement('button');
    foldAll.type = 'button';
    foldAll.className = 'dash-dock-btn';
    var toTop = document.createElement('button');
    toTop.type = 'button';
    toTop.className = 'dash-dock-btn dash-dock-top';
    toTop.setAttribute('aria-label', 'Back to top');
    toTop.title = 'Back to top';
    toTop.innerHTML = svg('<path d="M12 19V5M5 12l7-7 7 7"/>');
    var sep = document.createElement('span');
    sep.className = 'dash-dock-sep';
    sep.setAttribute('aria-hidden', 'true');
    dock.appendChild(chips); dock.appendChild(sep); dock.appendChild(foldAll); dock.appendChild(toTop);
    document.body.appendChild(dock);

    var last = null;
    function sync() {
        var every = !!(panels.length && panels.every(function (p) { return p.shut(); }));
        if (every === last) return;
        var first = last === null;
        last = every;
        foldAll.innerHTML = svg(every ? '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>' : '<path d="m7 20 5-5 5 5"/><path d="m7 4 5 5 5-5"/>') + '<span>' + (every ? 'Expand all' : 'Collapse all') + '</span>';
        foldAll.setAttribute('aria-label', every ? 'Expand all panels' : 'Collapse all panels');
        if (!first) {
            foldAll.classList.remove('is-swap');
            void foldAll.offsetWidth;
            foldAll.classList.add('is-swap');
        }
    }
    foldAll.addEventListener('click', function () {
        var shut = !panels.every(function (p) { return p.shut(); });
        panels.forEach(function (p) { p.set(shut); });
        persist(); sync();
    });
    toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: smooth() }); });
    sync();

    // Highlight the section being read.
    var setActive = function (id) {
        chips.querySelectorAll('.dash-dock-chip').forEach(function (c) { c.classList.toggle('is-active', c.dataset.target === id); });
    };
    if (sections.length) setActive(sections[0][0]);
    if (window.IntersectionObserver) {
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) setActive(e.target.id); });
        }, { rootMargin: '-25% 0px -65% 0px' });
        sections.forEach(function (s) { spy.observe(document.getElementById(s[0])); });
    }

    // Show the dock once the hero has scrolled away.
    var shown = false, queued = false;
    var update = function () {
        queued = false;
        var y = window.scrollY || root.scrollTop;
        var on = y > 360;
        dock.style.setProperty('--p', Math.min(100, Math.round(y / Math.max(1, root.scrollHeight - innerHeight) * 100)));
        if (on !== shown) { shown = on; dock.classList.toggle('is-visible', on); }
    };
    addEventListener('scroll', function () { if (!queued) { queued = true; requestAnimationFrame(update); } }, { passive: true });
    update();
    requestAnimationFrame(function () { requestAnimationFrame(function () { page.classList.add('is-ready'); }); });
})();
