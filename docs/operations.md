# Service, backups and change management

## Running and maintaining the local service

Follow the [README](../README.md) for schema installation, private configuration and the two local hosting options. Run the PHP service with read access to application files and write access only where needed for private `storage/` and PHP sessions. Use a dedicated database account with normal data privileges; schema installation and later DDL changes use a separate operator account. Never put real configuration, dumps or logs in `public/`.

For the PHP development server, run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1` from the project folder. The launcher starts PHP in the background with logs in `storage/`; it does not install a Windows service. Run it again after restarting Windows or stopping PHP. XAMPP's MySQL service must also be running for database-backed pages.

The club timezone defaults to `Australia/Sydney`; event dates and single-day wall times use it consistently, including committee validity. Changing the timezone changes interpretation of existing wall-time events, so review stored events before changing it. Nonexistent spring daylight-saving times are rejected. Avoid scheduling in the repeated hour at an autumn clock change: the proposal's date/time-only schema has no offset field to distinguish the two occurrences. Creation and registration timestamps are exchanged with the database in UTC. Keep the operating-system clock accurate for sessions, throttling and time-based permissions.

Review private PHP errors and audit outcomes regularly. JSONL audit filenames rotate by calendar month. As an initial operational policy, review audit/diagnostic logs monthly and retain only the most recent four weeks unless a documented incident requires a restricted hold. Monthly files may contain both recent and older lines: inspect dates before disposal, and use a reviewed rotation/archive process for `php-error.log` while avoiding concurrent writers. There is no automatic log-pruning task. Keep archived logs protected and out of Git; apply a corresponding policy to Apache access/error logs.

Watch available disk space, storage permissions, database availability and certificate expiry on any HTTPS host. Login fails closed if throttle/audit storage cannot be used. The file throttle is for one server; multiple application servers would require a redesigned shared limiter. The PHP development server is not a production service. Neither production deployment nor scheduled jobs are performed by this build.

## Weekly backup and four-week retention

The proposal calls for a weekly database backup and four weeks of retention. This is an operator procedure, not an installed scheduler. Choose a weekly time, confirm completion, and record filename, date, database and verification result. Keep four completed weekly snapshots on protected storage outside the web document root, preferably with a separate secured copy. Review backup timestamps before removing an expired copy. Never rotate away the only known restorable backup; investigate failures first. No destructive deletion command is included here.

In PowerShell, create a private destination under your user profile and use `--result-file` to avoid PowerShell SQL redirection/encoding problems:

```powershell
$backupDirectory = Join-Path $env:USERPROFILE 'ClubConnectBackups'
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
$backupFile = Join-Path $backupDirectory ('clubconnect-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& 'C:\xampp\mysql\bin\mysqldump.exe' --host=127.0.0.1 --user=root -p --single-transaction --default-character-set=utf8mb4 --result-file=$backupFile clubconnect
if ($LASTEXITCODE -ne 0) { throw 'Database backup failed; do not count this as a completed backup.' }
Get-Item -LiteralPath $backupFile
```

This example uses the local operator account and prompts for its password; do not place passwords in shell history or checked-in scripts. A maintained service should use an appropriately restricted backup account and secured credential mechanism. InnoDB's transaction snapshot provides a consistent data backup; avoid schema changes during the dump. This command intentionally omits `--databases`, so the dump can be imported into a separate database without embedding `CREATE DATABASE`/`USE clubconnect`.

Database dumps contain personal data and password hashes. Restrict file access, encrypt an off-machine copy, and apply the retention policy to every copy. Protect configuration secrets separately through an approved secret-backup mechanism. Keep versioned source/schema so a compatible application can be restored. Audit logs are separate files and are not part of the SQL dump.

For a seeded demonstration database, also preserve the corresponding private `storage/demo-seed-*.json` manifest alongside its dump. Its filename is derived from the database name and its IDs refer to that database's sample records. It prevents reseeding from duplicating renamed or edited demo records. Test databases keep their manifest under `storage/<test-database>/`. Do not discard a manifest or pair it with an unrelated database; do not run the seeder on a restored copy merely to test restoration. If moving the demo to a different database name, have the operator review the matching manifest location/name before any future seed run. The JSON manifest is setup metadata, not an extra application table.

If a seed is interrupted around commit and leaves a `.json.tmp` pending manifest, the next seed refuses to continue. Preserve the database, existing manifest and pending file. A maintainer must compare every pending ID and relationship against the database: if all changes committed, promote the matching pending file; otherwise restore a consistent database/manifest backup. Do not discard the pending file or reseed blindly.

## Restore rehearsal into a separate database

Do not overwrite the running `clubconnect` database for a test. Select a unique destination, such as `clubconnect_restore_20260928`; use a fresh suffix for later attempts. With a local database administrator:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -p
```

In that SQL console, create and select the separate destination, then import the chosen dump. Replace the username/path and backup timestamp with your actual file:

```sql
CREATE DATABASE clubconnect_restore_20260928 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clubconnect_restore_20260928;
SOURCE C:/Users/YOUR_WINDOWS_USER/ClubConnectBackups/clubconnect-YYYYMMDD-HHMMSS.sql;
SHOW TABLES;
SELECT 'members' AS table_name, COUNT(*) AS rows_count FROM members
UNION ALL SELECT 'committee', COUNT(*) FROM committee
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'registrations', COUNT(*) FROM registrations;
SELECT r.registration_id FROM registrations r
LEFT JOIN members m ON m.member_id = r.member_id
LEFT JOIN events e ON e.event_id = r.event_id
WHERE m.member_id IS NULL OR e.event_id IS NULL;
EXIT;
```

Record dump/import exit statuses, expected table counts and the zero-row orphan check. A nonempty dump is not proof of restorability. To rehearse the application, create a separate private application copy/configuration and restricted account targeting only the restore database, bind it to a different loopback port with its own storage, and exercise login, event reads and a fictional test RSVP. Do not point the live application at the restore database or send real emails. Keep the source database untouched. Document the rehearsal date, database, exact checks and outcome; a successful restore is not claimed until performed. Dispose of test copies only after an explicit operator review of paths, data dependencies and retention obligations.

## Manual member retention and disposal

Review records with the club contact at an agreed interval, initially each academic term. The database cannot determine when a member last used the service or became inactive. Do not infer either from `join_date`. Use an approved external decision record with minimal information, check correction/deletion requests, identify what is necessary for current membership and event history, and document the decision.

Inspect linked registrations, committee terms and events before proposing a change. `events.created_by` preserves historical committee ownership; foreign keys deliberately prevent cascading deletion. Deactivation is not deletion, and pseudonymising a name alone is not assured anonymisation. Have an operator review any disposal/anonymisation plan, protect an appropriate backup, apply only approved changes, and verify relationship integrity. There is no scheduled data purge and no hard-deletion UI or cleanup during setup/testing. Expired backup copies need coordinated handling so disposed information is not silently reintroduced after a restore.

## Change management

The application was built on the local `feature/clubconnect-build` branch. The source import uses the group project repository's existing initial commit as its parent, preserving that history. Review `git status` and `git diff` before committing later changes; the real local config, private logs, backups, runtime artifacts and local ZIP archive must remain untracked.

Use a focused branch for later changes, describe the user-visible behaviour and security/data effects, and keep [CHANGELOG.md](../CHANGELOG.md) and [kanban.md](kanban.md) current. Run syntax checks and the acceptance cases relevant to the change. Changes to authentication/CSRF require direct-request permission tests; capacity changes require the concurrent last-place test; view changes require keyboard/responsive checks. Record the actual environment, results and any remaining cases rather than marking planned checks as passed.

Schema changes require a reviewed migration and compatible rollback plan rather than reimporting the initial schema over live data. Back up first, rehearse on a separate database and inspect foreign-key effects. Before hosting, review HTTPS, the public document root, production credentials, real contact details, demo-data removal and service/backup ownership. Publishing source to GitHub does not deploy the application; hosting and database setup are separate operator actions.
