<?php
declare(strict_types=1);

// Run locally with PHP CLI, never through the website.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

if (!config('demo_mode')) {
    fwrite(STDERR, "Demo mode must be enabled in private configuration before seeding.\n");
    exit(1);
}

$pdo = db();
$today = club_now();
$password = 'ClubConnectDemo!2026';
$added = ['members' => 0, 'committee' => 0, 'events' => 0, 'registrations' => 0];
$manifestPath = STORAGE_DIR . '/demo-seed-' . hash('sha256', config('db')['name']) . '.json';
$manifestLock = fopen($manifestPath . '.lock', 'c+');
if (!$manifestLock || !flock($manifestLock, LOCK_EX)) { throw new RuntimeException('Cannot lock the private demo seed manifest.'); }
if (is_file($manifestPath . '.tmp')) {
    throw new RuntimeException('A pending private demo seed manifest exists. A previous seed may have stopped around its database commit, so this run made no changes. Preserve both manifest files and the database. Before retrying, have the maintainer compare every ID and relationship in the .tmp file with the database: if all matching rows committed, promote .tmp to the matching .json manifest; if they did not, restore a matching database-and-manifest backup. Do not simply delete the pending file or reseed over it.');
}
$manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : ['version' => 1, 'members' => [], 'committee' => [], 'events' => [], 'registrations' => []];
if (($manifest['version'] ?? null) !== 1) { throw new RuntimeException('The demo seed manifest is invalid. Restore its matching backup before continuing.'); }
foreach (['members', 'committee', 'events', 'registrations'] as $table) {
    if (!isset($manifest[$table]) || !is_array($manifest[$table])) { throw new RuntimeException('The demo seed manifest is incomplete.'); }
}

