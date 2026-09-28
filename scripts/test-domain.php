<?php
declare(strict_types=1);

// This test ADDS fictional fixtures only to a separately imported test database.
// It never drops a database or removes existing records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!preg_match('/^clubconnect_test_[a-z0-9_]+$/D', getenv('CLUBCONNECT_TEST_DB') ?: '')) {
    fwrite(STDERR, "Set CLUBCONNECT_TEST_DB to an isolated clubconnect_test_* database first.\n");
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';

if (($argv[1] ?? '') === '--expired-session') {
    $s = db()->prepare('SELECT member_id FROM members WHERE email = ?');
    $s->execute(['alex.member@example.test']);
    $id = (int) $s->fetchColumn();
    if (!$id) { throw new RuntimeException('Seed the isolated test database first.'); }
    session_name('clubconnect_session');
    session_start();
    $_SESSION = ['member_id' => $id, 'last_activity' => time() - 1900, 'csrf' => bin2hex(random_bytes(32))];
    echo session_id();
    session_write_close();
    exit;
}

if (($argv[1] ?? '') === '--worker') {
    $start = (float) $argv[4];
    while (microtime(true) < $start) { usleep(1000); }
    try {
        register_for_event((int) $argv[2], (int) $argv[3]);
        echo 'registered';
    } catch (DomainException $error) {
        echo 'rejected';
    }
    exit;
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
}
function rejects(callable $action, string $message): void
{
    try { $action(); } catch (DomainException $error) { check(true, $message); return; }
    throw new RuntimeException('FAIL: ' . $message);
}
function count_occupied(int $eventId): int
{
    $s = db()->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND attendance_status IN ('registered', 'attended')");
    $s->execute([$eventId]);
    return (int) $s->fetchColumn();
}

$run = bin2hex(random_bytes(5));
$members = [];
$stmt = db()->prepare("INSERT INTO members (first_name,last_name,student_id,email,phone,password_hash,membership_type,join_date,status) VALUES (?,?,?,?,NULL,?,'standard',?,'active')");
for ($i = 0; $i < 4; $i++) {
    $stmt->execute(['Test', 'Member' . $i, 'T' . $run . $i, 'test.' . $run . $i . '@example.test', password_hash('AcceptanceTest!2026', PASSWORD_BCRYPT, ['cost' => 12]), club_now()->format('Y-m-d')]);
    $members[] = (int) db()->lastInsertId();
}
$today = club_now();
$stmt = db()->prepare('INSERT INTO committee (member_id, role, term_start, term_end) VALUES (?, ?, ?, ?)');
$stmt->execute([$members[0], 'Test current chair', $today->modify('-1 day')->format('Y-m-d'), $today->modify('+1 year')->format('Y-m-d')]);
$committee = (int) db()->lastInsertId();
$stmt->execute([$members[1], 'Test expired chair', $today->modify('-1 year')->format('Y-m-d'), $today->modify('-1 day')->format('Y-m-d')]);
$expired = (int) db()->lastInsertId();
$base = ['event_name' => 'Acceptance ' . $run, 'description' => 'Fictional test event <script>alert(1)</script>', 'event_date' => $today->modify('+3 days')->format('Y-m-d'), 'start_time' => '15:00', 'end_time' => '16:00', 'location' => 'Test room', 'capacity' => '2'];
save_event($base, null, $committee, $members[0]);
$stmt = db()->prepare('SELECT event_id FROM events WHERE event_name = ?');
$stmt->execute([$base['event_name']]);
$eventId = (int) $stmt->fetchColumn();
check($eventId > 0, 'current committee creates event');
rejects(fn () => save_event($base, null, $expired, $members[1]), 'expired committee cannot create event');
rejects(fn () => save_event($base, null, $committee, $members[2]), 'forged committee identity rejected');
register_for_event($eventId, $members[1]);
register_for_event($eventId, $members[1]);
check(count_occupied($eventId) === 1, 'repeat RSVP occupies one place');
$stmt = db()->prepare('SELECT registration_id FROM registrations WHERE event_id = ? AND member_id = ?');
$stmt->execute([$eventId, $members[1]]);
$originalRegistration = (int) $stmt->fetchColumn();
register_for_event($eventId, $members[2]);
rejects(fn () => register_for_event($eventId, $members[3]), 'full event rejects additional registration');
$lower = $base; $lower['capacity'] = '1';
rejects(fn () => save_event($lower, $eventId, $committee, $members[0]), 'capacity cannot fall below occupied places');
rejects(fn () => mark_attended($eventId, $originalRegistration, $committee, $members[0]), 'attendance cannot be recorded before event starts');
cancel_registration($eventId, $members[1]);
check(count_occupied($eventId) === 1, 'cancellation releases a place');
register_for_event($eventId, $members[1]);
$stmt->execute([$eventId, $members[1]]);
check((int) $stmt->fetchColumn() === $originalRegistration, 'rejoining reuses original registration row');
$changed = $base; $changed['event_name'] .= ' edited';
save_event($changed, $eventId, $committee, $members[0]);
check((int) find_event($eventId)['created_by'] === $committee, 'editing preserves original event creator');

