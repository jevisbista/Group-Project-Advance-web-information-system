<?php
declare(strict_types=1);

/** Shared landmarks keep navigation and page metadata consistent across all eight pages. */
function render_header(string $title, string $nav, string $description = ''): void
{
    $member = current_member();
    $links = ['home' => ['index.php', 'Home'], 'about' => ['about.php', 'About'], 'committee' => ['committee.php', 'Committee'], 'events' => ['events.php', 'Events'], 'contact' => ['contact.php', 'Contact']];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($description ?: 'Find your people, discover club events and make more of university with ClubConnect.') ?>">
    <meta name="theme-color" content="#0B2545">
    <title><?= e($title) ?> · ClubConnect</title>
    <link rel="icon" href="<?= e(url('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= e(url('assets/icons/favicon.png')) ?>" type="image/png" sizes="32x32">
    <link rel="preload" href="<?= e(url('assets/fonts/InterVariable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="wordmark" href="<?= e(url('index.php')) ?>" aria-label="ClubConnect home"><svg viewBox="0 0 36 36" width="34" height="34" aria-hidden="true"><path d="M26 9a13 13 0 1 0 0 18" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/><circle cx="27" cy="9" r="4" fill="#ADCFF0"/><circle cx="27" cy="27" r="4" fill="#ADCFF0"/><path d="M17 18h13m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Club<span class="wordmark-light">Connect</span></span></a>
        <button class="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false"><span>Menu</span><span class="menu-bars" aria-hidden="true"></span></button>
        <nav class="site-nav" id="site-navigation" aria-label="Main navigation">
            <div class="nav-links">
            <?php foreach ($links as $key => [$path, $label]): ?>
                <a href="<?= e(url($path)) ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            </div>
            <div class="nav-account">
                <a href="<?= e(url('dashboard.php')) ?>"<?= $nav === 'dashboard' ? ' aria-current="page"' : '' ?>><?= $member ? 'My Dashboard' : 'Log in' ?></a>
                <?php if (!$member): ?><a class="nav-join" href="<?= e(url('register.php')) ?>"<?= $nav === 'register' ? ' aria-current="page"' : '' ?>>Join the Club <span aria-hidden="true">↗</span></a><?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<main id="main" tabindex="-1">
<?php foreach (take_flashes() as $message): ?><div class="container flash-container"><div class="alert <?= in_array($message['type'], ['error', 'warning'], true) ? 'alert-error' : 'alert-success' ?>" role="status"><?= e($message['message']) ?></div></div><?php endforeach;
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container footer-top">
        <div><a class="wordmark" href="<?= e(url('index.php')) ?>"><span>Club<span class="wordmark-light">Connect</span></span></a></div>
        <div class="footer-links"><a href="<?= e(url('about.php')) ?>">About the club</a><a href="<?= e(url('events.php')) ?>">Find an event</a><a href="<?= e(url('contact.php')) ?>">Get in touch <span aria-hidden="true">↗</span></a></div>
    </div>
    <div class="container footer-bottom"><p>© <?= e(club_now()->format('Y')) ?> ClubConnect<?= config('demo_mode') ? ' · A student project for ICT312' : '' ?></p><a href="<?= e(url('register.php#privacy-notice')) ?>">Privacy &amp; your information</a></div>
</footer>
</body>
</html>
<?php
}
