<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$member = current_member();
$errors = [];
$values = $member ?? [];
$eventId = positive_id(get('event_id')) ?? positive_id(post('event_id'));
if ($eventId && !find_event($eventId)) {
    $eventId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = post('action');
        if ($action === 'login' && !$member) {
            $email = mb_strtolower(trim(post('email')));
            if (!login_attempt_allowed($email)) {
                audit('login', 'throttled');
                $errors['form'] = 'Too many sign-in attempts. Wait 15 minutes and try again.';
                http_response_code(429);
            } else {
                $stmt = db()->prepare('SELECT member_id, password_hash, status FROM members WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $account = $stmt->fetch();
                // Fixed dummy bcrypt hash gives unknown accounts the same expensive check.
                $hash = $account['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                $password = post('password');
                $passwordValid = password_verify($password, $hash);
                if ($account && $account['status'] === 'active' && strlen($password) <= 72 && !str_contains($password, "\0") && $passwordValid) {
                    session_regenerate_id(true);
                    $_SESSION = ['member_id' => (int) $account['member_id'], 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
                    flash('Welcome back. You are logged in.');
                    redirect($eventId ? 'event_register.php?event_id=' . $eventId : 'dashboard.php');
                }
                audit('login', 'failed', $account ? ['member_id' => $account['member_id']] : []);
                $errors['form'] = 'The email address or password is incorrect, or the account is unavailable.';
            }
        } elseif ($action === 'logout' && $member) {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('You have been logged out.', 'info');
            redirect('dashboard.php');
        } elseif ($action === 'profile' && $member) {
            $values = profile_values();
            $errors = validate_profile($values, (int) $member['member_id']);
            if (!$errors) {
                $stmt = db()->prepare("UPDATE members SET first_name = ?, last_name = ?, student_id = ?, email = ?, phone = ? WHERE member_id = ? AND status = 'active'");
                $stmt->execute([$values['first_name'], $values['last_name'], $values['student_id'], $values['email'], $values['phone'] ?: null, $member['member_id']]);
                flash('Your profile has been updated.');
                redirect('dashboard.php#profile');
            }
        } elseif ($action === 'cancel' && $member) {
            $cancelEvent = positive_id(post('cancel_event_id'));
            if (!$cancelEvent || post('confirm_cancel') !== '1') {
                throw new DomainException('Please confirm the event cancellation.');
            }
            cancel_registration($cancelEvent, (int) $member['member_id']);
            flash('Your registration has been cancelled. The place is available to another member.');
            redirect('dashboard.php#registrations');
        } else {
            http_response_code(403);
            throw new DomainException('Please log in with an active member account to use that action.');
        }
    } catch (DomainException $error) {
        $errors['form'] = $error->getMessage();
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') {
            throw $error;
        }
        $errors['form'] = 'That email address or student ID is already in use. Please check your details.';
    }
}

render_header($member ? 'My dashboard' : 'Log in', 'dashboard');
if (!$member): ?>
<section class="container section">
    <div class="grid grid-2 login-layout">
        <div class="page-heading"><h1>Your next connection<br>is one login away.</h1><p class="lead">A workshop to spark an idea. A familiar face at a social. Find what’s next for you at ClubConnect.</p><img class="support-image" src="<?= e(url('assets/images/community.jpg')) ?>" alt="Stock photograph of people connecting at a social gathering" width="960" height="600"></div>
        <div class="card card-body login-card"><h2>Welcome back</h2><p class="muted">Log in to manage your profile and event registrations.</p>
            <?php if ($eventId): ?><div class="alert">Log in to continue to your selected event.</div><?php endif; ?>
            <?php error_summary($errors); ?>
            <form method="post" action="<?= e(url('dashboard.php')) ?>" class="stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="login">
                <?php if ($eventId): ?><input type="hidden" name="event_id" value="<?= $eventId ?>"><?php endif; ?>
                <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" maxlength="100" value="<?= e(post('email')) ?>" required></div>
                <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
                <button type="submit" class="button">Log in <span aria-hidden="true">→</span></button>
            </form><p>New around here? <a href="<?= e(url('register.php')) ?>">Join the club</a>.</p><p class="muted">Having trouble signing in? <a href="<?= e(url('contact.php')) ?>">Contact the club</a> for help.</p>
        </div>
    </div>
</section>
<?php else:
    $committee = current_committee();
    $stmt = db()->prepare('SELECT r.attendance_status, r.registration_id, e.* FROM registrations r JOIN events e ON e.event_id = r.event_id WHERE r.member_id = ? ORDER BY e.event_date ASC, e.start_time ASC');
    $stmt->execute([$member['member_id']]);
    $upcoming = [];
    $past = [];
    foreach ($stmt->fetchAll() as $registration) {
        if (event_is_future($registration)) {
            $upcoming[] = $registration;
        } else {
            $past[] = $registration;
        }
    }
    $confirmId = positive_id(get('cancel'));
    $confirm = null;
    foreach ($upcoming as $registration) {
        if ((int) $registration['event_id'] === $confirmId && $registration['attendance_status'] !== 'cancelled') {
            $confirm = $registration;
        }
    }
?>
<section class="container section">
    <div class="page-heading heading-row"><div><h1>Hello, <?= e($member['first_name']) ?>.</h1><p class="lead">Your community, your plans, your next chapter.</p></div><form method="post" action="<?= e(url('dashboard.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="button button-secondary" type="submit">Log out</button></form></div>
    <?php error_summary($errors); ?>
    <?php if ($committee): ?><div class="alert">You are a current committee member: <strong><?= e($committee['role']) ?></strong>. <a href="<?= e(url('events.php')) ?>">Manage events and attendance</a>.</div><?php endif; ?>
    <?php if ($confirm): ?><div class="card card-body cancellation-confirm" id="cancel-confirm"><h2>Cancel your place?</h2><p>You are about to cancel your registration for <strong><?= e($confirm['event_name']) ?></strong>. You can rejoin while places are available and the event has not started.</p><form method="post" action="<?= e(url('dashboard.php')) ?>" class="inline-actions"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="cancel_event_id" value="<?= (int) $confirm['event_id'] ?>"><input type="hidden" name="confirm_cancel" value="1"><button type="submit" class="button">Yes, cancel my place</button><a class="button button-secondary" href="<?= e(url('dashboard.php#registrations')) ?>">Keep my place</a></form></div><?php endif; ?>
    <div class="grid grid-2 dashboard-layout">
        <div class="stack">
            <section class="card card-body" id="registrations"><h2>Your upcoming events</h2>
                <?php if (!$upcoming): ?><div class="empty-state"><p>Your calendar is ready for a new connection.</p><a class="button" href="<?= e(url('events.php')) ?>">Explore events</a></div><?php endif; ?>
                <?php foreach ($upcoming as $registration): ?><article class="registration-item"><span class="badge"><?= e(ucfirst($registration['attendance_status'])) ?></span><h3><a href="<?= e(url('event_register.php?event_id=' . $registration['event_id'])) ?>"><?= e($registration['event_name']) ?></a></h3><p><?= e(event_when($registration)) ?><br><span class="muted"><?= e($registration['location']) ?></span></p>
                    <?php if ($registration['attendance_status'] !== 'cancelled'): ?><a class="button button-secondary button-small" href="<?= e(url('dashboard.php?cancel=' . $registration['event_id'] . '#cancel-confirm')) ?>">Cancel registration</a><?php else: ?><a class="button button-secondary button-small" href="<?= e(url('event_register.php?event_id=' . $registration['event_id'])) ?>">View event and rejoin</a><?php endif; ?>
                </article><?php endforeach; ?>
            </section>
            <section class="card card-body"><h2>Your event history</h2><p class="muted">Events move here when they start. “Registered” records a booking; only “Attended” confirms attendance.</p>
                <?php if (!$past): ?><p class="empty-state">Your shared experiences will appear here after events begin.</p><?php endif; ?>
                <?php foreach (array_reverse($past) as $registration): ?><article class="registration-item"><span class="badge"><?= e(ucfirst($registration['attendance_status'])) ?></span><h3><?= e($registration['event_name']) ?></h3><p class="muted"><?= e(event_when($registration)) ?></p></article><?php endforeach; ?>
            </section>
        </div>
        <section class="card card-body" id="profile"><h2>Keep your details up to date</h2><p class="muted"><?= e(ucfirst($member['membership_type'])) ?> membership · Joined <?= e(date('j M Y', strtotime($member['join_date']))) ?>. Your membership category is separate from committee permissions.</p><form method="post" action="<?= e(url('dashboard.php')) ?>" class="stack" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="profile"><div class="form-grid"><?php profile_fields($values, $errors); ?></div><button type="submit" class="button">Save my details</button></form><p class="muted">Need help with your data or a deletion request? <a href="<?= e(url('contact.php')) ?>">Contact the club</a>.</p></section>
    </div>
</section>
<?php endif; render_footer(); ?>
