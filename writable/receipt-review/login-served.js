(() => {
    'use strict';
    const key = 'pharxmaco-ui-settings';
    const scheme = matchMedia('(prefers-color-scheme: dark)');
    const read = () => {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '{}');
            return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        } catch (_) { return {}; }
    };
    let settings = read();
    const applyTheme = () => {
        const theme = ['light','dark'].includes(settings.theme) ? settings.theme : 'system';
        const dark = theme === 'dark' || (theme === 'system' && scheme.matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.classList.toggle('reduce-motion', settings.reduceMotion === true);
        document.documentElement.classList.toggle('high-contrast', settings.highContrast === true);
        document.documentElement.dataset.textSize = ['large','extra-large'].includes(settings.textSize) ? settings.textSize : 'normal';
        const button = document.getElementById('loginThemeToggle');
        if (button) {
            button.hidden = false;
            // One fixed name plus aria-pressed: changing the name as well makes screen readers announce the state twice.
            button.setAttribute('aria-pressed', String(dark));
            button.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
        }
    };
    // Motion is skipped for the system setting and for the in-app "Reduce motion" option.
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const canAnimate = () => !reducedMotion.matches && !document.documentElement.classList.contains('reduce-motion');
    // The new theme opens outward from the toggle. Browsers without View Transitions just switch.
    // Slow, eased in and out; per-element transitions are frozen meanwhile (login.css, `.theme-vt`).
    const REVEAL_MS = 850;
    const REVEAL_EASE = 'cubic-bezier(.4, 0, .2, 1)';
    let revealing = false;
    const swapTheme = origin => {
        if (!origin || typeof document.startViewTransition !== 'function' || !canAnimate()) { applyTheme(); return; }
        const root = document.documentElement;
        const box = origin.getBoundingClientRect();
        const x = box.left + box.width / 2;
        const y = box.top + box.height / 2;
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        const finish = () => { revealing = false; root.classList.remove('theme-vt'); };
        revealing = true;
        root.classList.add('theme-vt');
        try {
            const transition = document.startViewTransition(() => applyTheme());
            transition.ready.then(() => {
                root.animate(
                    {clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`]},
                    {duration: REVEAL_MS, easing: REVEAL_EASE, pseudoElement: '::view-transition-new(root)'}
                );
            }).catch(() => {});
            transition.finished.then(finish, finish);
        } catch (_) {
            applyTheme();
            finish();
        }
    };
    applyTheme();
    if (typeof scheme.addEventListener === 'function') scheme.addEventListener('change', applyTheme);
    else if (typeof scheme.addListener === 'function') scheme.addListener(applyTheme);
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) { settings = read(); applyTheme(); }
    });
    const initialize = () => {
        applyTheme();
        document.getElementById('loginThemeToggle')?.addEventListener('click', event => {
            if (revealing) return; // ignore clicks until the current reveal finishes
            settings = {...read(), theme: document.documentElement.classList.contains('dark') ? 'light' : 'dark'};
            const notice = document.getElementById('loginThemeNotice');
            try {
                localStorage.setItem(key, JSON.stringify(settings));
                if (notice) {
                    notice.hidden = true;
                    notice.textContent = '';
                }
            }
            catch (_) {
                if (notice) {
                    notice.textContent = 'Theme changed for this page only. Browser preferences could not be saved.';
                    notice.hidden = false;
                }
            }
            swapTheme(event.currentTarget);
        });
        const form = document.getElementById('loginForm');
        const password = document.getElementById('password');
        const toggle = document.getElementById('togglePass');
        const submit = document.getElementById('loginSubmit');
        const label = document.getElementById('loginSubmitLabel');
        const caps = document.getElementById('capsLockNotice');
        const errorBox = document.getElementById('loginError');
        const username = document.getElementById('username');

        // Keep the native form usable when this script meets incomplete or older markup.
        if (!form || !password || !username || !submit) return;

        // After a failed sign-in, put the cursor where the person needs to type next.
        if (errorBox) (username.value ? password : username).focus({preventScroll: true});

        // Playful extra: click a capsule and it pops out of its cell, then drops back in.
        const pack = document.querySelector('.brand-art');
        if (pack) {
            pack.addEventListener('click', event => {
                const cell = event.target.closest('.cell');
                if (!cell || cell.classList.contains('is-popped') || !canAnimate()) return;
                cell.classList.add('is-popped');
                setTimeout(() => cell.classList.remove('is-popped'), 1750);
            });
        }
        // Pointer parallax: the pack and chips drift toward the cursor, each at its own depth.
        // Skipped on touch screens and whenever motion is reduced.
        const panel = document.querySelector('.login-brand-panel');
        if (panel && matchMedia('(hover: hover) and (pointer: fine)').matches) {
            let tx = 0, ty = 0, cx = 0, cy = 0, frame = 0;
            const tick = () => {
                cx += (tx - cx) * .08;
                cy += (ty - cy) * .08;
                panel.style.setProperty('--mx', cx.toFixed(3));
                panel.style.setProperty('--my', cy.toFixed(3));
                frame = Math.abs(tx - cx) > .002 || Math.abs(ty - cy) > .002 ? requestAnimationFrame(tick) : 0;
            };
            const kick = () => { if (!frame) frame = requestAnimationFrame(tick); };
            panel.addEventListener('pointermove', event => {
                if (!canAnimate()) { tx = ty = 0; return; }
                const box = panel.getBoundingClientRect();
                tx = ((event.clientX - box.left) / box.width - .5) * 2;
                ty = ((event.clientY - box.top) / box.height - .5) * 2;
                kick();
            });
            panel.addEventListener('pointerleave', () => { tx = ty = 0; kick(); });
        }

        // Press ripple on the sign-in button.
        submit.addEventListener('pointerdown', event => {
            if (submit.disabled || !canAnimate()) return;
            const box = submit.getBoundingClientRect();
            const dot = document.createElement('span');
            dot.className = 'login-ripple';
            dot.style.left = (event.clientX - box.left) + 'px';
            dot.style.top = (event.clientY - box.top) + 'px';
            submit.appendChild(dot);
            dot.addEventListener('animationend', () => dot.remove());
            setTimeout(() => dot.remove(), 1000);
        });

        // A required field left empty shakes and turns red until the person types.
        form.addEventListener('invalid', event => {
            const wrap = event.target.closest && event.target.closest('.login-input-wrap');
            if (!wrap) return;
            wrap.classList.add('is-invalid');
            if (!canAnimate()) return;
            wrap.classList.remove('is-shaking');
            void wrap.offsetWidth; // restart the animation if it is already running
            wrap.classList.add('is-shaking');
            setTimeout(() => wrap.classList.remove('is-shaking'), 500);
        }, true);
        form.addEventListener('input', event => {
            const wrap = event.target.closest && event.target.closest('.login-input-wrap');
            if (wrap) wrap.classList.remove('is-invalid');
        });

        if (toggle) toggle.hidden = false;
        const hidePassword = () => {
            password.type = 'password';
            if (toggle) {
                toggle.textContent = 'Show';
                toggle.setAttribute('aria-label', 'Show password');
                toggle.setAttribute('aria-pressed', 'false');
            }
        };
        toggle?.addEventListener('click', () => {
            const show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(show));
        });
        const showCapsLock = event => { if (caps) caps.hidden = !event.getModifierState?.('CapsLock'); };
        password.addEventListener('keydown', showCapsLock);
        password.addEventListener('keyup', showCapsLock);
        password.addEventListener('blur', () => { if (caps) caps.hidden = true; });
        document.addEventListener('visibilitychange', () => { if (document.hidden) hidePassword(); });
        form.addEventListener('submit', event => {
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            submit.disabled = true;
            if (label) label.textContent = 'Signing in…';
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) {
                settings = read();
                applyTheme();
            }
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            submit.disabled = false;
            if (label) label.textContent = 'Sign in';
            if (caps) caps.hidden = true;
            hidePassword();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
    else initialize();
})();