$s = db()->prepare("UPDATE members SET status = 'inactive' WHERE member_id = ?");
$s->execute([$members[3]]);
rejects(fn () => register_for_event($eventId, $members[3]), 'inactive member cannot RSVP');
$_SESSION = ['member_id' => $members[3]];
check(current_member() === null, 'existing session cannot retain inactive-account access');
$s = db()->prepare("UPDATE members SET status = 'active' WHERE member_id = ?");
$s->execute([$members[3]]);

$s = db()->prepare('UPDATE events SET event_date = ? WHERE event_id = ?');
$s->execute([$today->modify('-1 day')->format('Y-m-d'), $eventId]);
rejects(fn () => register_for_event($eventId, $members[3]), 'past event rejects RSVP');
rejects(fn () => cancel_registration($eventId, $members[1]), 'past event rejects cancellation');
mark_attended($eventId, $originalRegistration, $committee, $members[0]);
$s = db()->prepare('SELECT attendance_status FROM registrations WHERE registration_id = ?');
$s->execute([$originalRegistration]);
check($s->fetchColumn() === 'attended', 'current committee records attendance after event starts');
check(count_occupied($eventId) === 2, 'attended records still occupy places');
rejects(fn () => mark_attended($eventId, $originalRegistration, $expired, $members[1]), 'expired committee cannot mark attendance');
$s = db()->prepare("INSERT INTO registrations (event_id,member_id,attendance_status) VALUES (?,?,'cancelled')");
$s->execute([$eventId, $members[3]]);
$cancelledId = (int) db()->lastInsertId();
rejects(fn () => mark_attended($eventId, $cancelledId, $committee, $members[0]), 'cancelled registration cannot be marked attended');
rejects(fn () => mark_attended($eventId, 2147483647, $committee, $members[0]), 'missing registration cannot be marked attended');
$invalid = $base; $invalid['event_date'] = '2026-02-30';
check(isset(validate_event($invalid)['event_date']), 'impossible calendar date rejected');
$invalid = $base; $invalid['capacity'] = '-1'; $invalid['end_time'] = '14:00';
$invalidErrors = validate_event($invalid);
check(isset($invalidErrors['capacity'], $invalidErrors['end_time']), 'invalid capacity and overnight times rejected');

$race = $base; $race['event_name'] = 'Race ' . $run; $race['capacity'] = '1';
save_event($race, null, $committee, $members[0]);
$s = db()->prepare('SELECT event_id FROM events WHERE event_name = ?');
$s->execute([$race['event_name']]);
$raceId = (int) $s->fetchColumn();
$start = microtime(true) + 2;
$workers = [];
foreach ([$members[2], $members[3]] as $id) {
    $pipes = [];
    $proc = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $raceId, (string) $id, (string) $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) { throw new RuntimeException('Cannot start race worker'); }
    fclose($pipes[0]);
    $workers[] = [$proc, $pipes];
}
$outcomes = [];
foreach ($workers as [$proc, $pipes]) {
    $outcomes[] = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($proc) === 0 && $err === '', 'concurrent PHP worker completed without errors');
}
sort($outcomes);
check($outcomes === ['registered', 'rejected'] && count_occupied($raceId) === 1, 'simultaneous independent connections cannot exceed final place');

try {
    $s = db()->prepare("INSERT INTO registrations (event_id,member_id,attendance_status) VALUES (?,?,'registered')");
    $s->execute([$eventId, $members[1]]);
    check(false, 'unique event/member database constraint');
} catch (PDOException $error) {
    check($error->getCode() === '23000', 'unique event/member database constraint enforced');
}
$_SESSION = []; $_POST = [];
rejects(fn () => verify_csrf(), 'missing CSRF token rejected');
csrf_field(); $_POST['csrf_token'] = 'wrong';
rejects(fn () => verify_csrf(), 'invalid CSRF token rejected');
$_POST['csrf_token'] = $_SESSION['csrf']; verify_csrf();
check(true, 'matching CSRF token accepted');
check(e('<script>" &') === '&lt;script&gt;&quot; &amp;', 'untrusted HTML is encoded');
check(count(glob(PROJECT_ROOT . '/public/*.php')) === 8, 'exactly eight public PHP pages');
$tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); sort($tables);
check($tables === ['committee', 'events', 'members', 'registrations'], 'exactly four application tables');
echo "Completed $checks checks. Fictional fixtures retained in the isolated test database.\n";
