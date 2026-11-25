# Pharxmaco security audit — 27 October 2026

Read-only source review, unauthenticated static HEAD requests against local Apache, and isolated security regression tests. No runtime files, accounts, active application sessions, application database records, schema or hosting were changed. HTTP response bodies containing secrets were not requested.

## Confirmed findings

### SEC01 — High: project-root static exposure bypasses application authentication

Unauthenticated HEAD requests returned 200 for `http://localhost/pharxmaco/.env`, `/pharxmaco/database/upgrades/2026-10-15-demo-users.sql`, `/pharxmaco/backuppp.zip` (788,358 bytes), `/pharxmaco/composer.json` and `/pharxmaco/docs/`. The protected `app/Config/Database.php` and `writable/` returned 403. This proves the actual local server serves configuration/SQL/archive paths outside public; merely using the application's /public URL does not protect sibling files. No secret values were fetched or printed.

Evidence: `static-exposure.json`. Apache `C:/UniServerZ/core/apache2/conf/httpd.conf:301` sets DocumentRoot to the parent www folder, line316 enables Indexes Includes, line328 grants access; lines343–345 only deny .ht* files. The project root has no .htaccess. `public/.htaccess:2` disables indexes only inside public, and `app/.htaccess:2`/`writable/.htaccess:2` deny only their own trees. `app/Config/Paths.php:89` points the environment directory to the project root. The local `.env:23` base URL uses /pharxmaco/public.

Risk: anyone able to reach this Apache deployment can request environment settings and copied database/code archives directly, without going through role/CSRF filters. Reachability from other devices/internet was not tested; this result does not prove the hosted site is exposed.

Correction: serve only the public directory, or use the already documented private-project/public_html split. Remove SQL, ZIPs, development documentation and environment files from web-reachable storage. Protect the parent tree if a temporary folder mapping is necessary. If this configuration has been reachable by untrusted users, rotate exposed credentials after removing access. Do not publish the current project root unchanged.

### SEC02 — Medium: password form accepts lengths bcrypt silently truncates

`app/Controllers/Admin/Users.php:111` and `:306` allow passwords up to255 characters; `:136` and `:313` hash with PASSWORD_DEFAULT. PHP8.3 uses bcrypt, which ignores bytes after72. `app/Controllers/Auth.php:70` accepts password_verify's result. The pure-code reproduction creates a hash for a73-byte synthetic value; a different73rd byte and the72-byte prefix alone both verify successfully. This is a credential-equivalence defect for accepted long passwords, not a demonstrated bypass of a short password.

Evidence: `password-boundary.php` and `password-boundary.json`; no actual account/credential involved. Use a supported password scheme that handles full input (with an intentional migration/rehash plan), or enforce bcrypt's72-byte boundary server-side until that change. Multi-byte text must be checked in bytes, not only character count. Affected existing long-password accounts need explicit consideration.

## Local deployment hardening facts, hosted status unknown

- `.env:73` is development; `app/Config/App.php:160` does not forceHTTPS; `app/Config/Cookie.php:57` secure=false. These are local development settings. Verify production environment/HTTPS/Secure cookies on hosting rather than assume they match.
- CSP is disabled at `app/Config/App.php:201`; the secureheaders global filter is commented out at `app/Config/Filters.php:86`. Review framing/content/security headers at the actual production server. Their absence alone does not prove XSS or account compromise.
- Login throttle is perIP and uses non-atomic cache read/increment/save at `app/Controllers/Auth.php:71–72`. Distributed/high-concurrency protection is not established by the current sequential tests. No brute-force attack was performed.
- Dependency findings are maintained by the parent audit; do not treat version matches as proof that an advisory's preconditions are reached by application code.

## Protections reviewed and passing evidence

47 focused PHP cases /398 assertions passed. Their XML is `focused.xml`. These are isolated in-memorySQLite/direct-controller/filter/service tests, not a production penetration test or real concurrent MySQL proof.

Coverage: account fingerprint revocation after password/role/branch/status changes; session regeneration on login/logout; no retained submitted passwords in failures; POST/CSRF logout; cashier single-login ownership/stale-token behavior; scoped presence tokens stored as hashes and unable to renew login lifetime; personal login-history/return pages reject forged branch filters and record IDs; CSV formula neutralization.

Source review also found explicit admin/cashier route groups, auto-routing disabled (`app/Config/Routing.php:97`), sessionCSRF global with limited stateless capability endpoints (`Filters.php:80`), revalidated database account authorization (`AccountSession.php`), bound/escaped database queries in critical paths, widespread output escaping, remote PDF assets disabled (`PdfFactory.php:26`), and data-only backup restore with admin password confirmation, random upload path, fixed extension/size, schema compatibility, transaction rollback and FK validation. No direct application SQL-injection or stored-XSS exploit was confirmed in this review. This is not an exhaustive proof of absence.

No active authenticated session cookie was copied or used. No restore/import, account write, sale/refund or destructive test was performed against the live application database.
