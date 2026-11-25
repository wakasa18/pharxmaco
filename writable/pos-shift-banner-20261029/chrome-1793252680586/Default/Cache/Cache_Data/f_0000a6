/*!
 * Themed calendar popup for <input type="date">.
 *
 * The native input stays (segment typing, min/max, required, ISO value for the server);
 * only the browser's own popup is replaced. Works for inputs added later too (event delegation).
 *
 *   data-native-date   on an input  -> keep the browser's popup for that field
 *
 * Runs only in browsers that let us restyle the calendar icon (Chrome, Edge, Safari desktop) and
 * not on touch phones/tablets, where the OS date wheel is nicer. Everywhere else nothing changes.
 */
(function () {
    'use strict';

    if (window.__pxDatePicker) { return; }
    window.__pxDatePicker = true;

    var canRestyle = window.CSS && CSS.supports && CSS.supports('selector(::-webkit-calendar-picker-indicator)');
    var touchOnly = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    if (!canRestyle || touchOnly) { return; }
    document.documentElement.classList.add('has-date-picker');

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    var WEEKDAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    var ICON = {
        prev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        next: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>',
        chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'
    };

    /* ---------- date helpers (local time, ISO strings compare lexicographically) ---------- */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function iso(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }          // m is 0-based
    function fromDate(dt) { return iso(dt.getFullYear(), dt.getMonth(), dt.getDate()); }
    function today() { return fromDate(new Date()); }
    function parse(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) { return null; }
        var y = +m[1], mo = +m[2] - 1, d = +m[3];
        var dt = new Date(y, mo, d);
        return (dt.getFullYear() === y && dt.getMonth() === mo && dt.getDate() === d) ? { y: y, m: mo, d: d } : null;
    }
    function addDays(s, n) { var p = parse(s); return fromDate(new Date(p.y, p.m, p.d + n)); }
    function addMonths(s, n) {
        var p = parse(s);
        var target = new Date(p.y, p.m + n, 1);
        var last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
        return iso(target.getFullYear(), target.getMonth(), Math.min(p.d, last));
    }
    function weekday(s) { var p = parse(s); return new Date(p.y, p.m, p.d).getDay(); }
    function longLabel(s) {
        var p = parse(s);
        return WEEKDAYS_LONG[weekday(s)] + ', ' + MONTHS[p.m] + ' ' + p.d + ', ' + p.y;
    }

    /* ---------- state ---------- */
    var pop = null;           // popup element
    var st = null;            // { input, view, year, month, focus, host }

    function limits(input) {
        // A stored legacy expiry may be exempt from native validation while left
        // unchanged. Its calendar must still offer only new, permitted dates.
        var expiry = input.closest('[data-expiry-date-control]');
        var min = expiry ? expiry.dataset.minimum : input.min;
        var max = expiry ? expiry.dataset.maximum : input.max;
        return { min: parse(min) ? min : '', max: parse(max) ? max : '' };
    }
    function inRange(s, lim) { return (!lim.min || s >= lim.min) && (!lim.max || s <= lim.max); }
    function clampToRange(s, lim) {
        if (lim.max && s > lim.max) { return lim.max; }
        if (lim.min && s < lim.min) { return lim.min; }
        return s;
    }
    function periodInRange(year, month, view, lim) {
        var first, last;
        if (view === 'days') {
            // Date normalizes December + 1 and January - 1 into the next year.
            first = fromDate(new Date(year, month, 1));
            last = fromDate(new Date(year, month + 1, 0));
        } else if (view === 'months') {
            first = iso(year, 0, 1); last = iso(year, 11, 31);
        } else {
            var base = Math.floor(year / 12) * 12;
            first = iso(base, 0, 1); last = iso(base + 11, 11, 31);
        }
        return (!lim.min || last >= lim.min) && (!lim.max || first <= lim.max);
    }
    function setFocus(target) {
        target = clampToRange(target, st.lim);
        var p = parse(target);
        if (!p) { return false; }
        st.focus = target; st.year = p.y; st.month = p.m;
        return true;
    }

    function eligible(input) {
        return input && input.tagName === 'INPUT' && input.type === 'date' &&
            !input.disabled && !input.readOnly && !input.hasAttribute('data-native-date');
    }

    /* ---------- open / close ---------- */
    function open(input) {
        if (!eligible(input)) { return; }
        if (st && st.input === input) { close(false); return; }
        close(false);

        var lim = limits(input);
        var start = clampToRange(parse(input.value) ? input.value : today(), lim);
        var p = parse(start);
        st = { input: input, view: 'days', year: p.y, month: p.m, focus: start, lim: lim };

        pop = document.createElement('div');
        pop.className = 'dp';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-modal', 'false');
        pop.setAttribute('aria-label', 'Choose date');
        // On the popup itself (not the document) so Escape can stop here and not also close a modal behind it.
        pop.addEventListener('keydown', onPopupKey);
        // Inside an open modal the popup must live in that modal, or its focus trap would reject it.
        (input.closest('[aria-hidden="false"], dialog[open]') || document.body).appendChild(pop);

        input.classList.add('dp-open');
        render(true);
        place();
    }

    function close(returnFocus) {
        if (!st) { return; }
        var input = st.input;
        input.classList.remove('dp-open');
        if (pop && pop.parentNode) { pop.parentNode.removeChild(pop); }
        pop = null; st = null;
        if (returnFocus && document.contains(input)) { input.focus({ preventScroll: true }); }
    }

    function place() {
        if (!pop || !st) { return; }
        var r = st.input.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) { close(false); return; }
        var margin = 8, gap = 6;
        var w = pop.offsetWidth, h = pop.offsetHeight;
        var left = Math.max(margin, Math.min(r.left, window.innerWidth - w - margin));
        var below = window.innerHeight - r.bottom - gap - margin;
        var above = r.top - gap - margin;
        var flip = h > below && above > below;
        var top = flip ? Math.max(margin, r.top - gap - h) : Math.min(r.bottom + gap, window.innerHeight - h - margin);
        pop.style.left = left + 'px';
        pop.style.top = Math.max(margin, top) + 'px';
        pop.style.setProperty('--dp-origin', (flip ? 'bottom ' : 'top ') + (r.left - left < w / 2 ? 'left' : 'right'));
        pop.style.setProperty('--dp-shift', flip ? '4px' : '-4px');
    }

    /* ---------- rendering ---------- */
    function btn(cls, attrs, html) {
        return '<button type="button" class="' + cls + '" ' + attrs + '>' + html + '</button>';
    }

    function render(focusGrid) {
        if (!pop || !st) { return; }
        var lim = st.lim = limits(st.input);
        setFocus(st.focus);
        var sel = parse(st.input.value) ? st.input.value : '', now = today();
        var title, prevOff = false, nextOff = false, prevLabel, nextLabel, body = '';

        if (st.view === 'days') {
            title = MONTHS[st.month] + ' ' + st.year;
            prevLabel = 'Previous month'; nextLabel = 'Next month';
            prevOff = !periodInRange(st.year, st.month - 1, 'days', lim);
            nextOff = !periodInRange(st.year, st.month + 1, 'days', lim);

            var first = new Date(st.year, st.month, 1);
            var gridStart = new Date(st.year, st.month, 1 - first.getDay());
            body += '<div class="dp-week" aria-hidden="true">';
            WEEKDAYS.forEach(function (w) { body += '<span class="dp-wd">' + w + '</span>'; });
            body += '</div><div role="grid" aria-label="' + title + '">';
            for (var row = 0; row < 6; row++) {
                body += '<div class="dp-row" role="row">';
                for (var col = 0; col < 7; col++) {
                    var dt = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + row * 7 + col);
                    var s = fromDate(dt);
                    var cls = 'dp-day' + (dt.getMonth() !== st.month ? ' is-out' : '') + (s === now ? ' is-today' : '') + (s === sel ? ' is-selected' : '');
                    var off = !inRange(s, lim);
                    body += btn(cls, 'role="gridcell" data-date="' + s + '" tabindex="' + (s === st.focus ? 0 : -1) + '"' +
                        ' aria-label="' + longLabel(s) + '"' + (s === sel ? ' aria-selected="true"' : '') +
                        (s === now ? ' aria-current="date"' : '') + (off ? ' disabled' : ''), dt.getDate());
                }
                body += '</div>';
            }
            body += '</div>';
        } else if (st.view === 'months') {
            title = String(st.year);
            prevLabel = 'Previous year'; nextLabel = 'Next year';
            prevOff = !periodInRange(st.year - 1, st.month, 'months', lim);
            nextOff = !periodInRange(st.year + 1, st.month, 'months', lim);
            body += '<div class="dp-grid" role="group" aria-label="Months of ' + st.year + '">';
            for (var m = 0; m < 12; m++) {
                var mFirst = iso(st.year, m, 1), mLast = iso(st.year, m, new Date(st.year, m + 1, 0).getDate());
                var mOff = (lim.min && mLast < lim.min) || (lim.max && mFirst > lim.max);
                var p = parse(sel);
                body += btn('dp-cell' + (p && p.y === st.year && p.m === m ? ' is-selected' : '') + (now.slice(0, 7) === mFirst.slice(0, 7) ? ' is-today' : ''),
                    'data-month="' + m + '"' + (m === st.month ? ' data-focus' : '') + (mOff ? ' disabled' : ''), MONTHS_SHORT[m]);
            }
            body += '</div>';
        } else {
            var base = Math.floor(st.year / 12) * 12;
            title = base + ' – ' + (base + 11);
            prevLabel = 'Previous 12 years'; nextLabel = 'Next 12 years';
            prevOff = !periodInRange(st.year - 12, st.month, 'years', lim);
            nextOff = !periodInRange(st.year + 12, st.month, 'years', lim);
            body += '<div class="dp-grid" role="group" aria-label="Years">';
            for (var y = base; y < base + 12; y++) {
                var yOff = (lim.min && iso(y, 11, 31) < lim.min) || (lim.max && iso(y, 0, 1) > lim.max);
                var sp = parse(sel);
                body += btn('dp-cell' + (sp && sp.y === y ? ' is-selected' : '') + (+now.slice(0, 4) === y ? ' is-today' : ''),
                    'data-year="' + y + '"' + (y === st.year ? ' data-focus' : '') + (yOff ? ' disabled' : ''), y);
            }
            body += '</div>';
        }

        var todayOk = inRange(now, lim);
        var canClear = !st.input.required;
        pop.innerHTML =
            '<div class="dp-head">' +
                btn('dp-title', 'data-act="title" aria-live="polite" aria-expanded="' + (st.view !== 'days') + '" aria-label="' + title + ', change view"',
                    '<span>' + title + '</span>' + ICON.chevron) +
                btn('dp-nav', 'data-act="prev" aria-label="' + prevLabel + '"' + (prevOff ? ' disabled' : ''), ICON.prev) +
                btn('dp-nav', 'data-act="next" aria-label="' + nextLabel + '"' + (nextOff ? ' disabled' : ''), ICON.next) +
            '</div>' +
            '<div class="dp-body">' + body + '</div>' +
            '<div class="dp-foot">' +
                (canClear ? btn('dp-link is-muted', 'data-act="clear"', 'Clear') : '<span></span>') +
                btn('dp-link', 'data-act="today"' + (todayOk ? '' : ' disabled'), 'Today') +
            '</div>';

        if (focusGrid) {
            var target = pop.querySelector('.dp-day[tabindex="0"]:not(:disabled)') || pop.querySelector('[data-focus]:not(:disabled)') ||
                pop.querySelector('.dp-day:not(:disabled)') || pop.querySelector('.dp-cell:not(:disabled)');
            if (target) { target.focus({ preventScroll: true }); }
        }
    }

    /* ---------- actions ---------- */
    function commit(value) {
        if (!st || !eligible(st.input)) { close(false); return; }
        var input = st.input;
        st.lim = limits(input);
        if (value === '' ? input.required : (!parse(value) || !inRange(value, st.lim))) {
            render(false);
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    }

    function moveFocus(target) {
        st.lim = limits(st.input);
        if (!setFocus(target)) { return; }
        render(true);
        place();
    }

    function shiftView(dir) {
        st.lim = limits(st.input);
        if (st.view === 'days') {
            if (!periodInRange(st.year, st.month + dir, 'days', st.lim)) { return; }
            setFocus(addMonths(st.focus, dir));
        } else {
            var year = st.year + dir * (st.view === 'years' ? 12 : 1);
            if (!periodInRange(year, st.month, st.view, st.lim)) { return; }
            setFocus(clampDay(year, st.month, parse(st.focus).d));
        }
        render(false);
    }

    function onClick(e) {
        var t = e.target.closest ? e.target.closest('button') : null;
        if (!t || !pop || !pop.contains(t) || t.disabled) { return; }
        st.lim = limits(st.input);

        if (t.dataset.date) { commit(t.dataset.date); return; }
        if (t.dataset.month !== undefined) {
            if (!periodInRange(st.year, +t.dataset.month, 'days', st.lim)) { render(false); return; }
            setFocus(clampDay(st.year, +t.dataset.month, parse(st.focus).d));
            st.view = 'days';
            render(true); return;
        }
        if (t.dataset.year !== undefined) {
            if (!periodInRange(+t.dataset.year, st.month, 'months', st.lim)) { render(false); return; }
            setFocus(clampDay(+t.dataset.year, st.month, parse(st.focus).d));
            st.view = 'months'; render(true); return;
        }

        switch (t.dataset.act) {
            case 'prev': shiftView(-1); keepFocus('prev'); break;
            case 'next': shiftView(1); keepFocus('next'); break;
            case 'title': st.view = st.view === 'days' ? 'months' : (st.view === 'months' ? 'years' : 'days'); render(true); break;
            case 'today': commit(today()); break;
            case 'clear': commit(''); break;
        }
    }
    function keepFocus(act) {
        var el = pop && pop.querySelector('[data-act="' + act + '"]:not(:disabled)');
        if (el) { el.focus({ preventScroll: true }); }
    }
    function clampDay(y, m, d) {
        var last = new Date(y, m + 1, 0).getDate();
        return iso(y, m, Math.min(d, last));
    }

    function onPopupKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); return; }
        var cell = e.target.closest && e.target.closest('.dp-day');
        if (!cell || st.view !== 'days') { return; }
        st.lim = limits(st.input);
        var cur = cell.dataset.date, next = null;
        switch (e.key) {
            case 'ArrowLeft':  next = addDays(cur, -1); break;
            case 'ArrowRight': next = addDays(cur, 1); break;
            case 'ArrowUp':    next = addDays(cur, -7); break;
            case 'ArrowDown':  next = addDays(cur, 7); break;
            case 'Home':       next = addDays(cur, -weekday(cur)); break;
            case 'End':        next = addDays(cur, 6 - weekday(cur)); break;
            case 'PageUp':     next = e.shiftKey ? addMonths(cur, -12) : addMonths(cur, -1); break;
            case 'PageDown':   next = e.shiftKey ? addMonths(cur, 12) : addMonths(cur, 1); break;
            default: return;
        }
        e.preventDefault();
        if (!inRange(next, st.lim)) { next = clampToRange(next, st.lim); }
        moveFocus(next);
    }

    /* ---------- wiring (delegated, so inputs created later by other scripts work too) ---------- */
    function inIndicator(input, e) {
        var r = input.getBoundingClientRect();
        var cs = getComputedStyle(input);
        var right = r.right - (parseFloat(cs.borderRightWidth) || 0) - 4;
        return e.clientX >= right - 34 && e.clientX <= right + 4;
    }

    // Capture phase: runs before the browser opens its own popup, and cancelling stops it.
    document.addEventListener('click', function (e) {
        var input = e.target;
        if (eligible(input) && inIndicator(input, e)) {
            e.preventDefault();
            open(input);
        } else if (pop && !pop.contains(e.target) && e.target !== (st && st.input)) {
            close(false);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.target && eligible(e.target) && ((e.altKey && e.key === 'ArrowDown') || e.key === 'F4')) {
            e.preventDefault();
            open(e.target);
        } else if (st && e.key === 'Escape' && !(pop && pop.contains(e.target))) {
            close(true);
        }
    }, true);

    document.addEventListener('focusin', function (e) {
        if (st && !pop.contains(e.target) && e.target !== st.input) { close(false); }
    });

    // Typing in the open field keeps the calendar in sync.
    document.addEventListener('input', function (e) {
        if (!st || e.target !== st.input) { return; }
        st.lim = limits(st.input);
        if (parse(st.input.value)) { setFocus(st.input.value); }
        render(false);
    });

    var raf = 0;
    function reposition() {
        if (!st) { return; }
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
            if (!st) { return; }
            if (!document.contains(st.input) || st.input.disabled) { close(false); } else { place(); }
        });
    }
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);

    // Popup-level events are attached once on the document and filtered to the popup.
    document.addEventListener('click', onClick);
})();
