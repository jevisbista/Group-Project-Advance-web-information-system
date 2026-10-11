# Test record and acceptance guide

This file distinguishes executed evidence from checks still pending. Proposal testing statements are not execution evidence. The contact-form submission check is **not applicable**: the chosen Contact implementation is a configured email link and FAQ.

## Environment checked

| Item | Observed result |
| --- | --- |
| Operating environment | Windows/XAMPP; workspace `C:\xampp\htdocs\Clubconnect` |
| PHP CLI | `C:\xampp\php\php.exe -v`: PHP 8.2.12 |
| Extensions | `php -m`: PDO, pdo_mysql, mbstring, session, filter, hash and json present |
| Database | Local MariaDB server 10.4.32 reachable; mysql client also reports MariaDB 10.4.32 |
| Apache binary | 2.4.58; binary presence alone does not prove a configured site |
| Browser execution | Chrome 154.0.8037.57 and Microsoft Edge 154.0.4258.37 ran the browser suites. Firefox was not installed and was not tested |
| Original scope source | Original proposal reviewed together with detailed implementation brief |
| Git | Source import prepared on `feature/clubconnect-build` with the group repository's initial commit preserved as its parent; source publication does not deploy the application |

## Execution ledger

Verification was completed on **28 September 2026**. The main browser log was last written at **12:10:32** and the completed protected-browser log at **12:15:42**, local time (Australia/Sydney). Evidence files are private: `storage/browser-results.log`, `storage/protected-browser-results-complete.log` and screenshots in `storage/browser-results/`. A **pending** item below remains unexecuted and is not a claimed pass.

| Check group | Status | Evidence / limit |
| --- | --- | --- |
| PHP syntax across all PHP files | Passed | All 16 PHP files, including ignored local configuration, passed `php -l`; the full lint was repeated after final changes |
| Eight HTTP routes | Passed | All eight routes returned 200 after expected redirects, each with one H1 and correct UTF-8; unauthenticated RSVP redirects to dashboard login |
| Schema installation | Passed | Imported into `clubconnect` and the separate acceptance database; initial seed in each added 4 members, 2 committee terms, 5 events and 6 registrations |
| Exactly four tables and eight pages | Passed | Final domain suite asserts the four expected table names and eight top-level public PHP files |
| Seeder idempotency and password preservation | Passed | Main database rerun added zero rows to all four tables; `scripts/test-seed.php` preserved edited email/student ID and event title/location, retained the password hash and added zero rows |
| Server-side domain and permissions suite | Passed | `scripts/test-domain.php`: 31 assertions, including active/current committee rules, repeated RSVP, full-event rejection, cancellation/rejoin, capacity floor, preserved creator, inactive session denial, past-event restrictions, attendance rules, input validation, CSRF, escaping and page/table counts |
| Independent-connection last-place race | Passed | Domain suite launches two independent PHP processes against one remaining place; exactly one registers, one is rejected and occupancy remains one |
| HTTP/browser flows, protected routes, CSRF | Passed | `scripts/test-browser.cjs`: 296 browser/HTTP assertions. Includes registration validation, protected event creation/editing, RSVP/cancel/rejoin, selected-event login return, CSRF rejection, SQL-like input, HTML escaping, profile persistence, inactive login, timeout and throttle persistence across cookie jars |
| Public responsive layouts/images/favicon | Passed | Chrome and Edge, eight public routes at 375, 768 and 1200px: successful loads/expected login redirect, one H1, no horizontal overflow, local images and favicon. Home desktop/mobile screenshots inspected |
| Static artwork and visual review | Passed for inspected assets/views | Seven local SVG files parsed successfully; PNG favicon verified at 32×32. Home screenshots and register/dashboard/events/contact/about at 1200px plus registration at 375px were visually inspected; broader browser coverage recorded separately |
| Keyboard and JavaScript-disabled baseline | Passed within recorded scope | Chrome and Edge reach the skip link first; mobile navigation and native form controls remain available with JavaScript disabled; no uncaught JavaScript errors in the main browser suite. Final Chrome 375px checks verified a visible skip link on Tab, Enter activation, menu expanded state and Escape closing with focus restored |
| Protected JavaScript-disabled browser walkthrough | Passed | `scripts/test-protected-browser.cjs`: 38 checks in Chrome and Edge. Actual no-JavaScript login, profile save, RSVP, confirmed cancellation, logout and committee event creation succeeded. Member dashboard, RSVP, event editor and participant table had no overflow at 375, 768 and 1200px in both browsers. Targeted verification followed the mobile scroll/focus refinement |
| Private audit/throttle inspection | Passed within inspected scope | Audit records used only approved minimal JSON keys and included failed-login/event-save actions. Throttle keys were 64-character HMACs and stored entries were within the configured 2,000-key bound |
| Chrome, Firefox and Edge coverage | Partial | Chrome and Edge executed; Firefox was not installed. Full assistive-technology audit and broader manual usability review remain unexecuted |
| Apache private-file boundary | Passed locally | Temporary Apache loopback port 8088: Home 200 and private project-root alias attempts denied. Existing XAMPP host `http://127.0.0.1/Clubconnect/` returned 403 for config, schema, seed script, PHP error log and `.git/config`. No global XAMPP configuration was modified; operator installation of the example virtual host remains separate |
| Private files excluded from Git | Passed | `git check-ignore` confirmed private local configuration, runtime artifacts and private development dependencies are ignored |
| Backup and separate-database restore rehearsal | Pending | Instructions in operations; no completed restore claimed |
| Hosted HTTPS deployment | Pending / outside local build | Local HTTP was exercised; no hosted deployment or TLS/certificate validation claimed |

