<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$member = require_member();
$eventId = positive_id(get('event_id'));
$event = $eventId ? find_event($eventId) : null;
$errors = [];
if (!$event) { http_response_code($eventId ? 404 : 400); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!$event) { throw new DomainException('Choose an event from the events page first.'); }
        if (post('action') !== 'register') { throw new DomainException('That action is not available.'); }
        register_for_event($eventId, (int) $member['member_id']);
        flash('Your place is reserved. You can view or cancel your registration from My Dashboard.');
        redirect('event_register.php?event_id=' . $eventId);
    } catch (DomainException $error) {
        $errors['form'] = $error->getMessage();
    }
    $event = $eventId ? find_event($eventId) : null;
}
$registration = $event ? event_registration($eventId, (int) $member['member_id']) : null;
render_header('Reserve your place', 'events', 'Confirm an RSVP to your next club activity.');
?>
<section class="section container">
    <div class="page-heading"><h1>Reserve your place</h1><p class="muted">Review the details, then confirm your RSVP.</p></div>
    <?php error_summary($errors); ?>
    <?php if (!$event): ?>
        <div class="empty-state"><h2>We could not find that event</h2><p>The link may be incomplete or the event may be unavailable.</p><a class="button" href="<?= e(url('events.php')) ?>">Explore events</a></div>
    <?php else: $asset = event_image($event); $registered = $registration && $registration['attendance_status'] !== 'cancelled'; $future = event_is_future($event); $remaining = max(0, (int) $event['capacity'] - (int) $event['occupied']); ?>
        <div class="grid grid-2">
            <article class="card event-card">
                <img class="event-image" src="<?= e($asset['src']) ?>" alt="<?= e($asset['alt']) ?>" width="960" height="600">
                <div class="card-body"><h2><?= e($event['event_name']) ?></h2><p class="event-description"><?= nl2br(e($event['description'])) ?></p>
                    <dl class="event-meta"><dt>When</dt><dd><?= e(event_when($event)) ?></dd><dt>Where</dt><dd><?= e($event['location']) ?></dd><dt>Places</dt><dd><?= (int) $event['occupied'] ?> occupied / <?= (int) $event['capacity'] ?> capacity · <?= $remaining ?> remaining</dd></dl>
                    <p class="muted">All event times: <?= e(config('timezone')) ?>.</p>
                </div>
            </article>
            <div class="card"><div class="card-body stack"><h2>Your registration</h2>
                <p>Signing up as <strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?></strong>.</p>
                <?php if ($registered): ?>
                    <div class="alert" role="status"><strong><?= $registration['attendance_status'] === 'attended' ? 'Attendance recorded' : 'Already registered' ?></strong><p>Your registration is saved. You do not need to submit it again.</p></div>
                    <a class="button" href="<?= e(url('dashboard.php')) ?>">View My Dashboard</a>
                <?php elseif (!$future): ?>
                    <div class="alert"><strong>Registration closed</strong><p>This event has already started. Choose another upcoming activity.</p></div>
                <?php elseif ($remaining === 0): ?>
                    <div class="alert"><strong>This event is full</strong><p>All places are currently occupied. A place may become available if someone cancels.</p></div>
                <?php else: ?>
                    <?php if ($registration): ?><p class="badge badge-muted">Previous registration cancelled</p><p>You can rejoin this event while places are available.</p><?php else: ?><p class="badge badge-success">Places available</p><?php endif; ?>
                    <p>Confirming reserves one place. If your plans change, cancel from your dashboard before the event starts so another member can join.</p>
                    <form method="post" action="<?= e(url('event_register.php?event_id=' . $eventId)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="register"><button class="button" type="submit"><?= $registration ? 'Rejoin this event' : 'Confirm my RSVP' ?></button></form>
                <?php endif; ?>
                <a href="<?= e(url('events.php')) ?>">Back to upcoming events</a>
            </div></div>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
