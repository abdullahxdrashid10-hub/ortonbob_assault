<?php
/**
 * logout.php - Parent Account Logout
 * 
 * Requires HTTP POST with a verified CSRF token to prevent
 * unauthorized logout triggers. Destroys active session via auth_logout().
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow state-changing logout via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('parent/dashboard.php');
}

// Verify CSRF token
csrf_check();

// Terminate session
auth_logout();
flash('success', 'You have been successfully logged out.');
redirect('parent/login.php');
