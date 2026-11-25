/* Server time + monotonic elapsed time. The device's wall clock is never a time source. */
(() => {
    'use strict';
    const clock = document.getElementById('topbarClock');
    const dateLabel = document.getElementById('clockDate');
    const timeLabel = document.getElementById('clockDisplay');
    if (!clock || !dateLabel || !timeLabel) return;

    const zone = 'Asia/Manila';
    const dateFormat = new Intl.DateTimeFormat('en-PH', {month: 'long', day: 'numeric', year: 'numeric', timeZone: zone});
    const timeFormat = new Intl.DateTimeFormat('en-PH', {hour: 'numeric', minute: '2-digit', hour12: true, timeZone: zone});
    let serverMs = Number(clock.dataset.serverTimeMs);
    if (!Number.isSafeInteger(serverMs) || serverMs <= 0 || !Number.isFinite(new Date(serverMs).getTime())) return;
    let sampledAt = performance.now();
    let busy = false;

    function render() {
        const now = new Date(serverMs + performance.now() - sampledAt);
        dateLabel.textContent = dateFormat.format(now);
        timeLabel.textContent = timeFormat.format(now);
        timeLabel.setAttribute('datetime', now.toISOString());
    }

    async function synchronize() {
        if (busy || document.hidden || !navigator.onLine || !clock.dataset.syncUrl) return;
        busy = true;
        const startedAt = performance.now();
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(clock.dataset.syncUrl, {
                credentials: 'omit', cache: 'no-store', redirect: 'error', signal: controller.signal,
                headers: {'Accept': 'application/json'}
            });
            if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Clock sync unavailable.');
            const sample = await response.json();
            const receivedAt = performance.now();
            if (!Number.isSafeInteger(sample.server_time_ms) || sample.server_time_ms <= 0
                || !Number.isFinite(new Date(sample.server_time_ms).getTime())) throw new Error('Invalid clock sample.');
            // Estimate one-way transit time, then advance only using performance.now().
            serverMs = sample.server_time_ms + Math.max(0, receivedAt - startedAt) / 2;
            sampledAt = receivedAt;
            clock.dataset.clockState = 'synchronized';
            clock.title = 'Server-synchronized Philippine time (Asia/Manila)';
            render();
        } catch (error) {
            // Keep advancing the last server sample while offline or when a request fails.
            clock.dataset.clockState = 'estimated';
            clock.title = 'Philippine time (Asia/Manila) · Continuing from the last server time; sync unavailable';
        } finally {
            window.clearTimeout(timeout);
            busy = false;
        }
    }

    render();
    synchronize();
    window.setInterval(() => { if (!document.hidden) render(); }, 1000);
    window.setInterval(synchronize, 5 * 60 * 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { render(); synchronize(); }
    });
    window.addEventListener('online', synchronize);
    window.addEventListener('focus', synchronize);
})();
