<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$member = current_member();
$committee = current_committee();
$past = get('view') === 'past';
$search = mb_substr(trim(get('q')), 0, 150);
$errors = [];
$editingId = $committee ? positive_id(get('edit')) : null;
$participantsId = $committee ? positive_id(get('participants')) : null;
$showEditor = $committee && (get('add') === '1' || $editingId);
$values = ['event_name' => '', 'description' => '', 'event_date' => club_now()->modify('+1 week')->format('Y-m-d'), 'start_time' => '14:00', 'end_time' => '16:00', 'location' => '', 'capacity' => '20'];
if ($editingId) {
    $editing = find_event($editingId);
    if ($editing) { foreach ($values as $field => $default) { $values[$field] = (string) $editing[$field]; } $values['start_time'] = substr($values['start_time'], 0, 5); $values['end_time'] = substr($values['end_time'], 0, 5); }
    else { $errors['form'] = 'That event could not be found.'; $editingId = null; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $committee = require_committee();
    $member = require_member();
    try {
        verify_csrf();
        if (post('action') === 'save_event') {
            $values = event_values();
            $editingId = post('event_id') === '' ? null : positive_id(post('event_id'));
            $showEditor = true;
            if (post('event_id') !== '' && !$editingId) { throw new DomainException('The event identifier is invalid.'); }
            $errors = validate_event($values);
            if (!$errors) {
                $savedId = save_event($values, $editingId, (int) $committee['committee_id'], (int) $member['member_id']);
                flash($editingId ? 'Event details updated.' : 'Your event has been created.');
                $saved = find_event($savedId);
                redirect('events.php' . ($saved && !event_is_future($saved) ? '?view=past' : '') . '#event-' . $savedId);
            }
        } elseif (post('action') === 'mark_attended') {
            $eventId = positive_id(post('event_id'));
            $registrationId = positive_id(post('registration_id'));
            if (!$eventId || !$registrationId) { throw new DomainException('Choose a valid event and registration.'); }
            $participantsId = $eventId;
            mark_attended($eventId, $registrationId, (int) $committee['committee_id'], (int) $member['member_id']);
            flash('Attendance recorded.');
            redirect('events.php?view=past&participants=' . $eventId . '#participants');
        } else { throw new DomainException('That action is not available.'); }
    } catch (DomainException $error) { $errors['form'] = $error->getMessage(); }
}

$events = get_events($past, $search);
$participantEvent = $participantsId ? find_event($participantsId) : null;
$participants = [];
if ($participantEvent && $committee) {
    // Only current committee members receive names and email addresses needed to manage attendees.
    $stmt = db()->prepare("SELECT r.registration_id, r.attendance_status, m.first_name, m.last_name, m.email FROM registrations r JOIN members m ON m.member_id = r.member_id WHERE r.event_id = ? AND r.attendance_status IN ('registered','attended') ORDER BY m.first_name, m.last_name, r.registration_id");
    $stmt->execute([$participantsId]); $participants = $stmt->fetchAll();
}
render_header($past ? 'Past events' : 'Upcoming events', 'events', 'Find your next workshop, gathering or community activity.');
?>
<section class="section container">
    <div class="page-heading"><h1><?= $past ? 'Past events' : 'Upcoming events' ?></h1><p class="muted"><?= $past ? 'Look back at the activities that brought our club together.' : 'Learn something new, meet a friendly face and be part of what happens next.' ?></p>
        <div class="inline-actions"><a class="button button-secondary" href="<?= e(url($past ? 'events.php' : 'events.php?view=past')) ?>"><?= $past ? 'View upcoming events' : 'View past events' ?></a><?php if ($committee): ?><a class="button" href="<?= e(url('events.php?add=1#event-editor')) ?>">Add Event</a><?php endif; ?></div>
    </div>
    <?php error_summary($errors); ?>
    <?php if ($committee): ?>
        <details class="card" id="event-editor"<?= $showEditor ? ' open' : '' ?>>
            <summary><?= $editingId ? 'Edit event' : 'Create an event' ?> · committee tools</summary>
            <div class="card-body">
                <h2><?= $editingId ? 'Edit event details' : 'Bring members together' ?></h2><p class="muted">All dates and times use <?= e(config('timezone')) ?>. Events start and end on the same day. Existing registrations are kept when an event is edited.</p>
                <form method="post" action="<?= e(url('events.php' . ($past ? '?view=past' : ''))) ?>" class="form-grid">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_event"><input type="hidden" name="event_id" value="<?= $editingId ?: '' ?>">
                    <div class="field full-width"><label for="event_name">Event name</label><input id="event_name" name="event_name" maxlength="100" required value="<?= e($values['event_name']) ?>"<?= invalid_attrs('event_name', $errors) ?>><?= field_error('event_name', $errors) ?></div>
                    <div class="field full-width"><label for="description">Description</label><textarea id="description" name="description" maxlength="5000" rows="5" required<?= invalid_attrs('description', $errors) ?>><?= e($values['description']) ?></textarea><?= field_error('description', $errors) ?></div>
                    <div class="field"><label for="event_date">Event date</label><input id="event_date" name="event_date" type="date" required value="<?= e($values['event_date']) ?>"<?= invalid_attrs('event_date', $errors) ?>><?= field_error('event_date', $errors) ?></div>
                    <div class="field"><label for="capacity">Capacity</label><input id="capacity" name="capacity" type="number" min="1" max="2147483647" step="1" required value="<?= e($values['capacity']) ?>"<?= invalid_attrs('capacity', $errors) ?>><?= field_error('capacity', $errors) ?></div>
                    <div class="field"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" required value="<?= e($values['start_time']) ?>"<?= invalid_attrs('start_time', $errors) ?>><?= field_error('start_time', $errors) ?></div>
                    <div class="field"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" required value="<?= e($values['end_time']) ?>"<?= invalid_attrs('end_time', $errors) ?>><?= field_error('end_time', $errors) ?></div>
                    <div class="field full-width"><label for="location">Location</label><input id="location" name="location" maxlength="150" required value="<?= e($values['location']) ?>"<?= invalid_attrs('location', $errors) ?>><?= field_error('location', $errors) ?></div>
                    <div class="full-width inline-actions"><button class="button" type="submit"><?= $editingId ? 'Save changes' : 'Create event' ?></button><?php if ($editingId): ?><a href="<?= e(url('events.php')) ?>">Cancel editing</a><?php endif; ?></div>
                </form>
            </div>
        </details>
    <?php endif; ?>

    <?php if ($participantsId && $committee): ?>
        <section class="card" id="participants"><div class="card-body">
            <?php if (!$participantEvent): ?><h2>Event unavailable</h2><p>Choose another event to manage its participants.</p>
            <?php else: ?><h2>Participants: <?= e($participantEvent['event_name']) ?></h2><p class="muted">Use these details only to manage this event. Cancelled registrations are excluded. Attendance can be recorded once the event starts.</p>
                <?php if (!$participants): ?><div class="empty-state"><p>No current registrations for this event yet.</p></div>
                <?php else: ?><div class="table-scroll" tabindex="0" role="region" aria-label="Event participants"><table><caption>Current registrations and attendance</caption><thead><tr><th scope="col">Member</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Attendance</th></tr></thead><tbody>
                    <?php foreach ($participants as $participant): ?><tr><td><?= e($participant['first_name'] . ' ' . $participant['last_name']) ?></td><td><?= e($participant['email']) ?></td><td><?= e(ucfirst($participant['attendance_status'])) ?></td><td>
                        <?php if ($participant['attendance_status'] === 'attended'): ?>Recorded
                        <?php elseif (event_is_future($participantEvent)): ?><span class="muted">Available after start</span>
                        <?php else: ?><form method="post" action="<?= e(url('events.php?view=past&participants=' . $participantsId . '#participants')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="mark_attended"><input type="hidden" name="event_id" value="<?= $participantsId ?>"><input type="hidden" name="registration_id" value="<?= (int) $participant['registration_id'] ?>"><button class="button button-small" type="submit" aria-label="Mark <?= e($participant['first_name'] . ' ' . $participant['last_name']) ?> attended">Mark attended</button></form><?php endif; ?>
                    </td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            <?php endif; ?>
        </div></section>
    <?php endif; ?>

    <form method="get" action="<?= e(url('events.php')) ?>" class="search-form">
        <?php if ($past): ?><input type="hidden" name="view" value="past"><?php endif; ?>
        <div class="field"><label for="q">Search by event name or location</label><input id="q" name="q" type="search" maxlength="150" value="<?= e($search) ?>" placeholder="Try workshop or student hub"></div><button class="button" type="submit">Search events</button><?php if ($search !== ''): ?><a href="<?= e(url('events.php' . ($past ? '?view=past' : ''))) ?>">Clear search</a><?php endif; ?>
    </form>
    <p class="muted">All event times: <?= e(config('timezone')) ?>. <?= count($events) ?> <?= count($events) === 1 ? 'event' : 'events' ?><?= $search !== '' ? ' matching your search' : '' ?>.</p>
    <?php if (!$events): ?>
        <div class="empty-state"><h2><?= $search !== '' ? 'No matching events' : ($past ? 'No past events yet' : 'Something good is on its way') ?></h2><p><?= $search !== '' ? 'Try a different event name or location, or clear your search.' : ($past ? 'Completed activities will appear here once their start time has passed.' : 'There are no upcoming events at the moment. Check back soon for the next club gathering.') ?></p><a href="<?= e(url('contact.php')) ?>">Suggest an activity</a></div>
    <?php else: ?>
        <div class="grid grid-3">
        <?php foreach ($events as $event): $asset = event_image($event); $remaining = max(0, (int) $event['capacity'] - (int) $event['occupied']); $registered = in_array($event['attendance_status'], ['registered','attended'], true); ?>
            <article class="card event-card" id="event-<?= (int) $event['event_id'] ?>">
                <img class="event-image" src="<?= e($asset['src']) ?>" alt="<?= e($asset['alt']) ?>" width="960" height="600" loading="lazy">
                <div class="card-body"><span class="badge<?= $registered ? ' badge-success' : ($past || !$remaining ? ' badge-muted' : '') ?>"><?= $registered ? 'Already registered' : ($past ? 'Past event' : ($remaining ? 'Places available' : 'Full')) ?></span>
                    <h2><?= e($event['event_name']) ?></h2><p class="event-description"><?= nl2br(e($event['description'])) ?></p>
                    <dl class="event-meta"><dt>When</dt><dd><?= e(event_when($event)) ?></dd><dt>Where</dt><dd><?= e($event['location']) ?></dd><dt>Places</dt><dd><?= (int) $event['occupied'] ?> occupied / <?= (int) $event['capacity'] ?> capacity<br><?= $remaining ?> remaining</dd></dl>
                    <div class="inline-actions">
                        <?php if (!$past): ?><a class="button button-small<?= $registered || !$remaining ? ' button-secondary' : '' ?>" href="<?= e(url('event_register.php?event_id=' . (int) $event['event_id'])) ?>"><?= $registered ? 'View your registration' : ($remaining ? ($member ? 'RSVP to this event' : 'Log in to RSVP') : 'View event') ?></a><?php endif; ?>
                        <?php if ($committee): ?><a href="<?= e(url('events.php?edit=' . (int) $event['event_id'] . ($past ? '&view=past' : '') . '#event-editor')) ?>">Edit event</a><a href="<?= e(url('events.php?participants=' . (int) $event['event_id'] . ($past ? '&view=past' : '') . '#participants')) ?>">Manage participants</a><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
