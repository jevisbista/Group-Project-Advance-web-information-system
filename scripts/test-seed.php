<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !preg_match('/^clubconnect_test_[a-z0-9_]+$/D', getenv('CLUBCONNECT_TEST_DB') ?: '')) {
    fwrite(STDERR, "Run only in an isolated CLUBCONNECT_TEST_DB database.\n"); exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';
function run_seed_check(): void
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__ . '/seed.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || !str_contains($out, '"members":0,"committee":0,"events":0,"registrations":0')) {
        throw new RuntimeException('Seed rerun changed row counts or failed: ' . $err);
    }
}
run_seed_check(); // Establish the manifest for a previously seeded database.
$member = db()->query("SELECT * FROM members WHERE email = 'alex.member@example.test'")->fetch();
$event = db()->query("SELECT * FROM events WHERE event_name = 'Demo: Creative skills workshop' ORDER BY event_id LIMIT 1")->fetch();
if (!$member || !$event) { throw new RuntimeException('Seed the database first.'); }
$memberUpdate = db()->prepare('UPDATE members SET email = ?, student_id = ? WHERE member_id = ?');
$eventUpdate = db()->prepare('UPDATE events SET event_name = ?, location = ? WHERE event_id = ?');
try {
    $memberUpdate->execute(['changed.seed@example.test', 'CHANGEDSEED', $member['member_id']]);
    $eventUpdate->execute(['Changed seed workshop', 'Changed seed room', $event['event_id']]);
    run_seed_check();
    $stmt = db()->prepare('SELECT email,student_id,password_hash FROM members WHERE member_id = ?'); $stmt->execute([$member['member_id']]); $after = $stmt->fetch();
    if ($after['email'] !== 'changed.seed@example.test' || $after['student_id'] !== 'CHANGEDSEED' || $after['password_hash'] !== $member['password_hash']) { throw new RuntimeException('Seed overwrote edited member details.'); }
    if (find_event((int) $event['event_id'])['event_name'] !== 'Changed seed workshop') { throw new RuntimeException('Seed overwrote edited event details.'); }
    echo "PASS: seed reruns add zero rows, preserve edited identities/event details and retain password hashes.\n";
} finally {
    // Restore only the deliberately edited fixture fields; do not delete any data.
    $memberUpdate->execute([$member['email'], $member['student_id'], $member['member_id']]);
    $eventUpdate->execute([$event['event_name'], $event['location'], $event['event_id']]);
}
