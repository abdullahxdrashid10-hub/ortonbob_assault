<?php
/**
 * header.php - shared page layout (top). Set before including:
 *   $title (string) and $role ('parent' | 'hospital' | null for public pages)
 */
require_once __DIR__ . '/../config/config.php';
$role = $role ?? null;
$title = $title ?? 'VaxCare';
$B = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | VaxCare</title>
<link rel="stylesheet" href="<?= $B ?>/assets/css/style.css">
</head>
<body class="role-<?= e($role ?? 'public') ?>">
<canvas id="bg-canvas" aria-hidden="true"></canvas>
<header class="nav">
  <a class="brand" href="<?= $B ?>/index.php">&#128137; Vax<span>Care</span></a>
  <button class="nav-toggle" aria-label="Menu">&#9776;</button>
  <nav class="nav-links">
  <?php if ($role === 'parent' && !empty($_SESSION['parent_id'])): ?>
    <a href="<?= $B ?>/parent/dashboard.php">Dashboard</a>
    <a href="<?= $B ?>/parent/children.php">My Children</a>
    <a href="<?= $B ?>/parent/hospitals.php">Hospitals</a>
    <a href="<?= $B ?>/parent/bookings.php">Bookings</a>
    <a href="<?= $B ?>/parent/report.php">Reports</a>
    <a href="<?= $B ?>/parent/profile.php">Profile</a>
    <a class="btn btn-sm" href="<?= $B ?>/parent/logout.php">Logout</a>
  <?php elseif ($role === 'hospital' && !empty($_SESSION['hospital_id'])): ?>
    <a href="<?= $B ?>/hospital/appointments.php">Appointments</a>
    <span class="who"><?= e($_SESSION['hospital_name'] ?? '') ?></span>
    <a class="btn btn-sm" href="<?= $B ?>/hospital/logout.php">Logout</a>
  <?php else: ?>
    <a href="<?= $B ?>/parent/login.php">Parent Login</a>
    <a href="<?= $B ?>/hospital/login.php">Hospital Login</a>
    <a class="btn btn-sm" href="<?= $B ?>/parent/register.php">Get Started</a>
  <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php
// Show and clear queued flash messages.
foreach ($_SESSION['flash'] ?? [] as $f) {
    echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}
unset($_SESSION['flash']);
