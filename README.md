# ClubConnect

ClubConnect is an ICT312 student project for club membership and event management. It uses PHP, PDO, MySQL-compatible InnoDB tables, HTML, CSS and vanilla JavaScript. The light interface uses white backgrounds and navy accents. All sample people, club facts and events are fictional; the site has no university affiliation.

The original `Advance_Information_System_Assessment_1 (1).docx` proposal was reviewed alongside the supplied implementation brief. The brief resolves the proposal's login, contact, logging and retention ambiguities. See [the requirements map](docs/requirements.md). Payments, university single sign-on, password recovery, email verification, uploads and native mobile applications are outside this build.

## Requirements and verified environment

Use PHP **8.2 or later**, Apache 2.4 for the XAMPP route, and a MySQL-compatible database with InnoDB and `utf8mb4`. Required PHP extensions are `PDO`, `pdo_mysql`, `mbstring`, `session`, `filter`, `hash` and `json`; standard PHP functions provide password hashing and cryptographic randomness. There is no Composer, npm or external font requirement to run the site.

The local environment inspected during this build was Windows/XAMPP, PHP CLI **8.2.12**, MariaDB server **10.4.32** and Apache binary **2.4.58**. XAMPP's MySQL control-panel label refers to MariaDB in this installation. MariaDB is the database engine verified here; a separate Oracle MySQL installation has not been claimed as tested. The required extensions were present in the CLI; confirm the Apache PHP configuration also enables them. Exact executed application checks and remaining browser/service checks are in [docs/testing.md](docs/testing.md).

Verification passed all 16 PHP syntax checks, 31 domain assertions including a real two-process capacity race, 296 browser/HTTP assertions and 38 protected browser checks with JavaScript disabled. Chrome and Edge were tested at 375px, 768px and 1200px; Firefox was not installed. Seed preservation and local Apache private-file protection also passed. A full assistive-technology review, backup restore rehearsal and hosted HTTPS deployment remain operator follow-ups.

## Local setup

Run PowerShell commands from `C:\xampp\htdocs\Clubconnect`. The examples assume XAMPP is at `C:\xampp`; adjust paths if needed. Do not expose the project root as the website's document root.

1. Start **MySQL** using the XAMPP Control Panel. For Apache hosting, also start **Apache**. The built-in PHP server alternative below only needs MySQL.
2. Open a local database administrator session. This prompts for the administrator password; a new local XAMPP installation may have an empty root password.

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' -u root -p
   ```

3. In that database console, create a fresh database and a dedicated application account. Replace the example password with your own local password. If these objects already exist, inspect and reuse them; do not drop an existing database or reset another account.

   ```sql
   CREATE DATABASE clubconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'clubconnect_app'@'127.0.0.1' IDENTIFIED BY 'CHOOSE_A_PRIVATE_LOCAL_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE ON clubconnect.* TO 'clubconnect_app'@'127.0.0.1';
   USE clubconnect;
   SOURCE C:/xampp/htdocs/Clubconnect/database/schema.sql;
   SHOW TABLES;
   EXIT;
   ```

   The schema creates exactly `members`, `committee`, `events` and `registrations`. Alternatively, create/select `clubconnect` in local phpMyAdmin and import `database/schema.sql` through **Import** using an administrator account. Schema installation and sample-data seeding are separate operations.

4. Copy the configuration template **only if local configuration does not already exist**:

   ```powershell
   if (-not (Test-Path -LiteralPath 'config\config.local.php')) {
       Copy-Item -LiteralPath 'config\config.example.php' -Destination 'config\config.local.php'
   }
   & 'C:\xampp\php\php.exe' -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

   Edit `config/config.local.php`: set `db.password` to the account password and paste the generated 64-character secret into `app_key`. Keep `db.host` as `127.0.0.1`, `db.name` as `clubconnect`, and `db.user` as `clubconnect_app` for the setup above. This file is ignored by Git. Do not share its contents.

   The template reads `base_path` from `CLUBCONNECT_BASE_PATH`, defaulting to an empty string. The Apache localhost configuration below supplies `/Clubconnect`; the PHP development server and optional `clubconnect.local` host use the empty default. If updating an older local configuration, set `'base_path' => getenv('CLUBCONNECT_BASE_PATH') ?: ''`. `timezone => 'Australia/Sydney'` determines event dates and committee terms. Keep `environment => 'local'` and `https_required => false` only for HTTP development on loopback. Hosted use requires HTTPS and `https_required => true`. `contact_email` remains clearly labelled as an example while `contact_is_example` is true; set both appropriately before real use. Keep `demo_mode` true while presenting sample data.

