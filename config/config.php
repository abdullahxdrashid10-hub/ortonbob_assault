<?php
/**
 * config.php - global configuration, DB connection (PDO) and helper functions.
 * Included at the top of every page.
 */

// ---- Database settings (XAMPP defaults) ----
const DB_HOST = 'localhost';
const DB_NAME = 'vaccination_db';
const DB_USER = 'root';
const DB_PASS = '';

// Task 3: Compute base URL dynamically from project root relative to document root.
// Encodes segments so URLs have no raw unescaped spaces. Adapts if folder is renamed or moved.
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

// Booking status values - MUST match the admin panel.
const ST_PENDING = 'Pending';
const ST_APPROVED = 'Approved';
const ST_REJECTED = 'Rejected';
const ST_VACCINATED = 'Vaccinated';
const ST_NOT_VACCINATED = 'Not Vaccinated';

// Task 8: Set secure session cookie flags before session_start()
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
            die('Database connection failed. Is MySQL running and sql/schema.sql imported?');
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

/** Guard: only a logged-in parent may continue. */
function require_parent(): void
{
    if (empty($_SESSION['parent_id'])) {
        redirect('parent/login.php');
    }
}

/** Guard: only a logged-in hospital may continue. */
function require_hospital(): void
{
    if (empty($_SESSION['hospital_id'])) {
        redirect('hospital/login.php');
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

/** CSS class for a status badge. */
function badge(string $status): string
{
    $map = [
        ST_PENDING => 'b-pending', 
        ST_APPROVED => 'b-approved', 
        ST_REJECTED => 'b-rejected',
        ST_VACCINATED => 'b-done', 
        ST_NOT_VACCINATED => 'b-missed',
        'Taken' => 'b-done',
        'Booked' => 'b-approved',
        'Overdue' => 'b-rejected',
        'Upcoming' => 'b-pending',
    ];
    return '<span class="badge ' . ($map[$status] ?? '') . '">' . e($status) . '</span>';
}
