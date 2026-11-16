<?php
/**
 * config.php - Global configuration, error logging, DB connection, and helper functions.
 * Adapts to the team's official vaccination_db schema.
 */

// ---- Task F1: Error handling & logging configuration ----
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/php-error.log');

// Global exception handler displaying a friendly error page while logging technical details
set_exception_handler(function (Throwable $e) {
    error_log(sprintf(
        "[%s] Uncaught Exception: %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>System Error | VaxCare</title>';
    echo '<style>body{background:#0b1020;color:#e8ecf8;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
    echo '.box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:32px;max-width:480px;text-align:center;}';
    echo 'h1{color:#ef4444;margin-top:0;}p{color:#9aa6c7;line-height:1.6;}a{color:#38bdf8;text-decoration:none;font-weight:600;}</style></head>';
    echo '<body><div class="box"><h1>Something went wrong</h1><p>An unexpected error occurred while processing your request. Our technical staff has been notified. Please try again shortly.</p>';
    echo '<p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . '/index.php">&larr; Return to Home</a></p></div></body></html>';
    exit;
});

// ---- Database settings (XAMPP defaults) ----
const DB_HOST = 'localhost';
const DB_NAME = 'vaccination_db';
const DB_USER = 'root';
const DB_PASS = '';

// Compute base URL dynamically from project root relative to document root.
// Encodes segments so URLs have no raw unescaped spaces.
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projDir = str_replace('\\', '/', realpath(dirname(__DIR__)));
$computedBase = '';
if ($docRoot && str_starts_with($projDir, $docRoot)) {
    $rel = substr($projDir, strlen($docRoot));
    $parts = array_map('rawurlencode', array_filter(explode('/', str_replace('\\', '/', $rel))));
    $computedBase = $parts ? '/' . implode('/', $parts) : '';
}
if (!defined('BASE_URL')) {
    define('BASE_URL', $computedBase);
}

// ---- Real Database Status Constants ----

// Appointments status values (enum: pending, approved, rejected, completed, cancelled)
const APT_PENDING   = 'pending';
const APT_APPROVED  = 'approved';
const APT_REJECTED  = 'rejected';
const APT_COMPLETED = 'completed';
const APT_CANCELLED = 'cancelled';

// Child vaccinations status values (enum: upcoming, requested, booked, completed, missed)
const CV_UPCOMING  = 'upcoming';
const CV_REQUESTED = 'requested';
const CV_BOOKED    = 'booked';
const CV_COMPLETED = 'completed';
const CV_MISSED    = 'missed';

// Set secure session cookie flags before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/** Returns a single shared PDO connection (created on first call). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            die('Database connection failed. Is MySQL running and sql/official_schema.sql imported?');
        }
    }
    return $pdo;
}

/** HTML-escape output to prevent XSS. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Redirect to a path inside the project and stop execution. */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

/** Store a one-time message shown on the next page. */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** CSRF token for forms. */
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/** Abort the request if the CSRF token is missing or wrong. */
function csrf_check(): void
{
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(400);
        die('Invalid form token. Go back and reload the page.');
    }
}

/** Human-readable age of a child from date of birth. */
function age_text(string $dob): string
{
    $d = (new DateTime($dob))->diff(new DateTime());
    if ($d->y > 0) return $d->y . ' yr ' . $d->m . ' mo';
    if ($d->m > 0) return $d->m . ' mo ' . $d->d . ' d';
    return $d->d . ' days';
}

/** CSS class for a status badge adapting to the team's real lowercase schema values. */
function badge(string $status): string
{
    $map = [
        // child_vaccinations values
        CV_UPCOMING  => 'b-pending',
        CV_REQUESTED => 'b-pending',
        CV_BOOKED    => 'b-approved',
        CV_COMPLETED => 'b-done',
        CV_MISSED    => 'b-missed',
        // appointments values
        APT_PENDING   => 'b-pending',
        APT_APPROVED  => 'b-approved',
        APT_REJECTED  => 'b-rejected',
        APT_COMPLETED => 'b-done',
        APT_CANCELLED => 'b-rejected',
    ];
    $cls = $map[strtolower(trim($status))] ?? 'b-pending';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}
