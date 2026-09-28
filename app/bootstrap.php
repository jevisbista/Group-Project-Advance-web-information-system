<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
// The override is process-level, restricted to CLI tooling/local test servers.
$testDatabase = in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? getenv('CLUBCONNECT_TEST_DB') : false;
if ($testDatabase && !preg_match('/^clubconnect_test_[a-z0-9_]+$/D', $testDatabase)) {
    throw new RuntimeException('Test database must use the clubconnect_test_ prefix.');
}
define('STORAGE_DIR', PROJECT_ROOT . '/storage' . ($testDatabase ? '/' . $testDatabase : ''));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0700, true);
}
ini_set('error_log', STORAGE_DIR . '/php-error.log');

/** Configuration is private; CLI tests can select an isolated database. */
function config(string $key): mixed
{
    static $settings;
    if ($settings === null) {
        $path = PROJECT_ROOT . '/config/config.local.php';
        if (!is_file($path)) {
            throw new RuntimeException('Local configuration is missing. Follow README.md.');
        }
        $settings = require $path;
        if (in_array(PHP_SAPI, ['cli', 'cli-server'], true) && getenv('CLUBCONNECT_TEST_DB')) {
            $name = getenv('CLUBCONNECT_TEST_DB');
            if (!preg_match('/^clubconnect_test_[a-z0-9_]+$/D', $name)) {
                throw new RuntimeException('Test database must use the clubconnect_test_ prefix.');
            }
            $settings['db']['name'] = $name;
        }
    }
    return $settings[$key] ?? null;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('base_path'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    // Only application-relative redirects, never arbitrary user-supplied URLs.
    if (preg_match('~^(?:https?:|//)|[\r\n]~i', $path)) {
        throw new LogicException('Invalid redirect target.');
    }
    header('Location: ' . url($path), true, 303);
    exit;
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : $default;
}

function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default;
}

function positive_id(mixed $value): ?int
{
    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)) {
        return null;
    }
    $id = (int) $value;
    return $id <= 2147483647 ? $id : null;
}

function club_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(config('timezone')));
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $cfg = config('db');
        $pdo = new PDO('mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // TIMESTAMPs are stored/retrieved in UTC; event wall times use the club zone.
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flashes'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $messages;
}

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf']) . '">';
}

function verify_csrf(): void
{
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], post('csrf_token'))) {
        http_response_code(403);
        throw new DomainException('Your form expired or could not be verified. Refresh the page and try again.');
    }
}

function current_member(): ?array
{
    $id = positive_id($_SESSION['member_id'] ?? null);
    if (!$id) {
        return null;
    }
    // Always reload status; a stale session cannot keep an inactive account active.
    $stmt = db()->prepare('SELECT member_id, first_name, last_name, student_id, email, phone, membership_type, join_date, status FROM members WHERE member_id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    if (!$member || $member['status'] !== 'active') {
        unset($_SESSION['member_id']);
        return null;
    }
    return $member;
}

function current_committee(): ?array
{
    $member = current_member();
    if (!$member) {
        return null;
    }
    $today = club_now()->format('Y-m-d');
    $stmt = db()->prepare('SELECT * FROM committee WHERE member_id = ? AND term_start <= ? AND (term_end IS NULL OR term_end >= ?) ORDER BY term_start DESC, committee_id DESC LIMIT 1');
    $stmt->execute([$member['member_id'], $today, $today]);
    return $stmt->fetch() ?: null;
}

function require_member(): array
{
    $member = current_member();
    if (!$member) {
        $id = positive_id(get('event_id'));
        flash('Please log in to continue.', 'info');
        redirect('dashboard.php' . ($id ? '?event_id=' . $id : ''));
    }
    return $member;
}

function require_committee(): array
{
    require_member();
    $committee = current_committee();
    if (!$committee) {
        http_response_code(403);
        throw new DomainException('This action is available to current committee members only.');
    }
    return $committee;
}

/** Audit only controlled action identifiers and internal numeric IDs, never form bodies. */
function audit(string $action, string $outcome, array $ids = []): void
{
    $entry = ['time' => gmdate('c'), 'action' => preg_replace('/[^a-z0-9_.-]/i', '', $action), 'outcome' => preg_replace('/[^a-z0-9_.-]/i', '', $outcome)];
    foreach ($ids as $key => $value) {
        if (preg_match('/^[a-z_]+_id$/D', (string) $key) && is_numeric($value)) {
            $entry[$key] = (int) $value;
        }
    }
    $path = STORAGE_DIR . '/audit-' . gmdate('Y-m') . '.jsonl';
    if (file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Audit storage is not writable.');
    }
}

