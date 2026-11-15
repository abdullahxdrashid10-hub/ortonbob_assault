<?php
/** Parent logout: destroys the session. */
require_once __DIR__ . '/../config/config.php';
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been logged out.');
redirect('parent/login.php');
