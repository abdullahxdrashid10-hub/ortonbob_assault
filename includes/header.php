<?php
/**
 * header.php - Shared Page Layout (Top).
 * Included at the top of every user-facing page.
 * 
 * 3B Navigation:
 * - Parent: Dashboard, My Children, Schedule, Hospitals, Bookings, Reports, Profile, Logout.
 * - Hospital: Appointments, Inventory, Profile, Hospital Name, Logout.
 * - Public: Parent Login, Hospital Login, Get Started.
 */
require_once __DIR__ . '/../config/config.php';
$role = $role ?? null;
$title = $title ?? 'VaxCare';
$B = BASE_URL;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
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
  <button class="nav-toggle" aria-label="Menu" aria-expanded="false" aria-controls="main-nav">&#9776;</button>
  <nav class="nav-links" id="main-nav">
  <?php if ($role === 'parent' && !empty($_SESSION['parent_id'])): ?>
    <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/dashboard.php">Dashboard</a>
    <a class="<?= ($currentPage === 'children.php' || $currentPage === 'child_edit.php') ? 'active' : '' ?>" href="<?= $B ?>/parent/children.php">My Children</a>
    <a class="<?= $currentPage === 'schedule.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/schedule.php">Schedule</a>
    <a class="<?= $currentPage === 'hospitals.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/hospitals.php">Hospitals</a>
    <a class="<?= $currentPage === 'bookings.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/bookings.php">Bookings</a>
    <a class="<?= $currentPage === 'report.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/report.php">Reports</a>
    <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= $B ?>/parent/profile.php">Profile</a>
    <form method="post" action="<?= $B ?>/parent/logout.php" style="display:inline; margin:0;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm">Logout</button>
    </form>
  <?php elseif ($role === 'hospital' && !empty($_SESSION['hospital_id'])): ?>
    <a class="<?= $currentPage === 'appointments.php' ? 'active' : '' ?>" href="<?= $B ?>/hospital/appointments.php">Appointments</a>
    <a class="<?= $currentPage === 'stock.php' ? 'active' : '' ?>" href="<?= $B ?>/hospital/stock.php">Vaccine Stock</a>
    <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= $B ?>/hospital/profile.php">Profile</a>
    <span class="who" style="color:var(--primary); font-weight:600; padding:0 6px;"><?= e($_SESSION['hospital_name'] ?? '') ?></span>
    <form method="post" action="<?= $B ?>/hospital/logout.php" style="display:inline; margin:0;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm">Logout</button>
    </form>
  <?php else: ?>
    <a class="<?= $currentPage === 'login.php' && str_contains($_SERVER['PHP_SELF'] ?? '', '/parent/') ? 'active' : '' ?>" href="<?= $B ?>/parent/login.php">Parent Login</a>
    <a class="<?= $currentPage === 'login.php' && str_contains($_SERVER['PHP_SELF'] ?? '', '/hospital/') ? 'active' : '' ?>" href="<?= $B ?>/hospital/login.php">Hospital Login</a>
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