/** Reserve an attempt BEFORE checking credentials, with an OS file lock across sessions. */
function login_attempt_allowed(string $email): bool
{
    $secret = config('app_key');
    if (!is_string($secret) || !preg_match('/^[a-f0-9]{64}$/D', $secret)) {
        throw new RuntimeException('Configure a random 64-character application key.');
    }
    $keys = [
        hash_hmac('sha256', 'account:' . mb_strtolower($email), $secret) => 8,
        hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), $secret) => 30,
    ];
    $handle = fopen(STORAGE_DIR . '/login-throttle.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Login throttle storage is not writable.');
    }
    try {
        $raw = stream_get_contents($handle);
        $entries = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
        $now = time();
        foreach ($entries as $key => $timestamps) {
            $entries[$key] = array_values(array_filter($timestamps, fn ($time) => is_int($time) && $time > $now - 900));
            if (!$entries[$key]) {
                unset($entries[$key]);
            }
        }
        $allowed = true;
        foreach ($keys as $key => $limit) {
            if (count($entries[$key] ?? []) >= $limit) {
                $allowed = false;
            }
        }
        // Keep the store bounded without evicting live limits an attacker could reset.
        $newKeys = count(array_diff(array_keys($keys), array_keys($entries)));
        if (count($entries) + $newKeys > 2000) {
            $allowed = false;
        }
        if ($allowed) {
            foreach ($keys as $key => $limit) {
                $entries[$key][] = $now;
            }
        }
        rewind($handle);
        $payload = json_encode($entries, JSON_THROW_ON_ERROR);
        if (!ftruncate($handle, 0) || fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
            throw new RuntimeException('Login throttle storage could not be saved.');
        }
        return $allowed;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function error_summary(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="alert alert-error" role="alert" tabindex="-1" id="form-errors"><strong>Please check the following:</strong><ul>';
    foreach ($errors as $name => $error) {
        echo '<li>' . ($name !== 'form' ? '<a href="#' . e($name) . '">' . e($error) . '</a>' : e($error)) . '</li>';
    }
    echo '</ul></div>';
}

function field_error(string $name, array $errors): string
{
    return isset($errors[$name]) ? '<span class="field-error" id="' . e($name) . '-error">' . e($errors[$name]) . '</span>' : '';
}

function invalid_attrs(string $name, array $errors): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '';
}

function profile_values(): array
{
    $values = [];
    foreach (['first_name', 'last_name', 'student_id', 'email', 'phone'] as $name) {
        $values[$name] = trim(post($name));
    }
    $values['email'] = mb_strtolower($values['email']);
    return $values;
}

function validate_profile(array $values, ?int $excludeId = null): array
{
    $errors = [];
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $name => $label) {
        if ($values[$name] === '' || mb_strlen($values[$name]) > 50 || preg_match('/[\x00-\x1F\x7F]/u', $values[$name])) {
            $errors[$name] = $label . ' must be between 1 and 50 characters without control characters.';
        }
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,19}$/D', $values['student_id'])) {
        $errors['student_id'] = 'Student ID must be 2 to 20 letters, numbers, hyphens or underscores.';
    }
    if (strlen($values['email']) > 100 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address of no more than 100 characters.';
    }
    if ($values['phone'] !== '' && !preg_match('/^\+?[0-9 ()-]{5,20}$/D', $values['phone'])) {
        $errors['phone'] = 'Use 5 to 20 characters: numbers, spaces, brackets, hyphens and an optional leading +.';
    }
    // Field identifiers are hard-coded, never accepted from a request.
    foreach (['email' => 'email address', 'student_id' => 'student ID'] as $field => $label) {
        if (!isset($errors[$field])) {
            $stmt = db()->prepare('SELECT member_id FROM members WHERE ' . $field . ' = ? AND member_id <> ?');
            $stmt->execute([$values[$field], $excludeId ?? 0]);
            if ($stmt->fetch()) {
                $errors[$field] = 'That ' . $label . ' is already in use.';
            }
        }
    }
    return $errors;
}

function profile_fields(array $values, array $errors): void
{
    $fields = [
        'first_name' => ['First name', 'text', 50, 'given-name'],
        'last_name' => ['Last name', 'text', 50, 'family-name'],
        'student_id' => ['Student ID', 'text', 20, 'off'],
        'email' => ['Email address', 'email', 100, 'email'],
        'phone' => ['Phone (optional)', 'tel', 20, 'tel'],
    ];
    foreach ($fields as $name => [$label, $type, $max, $autocomplete]) {
        echo '<div class="field"><label for="' . $name . '">' . $label . '</label><input id="' . $name . '" name="' . $name . '" type="' . $type . '" maxlength="' . $max . '" autocomplete="' . $autocomplete . '" value="' . e($values[$name] ?? '') . '"' . ($name === 'phone' ? '' : ' required') . invalid_attrs($name, $errors) . '>' . field_error($name, $errors) . '</div>';
    }
}

set_exception_handler(function (Throwable $error): void {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
    // Detailed diagnostics stay server-side; do not log submitted values or SQL.
    error_log(get_class($error) . ' code=' . $error->getCode() . ' at ' . $error->getFile() . ':' . $error->getLine());
    http_response_code($error instanceof DomainException ? 403 : 503);
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ClubConnect: unable to continue</title><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="container section"><h1>We could not complete that request</h1><p>' . e($error instanceof DomainException ? $error->getMessage() : 'ClubConnect is temporarily unavailable. Please try again shortly. If you are setting up this project, check the private configuration and database steps in README.md.') . '</p><p><a href="index.php">Return to ClubConnect</a></p></main></body></html>';
});

date_default_timezone_set(config('timezone'));
if (PHP_SAPI !== 'cli') {
    ob_start();
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $loopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$https && (config('https_required') || config('environment') !== 'local' || !$loopback)) {
        throw new DomainException('HTTPS is required. Local HTTP is available only on this computer.');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) config('session_timeout'));
    session_name('clubconnect_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => url(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] >= config('session_timeout')) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Your session expired after 30 minutes of inactivity. Please log in again.', 'info');
    }
    $_SESSION['last_activity'] = time();
}

require_once __DIR__ . '/events.php';
require_once __DIR__ . '/views.php';