5. Ensure PHP's process account can write to `storage/` and its configured PHP session directory. Keep write access limited to the operator and web service account. Generate fictional demonstration records:

   ```powershell
   & 'C:\xampp\php\php.exe' scripts/seed.php
   ```

   The seeder uses relative dates when inserting new sample records and PHP-generated password hashes. A private per-database `storage/demo-seed-*.json` ID manifest lets reruns preserve records even after names, emails or event titles are edited. Keep that manifest with its matching demo database and include it in private backups. Rerunning does not reset profiles, passwords, registrations or dates; missing recorded rows or changed relationships stop the script for review. Consequently an old demonstration's dates do not move forward on each run. For a fresh later demonstration, use a separate empty demo database, import the schema and seed it; preserve the original database and manifest.

6. Serve **only `public/`**, using either route below.

### XAMPP Apache at localhost/Clubconnect

Review [config/apache-localhost.conf.example](config/apache-localhost.conf.example). Inside the existing `localhost` `<VirtualHost *:80>` block in `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, add this include while retaining its existing settings and other virtual hosts:

```apache
Include "C:/xampp/htdocs/Clubconnect/config/apache-localhost.conf.example"
```

Confirm `httpd.conf` includes `conf/extra/httpd-vhosts.conf`. The new include belongs inside the localhost virtual host. It maps `/Clubconnect/` to **only `public/`**, supplies the `/Clubconnect` base path, restricts access to this computer and redirects `/Clubconnect` to `/Clubconnect/`. Private project files remain denied. No hosts-file entry is needed for `localhost`.

Check the configuration before restarting Apache:

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -t
```

Restart Apache through XAMPP and visit **http://localhost/Clubconnect/**. Log in at **http://localhost/Clubconnect/dashboard.php**. After restarting Windows, start **Apache** and **MySQL** in XAMPP to use these addresses again. This Apache option does not require the port 8000 launcher. Verify private URLs return 403/404 using [the deployment checks](docs/testing.md).

For an optional separate hostname, [config/apache-vhost.conf.example](config/apache-vhost.conf.example) supplies global directory rules and a `clubconnect.local` virtual host. Preserve existing virtual hosts, add `127.0.0.1 clubconnect.local` to the Windows hosts file as an administrator, validate with `httpd.exe -t`, then restart Apache and visit **http://clubconnect.local/**. Keep `CLUBCONNECT_BASE_PATH` scoped to the localhost virtual host so this separate hostname retains an empty base path. Both Apache examples use local HTTP; hosted use requires a separately configured HTTPS virtual host. Root and public `.htaccess` files provide additional protection where Apache allows them; they do not replace the Apache directory rules.

### Local PHP development server

This avoids changing Apache or the hosts file. Start MySQL in XAMPP, then run this launcher from the project folder:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

The launcher starts PHP in the background and stores its server logs in private `storage/`. If the same ClubConnect server is already running, it reuses it. Visit **http://127.0.0.1:8000/** or open **http://127.0.0.1:8000/dashboard.php** to log in. The PHP server must be running for these addresses to work. Run the launcher again after restarting Windows or stopping PHP; starting Apache alone does not start the server on port 8000.