## Sam demo login update verification

On 11 October 2026, fresh seeding was checked in the separate `clubconnect_test_sam_20261011_cf4844` database. Sam's requested email and password verified against the stored hash, while the other three demo accounts retained their original defaults. The existing `scripts/test-seed.php` preservation check passed, and Sam kept the new login and active committee term after repeated seeding. The main database was not used for these seed checks.

The seed script passed PHP syntax validation, and both updated browser scripts passed `node --check`. The full browser acceptance suites were not rerun for the fixture changes. Sam's updated local account was separately verified through the Apache login page and committee event form, followed by logout.

## Localhost Apache alias verification

On 11 October 2026, the localhost virtual host was configured to include `config/apache-localhost.conf.example`, serving `http://localhost/Clubconnect/` from `public/`. The prior Apache and private PHP configurations were backed up under ignored `storage/` before the change.

- Apache configuration validation and syntax checks for both PHP configuration files passed. Apache accepted its native Windows graceful reload event.
- Chrome 155.0.8059.39 loaded all eight public routes, including the expected signed-out RSVP redirect. Links, form actions and asset paths retained the `/Clubconnect/` prefix; nine distinct referenced assets returned HTTP 200.
- The fictional member account completed login and logout with JavaScript disabled. Redirects stayed under the new prefix, and the HttpOnly session cookie used `/Clubconnect/`.
- Inter loaded, Home had no horizontal overflow at 375px and 1200px, and no JavaScript page errors occurred.
- The bare `/Clubconnect` path redirected to `/Clubconnect/`. Private configuration, schema, scripts, storage, application source, Git metadata, environment and ZIP paths returned 403/404; directory listing and non-aliased case variants remained blocked.
- The existing port 8000 preview still returned HTTP 200 with root-relative asset paths. These checks covered the hosting-path change; broader data-mutation tests were not rerun.

## Visual refresh verification

After the requested banner, typography and photography changes on 28 September 2026:

