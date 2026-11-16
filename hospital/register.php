<?php
/**
 * register.php - Hospital Healthcare Facility Registration
 * 
 * Task 7 & F4: Registers a healthcare facility into the unified schema:
 * 1. Inserts into `users` table (email, password_hash, role='hospital', status='active')
 * 2. Inserts into `hospitals` table (user_id, hospital_name, registration_no, address, city, area,
 *    phone, email, opening_time, closing_time, daily_capacity, status='active')
 * Enforces strict column length validation with specific error messages for every field.
 */

$role = 'hospital';
$title = 'Hospital Register';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Redirect if already logged in as hospital
if (auth_is_hospital_logged_in()) {
    redirect('hospital/appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name          = trim($_POST['name'] ?? '');
    $regNo         = trim($_POST['registration_no'] ?? '') ?: null;
    $address       = trim($_POST['address'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $area          = trim($_POST['area'] ?? '') ?: null;
    $phone         = trim($_POST['phone'] ?? '') ?: null;
    $email         = strtolower(trim($_POST['email'] ?? ''));
    $openingTime   = trim($_POST['opening_time'] ?? '09:00');
    $closingTime   = trim($_POST['closing_time'] ?? '17:00');
    $dailyCapacity = (int)($_POST['daily_capacity'] ?? 50);
    $password      = $_POST['password'] ?? '';
    $confirmPass   = $_POST['confirm_password'] ?? '';

    // Normalize time formats (HH:MM to HH:MM:00)
    if (strlen($openingTime) === 5) $openingTime .= ':00';
    if (strlen($closingTime) === 5) $closingTime .= ':00';

    // Server-side validation matching database schema column constraints (F4)
    if ($name === '' || mb_strlen($name) > 150) {
        flash('error', 'Hospital name is required and cannot exceed 150 characters.');
    } elseif ($regNo !== null && mb_strlen($regNo) > 60) {
        flash('error', 'Registration number cannot exceed 60 characters.');
    } elseif ($address === '' || mb_strlen($address) > 255) {
        flash('error', 'Physical address is required and cannot exceed 255 characters.');
    } elseif ($city === '' || mb_strlen($city) > 80) {
        flash('error', 'City is required and cannot exceed 80 characters.');
    } elseif ($area !== null && mb_strlen($area) > 100) {
        flash('error', 'Area name cannot exceed 100 characters.');
    } elseif ($phone !== null && mb_strlen($phone) > 20) {
        flash('error', 'Phone number cannot exceed 20 characters.');
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        flash('error', 'A valid official email address is required and cannot exceed 150 characters.');
    } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $openingTime)) {
        flash('error', 'Opening time must be in valid 24-hour format (HH:MM).');
    } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $closingTime)) {
        flash('error', 'Closing time must be in valid 24-hour format (HH:MM).');
    } elseif ($openingTime >= $closingTime) {
        flash('error', 'Closing time must be later than opening time.');
    } elseif ($dailyCapacity < 1 || $dailyCapacity > 1000) {
        flash('error', 'Daily patient capacity must be a number between 1 and 1,000.');
    } elseif (strlen($password) < 6 || strlen($password) > 72) {
        flash('error', 'Password must be between 6 and 72 characters.');
    } elseif ($password !== $confirmPass) {
        flash('error', 'Password and confirmation password do not match.');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $hospitalId = shared_register_hospital_facility(
                $name,
                $email,
                $hash,
                $address,
                $city,
                $area,
                $phone,
                $regNo,
                $openingTime,
                $closingTime,
                $dailyCapacity
            );

            flash('success', 'Hospital registered successfully! You may now log in to the hospital management portal.');
            redirect('hospital/login.php');
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if ($e->errorInfo[1] === 1062 || str_contains($msg, 'Duplicate entry')) {
                if (str_contains($msg, 'registration_no')) {
                    flash('error', 'A hospital with that official registration number is already registered.');
                } else {
                    flash('error', 'That email address is already registered to a hospital or user account.');
                }
            } else {
                flash('error', 'Hospital registration could not be completed. Please check your information and try again.');
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal" style="max-width:640px;">
  <h1>Register Healthcare Facility</h1>
  <p class="muted">Enroll your hospital or clinic as an authorized child vaccination center.</p>

  <form method="post" action="register.php" class="form">
    <?= csrf_field() ?>

    <label>
      Hospital / Clinic Name:
      <input type="text" name="name" required maxlength="150" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. City General Hospital">
    </label>

    <div class="grid2">
      <label>
        Registration Number (optional):
        <input type="text" name="registration_no" maxlength="60" value="<?= e($_POST['registration_no'] ?? '') ?>" placeholder="e.g. REG-KHI-4001">
      </label>
      <label>
        Official Phone:
        <input type="text" name="phone" maxlength="20" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="e.g. 021-34567890">
      </label>
    </div>

    <label>
      Physical Address:
      <input type="text" name="address" required maxlength="255" value="<?= e($_POST['address'] ?? '') ?>" placeholder="Street, block, or compound">
    </label>

    <div class="grid2">
      <label>
        City:
        <input type="text" name="city" required maxlength="80" value="<?= e($_POST['city'] ?? '') ?>" placeholder="e.g. Karachi">
      </label>
      <label>
        Area / District:
        <input type="text" name="area" maxlength="100" value="<?= e($_POST['area'] ?? '') ?>" placeholder="e.g. Gulshan-e-Iqbal">
      </label>
    </div>

    <div class="grid3">
      <label>
        Opening Time:
        <input type="time" name="opening_time" required value="<?= e($_POST['opening_time'] ?? '09:00') ?>">
      </label>
      <label>
        Closing Time:
        <input type="time" name="closing_time" required value="<?= e($_POST['closing_time'] ?? '17:00') ?>">
      </label>
      <label>
        Daily Capacity:
        <input type="number" name="daily_capacity" min="1" max="1000" required value="<?= e($_POST['daily_capacity'] ?? '50') ?>">
      </label>
    </div>

    <label>
      Staff Login Email:
      <input type="email" name="email" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>" placeholder="staff@hospital.pk">
    </label>

    <div class="grid2">
      <label>
        Password:
        <input type="password" name="password" minlength="6" maxlength="72" required placeholder="Min 6 characters">
      </label>
      <label>
        Confirm Password:
        <input type="password" name="confirm_password" minlength="6" maxlength="72" required placeholder="Repeat password">
      </label>
    </div>

    <div style="margin-top:10px;">
      <button type="submit" class="btn" style="width:100%;">Complete Hospital Registration</button>
    </div>

    <p class="muted" style="text-align:center; margin-top:14px;">
      Already registered? <a href="login.php">Log In to Hospital Portal</a>
    </p>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
