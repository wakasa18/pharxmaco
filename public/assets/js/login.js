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
    // The new theme is revealed from the toggle in the style saved in Interface settings
    // (see theme-transitions.js). Browsers without View Transitions just switch.
    // Per-element transitions are frozen meanwhile (login.css, `.theme-vt`).
    const styles = window.PharxmacoThemeTransitions;
    let revealing = false;
    const swapTheme = origin => {
        if (!origin || !styles || typeof document.startViewTransition !== 'function' || !canAnimate()) { applyTheme(); return; }
        const root = document.documentElement;
        const style = styles.pick(settings.themeTransition);
        const finish = () => { revealing = false; root.classList.remove('theme-vt'); };
        revealing = true;
        root.classList.add('theme-vt');
        try {
            const transition = document.startViewTransition(() => applyTheme());
            transition.ready.then(() => {
                // Phones get the opacity-only cross-fade (GPU composited); see theme-transitions.js.
                styles.play(root, style, origin, matchMedia('(max-width: 900px), (pointer: coarse)').matches);
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
                panel.style.setProperty('--mx2', (cx * .5).toFixed(3));
                panel.style.setProperty('--my2', (cy * .5).toFixed(3));
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

        // Magnetic hover on the sign-in button: softly pulls toward the cursor when near.
        if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
            let magneticActive = false;
            const resetMagnetic = () => {
                if (!magneticActive) return;
                magneticActive = false;
                submit.style.translate = '';
            };
            window.addEventListener('pointermove', event => {
                if (!canAnimate() || submit.disabled) {
                    resetMagnetic();
                    return;
                }
                const rect = submit.getBoundingClientRect();
                const dx = Math.max(rect.left - event.clientX, 0, event.clientX - rect.right);
                const dy = Math.max(rect.top - event.clientY, 0, event.clientY - rect.bottom);
                const dist = Math.hypot(dx, dy);

                if (dist <= 60) {
                    magneticActive = true;
                    const cx = rect.left + rect.width / 2;
                    const cy = rect.top + rect.height / 2;
                    const ox = (event.clientX - cx) * 0.12;
                    const oy = (event.clientY - cy) * 0.22;
                    const clampX = Math.max(-8, Math.min(8, ox));
                    const clampY = Math.max(-5, Math.min(5, oy));
                    submit.style.translate = `${clampX.toFixed(2)}px ${clampY.toFixed(2)}px`;
                } else {
                    resetMagnetic();
                }
            });
            submit.addEventListener('pointerleave', resetMagnetic);
            window.addEventListener('blur', resetMagnetic);
        }

        // Input focus transition: scale bounce on the icon tab.
        form.querySelectorAll('.login-input-wrap input').forEach(input => {
            input.addEventListener('focus', () => {
                if (!canAnimate()) return;
                const tab = input.closest('.login-input-wrap')?.querySelector('.login-input-tab');
                if (tab && typeof tab.animate === 'function') {
                    tab.animate([
                        { transform: 'scale(1)' },
                        { transform: 'scale(1.15)', offset: 0.4 },
                        { transform: 'scale(0.97)', offset: 0.75 },
                        { transform: 'scale(1)' }
                    ], {
                        duration: 400,
                        easing: 'ease-out'
                    });
                }
            });
        });

        // Typing indicator dots: show subtle dots below password field while typing.
        const passWrap = password.closest('.login-input-wrap');
        let typingTimer = null;
        const clearTyping = () => {
            if (typingTimer) {
                clearTimeout(typingTimer);
                typingTimer = null;
            }
            if (passWrap) passWrap.classList.remove('is-typing');
        };
        if (passWrap) {
            let indicator = passWrap.parentElement?.querySelector('.login-typing-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'login-typing-indicator';
                indicator.setAttribute('aria-hidden', 'true');
                indicator.innerHTML = '<span></span><span></span><span></span>';
                passWrap.after(indicator);
            }
            password.addEventListener('input', () => {
                if (!canAnimate()) {
                    clearTyping();
                    return;
                }
                if (password.value.length > 0) {
                    passWrap.classList.add('is-typing');
                    if (typingTimer) clearTimeout(typingTimer);
                    typingTimer = setTimeout(clearTyping, 1500);
                } else {
                    clearTyping();
                }
            });
            password.addEventListener('blur', clearTyping);
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
            clearTyping();
            if (canAnimate()) {
                const card = form.closest('.login-form-content') || document.querySelector('.login-form-content');
                if (card) card.classList.add('is-submitting');
            }
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) {
                settings = read();
                applyTheme();
            }
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            submit.disabled = false;
            const card = form.closest('.login-form-content') || document.querySelector('.login-form-content');
            if (card) card.classList.remove('is-submitting');
            clearTyping();
            if (label) label.textContent = 'Sign in';
            if (caps) caps.hidden = true;
            hidePassword();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
    else initialize();
})();
