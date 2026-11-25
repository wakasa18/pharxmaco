/* Renew cashier access for actual interaction; all staff can display explicit session-ended notices. */
(() => {
    const script = document.currentScript;
    if (!script?.dataset.activityUrl) return;
    let dirty = false, busy = false, ended = false, lastRenew = Date.now();
    function endSession(text, broadcast = true) {
        if (ended) return;
        ended = true;
        const notice = document.createElement('div');
        notice.className = 'alert error';
        notice.id = 'cashierSessionNotice';
        notice.setAttribute('role', 'alert');
        notice.style.flexWrap = 'wrap';
        const message = document.createElement('span');
        message.style.flex = '1 1 240px';
        message.textContent = text || 'Your login ended. Sign in again before continuing.';
        const link = document.createElement('a');
        link.href = script.dataset.loginUrl;
        link.className = 'btn secondary btn-sm';
        link.textContent = 'Sign in';
        notice.append(message, link);
        const main = document.querySelector('main, .main-content, .content') || document.body;
        main.prepend(notice);
        if (broadcast) window.dispatchEvent(new CustomEvent('pharxmaco:session-ended', {detail: {message: message.textContent}}));
    }
    window.addEventListener('pharxmaco:session-ended', event => endSession(event.detail?.message, false));
    // Administrators keep ordinary sessions; retain the POS sign-in notice without lease heartbeats.
    if (script.dataset.renewLogin === '0') return;
    const markActivity = event => {
        if (!event.isTrusted) return;
        dirty = true;
        if (Date.now() - lastRenew >= 30000) renew();
    };
    for (const type of ['pointerdown', 'keydown', 'input', 'touchstart', 'wheel']) {
        document.addEventListener(type, markActivity, {passive: true, capture: true});
    }
    async function renew() {
        if (!dirty || busy || ended || document.hidden || !navigator.onLine) return;
        busy = true;
        lastRenew = Date.now();
        dirty = false;
        try {
            const response = await fetch(script.dataset.activityUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Pharxmaco-Activity': '1'}
            });
            if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Session status unavailable.');
            const result = await response.json();
            if (response.status === 401) {
                // Keep the draft and checkout-recovery information on screen until the user chooses Sign in.
                endSession(result.message);
            } else if (!response.ok || result.success !== true) throw new Error('Session status unavailable.');
        } catch (error) {
            dirty = true; // A failed heartbeat never signs the cashier out locally or authorizes access.
        } finally {
            busy = false;
        }
    }
    window.setInterval(renew, 30000);
    window.addEventListener('online', renew);
})();
