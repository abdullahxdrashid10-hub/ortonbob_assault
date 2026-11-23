<?php
/**
 * logout.php - Administrator Logout
 * 
 * Requires HTTP POST with a verified CSRF token. Destroys active session via auth_logout().
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}

csrf_check();
auth_logout();
flash('success', 'You have been logged out of the Admin Portal.');
redirect('admin/login.php');