Alternatively, run PHP in a terminal and leave that terminal open:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
```

Stop the terminal server with Ctrl+C when finished. This server is for local development, with no production-service or concurrency guarantee. It does not process Apache `.htaccess`; its safety here comes from binding to loopback and serving only `public/`.

## Demo accounts

These ClubConnect demo credentials apply to accounts created by the current seeder in a fresh database. Updating the source or rerunning the seeder preserves existing accounts and does not change their emails or passwords.

| Email | Demo password | Demonstration role |
| --- | --- | --- |
| `alex.member@example.test` | `ClubConnectDemo!2026` | Active ordinary member |
| `sam@gmail.com` | `123456789sam` | Active member with a current committee term |
| `taylor.expired@example.test` | `ClubConnectDemo!2026` | Active member with an expired committee term; no event-management permission |
| `inactive.member@example.test` | `ClubConnectDemo!2026` | Inactive account; login/member actions are denied |

Use unique fictional details for your own registration demo. The password policy is at least 12 characters, at most 72 UTF-8 bytes, no null bytes, with no forced mixture of capitals, digits or symbols. Spaces and long passphrases are welcome within that byte limit. Bcrypt hashing uses cost 12; submitted passwords are never put back into the form. Registration does not verify an email address.

## How the application fits together

| Page | Purpose |
| --- | --- |
| `index.php` | Introduction, membership benefits and up to three upcoming database events |
| `about.php` | Mission, activities and labelled demonstration club facts |
| `committee.php` | Current committee names, roles and terms, without private contact details |
| `register.php` | Account creation, validation and privacy acknowledgement |
| `events.php` | Upcoming/past events, search, committee event forms and attendance |
| `event_register.php` | Authenticated event details and explicit POST RSVP confirmation |
| `dashboard.php` | Login when signed out; own profile, history, cancellation and logout when signed in |
| `contact.php` | Configured email link and FAQ; no message storage or sending |

The four tables form a small relational model: `members` have `committee` terms; a committee term owns each created `events` record; `registrations` connects one member to one event. A unique event/member pair prevents duplicate RSVPs. Foreign keys restrict deletion to preserve history. A membership category (`standard` or `life`) never grants committee permission.

Every protected request reloads active account status. Committee access additionally requires a term whose start date is today or earlier and whose end date is absent or today or later. The server takes identity from the session. Event-row locks serialize capacity-sensitive changes: RSVP, cancellation, rejoining and capacity edits. Registered and attended rows occupy places; cancelled rows do not. Rejoining updates the same row. All event times are single-date local wall times in the configured club timezone; overnight events are outside this baseline. Database creation/registration timestamps use UTC.

```text
app/                    Shared bootstrap, validation, permissions, events and views
config/                 Safe template, private local config and Apache example
database/schema.sql     Exactly four InnoDB application tables
docs/                   Requirements, testing, security, operations and asset credits
public/                 Exactly eight PHP pages, CSS, JavaScript, images and icons
scripts/                CLI-only demo seed and acceptance checks
storage/                Private throttle, audit logs and PHP error diagnostics
```

## Demonstration sequence

1. Browse Home, About, Committee and Events. Explain that data and photographs are representative. Search by an event title or location and open the past-events view.
2. Join with fictional details, acknowledge the privacy notice, and show the confirmation at the dashboard login page. Try a duplicate email/student ID separately to show validation.
3. Log in. Open a future event with places available, confirm the RSVP, refresh, and show the single registration on the dashboard.
4. Start cancellation from the dashboard, review the confirmation, submit it, then show the released place. Rejoin to demonstrate reuse of the existing record.
5. Update your own profile, refresh, then log out and back in to show persistence.
6. Log in as `sam@gmail.com` with `123456789sam` for a newly seeded demo. If using an existing database, use that account's current credentials. Add a future event, edit it, inspect participants, and use the seeded past event to demonstrate attendance management. Explain why capacity cannot fall below occupied places.
7. Log in as the expired committee account to show the member experience without management controls. Explain that the inactive sample cannot log in.
8. Show Contact's `mailto:` link and FAQ. It opens an email application; the site does not send a message.

## Common setup problems

| Symptom | Check |
| --- | --- |
| Browser cannot reach `localhost/Clubconnect/` | Start Apache and MySQL in XAMPP; confirm the localhost virtual host includes `config/apache-localhost.conf.example`, validate Apache configuration and restart Apache after changes |
| Setup/unavailable message | Private `storage/php-error.log`, database service, imported schema and local configuration; avoid exposing diagnostics in the browser |
| `could not find driver` | Enable `pdo_mysql` in the PHP configuration actually used, then restart Apache |
| Browser `ERR_CONNECTION_REFUSED` on port 8000 | Run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1` from the project folder; the PHP server must be running |
| Database connection refused / access denied | MySQL running, port, password, `127.0.0.1` account host and database grants |
| Missing table | Import `database/schema.sql` into the database named in local configuration |
| Login cannot continue | Valid 64-character `app_key`, writable private storage and session directory; wait for the 15-minute throttle window if the attempt limit was reached |
| Expired form / session | Refresh to receive a current CSRF token; cookies must be enabled; sessions expire after 30 minutes idle |
| HTTP rejected | Local HTTP requires a loopback request and local environment; use HTTPS for hosted or remote access |
| Broken navigation/assets | Serve only `public/`; the localhost alias must supply `CLUBCONNECT_BASE_PATH /Clubconnect`, while a site mounted at `/` uses an empty base path |
| No current demo events | Dates are generated only when new rows are seeded; use a separate fresh demo database rather than changing existing history |
| Contact email does not deliver | The initial address is an example; configure a real club mailbox and update the example flag |

See [operations](docs/operations.md) for weekly backups and a separate-database restore, [security and privacy](docs/security-privacy.md) for safeguards and risks, and [testing](docs/testing.md) for evidence and repeatable checks. Publishing this source repository does not deploy the application. Private local configuration, runtime storage and the local ZIP archive are excluded from Git.
