<?php
/**
 * login.php - Parent Authentication
 * 
 * Authenticates parents against the unified `users` table joined with `parents`.
 * Task A: Uses centralized auth_login_parent() in includes/auth.php.
 */

$role = 'parent';
$title = 'Parent Login';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (auth_is_parent_logged_in()) {
    redirect('parent/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Delegate verification to auth module (enforces role='parent', status='active', updates last_login_at)
    if (auth_login_parent($email, $password)) {
        flash('success', 'Welcome back, ' . $_SESSION['parent_name'] . '!');
        redirect('parent/dashboard.php');
    } else {
        // Generic failure message to prevent account enumeration
        flash('error', 'Invalid email or password.');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Parent Login</h1>
  <p class="muted">Access your child immunization dashboard and manage scheduled doses.</p>

  <form method="post" action="login.php" class="form">
    <?= csrf_field() ?>
    <label>
      Email Address:
      <input type="email" name="email" required placeholder="parent@example.com" value="<?= e($_POST['email'] ?? '') ?>">
    </label>
    <label>
      Password:
      <input type="password" name="password" required placeholder="Enter your password">
    </label>
    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Log In</button>
    </div>
    <p class="muted" style="text-align:center; margin-top:14px;">
      New here? <a href="register.php">Create Parent Account</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