- All 11 PHP files in `app/` and `public/` passed syntax checks.
- Chrome and Edge checked all eight routes at 375, 768 and 1200 pixels: 48 route/viewport combinations passed, including expected authentication redirects.
- Local JPEGs loaded, Inter finished loading, and there was no horizontal overflow or uncaught JavaScript error.
- The demo banner, decorative labels and image captions were absent. Form password guidance and committee roles remained available.
- Rendered application copy contained no em dashes. Home screenshots at desktop and mobile sizes were visually reviewed; screenshots are in private `storage/visual-refresh/`.
- These checks cover the visual changes. Earlier database and authorization results below refer to the original implementation and were not rerun for this presentation-only update.

## Repeatable local checks

From the project root, syntax-check PHP files without executing the application:

```powershell
$phpFiles = Get-ChildItem -Path app,config,public,scripts -Filter '*.php' -Recurse -File
foreach ($phpFile in $phpFiles) {
    & 'C:\xampp\php\php.exe' -l $phpFile.FullName
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($phpFile.FullName)" }
}
(Get-ChildItem -LiteralPath public -Filter '*.php' -File).Count
```

The public count must be **8**. Use phpMyAdmin or the database console to verify exactly four application tables:

```sql
SHOW TABLES FROM clubconnect;
SELECT table_name, engine FROM information_schema.tables WHERE table_schema = 'clubconnect';
SHOW CREATE TABLE clubconnect.registrations;
```

Acceptance scripts mutate test fixtures, so use a separate schema and never a real member database. The process-level `CLUBCONNECT_TEST_DB` override is accepted only by CLI/local test servers and requires a `clubconnect_test_` prefix. The acceptance database created for this build is `clubconnect_test_acceptance_20260928`; its records are retained rather than destructively cleaned up. Give the local test account data privileges on that exact database and import the schema there as an operator. Tests use isolated storage under `storage/<test-database>/`.

```powershell
$env:CLUBCONNECT_TEST_DB = 'clubconnect_test_acceptance_20260928'
& 'C:\xampp\php\php.exe' scripts/seed.php
& 'C:\xampp\php\php.exe' scripts/test-seed.php
& 'C:\xampp\php\php.exe' scripts/test-domain.php
Remove-Item Env:CLUBCONNECT_TEST_DB
```

For browser checks, install the optional Playwright tooling into ignored private storage (Node.js/npm are needed only for this check):

```powershell
npm.cmd install --prefix storage/browser-tools --no-audit --no-fund playwright
```

Start the normal loopback preview on port 8000 as in the README. In a second terminal, start the isolated test server against the prepared and seeded acceptance database:

```powershell
$env:CLUBCONNECT_TEST_DB = 'clubconnect_test_acceptance_20260928'
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8001 -t public
```

In a third terminal, run the browser script. Chrome and Edge are discovered at the explicit Windows installation paths in the script; adjust those if necessary. The script does not download or claim to test Firefox.

```powershell
$env:CLUBCONNECT_TEST_DB = 'clubconnect_test_acceptance_20260928'
$env:CLUBCONNECT_TEST_URL = 'http://127.0.0.1:8001'
$env:CLUBCONNECT_PREVIEW_URL = 'http://127.0.0.1:8000'
node scripts/test-browser.cjs
node scripts/test-protected-browser.cjs
Remove-Item Env:CLUBCONNECT_TEST_DB,Env:CLUBCONNECT_TEST_URL,Env:CLUBCONNECT_PREVIEW_URL
```

Read the scripts before rerunning them and record any environment changes. Browser automation is a development check, not a runtime dependency for the website. Record browser version, execution output and screenshots from `storage/browser-results/` when available. Do not run a broad registration/login suite against a shared real account because the server-side throttle correctly persists across sessions. Repeated suites within 15 minutes may intentionally hit the persistent IP limit; wait for expiry or prepare a separate isolated acceptance environment. Do not remove a live throttle store to work around production limits.

## Acceptance cases

