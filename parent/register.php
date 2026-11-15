<?php
/**
 * register.php - Parent Account Registration
 * 
 * Validates parent contact information and credentials.
 * Task 6: Adds confirm-password field with strict server-side match verification.
 * Hashes password using Bcrypt.
 */

$role = 'parent';
$title = 'Parent Register';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Validation checks
    if ($name === '' || $phone === '' || $addr === '') {
        flash('error', 'Please fill in all contact fields (Full name, Phone, and Address).');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } elseif (strlen($pass) < 6) {
        flash('error', 'Password must be at least 6 characters in length.');
    } elseif ($pass !== $confirmPass) {
        // Task 6: Server-side password match verification
        flash('error', 'Password and confirmation password do not match.');
    } else {
        // Check for duplicate registered email
        $q = db()->prepare('SELECT id FROM parents WHERE email = ?');
        $q->execute([$email]);
        if ($q->fetch()) {
            flash('error', 'That email address is already registered to a parent account.');
        } else {
            // Save new parent with hashed password
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            db()->prepare('INSERT INTO parents (full_name, email, phone, address, password_hash) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $email, $phone, $addr, $hash]);
            flash('success', 'Account created successfully! Please log in with your email and password.');
            redirect('parent/login.php');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Create Parent Account</h1>
  <p class="muted">Register to manage your children's immunization records and book appointments.</p>

  <form method="post" action="register.php" class="form">
    <?= csrf_field() ?>
    <label>
      Full Name:
      <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="e.g. Sara Khan">
    </label>
    <label>
      Email Address:
      <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="parent@example.com">
    </label>
    <label>
      Phone Number:
      <input type="text" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>" placeholder="e.g. 0300-1234567">
    </label>
    <label>
      Residential Address:
      <input type="text" name="address" required value="<?= e($_POST['address'] ?? '') ?>" placeholder="e.g. Block 5, Karachi">
    </label>
    <label>
      Password (min 6 characters):
      <input type="password" name="password" minlength="6" required placeholder="Choose a secure password">
    </label>
    <!-- Task 6: Confirm Password Field -->
    <label>
      Confirm Password:
      <input type="password" name="confirm_password" minlength="6" required placeholder="Re-enter your password">
    </label>
    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Register Account</button>
    </div>
    <p class="muted" style="text-align:center; margin-top:14px;">
      Already registered? <a href="login.php">Log In</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
