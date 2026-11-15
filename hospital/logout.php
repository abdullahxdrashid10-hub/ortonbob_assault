<?php
/** Hospital logout. */
require_once __DIR__ . '/../config/config.php';
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been logged out.');
redirect('hospital/login.php');
