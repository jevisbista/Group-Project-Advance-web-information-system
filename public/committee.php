<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
// Only public names and committee terms are selected; private member fields stay private.
$today = club_now()->format('Y-m-d');
$statement = db()->prepare("SELECT m.first_name, m.last_name, c.role, c.term_start, c.term_end FROM committee c JOIN members m ON m.member_id = c.member_id WHERE m.status = 'active' AND c.term_start <= ? AND (c.term_end IS NULL OR c.term_end >= ?) ORDER BY c.term_start, c.committee_id");
$statement->execute([$today, $today]);
$committee = $statement->fetchAll();
render_header('Meet the committee', 'committee');
?>
<section class="container section"><div class="page-heading"><h1>A shared club.<br>A team that cares.</h1><p class="lead">Meet the current committee members helping turn ideas into events and keeping the club connected.</p><?php if (config('demo_mode')): ?><p class="content-note">The names and committee terms below are fictional demonstration content.</p><?php endif; ?></div>
<?php if (!$committee): ?><div class="empty-state"><h2>A new chapter is taking shape.</h2><p>No current committee members are listed yet. Contact the club for information about getting involved.</p><a class="button button-secondary" href="<?= e(url('contact.php')) ?>">Get in touch →</a></div><?php else: ?><div class="grid grid-3 committee-grid">
<?php foreach ($committee as $person): $initials = mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1); ?>
<article class="card card-body committee-card"><div class="committee-avatar" aria-hidden="true"><?= e(mb_strtoupper($initials)) ?></div><p class="committee-role"><?= e($person['role']) ?></p><h2><?= e($person['first_name'] . ' ' . $person['last_name']) ?></h2><p class="committee-term"><span>Current term</span><br><?= e((new DateTimeImmutable($person['term_start']))->format('j M Y')) ?>  to  <?= $person['term_end'] ? e((new DateTimeImmutable($person['term_end']))->format('j M Y')) : 'ongoing' ?></p></article>
<?php endforeach; ?></div><?php endif; ?>
<div class="join-strip"><div><h2>Good clubs are built together.</h2><p>Suggest an event, ask a question or find out how to contribute.</p></div><a class="button" href="<?= e(url('contact.php')) ?>">Talk to the club <span aria-hidden="true">↗</span></a></div></section>
<?php render_footer(); ?>
