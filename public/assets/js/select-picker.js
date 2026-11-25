/*!
 * Themed option list for <select>.
 *
 * The native <select> stays in place as the visible field, so styling, validation, form posts and
 * existing "change" handlers keep working; only the browser's own popup list is replaced. Selects
 * created later by other scripts (DataTables filters, etc.) are covered by event delegation.
 *
 *   data-native-select   on a <select>  -> keep the browser's popup for that field
 *
 * Skipped for <select multiple>, list boxes (size > 1) and touch phones/tablets, where the OS picker
 * is the better experience. Lists with 12+ options get a filter box; shorter ones support type-ahead.
 */
(function () {
    'use strict';

    if (window.__pxSelectPicker) { return; }
    window.__pxSelectPicker = true;

    var touchOnly = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    if (touchOnly) { return; }

    var SEARCH_AT = 12;      // selectable options before the filter box appears
    var SVG_CHECK = '<svg class="sp-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
    var SVG_SEARCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';

    var pop = null;   // popup element
    var st = null;    // { sel, items[], active, list, search, empty, buf, bufTimer }
    var uid = 0;

    function eligible(s) {
        return !!s && s.tagName === 'SELECT' && !s.matches(':disabled') && !s.multiple && !(s.size > 1) &&
            !s.hasAttribute('data-native-select') && s.options.length > 0;
    }

    /* ---------- open / close ---------- */
    function open(sel) {
        if (!eligible(sel)) { return; }
        if (st && st.sel === sel) { close(true); return; }
        close(false);

        var items = [];
        var list = document.createElement('ul');
        var id = 'sp-' + (++uid);
        list.className = 'sp-list';
        list.id = id;
        list.setAttribute('role', 'listbox');
        list.tabIndex = -1;
        if (sel.getAttribute('aria-label')) { list.setAttribute('aria-label', sel.getAttribute('aria-label')); }

        function addOption(o, groupDisabled, groupEl) {
            if (o.hidden) { return; }
            var li = document.createElement('li');
            var text = o.label || o.text || '';
            var disabled = o.disabled || groupDisabled;
            li.id = id + '-' + o.index;
            li.className = 'sp-opt' + (o.index === sel.selectedIndex ? ' is-selected' : '') +
                (o.value === '' ? ' is-placeholder' : '') + (disabled ? ' is-disabled' : '');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', o.index === sel.selectedIndex ? 'true' : 'false');
            if (disabled) { li.setAttribute('aria-disabled', 'true'); }
            var span = document.createElement('span');
            span.className = 'sp-text';
            span.textContent = text;
            li.appendChild(span);
            li.insertAdjacentHTML('beforeend', SVG_CHECK);
            li.title = text;
            list.appendChild(li);
            items.push({ idx: o.index, text: text, lower: text.toLowerCase(), disabled: disabled, el: li, groupEl: groupEl || null });
        }

        Array.prototype.forEach.call(sel.children, function (ch) {
            if (ch.tagName === 'OPTGROUP') {
                var g = document.createElement('li');
                g.className = 'sp-group';
                g.setAttribute('role', 'presentation');
                g.textContent = ch.label;
                list.appendChild(g);
                Array.prototype.forEach.call(ch.children, function (o) { if (o.tagName === 'OPTION') { addOption(o, ch.disabled, g); } });
            } else if (ch.tagName === 'OPTION') {
                addOption(ch, false, null);
            }
        });
        if (!items.length) { return; }

        pop = document.createElement('div');
        pop.className = 'phx-select-popup';
        pop.style.minWidth = Math.max(160, sel.getBoundingClientRect().width) + 'px';

        var selectable = items.filter(function (i) { return !i.disabled; }).length;
        var search = null;
        if (selectable >= SEARCH_AT) {
            var wrap = document.createElement('div');
            wrap.className = 'sp-search';
            wrap.innerHTML = SVG_SEARCH;
            search = document.createElement('input');
            search.type = 'text';
            search.placeholder = 'Search…';
            search.setAttribute('aria-label', 'Filter options');
            search.setAttribute('role', 'combobox');
            search.setAttribute('aria-expanded', 'true');
            search.setAttribute('aria-controls', id);
            search.setAttribute('autocomplete', 'off');
            search.setAttribute('spellcheck', 'false');
            wrap.appendChild(search);
            pop.appendChild(wrap);
        }
        pop.appendChild(list);
        var empty = document.createElement('div');
        empty.className = 'sp-empty';
        empty.textContent = 'No matches';
        empty.hidden = true;
        pop.appendChild(empty);

        st = { sel: sel, items: items, active: -1, list: list, search: search, empty: empty, buf: '', bufTimer: 0 };

        // Inside an open modal the list must live in that modal, or its focus trap would reject it.
        (sel.closest('[aria-hidden="false"], dialog[open]') || document.body).appendChild(pop);
        sel.classList.add('sp-open');
        sel.setAttribute('aria-expanded', 'true');

        pop.addEventListener('keydown', onKey);
        pop.addEventListener('mousedown', function (e) { if (e.target !== search) { e.preventDefault(); } });
        pop.addEventListener('click', onPick);
        pop.addEventListener('mousemove', function (e) {
            var li = e.target.closest && e.target.closest('.sp-opt');
            if (!li) { return; }
            for (var i = 0; i < st.items.length; i++) {
                if (st.items[i].el === li) { if (!st.items[i].disabled) { setActive(i, false); } return; }
            }
        });
        if (search) { search.addEventListener('input', onFilter); }

        // Start on the current value (or first selectable option) and keep it in view.
        var start = -1;
        items.forEach(function (it, i) { if (it.idx === sel.selectedIndex && !it.disabled) { start = i; } });
        if (start < 0) { start = firstEnabled(); }
        place();
        setActive(start, true, true);
        (search || list).focus({ preventScroll: true });
    }

    function close(returnFocus) {
        if (!st) { return; }
        var sel = st.sel;
        clearTimeout(st.bufTimer);
        sel.classList.remove('sp-open');
        sel.removeAttribute('aria-expanded');
        if (pop && pop.parentNode) { pop.parentNode.removeChild(pop); }
        pop = null; st = null;
        if (returnFocus && document.contains(sel)) { sel.focus({ preventScroll: true }); }
    }

    /* ---------- positioning ---------- */
    function place() {
        if (!pop || !st) { return; }
        var r = st.sel.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) { close(false); return; }
        var margin = 8, gap = 6, cap = 340;

        pop.style.maxHeight = '';
        var natural = Math.min(pop.offsetHeight, cap);
        var below = window.innerHeight - r.bottom - gap - margin;
        var above = r.top - gap - margin;
        var flip = natural > below && above > below;
        var room = Math.max(120, flip ? above : below);
        var h = Math.min(natural, room);
        pop.style.maxHeight = h + 'px';
        pop.style.maxWidth = Math.min(480, window.innerWidth - margin * 2) + 'px';

        var w = pop.offsetWidth;
        var left = Math.max(margin, Math.min(r.left, window.innerWidth - w - margin));
        var top = flip ? Math.max(margin, r.top - gap - h) : Math.min(r.bottom + gap, window.innerHeight - h - margin);
        pop.style.left = left + 'px';
        pop.style.top = Math.max(margin, top) + 'px';
        pop.style.setProperty('--sp-origin', (flip ? 'bottom ' : 'top ') + 'left');
        pop.style.setProperty('--sp-shift', flip ? '4px' : '-4px');
    }

    /* ---------- active option ---------- */
    function visible(it) { return !it.el.hidden; }
    function navigable() {
        var out = [];
        st.items.forEach(function (it, i) { if (!it.disabled && visible(it)) { out.push(i); } });
        return out;
    }
    function firstEnabled() { var n = navigable(); return n.length ? n[0] : -1; }

    function setActive(i, scroll, center) {
        if (!st) { return; }
        if (st.active >= 0 && st.items[st.active]) { st.items[st.active].el.classList.remove('is-active'); }
        st.active = i;
        var holder = st.search || st.list;
        if (i < 0) { holder.removeAttribute('aria-activedescendant'); return; }
        var el = st.items[i].el;
        el.classList.add('is-active');
        holder.setAttribute('aria-activedescendant', el.id);
        if (scroll) {
            var list = st.list;
            if (center) {
                list.scrollTop = el.offsetTop - (list.clientHeight - el.offsetHeight) / 2;
            } else {
                if (el.offsetTop < list.scrollTop) { list.scrollTop = el.offsetTop; }
                else if (el.offsetTop + el.offsetHeight > list.scrollTop + list.clientHeight) { list.scrollTop = el.offsetTop + el.offsetHeight - list.clientHeight; }
            }
        }
    }

    function move(delta) {
        var nav = navigable();
        if (!nav.length) { return; }
        var pos = nav.indexOf(st.active);
        pos = pos < 0 ? (delta > 0 ? 0 : nav.length - 1) : Math.max(0, Math.min(nav.length - 1, pos + delta));
        setActive(nav[pos], true);
    }

    /* ---------- choosing ---------- */
    function commit(i) {
        if (!st || i < 0 || st.items[i].disabled) { return; }
        var sel = st.sel, idx = st.items[i].idx;
        var changed = sel.selectedIndex !== idx;
        sel.selectedIndex = idx;
        close(true);
        if (changed) {
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function onPick(e) {
        var li = e.target.closest && e.target.closest('.sp-opt');
        if (!li || !st) { return; }
        for (var i = 0; i < st.items.length; i++) {
            if (st.items[i].el === li) { commit(i); return; }
        }
    }

    /* ---------- filter + type-ahead ---------- */
    function onFilter() {
        var q = st.search.value.trim().toLowerCase(), any = false;
        var groupsShown = [];
        st.items.forEach(function (it) {
            var show = !q || it.lower.indexOf(q) >= 0;
            it.el.hidden = !show;
            if (show) { any = true; if (it.groupEl && groupsShown.indexOf(it.groupEl) < 0) { groupsShown.push(it.groupEl); } }
        });
        Array.prototype.forEach.call(st.list.querySelectorAll('.sp-group'), function (g) { g.hidden = groupsShown.indexOf(g) < 0; });
        st.empty.hidden = any;
        st.list.hidden = !any;
        var nav = navigable();
        var keep = nav.indexOf(st.active) >= 0 ? st.active : (nav.length ? nav[0] : -1);
        setActive(keep, true);
        place();
    }

    function typeAhead(ch) {
        clearTimeout(st.bufTimer);
        st.buf += ch.toLowerCase();
        st.bufTimer = setTimeout(function () { if (st) { st.buf = ''; } }, 700);
        var nav = navigable();
        if (!nav.length) { return; }
        var from = Math.max(0, nav.indexOf(st.active)) + (st.buf.length === 1 ? 1 : 0);
        for (var n = 0; n < nav.length; n++) {
            var i = nav[(from + n) % nav.length];
            if (st.items[i].lower.indexOf(st.buf) === 0) { setActive(i, true); return; }
        }
    }

    function onKey(e) {
        switch (e.key) {
            case 'Escape': e.preventDefault(); e.stopPropagation(); close(true); return;
            case 'Tab': close(true); return;   // focus returns to the field, then Tab moves on from there
            case 'ArrowDown': e.preventDefault(); move(1); return;
            case 'ArrowUp': e.preventDefault(); move(-1); return;
            case 'PageDown': e.preventDefault(); move(8); return;
            case 'PageUp': e.preventDefault(); move(-8); return;
            case 'Home': if (!st.search) { e.preventDefault(); move(-1e6); } return;
            case 'End': if (!st.search) { e.preventDefault(); move(1e6); } return;
            case 'Enter': e.preventDefault(); e.stopPropagation(); commit(st.active); return;
            case ' ':
                if (!st.search && !st.buf) { e.preventDefault(); commit(st.active); return; }
                if (!st.search) { e.preventDefault(); typeAhead(' '); }
                return;
        }
        if (!st.search && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            typeAhead(e.key);
        }
    }

    /* ---------- wiring (delegated) ---------- */
    // mousedown in the capture phase runs before the browser opens its own list; cancelling stops it.
    document.addEventListener('mousedown', function (e) {
        var t = e.target;
        if (e.button === 0 && eligible(t)) {
            e.preventDefault();
            t.focus({ preventScroll: true });
            open(t);
        } else if (st && pop && !pop.contains(t)) {
            close(false);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        var t = e.target;
        if (!eligible(t) || (pop && pop.contains(t))) { return; }
        if (e.key === ' ' || e.key === 'F4' || (e.altKey && (e.key === 'ArrowDown' || e.key === 'ArrowUp'))) {
            e.preventDefault();
            open(t);
        }
    }, true);

    document.addEventListener('focusin', function (e) {
        if (st && pop && !pop.contains(e.target) && e.target !== st.sel) { close(false); }
    });

    var raf = 0;
    function reposition(e) {
        if (!st || (e && e.type === 'scroll' && pop && pop.contains(e.target))) { return; }
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
            if (!st) { return; }
            if (!document.contains(st.sel) || st.sel.matches(':disabled')) { close(false); } else { place(); }
        });
    }
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);
})();
