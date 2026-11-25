/* Shared, validated browser preferences. Signed-in pages provide an account key. */
(() => {
    'use strict';
    const root = document.documentElement;
    const browserKey = 'pharxmaco-ui-settings';
    const key = root.dataset.uiSettingsKey || browserKey;
    const defaults = {theme:'system',accent:'blue',themeTransition:'shuffle',textSize:'normal',density:'comfortable',largeControls:false,reduceMotion:false,highContrast:false,stickyHeaders:false};
    const allowed = {theme:['system','light','dark'],accent:['blue','green','purple','orange'],themeTransition:['shuffle','circle','diamond','wipe','curtain','rise','split','diagonal','fade'],textSize:['normal','large','extra-large'],density:['compact','comfortable','spacious']};
    const normalize = value => Object.fromEntries(Object.entries(defaults).map(([name,fallback]) => [name,
        allowed[name] ? (allowed[name].includes(value?.[name]) ? value[name] : fallback) : value?.[name] === true
    ]));
    const read = () => {
        try {
            // Existing browser choices are the starting point until this account saves its own.
            const stored = localStorage.getItem(key) ?? (key !== browserKey ? localStorage.getItem(browserKey) : null);
            const value = stored ? JSON.parse(stored) : {};
            if (!stored) {
                const legacy = localStorage.getItem('pharxmaco-theme');
                if (['light','dark'].includes(legacy)) value.theme = legacy;
            }
            return normalize(value);
        } catch (_) { return {...defaults}; }
    };
    const save = value => localStorage.setItem(key, JSON.stringify(normalize(value)));
    const apply = (value = read()) => {
        const settings = normalize(value);
        const dark = settings.theme === 'dark' || (settings.theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        root.classList.toggle('dark', dark);
        root.dataset.themePreference = settings.theme;
        root.dataset.accent = settings.accent;
        root.dataset.textSize = settings.textSize;
        root.dataset.density = settings.density;
        for (const [name, className] of Object.entries({largeControls:'large-controls',reduceMotion:'reduce-motion',highContrast:'high-contrast',stickyHeaders:'sticky-table-headers'})) {
            root.classList.toggle(className, settings[name]);
        }
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.setAttribute('aria-pressed', String(dark));
            toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            toggle.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
        }
        window.dispatchEvent(new CustomEvent('pharxmaco:ui-settings-applied', {detail:settings}));
    };
    window.PharxmacoUiPreferences = {key,defaults,normalize,read,save,apply};
    apply();
})();