| # | Procedure | Expected result |
| --- | --- | --- |
| 1 | Load all eight page routes, using a valid event ID for RSVP; repeat with a database connection problem in a controlled test configuration | Normal routes load; unavailable-service response is readable and contains no SQL, credentials or stack traces |
| 2 | Join using unique fictional details, RSVP, update profile, refresh and log out/in | Changes persist in MySQL and remain tied to the same account |
| 3 | Submit missing acknowledgement, duplicate email/student ID, invalid email/lengths, mismatched password, under-12-character password and over-72-byte password | Server rejects each; linked feedback explains correction; no password value is redisplayed |
| 4 | Inspect stored password prefix/verify using PHP; login/logout; use inactive account and deactivate a test account during an active session | Hash rather than plaintext stored; session regenerated; inactive member actions denied immediately |
| 5 | Visit protected page while signed out; submit another member ID/registration ID; forge committee POST as ordinary member | No protected data leak or cross-member change; permission denied server-side |
| 6 | Use current, expired, future-start and inactive committee fixtures | Only active members with a current term can manage events |
| 7 | Open RSVP without submitting, submit twice, run two database connections against one remaining place | GET creates nothing; only one row for same member; one of the competing claims succeeds and occupancy stays within capacity |
| 8 | Cancel with confirmation; rejoin; try started/past event RSVP/cancellation | Place released; same registration ID reused; started/past changes refused |
| 9 | Reduce capacity below occupied; forge creator; mark attendance before/after start and on cancelled row | Capacity reduction/creator change rejected or ignored; only current committee after-start non-cancelled registration can become attended |
| 10 | Omit/change CSRF token on each mutation; search/store SQL-like and HTML-like strings | CSRF rejected; values treated as data and escaped, with no script execution or altered query structure |
| 11 | Search title/location, open empty result, submit invalid forms; use keyboard and no JavaScript at all target widths | Useful empty state, readable labels/errors/focus, working core navigation/forms and no horizontal page scroll |
| 12 | Count pages/tables; request private paths from each enabled host alias | Eight public pages/four tables; private file URLs return 403/404 without contents |
| 13 | Load all pages, inspect favicon links, images/dimensions/alt text; create an unmatched-title event | Local assets load, below-fold images lazy-load, hero is responsive, fallback event image works |

Check private paths including `/config/config.local.php`, `/database/schema.sql`, `/scripts/seed.php`, `/storage/login-throttle.json`, `/storage/php-error.log`, `/.git/config` and `/.env`. For default XAMPP aliases also test `/Clubconnect/config/config.local.php` and `/Clubconnect/storage/php-error.log`; a correct virtual host alone does not prove the default host is safe. Test both missing and existing private paths without printing their contents into public reports.

For throttling, use an isolated test account/IP context: submit the account attempt limit, clear browser cookies, confirm another attempt is still refused, then confirm expiry after the window. Review the private store for hashed keys and the 2,000-key bound; do not commit it. For session expiry use a separate test configuration with a temporarily shortened timeout, record that deviation, then restore the default of 1,800 seconds.

## Browser and accessibility checklist

- At **375px**, **768px** and **1200px**, inspect Home, registration errors, Events, event form/participant list and Dashboard. Confirm no document overflow, clipped controls or overlapping text.
- Use Tab/Shift+Tab/Enter/Space through the skip link, mobile menu, navigation, forms and cancellation confirmation. Focus must remain visible and reach every interactive control.
- Confirm a single H1 and semantic landmarks, current-navigation indication, labels, error summary and field error associations. Check meaningful image alternatives and readable colour contrast.
- Disable JavaScript before loading the site. Navigation, login, registration, search, RSVP, cancellation and committee forms must work through ordinary links and server submissions.
- Record actual checks separately for Chrome, Firefox and Edge where available. A Chromium automation run alone does not establish all three browsers.
- Reload after each mutation to check Post/Redirect/Get and stable data. Confirm no live submitted password appears in returned HTML.

Use [operations.md](operations.md) for backup/restore verification. Record failures and remaining work in [kanban.md](kanban.md); never label an unexecuted browser, restore, push or deployment step as complete.
