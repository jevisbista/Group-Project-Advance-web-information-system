<?php
declare(strict_types=1);

/** Event dates and times are wall times in the configured club timezone. */
function event_is_future(array $event): bool
{
    return $event['event_date'] . ' ' . $event['start_time'] > club_now()->format('Y-m-d H:i:s');
}

function event_when(array $event): string
{
    $date = new DateTimeImmutable($event['event_date'] . ' ' . $event['start_time'], new DateTimeZone(config('timezone')));
    $end = new DateTimeImmutable($event['event_date'] . ' ' . $event['end_time'], new DateTimeZone(config('timezone')));
    return $date->format('D j M Y · g:ia') . ' to ' . $end->format('g:ia');
}

function event_image(array $event): array
{
    $name = mb_strtolower($event['event_name'] ?? '');
    $kind = 'community';
    $alt = 'Stock photograph of friends talking around a cafe table';
    if (preg_match('/workshop|skill|code|design|study/', $name)) {
        $kind = 'workshop'; $alt = 'Stock photograph of students working together with laptops';
    } elseif (preg_match('/game|quiz|board/', $name)) {
        $kind = 'games'; $alt = 'Stock photograph of friends playing a board game';
    } elseif (preg_match('/volunteer|garden|clean|service/', $name)) {
        $kind = 'volunteer'; $alt = 'Stock photograph of volunteers gardening together';
    }
    if (!is_file(PROJECT_ROOT . '/public/assets/images/' . $kind . '.jpg')) {
        $kind = 'community'; $alt = 'Stock photograph of friends talking around a cafe table';
    }
    return ['src' => url('assets/images/' . $kind . '.jpg'), 'alt' => $alt];
}

