/* Limited presence capabilities use no login cookies and never renew authentication. */
(() => {
    'use strict';
    const script = document.currentScript;
    const token = script?.dataset.presenceToken;
    if (!/^[a-f0-9]{64}$/.test(token || '') || !script.dataset.heartbeatUrl) return;
    const table = document.querySelector('table[data-presence-users-url]');
    const note = document.querySelector('[data-presence-update]');
    const count = document.querySelector('[data-online-count]');
    let busy = false, ended = false;

    async function request(url, method) {
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(url, {
                method, credentials: 'omit', cache: 'no-store', redirect: 'error', signal: controller.signal,
                headers: {'Accept': 'application/json', 'X-Pharxmaco-Presence': token}
            });
            if (response.status === 401 || response.status === 403) ended = true;
            if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Presence unavailable.');
            const data = await response.json();
            if (data.success !== true) throw new Error('Presence unavailable.');
            return data;
        } finally {
            window.clearTimeout(timer);
        }
    }

    function update(data) {
        if (!table) return;
        const jq = window.jQuery;
        const api = jq?.fn?.dataTable?.isDataTable(table) ? jq(table).DataTable() : null;
        const rows = api ? api.rows().nodes().toArray() : [...table.tBodies[0].rows];
        rows.forEach(row => {
            const status = row.querySelector('[data-presence-status]');
            const lastSeen = row.querySelector('[data-presence-last-seen]');
            if (!status || !lastSeen) return;
            const seen = data?.users?.[row.dataset.userId];
            const label = !data || !seen ? 'Unavailable' : (seen.online === true ? 'Online' : 'Offline');
            const badge = document.createElement('span');
            badge.className = 'badge ' + (label === 'Online' ? 'badge-success' : label === 'Offline' ? 'badge-secondary' : 'badge-warning');
            badge.textContent = label;
            status.replaceChildren(badge);
            status.setAttribute('data-order', label === 'Online' ? '1' : label === 'Offline' ? '0' : '-1');
            status.setAttribute('data-search', label);
            if (seen) {
                lastSeen.textContent = seen.last_seen_label;
                lastSeen.setAttribute('data-order', String(Number.isSafeInteger(seen.last_seen_at) ? seen.last_seen_at * 1000 : -1000));
                lastSeen.setAttribute('data-search', seen.last_seen_label);
            }
        });
        if (count) count.textContent = data ? String(data.online_count) : '—';
        if (note) note.textContent = data ? 'Updated ' + data.as_of_label + ' · refreshes every 30 seconds.'
            : 'Online status is unavailable. Last seen values are from the previous update.';
        // Update every loaded row, including other pages, without resetting filters/order/page or forms.
        if (api) {
            api.rows().invalidate('dom').draw(false);
            const info = api.page.info();
            if (info.page >= Math.max(1, info.pages)) api.page(Math.max(0, info.pages - 1)).draw('page');
        }
    }

    async function refresh() {
        if (busy || ended || document.hidden || !navigator.onLine) return;
        busy = true;
        try {
            await request(script.dataset.heartbeatUrl, 'POST');
            if (table) {
                const data = await request(table.dataset.presenceUsersUrl, 'GET');
                if (!data.users || typeof data.users !== 'object' || !Number.isSafeInteger(data.online_count)
                    || data.online_count < 0 || typeof data.as_of_label !== 'string') throw new Error('Invalid presence summary.');
                update(data);
            }
        } catch (error) {
            update(null);
        } finally {
            busy = false;
        }
    }
    refresh();
    window.setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('online', refresh);
    window.addEventListener('focus', refresh);
    window.addEventListener('offline', () => update(null));
    window.addEventListener('pharxmaco:session-ended', () => { ended = true; update(null); });
})();
