# Requirements and scope

The supplied `Advance_Information_System_Assessment_1 (1).docx` was reviewed. Its sitemap, four-table model, local PHP/MySQL stack, Kanban process and weekly backup approach inform this build. The detailed implementation brief is authoritative for the decisions below. Statements about completed testing in the proposal are planning material, not evidence that this implementation passed a check. Actual evidence belongs in [testing.md](testing.md).

## Page and data mapping

| Requirement | Implementation | Acceptance check |
| --- | --- | --- |
| Home with welcoming content and live event preview | `public/index.php`, `app/views.php`; up to three upcoming events | Empty/non-empty lists, ordering, links, local hero image |
| About mission and benefits | `public/about.php` | Original club text; demo affiliation/history label; supporting image |
| Public committee | `public/committee.php`; `committee` joins `members` | Current-term date boundaries; empty state; no student IDs, private emails, phones or hashes |
| Join and privacy acknowledgement | `public/register.php`, shared validation | Required fields, valid lengths/email, duplicate ID/email, matching password, privacy checkbox, safe value preservation |
| Events and search | `public/events.php`, `app/events.php` | Upcoming chronological order, title/location search, explicit past view, accurate remaining places |
| Create/edit and attendance in Events | Same page, current committee checks | Member/expired/inactive denial; valid single-day dates/times; capacity floor; immutable creator; attendance only after start |
| Explicit authenticated RSVP | `public/event_register.php` | GET has no mutation; selected event survives login; full/started/missing event denial; no arbitrary redirect |
| Login, own profile/history, cancellation and logout | `public/dashboard.php` | No protected output before login, owner-only update, future-only confirmed cancellation, CSRF-protected logout |
| Email and FAQ contact option | `public/contact.php`, `config/config.example.php` | Labelled example email; working `mailto:`; no false sent confirmation |
| Exactly four application tables | `database/schema.sql` | `SHOW TABLES`; InnoDB, keys, indexes, `utf8mb4` and constraints |
| Repeatable demo setup | `scripts/seed.php`, private per-database seed manifest | Fictional accounts and relative dates; stored IDs preserve existing rows/edits on rerun; retain matching manifest with backup |
| Reusable server logic and layout | `app/bootstrap.php`, `app/events.php`, `app/views.php` | Eight public PHP files; supporting PHP is outside document root |

## Decisions resolving the proposal

- **Login:** `dashboard.php` serves the login form when signed out and protected member content when signed in. Logout is a POST on that page. No ninth login/admin/reset page is added.
- **Contact:** use the proposal's club-email/FAQ option. There is no server-side contact form, email delivery service, message table or message-sent state. The proposal's contact-form submission test is **not applicable** to this variant.
- **Logs:** the proposal mentions a log table, but the four-table limit is preserved. Minimal structured audit events are written outside the public directory to protected files. This is not a fifth database table.
- **Retention:** the schema has neither last-active nor inactive-since data. Retention therefore requires a documented manual review, not an automatic two-year cleanup based on join date. Foreign-key and historical ownership dependencies must be reviewed before any disposal.
- **Identity and permissions:** `members` holds account credentials. Active status is required for member actions; committee access derives only from a current `committee` term. `membership_type` is a category, not a role.
- **Events:** the proposal's separate date/start/end fields represent one day in `Australia/Sydney` by default. End time must be after start time; overnight/multi-day events are outside scope. Creation/registration timestamps are handled as UTC.
- **Images:** local representative stock photography and a simple server-side event image map avoid uploads or an additional table. Asset provenance is documented in [asset-credits.md](asset-credits.md).

## Relational model and integrity

| Table | Fields retained from the proposal | Relationships and rules |
| --- | --- | --- |
| `members` | `member_id`, `first_name`, `last_name`, `student_id`, `email`, `phone`, `password_hash`, `membership_type`, `join_date`, `status` | Unique student ID/email; nullable phone; server assigns active/standard and join date at registration |
| `committee` | `committee_id`, `member_id`, `role`, `term_start`, `term_end` | Member can have committee terms; null term end means open-ended |
| `events` | `event_id`, `event_name`, `description`, `event_date`, `start_time`, `end_time`, `location`, `capacity`, `created_by`, `created_at` | Creator references a committee term and is preserved when editing |
| `registrations` | `registration_id`, `event_id`, `member_id`, `registration_date`, `attendance_status` | One row per event/member, with registered/attended/cancelled status |

Foreign-key deletion is restricted rather than cascading away historical records. There is no hard-delete user workflow. All capacity changes lock the event row first in an InnoDB transaction and recheck current occupancy and time inside the transaction. Registered and attended rows count as occupied; cancelled rows can be reactivated without inserting another row.

## Design, accessibility and security mapping

| Requirement | Implementation/check |
| --- | --- |
| White/light layout with navy accents | CSS variables: `#0B2545`, `#163B63`, `#FFFFFF`, `#F3F7FC`, `#1F2937`, `#526176`, `#D9E2EC`; Grid/Flexbox; approximately 1160px content width |
| Local fonts and relevant imagery | Locally hosted licensed Inter with system fallback; local photographs/icons; shared favicon head; informative alt text and explicit dimensions |
| Responsive and keyboard use | 375px, 768px, 1200px checks; skip link, one H1, landmarks, labels, error associations, current-page navigation and focus styling |
| Progressive enhancement | Ordinary links/POST forms/server-side search work without JavaScript; navigation remains available |
| Passwords and sessions | Bcrypt cost 12, 12-character minimum/72-byte maximum, session regeneration, 30-minute idle timeout, secure cookie configuration |
| CSRF and permissions | Server-generated token on every mutation, POST-only state changes, active/role/ownership checks on server |
| Injection prevention | PDO prepared statements, explicit identifier choices and contextual HTML encoding |
| Throttling and audit | Private locked/bounded throttle file, minimal monthly JSONL audit, no submitted secrets in logs |
| Protected project files | Public-only document root; Apache parent deny rules; ignored real configuration and runtime files |
| Operations and change control | [operations.md](operations.md), [security-privacy.md](security-privacy.md), [kanban.md](kanban.md), [CHANGELOG.md](../CHANGELOG.md) |

The project does not implement payments, SSO, native mobile clients, a separate admin application, password recovery, verification emails, uploads or contact-message storage. Safeguards are described as implemented controls, not legal certification or automatic compliance.
