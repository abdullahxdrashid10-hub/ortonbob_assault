<?php
/**
 * profile.php - Hospital Facility Profile & Account Security
 * 
 * 3B Item 11 & F4: Allows hospital staff to update healthcare facility details
 * and change login password in the unified `users` table.
 * Enforces strict column length validation with specific error messages for all fields.
 */

$role = 'hospital';
$title = 'Hospital Profile';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_hospital();
$hospitalId = (int)$_SESSION['hospital_id'];
$userId     = (int)$_SESSION['user_id'];

// Load profile record
$profile = shared_get_hospital_profile($hospitalId);
if (!$profile) {
    flash('error', 'Hospital profile not found.');
    redirect('hospital/login.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Facility Information
    if ($action === 'update_profile') {
        $name          = trim($_POST['hospital_name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '') ?: null;
        $address       = trim($_POST['address'] ?? '');
        $city          = trim($_POST['city'] ?? '');
        $area          = trim($_POST['area'] ?? '') ?: null;
        $openingTime   = trim($_POST['opening_time'] ?? '09:00');
        $closingTime   = trim($_POST['closing_time'] ?? '17:00');
        $dailyCapacity = (int)($_POST['daily_capacity'] ?? 50);

        if (strlen($openingTime) === 5) $openingTime .= ':00';
        if (strlen($closingTime) === 5) $closingTime .= ':00';

        // Column length validation matching official database schema
        if ($name === '' || mb_strlen($name) > 150) {
            flash('error', 'Hospital name is required and cannot exceed 150 characters.');
        } elseif ($address === '' || mb_strlen($address) > 255) {
            flash('error', 'Physical address is required and cannot exceed 255 characters.');
        } elseif ($city === '' || mb_strlen($city) > 80) {
            flash('error', 'City is required and cannot exceed 80 characters.');
        } elseif ($area !== null && mb_strlen($area) > 100) {
            flash('error', 'Area cannot exceed 100 characters.');
        } elseif ($phone !== null && mb_strlen($phone) > 20) {
            flash('error', 'Phone number cannot exceed 20 characters.');
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $openingTime)) {
            flash('error', 'Opening time must be in valid format (HH:MM).');
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $closingTime)) {
            flash('error', 'Closing time must be in valid format (HH:MM).');
        } elseif ($openingTime >= $closingTime) {
            flash('error', 'Closing time must be later than opening time.');
        } elseif ($dailyCapacity < 1 || $dailyCapacity > 1000) {
            flash('error', 'Daily patient capacity must be between 1 and 1,000.');
        } else {
            shared_update_hospital_profile(
                $hospitalId,
                $name,
                $address,
                $city,
                $area,
                $phone,
                $openingTime,
                $closingTime,
                $dailyCapacity
            );

            // Update session hospital name
            $_SESSION['hospital_name'] = $name;
            flash('success', 'Hospital facility details updated successfully.');
            redirect('hospital/profile.php');
        }
    }

    // Action 2: Change Password in `users`
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $profile['password_hash'])) {
            flash('error', 'Verification failed: Your current password was incorrect.');
        } elseif (strlen($newPassword) < 6 || strlen($newPassword) > 72) {
            flash('error', 'New password must be between 6 and 72 characters.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation password do not match.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            shared_update_user_password($userId, $newHash);

            flash('success', 'Password updated successfully! Please use your new password next time you log in.');
            redirect('hospital/profile.php');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Hospital Facility Profile</h1>
  <p class="muted">Manage facility operating hours, contact information, and staff portal security credentials.</p>
</div>

<div class="grid2">
  <!-- Facility Details Form -->
  <section class="card reveal">
    <h2>Facility Information</h2>
    <form method="post" action="profile.php" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">

      <label>
        Hospital / Clinic Name:
        <input type="text" name="hospital_name" required maxlength="150" value="<?= e($profile['hospital_name']) ?>">
      </label>

      <div class="grid2">
        <label>
          Registration Number (Read-only):
          <input type="text" value="<?= e($profile['registration_no'] ?? 'N/A') ?>" readonly disabled style="opacity:0.75; cursor:not-allowed;">
        </label>
        <label>
          Official Phone:
          <input type="text" name="phone" maxlength="20" value="<?= e($profile['phone'] ?? '') ?>" placeholder="e.g. 021-34567890">
        </label>
      </div>

      <label>
        Physical Address:
        <input type="text" name="address" required maxlength="255" value="<?= e($profile['address']) ?>">
      </label>

      <div class="grid2">
        <label>
          City:
          <input type="text" name="city" required maxlength="80" value="<?= e($profile['city']) ?>">
        </label>
        <label>
          Area / District:
          <input type="text" name="area" maxlength="100" value="<?= e($profile['area'] ?? '') ?>">
        </label>
      </div>

      <div class="grid3">
        <label>
          Opening Time:
          <input type="time" name="opening_time" required value="<?= e(substr($profile['opening_time'], 0, 5)) ?>">
        </label>
        <label>
          Closing Time:
          <input type="time" name="closing_time" required value="<?= e(substr($profile['closing_time'], 0, 5)) ?>">
        </label>
        <label>
          Daily Capacity:
          <input type="number" name="daily_capacity" min="1" max="1000" required value="<?= (int)$profile['daily_capacity'] ?>">
        </label>
      </div>

      <div style="margin-top:10px;">
        <button type="submit" class="btn" style="width:100%;">Save Facility Changes</button>
      </div>
    </form>
  </section>

  <!-- Login Credentials & Password Change Form -->
  <section class="card reveal">
    <h2>Staff Account Security</h2>
    <form method="post" action="profile.php" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">

      <label>
        Login Email (Read-only):
        <input type="email" value="<?= e($profile['email']) ?>" readonly disabled style="opacity:0.75; cursor:not-allowed;">
      </label>

      <label>
        Current Password:
        <input type="password" name="current_password" required placeholder="Enter your current password">
      </label>

      <label>
        New Password:
        <input type="password" name="new_password" minlength="6" maxlength="72" required placeholder="Min 6 characters">
      </label>

      <label>
        Confirm New Password:
        <input type="password" name="confirm_password" minlength="6" maxlength="72" required placeholder="Repeat new password">
      </label>

      <div style="margin-top:10px;">
        <button type="submit" class="btn btn-ghost" style="width:100%;">Update Staff Password</button>
      </div>
    </form>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
