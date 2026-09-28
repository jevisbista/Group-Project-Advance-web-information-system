<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
render_header('Get in touch', 'contact');
$email = (string) config('contact_email');
?>
<section class="container section"><div class="page-heading"><h1>A question? An idea?<br>We're all ears.</h1><p class="lead">Find a quick answer below or reach out to the club. You don't need to be a member to say hello.</p></div>
<div class="grid grid-2 contact-layout"><aside class="contact-card"><span class="mail-symbol" aria-hidden="true">↗</span><h2>Good things start<br>with a hello.</h2><p>For membership questions, event ideas or help with your account, email the club.</p><a class="contact-email" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php if (config('contact_is_example')): ?><p class="example-email">Example address · Replace it with the club's real contact address before use.</p><?php endif; ?><p class="contact-explainer">This link opens your email application. This website does not send or store contact messages.</p><div class="contact-decoration" aria-hidden="true">✳</div></aside>
<div class="faq"><h2>Before you say hello.</h2>
<details open><summary>How do I join the club?</summary><div><p>Visit <a href="<?= e(url('register.php')) ?>">Join the Club</a>, enter your student and contact details, choose a passphrase and acknowledge the privacy notice. Once your account is created, you can log in and register for events.</p></div></details>
<details><summary>How do I log in, or get help with access?</summary><div><p>Open <a href="<?= e(url('dashboard.php')) ?>">Log in</a> and use the email and password you registered with. If you cannot access your account, contact the club using the email above. There is no automated password reset in this project.</p></div></details>
<details><summary>How do event RSVPs work?</summary><div><p>Browse <a href="<?= e(url('events.php')) ?>">Upcoming Events</a>, choose an event and confirm your registration while logged in. Your place is reserved only after confirmation, subject to availability. Event dates and times use <?= e(config('timezone')) ?>.</p></div></details>
<details><summary>Can I cancel or change my plans?</summary><div><p>Yes. Open My Dashboard and confirm cancellation for a future registration before the event starts. This makes the place available to someone else. You can rejoin if places are still available.</p></div></details>
<details><summary>How can I correct or remove my information?</summary><div><p>You can update your own name, student ID, email and phone in My Dashboard. To request deletion or another correction, contact the club. The club needs to review event records and responsibilities before removing linked information. Read the <a href="<?= e(url('register.php#privacy-notice')) ?>">privacy notice</a> for details.</p></div></details>
<details><summary>Can I suggest an event or help out?</summary><div><p>Absolutely. Send your idea to the club, including the activity you have in mind and how you'd like to be involved. The committee manages event scheduling and available places.</p></div></details>
</div></div></section>
<?php render_footer(); ?>
