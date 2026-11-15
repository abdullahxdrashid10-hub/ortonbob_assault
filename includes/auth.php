<?php
/**
 * auth.php - Centralized Authentication & Session Guard Module
 * 
 * Manages user authentication, session state, and access-control guards.
 * Adapts to the team's unified `users` table + role-specific profile tables (`parents`, `hospitals`).
 * 
 * Standard Session Keys:
 * - user_id (int)
 * - role (string: 'parent' | 'hospital' | 'admin')
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
