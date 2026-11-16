<?php
/**
 * auth.php - Centralized Authentication & Session Guard Module
 * 
 * Manages user authentication, session state, and access-control guards.
 * Supports parents and hospital staff using the unified `users` table joined
 * with role profile tables (`parents`, `hospitals`).
 * 
 * Session Keys:
 * - user_id (int)
 * - role ('parent' | 'hospital' | 'admin')
 * - parent_id (int, for parents)
 * - parent_name (string, for parents)
 * - hospital_id (int, for hospitals)
 * - hospital_name (string, for hospitals)
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Attempt parent authentication.
 * Verifies email, active status, parent role, and password hash.
 * On success, initializes session keys and updates users.last_login_at.
 * 
 * @param string $email
 * @param string $password
 * @return bool True if authenticated successfully, false otherwise.
 */
function auth_login_parent(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $pdo = db();

    // Query active parent user record and join parents profile
    $sql = "SELECT u.user_id, u.email, u.password_hash, u.role, u.status,
                   p.parent_id, p.full_name
            FROM users u
            JOIN parents p ON p.user_id = u.user_id
            WHERE u.email = :email AND u.role = 'parent' AND u.status = 'active'
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Prevent session fixation when headers have not yet been sent
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Store authoritative session keys
        $_SESSION['user_id']     = (int)$user['user_id'];
        $_SESSION['role']        = 'parent';
        $_SESSION['parent_id']   = (int)$user['parent_id'];
        $_SESSION['parent_name'] = $user['full_name'];

        // Record last login timestamp
        $up = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
        $up->execute([$user['user_id']]);

        return true;
    }

    return false;
}

/**
 * Attempt hospital staff authentication.
 * Verifies email, password hash, and active status.
 * If password is correct but account or hospital is inactive, returns false with
 * $errorMsg = "Your registration is awaiting admin approval".
 * Check the password FIRST to prevent email enumeration.
 * 
 * @param string $email
 * @param string $password
 * @param string|null &$errorMsg Output error description
 * @return bool True if authenticated successfully, false otherwise.
 */
function auth_login_hospital(string $email, string $password, ?string &$errorMsg = null): bool
{
    $email = strtolower(trim($email));
    $pdo = db();

    // Query hospital user by email and role='hospital'
    $sql = "SELECT u.user_id, u.email, u.password_hash, u.role, u.status AS user_status,
                   h.hospital_id, h.hospital_name, h.status AS hospital_status
            FROM users u
            JOIN hospitals h ON h.user_id = u.user_id
            WHERE u.email = :email AND u.role = 'hospital'
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Check password FIRST to prevent email discovery
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errorMsg = 'Invalid email or password.';
        return false;
    }

    // Password verified! Now check account activation status
    if ($user['user_status'] !== 'active' || $user['hospital_status'] !== 'active') {
        $errorMsg = 'Your registration is awaiting admin approval.';
        return false;
    }

    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']       = (int)$user['user_id'];
    $_SESSION['role']          = 'hospital';
    $_SESSION['hospital_id']   = (int)$user['hospital_id'];
    $_SESSION['hospital_name'] = $user['hospital_name'];

    $up = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
    $up->execute([$user['user_id']]);

    return true;
}

/**
 * Check if the active session is an authenticated parent.
 * 
 * @return bool
 */
function auth_is_parent_logged_in(): bool
{
    return !empty($_SESSION['user_id']) 
        && !empty($_SESSION['parent_id']) 
        && (($_SESSION['role'] ?? '') === 'parent');
}

/**
 * Check if the active session is an authenticated hospital.
 * 
 * @return bool
 */
function auth_is_hospital_logged_in(): bool
{
    return !empty($_SESSION['user_id']) 
        && !empty($_SESSION['hospital_id']) 
        && (($_SESSION['role'] ?? '') === 'hospital');
}

/**
 * Access control guard: requires a logged-in parent session.
 * Redirects unauthorized requests to the parent login page.
 */
function require_parent(): void
{
    if (!auth_is_parent_logged_in()) {
        redirect('parent/login.php');
    }
}

/**
 * Access control guard: requires a logged-in hospital staff session.
 * Redirects unauthorized requests to the hospital login page.
 */
function require_hospital(): void
{
    if (!auth_is_hospital_logged_in()) {
        redirect('hospital/login.php');
    }
}

/**
 * Terminate user session securely.
 */
function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
}
