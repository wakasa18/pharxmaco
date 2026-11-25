<?php
// Keep submissions on the current host and support installations in a subfolder.
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$loginAction = ($basePath === '' || $basePath === '.') ? '/login' : $basePath . '/login';
$loginError = session()->getFlashdata('error');
$loginSuccess = session()->getFlashdata('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#0a4a9e">
    <title>Sign in — Pharxmaco Drugstore</title>
    <link rel="icon" type="image/jpeg" href="<?= base_url('assets/images/pharxmaco-favicon.jpg') ?>?v=20260923">
    <script src="<?= base_url('assets/js/theme-transitions.js') ?>?v=20261012-tt1"></script>
    <script src="<?= base_url('assets/js/login.js') ?>?v=20261016-v9"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>?v=20261016-v7">
</head>
<body>
    <a class="login-skip" href="#username">Skip to sign in</a>
    <div class="login-shell">
        <button type="button" id="loginThemeToggle" class="login-theme-toggle" aria-label="Dark mode" title="Switch to dark mode" aria-pressed="false" hidden>
            <svg class="theme-moon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20.5 14A8.5 8.5 0 0 1 10 3.5 8.5 8.5 0 1 0 20.5 14Z"/></svg>
            <svg class="theme-sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/></svg>
        </button>

        <main class="login-form-panel" aria-labelledby="loginTitle">
            <div class="login-form-content">
                <p id="loginThemeNotice" class="theme-notice" role="status" hidden></p>
                <h1 id="loginTitle" style="--d:0">Welcome back</h1>
                <p class="login-intro" style="--d:1">Sign in with your staff account to get started.</p>

                <?php if ($loginError): ?><div class="login-alert login-alert-error" role="alert" id="loginError"><?= esc($loginError) ?></div><?php endif; ?>
                <?php if ($loginSuccess): ?><div class="login-alert login-alert-success" role="status"><?= esc($loginSuccess) ?></div><?php endif; ?>

                <form id="loginForm" method="post" action="<?= esc($loginAction, 'attr') ?>" autocomplete="on">
                    <?= csrf_field() ?>
                    <div class="login-field" style="--d:2">
                        <label for="username">Username</label>
                        <div class="login-input-wrap">
                            <span class="login-input-tab" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a7 7 0 0 1 14 0v2"/></svg></span>
                            <input id="username" name="username" type="text" value="<?= old('username', '', 'attr') ?>" placeholder="Your username" required autocomplete="username" autocapitalize="none" spellcheck="false" enterkeyhint="next">
                        </div>
                    </div>
                    <div class="login-field" style="--d:3">
                        <label for="password">Password</label>
                        <div class="login-input-wrap">
                            <span class="login-input-tab" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span>
                            <input id="password" name="password" type="password" placeholder="Your password" required autocomplete="current-password" enterkeyhint="go" aria-describedby="capsLockNotice">
                            <button type="button" class="password-toggle" id="togglePass" aria-label="Show password" aria-pressed="false" aria-controls="password" hidden>Show</button>
                        </div>
                        <div class="login-typing-indicator" id="passwordTypingIndicator" aria-hidden="true">
                            <span></span><span></span><span></span>
                        </div>
                        <p class="caps-lock-notice" id="capsLockNotice" role="status" hidden>Caps Lock is on.</p>
                    </div>
                    <button type="submit" class="login-submit" id="loginSubmit" style="--d:4">
                        <span class="login-submit-label" id="loginSubmitLabel">Sign in</span>
                        <span class="login-submit-chip" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
                        <span class="login-submit-progress" aria-hidden="true"></span>
                    </button>
                </form>

                <details class="login-help" style="--d:5">
                    <summary>Need help signing in?</summary>
                    <p>Ask your administrator to check your username, reset your password, or enable your account.</p>
                </details>
                <div class="form-footnote" style="--d:6"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>For authorized pharmacy staff</div>
            </div>
            <footer class="login-footer"><span>Pharxmaco Drugstore</span><span>A little order. A lot of care.</span></footer>
        </main>

        <aside class="login-brand-panel" aria-label="Pharxmaco Drugstore">
            <div class="login-brand">
                <img src="<?= base_url('assets/images/295259270_419609253521915_2551810649101629249_n.jpg') ?>" alt="" width="48" height="48">
                <div><strong>Pharxmaco</strong><span>Drugstore workspace</span></div>
            </div>
            <div class="brand-copy">
                <h2 class="brand-title"><span class="line"><span>Every shelf.</span></span> <span class="line"><span>Every sale.</span></span> <span class="line"><span>One workspace.</span></span></h2>
                <p>From the first sale to the last stock check: point of sale, inventory and reports in one place.</p>
            </div>
            <div class="brand-art" aria-hidden="true">
                <div class="pack-float">
                <div class="pack">
<svg viewBox="0 0 720 460" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
<defs>
<clipPath id="sheetClip"><rect x="30" y="30" width="660" height="400" rx="34"/></clipPath>
<linearGradient id="foil" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fcfdff"/><stop offset=".42" stop-color="#dde6f1"/><stop offset=".7" stop-color="#f2f6fb"/><stop offset="1" stop-color="#c7d4e5"/></linearGradient>
<linearGradient id="sheen" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".5"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
<linearGradient id="dome" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".98"/><stop offset="1" stop-color="#b9cde6" stop-opacity=".9"/></linearGradient>
<radialGradient id="domeIn" cx=".5" cy=".42" r=".75"><stop offset="0" stop-color="#f4f9ff"/><stop offset="1" stop-color="#a7c0e3" stop-opacity=".7"/></radialGradient>
<linearGradient id="capWhite" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#dfe8f4"/></linearGradient>
</defs>
<rect x="30" y="30" width="660" height="400" rx="34" fill="url(#foil)"/>
<rect x="30.75" y="30.75" width="658.5" height="398.5" rx="33.25" stroke="#fff" stroke-width="1.5"/>
<rect x="46" y="46" width="628" height="368" rx="22" stroke="#9db0c9" stroke-opacity=".55"/>
<g clip-path="url(#sheetClip)"><path class="foil-sheen" d="M-40 520 300 -60h120L80 520Z" fill="url(#sheen)" opacity=".55" transform="translate(120 0)"/></g>
<path d="M195 52V408M360 52V408M525 52V408" stroke="#8ea4c2" stroke-width="2" stroke-linecap="round" stroke-dasharray="1 8"/>
<text x="64" y="396" fill="#0864c3" font-family="'Bricolage Grotesque','Figtree',sans-serif" font-size="17" font-weight="800" letter-spacing="1.6">PHARXMACO</text>
<text x="64" y="412" fill="#4d6485" font-family="'Figtree',sans-serif" font-size="10.5" font-weight="600" letter-spacing="1.2">DRUGSTORE</text>
<text x="640" y="396" text-anchor="end" fill="#4d6485" font-family="'Figtree',sans-serif" font-size="11" font-weight="600" letter-spacing="1">EXP 10/2028</text>
<text x="640" y="412" text-anchor="end" fill="#4d6485" font-family="'Figtree',sans-serif" font-size="11" font-weight="600" letter-spacing="1">LOT 2019</text>
<g class="cell" transform="translate(112.5 124)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:0"><g class="pill-body"><g transform="rotate(-18)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-blue" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(112.5 222)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:1"><g class="pill-body"><g><circle class="cap-shadow" cx="1" cy="4" r="18"/><circle r="18" fill="url(#capWhite)" stroke="#0a2a55" stroke-opacity=".16"/><path d="M-14 0H14" stroke="#0a2a55" stroke-opacity=".2" stroke-width="2" stroke-linecap="round"/><ellipse cx="-6" cy="-9" rx="7" ry="3" fill="#fff" fill-opacity=".7"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(112.5 320)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:2"><g class="pill-body"><g transform="rotate(14)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-amber" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(277.5 124)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:3"><g class="pill-body"><g transform="rotate(20)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-mint" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(277.5 222)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:4"><g class="pill-body"><g transform="rotate(-10)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-blue" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(277.5 320)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:5"><g class="pill-body"><g><circle class="cap-shadow" cx="1" cy="4" r="18"/><circle r="18" fill="url(#capWhite)" stroke="#0a2a55" stroke-opacity=".16"/><path d="M-14 0H14" stroke="#0a2a55" stroke-opacity=".2" stroke-width="2" stroke-linecap="round"/><ellipse cx="-6" cy="-9" rx="7" ry="3" fill="#fff" fill-opacity=".7"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(442.5 124)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:6"><g class="pill-body"><g><circle class="cap-shadow" cx="1" cy="4" r="18"/><circle r="18" fill="url(#capWhite)" stroke="#0a2a55" stroke-opacity=".16"/><path d="M-14 0H14" stroke="#0a2a55" stroke-opacity=".2" stroke-width="2" stroke-linecap="round"/><ellipse cx="-6" cy="-9" rx="7" ry="3" fill="#fff" fill-opacity=".7"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(442.5 222)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:7"><g class="pill-body"><g transform="rotate(-20)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-amber" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(442.5 320)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:8"><g class="pill-body"><g transform="rotate(12)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-mint" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(607.5 124)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:9"><g class="pill-body"><g transform="rotate(16)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-blue" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(607.5 222)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:10"><g class="pill-body"><g transform="rotate(-14)"><rect class="cap-shadow" x="-31" y="-10" width="64" height="26" rx="13"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="url(#capWhite)"/><path class="cap-mint" d="M0 -13H-19a13 13 0 0 0 0 26H0Z"/><rect x="-28" y="-10" width="54" height="4.5" rx="2.25" fill="#fff" fill-opacity=".42"/><rect x="-32" y="-13" width="64" height="26" rx="13" fill="none" stroke="#0a2a55" stroke-opacity=".14"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
<g class="cell" transform="translate(607.5 320)">
<rect class="cell-shadow" x="-53" y="-25" width="112" height="62" rx="31"/>
<rect x="-56" y="-31" width="112" height="62" rx="31" fill="url(#dome)" stroke="#6f8bb1" stroke-opacity=".55"/>
<rect x="-50" y="-25" width="100" height="50" rx="25" fill="url(#domeIn)"/>
<g class="pill" style="--i:11"><g class="pill-body"><g><circle class="cap-shadow" cx="1" cy="4" r="18"/><circle r="18" fill="url(#capWhite)" stroke="#0a2a55" stroke-opacity=".16"/><path d="M-14 0H14" stroke="#0a2a55" stroke-opacity=".2" stroke-width="2" stroke-linecap="round"/><ellipse cx="-6" cy="-9" rx="7" ry="3" fill="#fff" fill-opacity=".7"/></g></g></g>
<ellipse cx="-27" cy="-20" rx="21" ry="4.5" fill="#fff" fill-opacity=".62" transform="rotate(-6 -27 -20)"/>
<path d="M32 22a30 20 0 0 0 16-8" stroke="#fff" stroke-opacity=".45" stroke-width="2.5" stroke-linecap="round" fill="none"/>
</g>
</svg>
                </div>
                <div class="brand-chip chip-stock">
                    <span class="chip-icon chip-mint"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
                    <span class="chip-text"><strong>Stock in sync</strong><span>All branches</span></span>
                </div>
                <div class="brand-chip chip-receipt">
                    <span class="chip-icon chip-amber"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9.5 8h5M9.5 12h5"/></svg></span>
                    <span class="chip-text"><strong>Receipt ready</strong><span>Sale completed</span></span>
                </div>
                </div>
            </div>
        </aside>
    </div>
</body>
</html>