function get_events(bool $past = false, string $search = '', int $limit = 0): array
{
    $member = current_member();
    $now = club_now();
    $condition = $past
        ? '(ev.event_date < :day OR (ev.event_date = :same_day AND ev.start_time <= :clock))'
        : '(ev.event_date > :day OR (ev.event_date = :same_day AND ev.start_time > :clock))';
    $sql = "SELECT ev.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = ev.event_id AND r.attendance_status IN ('registered','attended')) AS occupied,
        own.attendance_status FROM events ev LEFT JOIN registrations own ON own.event_id = ev.event_id AND own.member_id = :member WHERE " . $condition;
    $params = ['member' => $member['member_id'] ?? 0, 'day' => $now->format('Y-m-d'), 'same_day' => $now->format('Y-m-d'), 'clock' => $now->format('H:i:s')];
    if ($search !== '') {
        // Escape LIKE wildcards so searching for % or _ treats them as ordinary text.
        $search = '%' . strtr($search, ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
        $sql .= " AND (ev.event_name LIKE :title ESCAPE '=' OR ev.location LIKE :location ESCAPE '=')";
        $params['title'] = $search; $params['location'] = $search;
    }
    $sql .= $past ? ' ORDER BY ev.event_date DESC, ev.start_time DESC, ev.event_id DESC' : ' ORDER BY ev.event_date, ev.start_time, ev.event_id';
    if ($limit > 0) { $sql .= ' LIMIT ' . min($limit, 100); }
    $stmt = db()->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_event(int $eventId): ?array
{
    $stmt = db()->prepare("SELECT ev.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = ev.event_id AND r.attendance_status IN ('registered','attended')) AS occupied FROM events ev WHERE ev.event_id = ?");
    $stmt->execute([$eventId]);
    return $stmt->fetch() ?: null;
}

function event_registration(int $eventId, int $memberId): ?array
{
    $stmt = db()->prepare('SELECT * FROM registrations WHERE event_id = ? AND member_id = ?');
    $stmt->execute([$eventId, $memberId]);
    return $stmt->fetch() ?: null;
}

/** Call only inside a transaction, before reading/changing any capacity-affecting row. */
function lock_event(int $eventId): array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE event_id = ? FOR UPDATE');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) { throw new DomainException('That event could not be found.'); }
    return $event;
}

function event_occupied(int $eventId): int
{
    // Locking read uses the latest committed records, even under REPEATABLE READ.
    $stmt = db()->prepare("SELECT registration_id FROM registrations WHERE event_id = ? AND attendance_status IN ('registered','attended') FOR UPDATE");
    $stmt->execute([$eventId]);
    return count($stmt->fetchAll());
}

function assert_active_event_member(int $memberId): void
{
    $stmt = db()->prepare('SELECT status FROM members WHERE member_id = ? FOR UPDATE');
    $stmt->execute([$memberId]);
    if ($stmt->fetchColumn() !== 'active') { throw new DomainException('An active member account is required.'); }
}

function assert_event_committee(int $committeeId, int $memberId): void
{
    assert_active_event_member($memberId);
    $today = club_now()->format('Y-m-d');
    $stmt = db()->prepare('SELECT committee_id FROM committee WHERE committee_id = ? AND member_id = ? AND term_start <= ? AND (term_end IS NULL OR term_end >= ?) FOR UPDATE');
    $stmt->execute([$committeeId, $memberId, $today, $today]);
    if (!$stmt->fetchColumn()) { throw new DomainException('A current committee appointment is required for this action.'); }
}

function register_for_event(int $eventId, int $memberId): void
{
    $pdo = db(); $pdo->beginTransaction();
    try {
        $event = lock_event($eventId);
        assert_active_event_member($memberId);
        if (!event_is_future($event)) { throw new DomainException('Registration closes when an event starts. Please choose an upcoming event.'); }
        $registration = event_registration($eventId, $memberId);
        if ($registration && $registration['attendance_status'] !== 'cancelled') {
            $pdo->commit(); return; // Double submission is harmless.
        }
        if (event_occupied($eventId) >= (int) $event['capacity']) { throw new DomainException('This event is full. Please check again if a place becomes available.'); }
        if ($registration) {
            $stmt = $pdo->prepare("UPDATE registrations SET attendance_status = 'registered', registration_date = CURRENT_TIMESTAMP WHERE registration_id = ?");
            $stmt->execute([$registration['registration_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO registrations (event_id, member_id, attendance_status) VALUES (?, ?, 'registered')");
            $stmt->execute([$eventId, $memberId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}

function cancel_registration(int $eventId, int $memberId): void
{
    $pdo = db(); $pdo->beginTransaction();
    try {
        $event = lock_event($eventId);
        assert_active_event_member($memberId);
        if (!event_is_future($event)) { throw new DomainException('You can only cancel before an event starts.'); }
        $registration = event_registration($eventId, $memberId);
        if (!$registration) { throw new DomainException('You do not have a registration for that event.'); }
        $stmt = $pdo->prepare("UPDATE registrations SET attendance_status = 'cancelled' WHERE event_id = ? AND member_id = ?");
        $stmt->execute([$eventId, $memberId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}

function event_values(): array
{
    $values = [];
    foreach (['event_name', 'description', 'event_date', 'start_time', 'end_time', 'location', 'capacity'] as $field) { $values[$field] = trim(post($field)); }
    return $values;
}

function validate_event(array $values): array
{
    $errors = [];
    foreach (['event_name' => ['Event name', 100], 'description' => ['Description', 5000], 'location' => ['Location', 150]] as $field => [$label, $max]) {
        $text = (string) ($values[$field] ?? '');
        if (trim($text) === '' || mb_strlen($text) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text)) { $errors[$field] = $label . ' must contain 1 to ' . $max . ' characters without control characters.'; }
    }
    $date = (string) ($values['event_date'] ?? '');
    $parsed = preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(config('timezone')))
        : false;
    if (!$parsed || $parsed->format('Y-m-d') !== $date || (int) substr($date, 0, 4) < 1000) { $errors['event_date'] = 'Enter a valid date.'; }
    foreach (['start_time' => 'Start time', 'end_time' => 'End time'] as $field => $label) {
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', (string) ($values[$field] ?? ''))) { $errors[$field] = $label . ' must be a valid time.'; }
        if (!isset($errors['event_date']) && !isset($errors[$field])) {
            $wallTime = $date . ' ' . $values[$field];
            $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $wallTime, new DateTimeZone(config('timezone')));
            if (!$time || $time->format('Y-m-d H:i') !== $wallTime) { $errors[$field] = $label . ' does not exist on that date because the club timezone changes for daylight saving.'; }
        }
    }
    if (!isset($errors['start_time']) && !isset($errors['end_time']) && ($values['end_time'] ?? '') <= ($values['start_time'] ?? '')) { $errors['end_time'] = 'End time must be after start time on the same day.'; }
    if (!positive_id($values['capacity'] ?? null)) { $errors['capacity'] = 'Capacity must be a positive whole number (up to 2,147,483,647).'; }
    return $errors;
}

function save_event(array $values, ?int $eventId, int $committeeId, int $memberId): int
{
    $errors = validate_event($values);
    if ($errors) { throw new DomainException(implode(' ', $errors)); }
    $pdo = db(); $pdo->beginTransaction();
    try {
        if ($eventId) { lock_event($eventId); }
        assert_event_committee($committeeId, $memberId);
        if ($eventId && event_occupied($eventId) > (int) $values['capacity']) { throw new DomainException('Capacity cannot be lower than the number of occupied places.'); }
        $params = array_map(fn ($key) => $values[$key], ['event_name', 'description', 'event_date', 'start_time', 'end_time', 'location', 'capacity']);
        if ($eventId) {
            $stmt = $pdo->prepare('UPDATE events SET event_name = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, location = ?, capacity = ? WHERE event_id = ?');
            $stmt->execute([...$params, $eventId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO events (event_name, description, event_date, start_time, end_time, location, capacity, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([...$params, $committeeId]);
            $eventId = (int) $pdo->lastInsertId();
        }
        audit('event_save', 'success', ['event_id' => $eventId, 'committee_id' => $committeeId, 'member_id' => $memberId]);
        $pdo->commit();
        return $eventId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        audit('event_save', 'rejected', ['event_id' => $eventId ?? 0, 'committee_id' => $committeeId, 'member_id' => $memberId]);
        throw $error;
    }
}

function mark_attended(int $eventId, int $registrationId, int $committeeId, int $memberId): void
{
    $pdo = db(); $pdo->beginTransaction();
    try {
        $event = lock_event($eventId);
        assert_event_committee($committeeId, $memberId);
        if (event_is_future($event)) { throw new DomainException('Attendance can only be recorded once the event has started.'); }
        $stmt = $pdo->prepare('SELECT attendance_status FROM registrations WHERE registration_id = ? AND event_id = ? FOR UPDATE');
        $stmt->execute([$registrationId, $eventId]);
        $status = $stmt->fetchColumn();
        if (!$status || $status === 'cancelled') { throw new DomainException('Only an existing, non-cancelled registration can be marked attended.'); }
        $stmt = $pdo->prepare("UPDATE registrations SET attendance_status = 'attended' WHERE registration_id = ? AND event_id = ?");
        $stmt->execute([$registrationId, $eventId]);
        audit('attendance_update', 'success', ['event_id' => $eventId, 'registration_id' => $registrationId, 'member_id' => $memberId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        audit('attendance_update', 'rejected', ['event_id' => $eventId, 'registration_id' => $registrationId, 'member_id' => $memberId]);
        throw $error;
    }
}
