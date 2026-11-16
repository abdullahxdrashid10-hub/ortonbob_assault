<?php
/**
 * login.php - Hospital Authentication
 * 
 * Item 6: Verifies hospital credentials against the unified `users` table
 * and associated `hospitals` facility record using auth_login_hospital().
 * Sets session keys: user_id, role ('hospital'), hospital_id, hospital_name.
 */

$role = 'hospital';
$title = 'Hospital Login';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as hospital
if (auth_is_hospital_logged_in()) {
    redirect('hospital/appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        flash('error', 'Please enter your hospital email and password.');
    } elseif (auth_login_hospital($email, $password)) {
        flash('success', 'Welcome, ' . ($_SESSION['hospital_name'] ?? 'Healthcare Staff') . '!');
        redirect('hospital/appointments.php');
    } else {
        flash('error', 'Invalid email or password, or healthcare facility account is inactive.');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Hospital Portal Login</h1>
  <p class="muted">Access your hospital portal to view approved appointments, administer vaccines, and manage vaccine inventory.</p>

  <form method="post" action="login.php" class="form">
    <?= csrf_field() ?>

    <label>
      Hospital Login Email:
      <input type="email" name="email" required placeholder="e.g. staff@hospital.pk" value="<?= e($_POST['email'] ?? '') ?>">
    </label>

    <label>
      Password:
      <input type="password" name="password" required placeholder="Enter password">
    </label>

    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Log In to Hospital Portal</button>
    </div>

    <p class="muted" style="text-align:center; margin-top:14px;">
      Register a new facility? <a href="register.php">Hospital Registration</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
