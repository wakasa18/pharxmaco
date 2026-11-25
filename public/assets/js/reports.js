(() => {
    const root = document.querySelector('.reports-workspace');
    if (!root) return;
    const forms = [...root.querySelectorAll('#salesReportFilters, #salesForecastFilters')];
    const serialize = form => new URLSearchParams(new FormData(form)).toString();
    const original = forms.map(serialize);
    const notice = document.getElementById('reportPendingFilters');
    const forecastNotice = document.getElementById('forecastPendingFilters');
    const updatePending = () => {
        notice.hidden = forms.every((form, index) => serialize(form) === original[index]);
        const index = forms.findIndex(form => form.id === 'salesForecastFilters');
        if (forecastNotice && index !== -1) forecastNotice.hidden = serialize(forms[index]) === original[index];
    };
    forms.forEach(form => {
        form.addEventListener('input', updatePending);
        form.addEventListener('change', updatePending);
    });

    // Keep the forecast section at its previous viewport position after POST/redirect/GET.
    const forecastForm = document.getElementById('salesForecastFilters');
    const scrollKey = 'reports:forecast-scroll';
    const sortedQuery = value => {
        const params = new URLSearchParams(value);
        const filters = ['branch_id', 'report_type', 'report_date_from', 'report_date_to', 'forecast_type', 'forecast_date_from', 'forecast_date_to'];
        [...params.keys()].forEach(key => { if (!filters.includes(key)) params.delete(key); });
        params.sort();
        return params.toString();
    };
    if (forecastForm) {
        window.addEventListener('pageshow', () => {
            const button = forecastForm.querySelector('button[type="submit"]');
            if (button && button.dataset.originalLabel) {
                button.disabled = false;
                button.textContent = button.dataset.originalLabel;
            }
        });
        forecastForm.addEventListener('submit', () => {
            try {
                sessionStorage.setItem(scrollKey, JSON.stringify({
                    path: new URL(forecastForm.dataset.returnUrl || forecastForm.action, location.href).pathname,
                    query: sortedQuery(new FormData(forecastForm)),
                    top: forecastForm.getBoundingClientRect().top,
                    x: window.scrollX,
                    time: Date.now()
                }));
            } catch (_) { /* Submission still works when storage is unavailable. */ }
        });
        try {
            const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
            sessionStorage.removeItem(scrollKey);
            if (saved && saved.path === location.pathname && saved.query === sortedQuery(location.search)
                && Date.now() - saved.time < 300000 && Number.isFinite(saved.top) && Number.isFinite(saved.x)) {
                const restore = async () => {
                    if (document.fonts) await document.fonts.ready;
                    requestAnimationFrame(() => {
                        window.scrollTo({
                            left: saved.x,
                            top: window.scrollY + forecastForm.getBoundingClientRect().top - saved.top,
                            behavior: 'instant'
                        });
                    });
                };
                if (document.readyState === 'complete') restore();
                else window.addEventListener('load', restore, {once: true});
            }
        } catch (_) { /* Ignore unavailable storage or an outdated saved position. */ }
    }

    const from = document.getElementById('reportDateFrom');
    const to = document.getElementById('reportDateTo');
    const presets = root.querySelector('.rp-date-presets');
    const buttons = [...presets.querySelectorAll('button')];
    const formatDate = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    const range = type => {
        const end = new Date(presets.dataset.today + 'T12:00:00');
        const start = new Date(end);
        if (type === 'month') start.setDate(1);
        else if (type !== 'today') start.setDate(start.getDate() - Number(type) + 1);
        return [formatDate(start), formatDate(end)];
    };
    const updatePresets = () => buttons.forEach(button => {
        const [start, end] = range(button.dataset.reportRange);
        button.setAttribute('aria-pressed', String(from.value === start && to.value === end));
    });
    buttons.forEach(button => button.addEventListener('click', () => {
        [from.value, to.value] = range(button.dataset.reportRange);
        [from, to].forEach(input => input.dispatchEvent(new Event('change', {bubbles:true})));
        updatePresets();
    }));
    [from, to].forEach(input => input.addEventListener('change', updatePresets));
    updatePresets();
})();

/* ── Presentation: net sales count-up, section navigation ── */
(() => {
    const root = document.querySelector('.reports-workspace');
    if (!root) return;
    const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || document.documentElement.classList.contains('reduce-motion');

    // Net sales counts up once on load. The real figure is already in the page,
    // so this only plays when it can be seen and motion is allowed.
    const figure = root.querySelector('[data-count]');
    if (figure && !still()) {
        const target = Number(figure.dataset.count);
        const whole = figure.querySelector('.rp-whole');
        const cents = figure.querySelector('.rp-cents');
        if (Number.isFinite(target) && whole && cents) {
            const finalWhole = whole.textContent;
            const finalCents = cents.textContent;
            const show = value => {
                const [w, c] = Math.abs(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).split('.');
                whole.textContent = (value < 0 ? '-' : '') + w;
                cents.textContent = '.' + c;
            };
            const duration = 1200;
            const delay = 220;
            let start = null;
            show(0);
            const tick = now => {
                if (start === null) start = now + delay;
                const k = Math.min(1, Math.max(0, (now - start) / duration));
                if (k >= 1) {
                    whole.textContent = finalWhole;
                    cents.textContent = finalCents;
                    return;
                }
                show(target * (1 - Math.pow(1 - k, 4)));
                requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }
    }

    // Section nav: highlight the section being read, and slide the marker to it.
    const nav = root.querySelector('.rp-section-nav');
    if (nav) {
        const marker = nav.querySelector('.rp-nav-indicator');
        const links = [...nav.querySelectorAll('a[href^="#"]')];
        const targets = links.map(link => document.getElementById(link.hash.slice(1)));
        let current = -1;

        const place = link => {
            if (!marker || !link) return;
            marker.style.setProperty('--x', link.offsetLeft + 'px');
            marker.style.setProperty('--w', link.offsetWidth + 'px');
            marker.classList.add('is-ready');
            const left = link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2;
            if (nav.scrollWidth > nav.clientWidth) nav.scrollTo({ left, behavior: still() ? 'auto' : 'smooth' });
        };
        const activate = index => {
            if (index === current) return;
            current = index;
            links.forEach((link, i) => {
                if (i === index) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            });
            place(links[index]);
        };
        const measure = () => {
            const line = (document.querySelector('.topbar')?.offsetHeight || 0) + nav.offsetHeight + 40;
            let index = 0;
            targets.forEach((target, i) => { if (target && target.getBoundingClientRect().top <= line) index = i; });
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) index = links.length - 1;
            activate(index);
        };
        let queued = false;
        window.addEventListener('scroll', () => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; measure(); });
        }, { passive: true });
        window.addEventListener('resize', () => { const i = current; current = -1; activate(i < 0 ? 0 : i); });
        if (document.fonts) document.fonts.ready.then(() => { const i = current; current = -1; activate(i < 0 ? 0 : i); });

        links.forEach((link, i) => link.addEventListener('click', event => {
            const target = targets[i];
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' });
            if (history.replaceState) history.replaceState(null, '', link.hash);
            target.focus?.({ preventScroll: true });
        }));
        measure();
    }

})();
