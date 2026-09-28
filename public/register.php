<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$errors = [];
$values = ['first_name' => '', 'last_name' => '', 'student_id' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = profile_values();
    try {
        verify_csrf();
        $errors = validate_profile($values);
        $password = post('password');
        // bcrypt truncates at 72 bytes. Reject oversized UTF-8 input explicitly.
        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            $errors['password'] = 'Use a passphrase of at least 12 characters and no more than 72 UTF-8 bytes, without null characters.';
        }
        if (post('confirm_password') !== $password) {
            $errors['confirm_password'] = 'The passwords must match.';
        }
        if (post('privacy') !== '1') {
            $errors['privacy'] = 'Please read and acknowledge the privacy notice.';
        }
        if (!$errors) {
            $stmt = db()->prepare("INSERT INTO members (first_name, last_name, student_id, email, phone, password_hash, membership_type, join_date, status) VALUES (?, ?, ?, ?, ?, ?, 'standard', ?, 'active')");
            $stmt->execute([$values['first_name'], $values['last_name'], $values['student_id'], $values['email'], $values['phone'] ?: null, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), club_now()->format('Y-m-d')]);
            flash('Your account is ready. Log in with your email address and password. Your email has not been verified.');
            redirect('dashboard.php');
        }
    } catch (DomainException $error) {
        $errors['form'] = $error->getMessage();
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') {
            throw $error;
        }
        // The unique indexes also protect simultaneous registrations.
        $errors['form'] = 'That email address or student ID is already in use. Please check your details.';
    }
    unset($password);
}
render_header('Join the club', 'register', 'A new connection starts here.');
?>
<section class="container section">
    <div class="page-heading"><h1>A little curiosity.<br>A lot of possibility.</h1><p class="lead">Join ClubConnect to find your people, try something new and make room for more than lectures.</p></div>
    <div class="grid grid-2 form-layout">
        <div class="card card-body">
            <h2>Create your membership</h2><p class="muted">All fields are required except phone. Already a member? <a href="<?= e(url('dashboard.php')) ?>">Log in</a>.</p>
            <?php error_summary($errors); ?>
            <form method="post" action="<?= e(url('register.php')) ?>" class="stack" data-validate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <?php profile_fields($values, $errors); ?>
                    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required minlength="12" autocomplete="new-password" aria-describedby="password-help<?= isset($errors['password']) ? ' password-error' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>><small id="password-help">At least 12 characters; up to 72 UTF-8 bytes. A long, memorable passphrase works well.</small><?= field_error('password', $errors) ?></div>
                    <div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" type="password" name="confirm_password" required autocomplete="new-password"<?= invalid_attrs('confirm_password', $errors) ?>><?= field_error('confirm_password', $errors) ?></div>
                </div>
                <div class="field"><label class="checkbox-label" for="privacy"><input id="privacy" type="checkbox" name="privacy" value="1" required<?= post('privacy') === '1' ? ' checked' : '' ?><?= invalid_attrs('privacy', $errors) ?>> <span>I have read the <a href="#privacy-notice">privacy notice</a> and acknowledge how my membership information is used.</span></label><?= field_error('privacy', $errors) ?></div>
                <button class="button" type="submit">Create my account <span aria-hidden="true">→</span></button>
            </form>
        </div>
        <aside class="stack">
            <div class="card card-body soft-card"><h2>Make yourself at home.</h2><ul class="benefit-list"><li>Discover workshops, socials and shared interests.</li><li>Save your place at upcoming events.</li><li>Keep your details and attendance history in one place.</li></ul><p class="muted">This is a fictional student club demonstration. Standard membership does not grant committee access.</p></div>
            <div class="card card-body" id="privacy-notice"><h2>Your information, explained</h2><p>We collect your name, student ID, email, optional phone number and a hashed password to manage membership, sign-in and event registrations.</p><p>Your profile is private. Current committee members can see participant names and email addresses for event administration. Public committee listings show only names, roles and terms.</p><p>You can correct your details in My Dashboard. To request access or deletion, use the <a href="<?= e(url('contact.php')) ?>">club contact email</a>. A maintainer reviews requests and event history before disposal; deletion is not automatic.</p><p class="muted">For this local demonstration, please use fictional details. No email verification or payment is required.</p></div>
        </aside>
    </div>
</section>
<?php render_footer(); ?>
