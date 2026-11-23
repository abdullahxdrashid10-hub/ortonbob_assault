<?php
/**
 * login.php - Administrator Authentication
 * 
 * Verifies administrator credentials against the unified `users` table
 * joined with `admins` via auth_login_admin().
 * Sets session keys: user_id, role ('admin'), admin_id, admin_name.
 */

$role = 'admin';
$title = 'Admin Login';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (auth_is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        flash('error', 'Please enter your administrator email and password.');
    } elseif (auth_login_admin($email, $password)) {
        flash('success', 'Welcome back, ' . ($_SESSION['admin_name'] ?? 'Administrator') . '!');
        redirect('admin/dashboard.php');
    } else {
        flash('error', 'Invalid administrator credentials or inactive account.');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Admin Portal Login</h1>
  <p class="muted">System administration for approving vaccination requests, managing hospitals, and reviewing immunization reports.</p>

  <form method="post" action="login.php" class="form">
    <?= csrf_field() ?>

    <label>
      Admin Email:
      <input type="email" name="email" required placeholder="e.g. admin@vaccinecare.pk" value="<?= e($_POST['email'] ?? '') ?>">
    </label>

    <label>
      Password:
      <input type="password" name="password" required placeholder="Enter password">
    </label>

    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Log In to Admin Portal</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
