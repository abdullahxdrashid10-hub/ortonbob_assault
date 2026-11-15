<?php
/**
 * register.php - Hospital Facility Registration
 * 
 * Creates an approved hospital record and creates its login credentials.
 * Task 5: Uses centralized shared queries in shared_queries.php.
 */

$role = 'hospital';
$title = 'Hospital Register';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $loc = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';

    if ($name === '' || $addr === '' || $loc === '') {
        flash('error', 'Hospital name, address, and city/location are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please provide a valid official email address.');
    } elseif (strlen($pass) < 6) {
        flash('error', 'Password must be at least 6 characters in length.');
    } else {
        // Task 5: Check duplicate email via centralized query
        if (shared_hospital_email_exists($email)) {
            flash('error', 'That email address is already registered to a hospital account.');
        } else {
            // Task 5: Create hospital and credentials via centralized query with SHARED TABLE comments
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            shared_register_hospital($name, $addr, $loc, $phone, $email, $hash);
            flash('success', 'Hospital registered successfully! Please log in with your credentials.');
            redirect('hospital/login.php');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Register Hospital</h1>
  <p class="muted">Enroll your healthcare facility as an authorized child vaccination center.</p>

  <form method="post" action="register.php" class="form">
    <?= csrf_field() ?>
    <label>
      Hospital / Clinic Name:
      <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. City General Hospital">
    </label>
    <label>
      Physical Address:
      <input type="text" name="address" required value="<?= e($_POST['address'] ?? '') ?>" placeholder="Street, block, or area">
    </label>
    <label>
      City / Location:
      <input type="text" name="location" required value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. Karachi">
    </label>
    <label>
      Contact Phone:
      <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="e.g. 021-34567890">
    </label>
    <label>
      Staff Login Email:
      <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="staff@hospital.pk">
    </label>
    <label>
      Password (min 6 characters):
      <input type="password" name="password" minlength="6" required placeholder="Choose a secure password">
    </label>
    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Complete Registration</button>
    </div>
    <p class="muted" style="text-align:center; margin-top:14px;">
      Already registered? <a href="login.php">Log In</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