/** A private per-database ID manifest preserves demo records even after they are edited. */
function seeded_record(array $manifest, string $table, string $key): ?array
{
    $primary = ['members' => 'member_id', 'committee' => 'committee_id', 'events' => 'event_id', 'registrations' => 'registration_id'];
    if (!isset($manifest[$table][$key])) { return null; }
    $id = positive_id($manifest[$table][$key]);
    if (!$id) { throw new RuntimeException('The demo seed manifest contains an invalid identifier.'); }
    $stmt = db()->prepare('SELECT * FROM ' . $table . ' WHERE ' . $primary[$table] . ' = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) { throw new RuntimeException('A previously seeded record is missing. Restore the matching database and seed manifest; this script will not recreate or replace it.'); }
    return $record;
}

$pdo->beginTransaction();
try {
    $members = [];
    foreach ([
        ['alex', 'Alex', 'Morgan', 'CCDEMO001', 'alex.member@example.test', 'active'],
        ['sam', 'Sam', 'Rivera', 'CCDEMO002', 'sam.committee@example.test', 'active'],
        ['taylor', 'Taylor', 'Chen', 'CCDEMO003', 'taylor.expired@example.test', 'active'],
        ['inactive', 'Jordan', 'Lee', 'CCDEMO004', 'inactive.member@example.test', 'inactive'],
    ] as [$key, $first, $last, $student, $email, $status]) {
        $saved = seeded_record($manifest, 'members', $key);
        if ($saved) { $members[$key] = (int) $saved['member_id']; continue; }
        $stmt = $pdo->prepare('SELECT member_id, student_id FROM members WHERE email = ?');
        $stmt->execute([$email]); $existing = $stmt->fetch();
        if ($existing) {
            if ($existing['student_id'] !== $student) { throw new RuntimeException('A reserved demo email is already used by another record; no changes were made.'); }
            $members[$key] = (int) $existing['member_id'];
            $manifest['members'][$key] = $members[$key];
            continue;
        }
        $stmt = $pdo->prepare("INSERT INTO members (first_name,last_name,student_id,email,phone,password_hash,membership_type,join_date,status) VALUES (?,?,?,?,NULL,?,'standard',?,?)");
        $stmt->execute([$first, $last, $student, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $today->modify('-1 month')->format('Y-m-d'), $status]);
        $members[$key] = (int) $pdo->lastInsertId(); $added['members']++;
        $manifest['members'][$key] = $members[$key];
    }
    $committee = [];
    foreach ([
        ['sam', 'Demo club coordinator', '-1 month', '+1 year'],
        ['taylor', 'Demo former events lead', '-2 years', '-1 year'],
    ] as [$key, $role, $start, $end]) {
        $saved = seeded_record($manifest, 'committee', $key);
        if ($saved) {
            if ((int) $saved['member_id'] !== $members[$key]) { throw new RuntimeException('A seeded committee relationship has changed. Review the manifest before proceeding.'); }
            $committee[$key] = (int) $saved['committee_id']; continue;
        }
        $stmt = $pdo->prepare('SELECT committee_id FROM committee WHERE member_id = ? AND role = ? ORDER BY committee_id LIMIT 1');
        $stmt->execute([$members[$key], $role]);
        $existing = $stmt->fetchColumn();
        if ($existing) { $committee[$key] = (int) $existing; $manifest['committee'][$key] = $committee[$key]; continue; }
        $stmt = $pdo->prepare('INSERT INTO committee (member_id, role, term_start, term_end) VALUES (?, ?, ?, ?)');
        $stmt->execute([$members[$key], $role, $today->modify($start)->format('Y-m-d'), $today->modify($end)->format('Y-m-d')]);
        $committee[$key] = (int) $pdo->lastInsertId(); $added['committee']++;
        $manifest['committee'][$key] = $committee[$key];
    }
    $events = [];
    $definitions = [
        ['workshop', 'Demo: Creative skills workshop', 'Try a small design challenge with other students, share ideas and leave with something you made. Bring a notebook; no experience is needed. This is a fictional demo event.', '+7 days', '14:00', '16:00', 'Demo student hub · Workshop room', 24],
        ['community', 'Demo: Meet your club community', 'A relaxed welcome session with conversation starters and a chance to suggest our next club activity. Come on your own or bring a friend. This is a fictional demo event.', '+12 days', '16:00', '17:30', 'Demo student hub · Common room', 30],
        ['volunteer', 'Demo: Community garden volunteering', 'Work together on a small community garden project and meet students who enjoy making a practical difference. Wear comfortable clothes. This is a fictional demo event.', '+18 days', '10:00', '12:00', 'Demo community garden', 16],
        ['full', 'Demo: Board games afternoon', 'An easygoing afternoon of short board games and friendly introductions. This small fictional demo event is seeded at capacity to demonstrate the full-event state.', '+9 days', '13:00', '15:00', 'Demo student hub · Games corner', 2],
        ['past', 'Demo: Welcome and ideas evening', 'A fictional previous gathering where members shared activity ideas and learned how the club works. Used to demonstrate attendance and registration history.', '-7 days', '17:00', '18:30', 'Demo student hub · Common room', 25],
    ];
    foreach ($definitions as [$key, $name, $description, $relative, $start, $end, $location, $capacity]) {
        $saved = seeded_record($manifest, 'events', $key);
        if ($saved) {
            if ((int) $saved['created_by'] !== $committee['sam']) { throw new RuntimeException('A seeded event ownership relationship has changed. Review the manifest before proceeding.'); }
            $events[$key] = (int) $saved['event_id']; continue;
        }
        $stmt = $pdo->prepare('SELECT event_id FROM events WHERE event_name = ? AND created_by = ? AND location = ? ORDER BY event_id LIMIT 1');
        $stmt->execute([$name, $committee['sam'], $location]);
        $existing = $stmt->fetchColumn();
        if ($existing) { $events[$key] = (int) $existing; $manifest['events'][$key] = $events[$key]; continue; }
        $stmt = $pdo->prepare('INSERT INTO events (event_name,description,event_date,start_time,end_time,location,capacity,created_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$name, $description, $today->modify($relative)->format('Y-m-d'), $start, $end, $location, $capacity, $committee['sam']]);
        $events[$key] = (int) $pdo->lastInsertId(); $added['events']++;
        $manifest['events'][$key] = $events[$key];
    }
    foreach ([['full','alex','registered'], ['full','taylor','registered'], ['workshop','sam','registered'], ['past','alex','attended'], ['past','sam','registered'], ['past','taylor','cancelled']] as [$event, $member, $status]) {
        $key = $event . '_' . $member;
        $saved = seeded_record($manifest, 'registrations', $key);
        if ($saved) {
            if ((int) $saved['event_id'] !== $events[$event] || (int) $saved['member_id'] !== $members[$member]) { throw new RuntimeException('A seeded registration relationship has changed. Review the manifest before proceeding.'); }
            continue;
        }
        // Follow the same event-row lock as the website, even when a seed is rerun.
        $lockedEvent = lock_event($events[$event]);
        $stmt = $pdo->prepare('SELECT registration_id FROM registrations WHERE event_id = ? AND member_id = ?');
        $stmt->execute([$events[$event], $members[$member]]);
        $existing = $stmt->fetchColumn();
        if ($existing) { $manifest['registrations'][$key] = (int) $existing; continue; }
        if ($status !== 'cancelled' && event_occupied($events[$event]) >= (int) $lockedEvent['capacity']) { continue; }
        $stmt = $pdo->prepare('INSERT INTO registrations (event_id,member_id,attendance_status) VALUES (?,?,?)');
        $stmt->execute([$events[$event], $members[$member], $status]); $added['registrations']++;
        $manifest['registrations'][$key] = (int) $pdo->lastInsertId();
    }
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($manifestPath . '.tmp', $manifestJson, LOCK_EX) !== strlen($manifestJson)) { throw new RuntimeException('Cannot save the private seed manifest.'); }
    $pdo->commit();
    if (!rename($manifestPath . '.tmp', $manifestPath)) { throw new RuntimeException('Database seeding succeeded but the manifest could not be finalised. Preserve the private temporary manifest before retrying.'); }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $error;
} finally {
    flock($manifestLock, LOCK_UN);
    fclose($manifestLock);
}
echo "Demo seed complete. Added: " . json_encode($added, JSON_THROW_ON_ERROR) . PHP_EOL;
echo "Existing matching demo records, passwords, dates and registrations were preserved. No unrelated data was removed.\n";
echo "Keep the private storage/demo-seed-*.json manifest with this demo database and include it in backups.\n";
echo "New demo account password: $password\n";
echo "Member: alex.member@example.test\nCurrent committee: sam.committee@example.test\nExpired committee (member only): taylor.expired@example.test\nInactive (login rejected): inactive.member@example.test\n";
