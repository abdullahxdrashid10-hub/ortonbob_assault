<?php
/**
 * register.php - Parent Account Registration
 * 
 * Task B: Registers parent in the unified team schema using a database transaction:
 * 1. Inserts into `users` (email, password_hash, role='parent', status='active')
 * 2. Inserts into `parents` (user_id, full_name, cnic, phone, address, city)
 * Catches duplicate key exceptions (email / cnic) cleanly without leaking database internals.
 */

$role = 'parent';
$title = 'Parent Register';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (auth_is_parent_logged_in()) {
    redirect('parent/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $cnic       = trim($_POST['cnic'] ?? '') ?: null;
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Task F4: Specific server-side validation matching database schema column constraints
    if ($fullName === '' || mb_strlen($fullName) > 120) {
        flash('error', 'Full Name is required and cannot exceed 120 characters.');
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        flash('error', 'A valid email address is required and cannot exceed 150 characters.');
    } elseif ($phone === '' || mb_strlen($phone) > 20) {
        flash('error', 'Phone number is required and cannot exceed 20 characters.');
    } elseif ($address !== '' && mb_strlen($address) > 255) {
        flash('error', 'Address cannot exceed 255 characters.');
    } elseif ($city !== '' && mb_strlen($city) > 80) {
        flash('error', 'City cannot exceed 80 characters.');
    } elseif ($cnic !== null && mb_strlen($cnic) > 20) {
        flash('error', 'CNIC cannot exceed 20 characters.');
    } elseif (strlen($password) < 6 || strlen($password) > 72) {
        flash('error', 'Password must be between 6 and 72 characters.');
    } elseif ($password !== $confirm) {
        flash('error', 'Password and confirmation password do not match.');
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // 1. Insert into unified users table
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, 'parent', 'active')");
            $stmtUser->execute([$email, $hash]);
            $userId = (int)$pdo->lastInsertId();

            // 2. Insert into parents profile table
            $stmtParent = $pdo->prepare("INSERT INTO parents (user_id, full_name, cnic, phone, address, city) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtParent->execute([$userId, $fullName, $cnic, $phone, $address ?: null, $city ?: null]);

            $pdo->commit();

            flash('success', 'Parent account registered successfully! Please log in.');
            redirect('parent/login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Friendly duplicate key detection without leaking database SQL
            $errorInfo = $e->errorInfo[1] ?? 0;
            $msg = $e->getMessage();
            if ($errorInfo === 1062 || str_contains($msg, 'Duplicate entry')) {
                if (str_contains($msg, 'email') || str_contains($msg, 'users')) {
                    flash('error', 'That email address is already registered. Please log in or use another email.');
                } elseif (str_contains($msg, 'cnic')) {
                    flash('error', 'That CNIC is already registered to another parent account.');
                } else {
                    flash('error', 'An account with these details already exists.');
                }
            } else {
                flash('error', 'Registration could not be completed. Please check your details and try again.');
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal">
  <h1>Create Parent Account</h1>
  <p class="muted">Register to track your children's immunization schedules and book hospital visits.</p>

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
      City:
      <input type="text" name="city" value="<?= e($_POST['city'] ?? '') ?>" placeholder="e.g. Karachi">
    </label>

    <label>
      Residential Address:
      <input type="text" name="address" value="<?= e($_POST['address'] ?? '') ?>" placeholder="e.g. Block 5, Gulshan-e-Iqbal">
    </label>

    <label>
      CNIC (Optional):
      <input type="text" name="cnic" value="<?= e($_POST['cnic'] ?? '') ?>" placeholder="e.g. 42101-1234567-8">
    </label>

    <label>
      Password (min 6 characters):
      <input type="password" name="password" minlength="6" required placeholder="Choose a secure password">
    </label>

    <label>
      Confirm Password:
      <input type="password" name="confirm_password" minlength="6" required placeholder="Re-enter your password">
    </label>

    <div style="margin-top:8px;">
      <button type="submit" class="btn" style="width:100%;">Create Account</button>
    </div>

    <p class="muted" style="text-align:center; margin-top:14px;">
      Already registered? <a href="login.php">Log In</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
