# Security, privacy and risks

This student application uses layered safeguards. These are implementation choices, not a claim of legal certification or a replacement for an operator's review before real use. Use fictional data for demonstrations.

## Implemented controls

- Database values use PDO prepared statements with native prepares. Any dynamic field identifiers come from fixed application choices. PDO parameters represent values, not SQL identifiers; structure must remain controlled by code. See the [PHP PDO documentation](https://www.php.net/manual/en/pdo.prepare.php).
- Untrusted names, profile fields, event text, search values and errors are HTML-encoded at output. Descriptions are text, never trusted submitted HTML. A same-origin content-security policy disallows inline/external scripts and framing; assets are local.
- Passwords use `password_hash` with `PASSWORD_BCRYPT`, cost 12, and automatically generated salts, then `password_verify` at login. The application rejects passwords above 72 bytes so bcrypt cannot silently truncate them. Minimum length is 12 characters, with spaces allowed and no composition rule; null bytes are rejected. See [PHP password hashing](https://www.php.net/manual/en/function.password-hash.php).
- Login uses a generic invalid-credentials message and regenerates the session identifier after authentication. Session cookies are HttpOnly and SameSite=Lax; HTTPS requests also use Secure cookies. Sessions expire after 30 minutes of inactivity. Account status and current committee terms are reloaded on protected requests.
- Every change requires POST and a server-generated session CSRF token, including account creation, login/logout, profile edits, RSVP/cancellation, events and attendance. Tokens use cryptographic randomness and constant-time comparison. SameSite cookies complement token verification. See the [OWASP CSRF guidance](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).
- Identity and ownership come from the authenticated session. Request fields cannot assign membership status, membership category, a committee role, another member's profile or an event's creator. Attendance only accepts the application's allowed status transition for an existing non-cancelled registration after the event begins.
- Event-row locks serialize capacity-sensitive transactions. Re-registration, cancellation and capacity edits follow the same discipline as a new RSVP. The unique event/member constraint is a second duplicate safeguard.
- Configuration, SQL, CLI scripts, backups and logs stay outside `public/`. Real local configuration and runtime files are ignored by Git. Browser errors remain readable without exposing queries, credentials or stack traces; private diagnostics record error type, code and source location rather than submitted form bodies.

## Login throttling and logging

`storage/login-throttle.json` is a server-side file protected by an exclusive file lock. It counts all reserved login attempts, including successful ones, across browser sessions. Limits are 8 attempts per normalized email key and 30 per IP key in a rolling 15-minute window. Clearing browser cookies does not clear the file. Keys are HMAC hashes using the private `app_key`; raw emails and IP addresses are not written there. Expired entries are removed during use, the file is bounded to 2,000 live keys, and a full store refuses new attempts rather than evicting active limits. File/storage failure fails closed.

This is a modest single-server defence. It is not a distributed rate limiter, and shared campus IPs can reach the IP limit. Public hosting needs an operational plan for abuse and legitimate lockouts. The implementation uses the direct remote address, not an untrusted forwarded-address header. Reverse-proxy deployments require deliberate trusted-proxy/TLS configuration rather than blindly trusting client headers.

Failed login attempts and committee event/attendance changes produce monthly `storage/audit-YYYY-MM.jsonl` files. Entries contain a timestamp, controlled action/outcome identifiers and relevant numeric internal IDs. They omit passwords, tokens, full forms, email addresses, phone numbers and student numbers. Internal IDs can still relate to a person, so access remains restricted. `storage/php-error.log` is separate private diagnostic output. Audit files split by month automatically; retention and diagnostic-log rotation are operator duties described in [operations.md](operations.md). Apache logs may also contain request addresses; apply the same access/retention review to server logs.

## Local HTTP and hosted HTTPS

`environment => 'local'` and `https_required => false` permit HTTP only from loopback. This lets XAMPP and `127.0.0.1` development sessions work on the same computer. Remote or hosted requests require HTTPS, including a hosted classroom demonstration. Configure real TLS, use `https_required => true`, and use an environment value other than `local` on hosting. Secure cookies follow verified HTTPS at the PHP web-server interface. Do not expose the PHP development server or XAMPP administration interfaces publicly. Supply a real mailbox, protect the application account and remove demo credentials/data from a real member service.

## Collection and member requests

The application collects first/last name, student ID, email, optional phone, password hash, membership category, join date/status, event registration and attendance information. These support membership identity, login, contact and event administration. Public committee cards expose only display names, roles and terms. Members can access and change their own permitted profile details; current committee members see only the participant information needed for attendance management.

The registration privacy notice explains collection and the correction/deletion contact route. Dashboard edits support direct profile correction. Other correction or deletion requests go to the configured club email; initially that address is a labelled example, so configure a real contact before collecting real data. An operator should verify a requester's identity proportionately, identify linked committee/event/registration records, decide a lawful and appropriate retention/disposal response, and communicate the outcome. Do not ask a member to send a password.

There is no last-active or inactive-since field. `join_date` does **not** establish inactivity. Periodically review account status and documented club records manually; do not run an automatic two-year purge based on join date. Before disposal, check committee authorship and event/registration foreign keys. The default schema restricts deletion, and there is no hard-delete interface. Consider a reviewed minimal anonymisation or other approved process where historical records must remain; removing profile identifiers alone does not prove linked data is anonymous. Plan any database changes, backups and validation explicitly. Setup and tests do not perform destructive retention cleanup.

## Risk register

| Risk | Impact | Mitigation and remaining work | Owner |
| --- | --- | --- | --- |
| Wrong document root or exposed backup | Private data/credentials disclosed | Public-only root, global parent deny rule, HTTP boundary checks; operator verifies actual host | Operator |
| Public use of known demo accounts | Unauthorised access | Fictional local demo only; remove/replace credentials and data before real use | Operator |
| Brute force or shared-IP lockout | Account compromise or lost access | Bcrypt and bounded file throttle; monitor limits; single-server and shared-IP limitations remain | Operator |
| Simultaneous last-place requests | Oversubscribed event | InnoDB event lock for every capacity path and unique pair; re-run parallel connection test after domain changes | Developer |
| Changed/expired committee access | Unauthorised management | Recheck active status/current term server-side; test date boundaries and direct forged requests | Developer |
| Missing backup or failed restore | Lost membership/history | Weekly backups, four-week retention and documented separate-database restore verification | Operator |
| Excess retention or incorrect disposal | Unnecessary personal-data exposure/history loss | Manual review; no join-date inactivity inference; inspect foreign-key links; no automatic purge | Club contact/operator |
| Old runtime or misconfigured TLS | Vulnerabilities/session exposure | Keep supported PHP/Apache/database patched; enforce HTTPS for hosting; actual installed versions are listed without claiming current support | Operator |
| Unverified browser/accessibility behaviour | People cannot complete core tasks | Keyboard, no-JavaScript and responsive tests; record actual browser coverage and unresolved cases | Tester |
| Forgotten password or mailbox issue | Member unable to recover access | Recovery is outside this baseline; publish a functioning contact process and plan a reviewed extension | Club contact |

Source guidance was checked during implementation. Sources describe technical mechanisms; they do not attest that this application is certified or independently audited.
