<?php
/**
 * login.php - Hospital Authentication
 * 
 * Verifies hospital login credentials against hospital_users.
 * Retrieves hospital name and sets session context.
 * Task 5: Uses centralized shared query in shared_queries.php.
 */

$role = 'hospital';
$title = 'Hospital Login';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Task 5: Fetch user via centralized query (joins hospitals for name)
    $u = shared_get_hospital_user_by_email($email);

    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['hospital_id'] = (int)$u['hospital_id'];
        $_SESSION['hospital_name'] = $u['name'];
        flash('success', 'Welcome, ' . $u['name'] . '!');
        redirect('hospital/appointments.php');
    }
    flash('error', 'Invalid email or password.');
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Hospital Login</h1>
  <p class="muted">Access your hospital portal to view appointments and update vaccine administration status.</p>

  <form method="post" action="login.php" class="form">
    <?= csrf_field() ?>
    <label>
      Hospital Login Email:
      <input type="email" name="email" required placeholder="e.g. clinic@hospital.pk" value="<?= e($_POST['email'] ?? '') ?>">
    </label>
    <label>
      Password:
      <input type="password" name="password" required placeholder="Enter password">
    </label>
    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Log In</button>
    </div>
    <p class="muted" style="text-align:center; margin-top:14px;">
      Register a new facility? <a href="register.php">Hospital Registration</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
