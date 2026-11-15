<?php
/**
 * logout.php - Parent Account Logout
 * 
 * Task 7: Requires HTTP POST with a verified CSRF token to prevent
 * unauthorized or cross-site logout requests. Destroys active session.
 */

require_once __DIR__ . '/../config/config.php';

// Only allow state-changing logout via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('parent/dashboard.php');
}

// Verify CSRF token
csrf_check();

// Clear and destroy session
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been successfully logged out.');
redirect('parent/login.php');
