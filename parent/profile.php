<?php
/**
 * profile.php - Parent Profile Management & Security Settings
 * 
 * Includes two distinct, secure forms:
 * 1. Personal Information Form: Edit full name, phone, and residential address.
 * 2. Password Security Form: Change login credentials (requires verification
 *    of current password before hashing and saving the new password).
 */

$role = 'parent';
$title = 'My Profile';
require_once __DIR__ . '/../config/config.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch current parent account details
$stmt = db()->prepare("SELECT id, full_name, email, phone, address, password_hash, created_at FROM parents WHERE id = ?");
$stmt->execute([$parentId]);
$parent = $stmt->fetch();

if (!$parent) {
    flash('error', 'Parent account record not found.');
    redirect('parent/login.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Contact Information
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($fullName === '' || $phone === '' || $address === '') {
            flash('error', 'All contact profile fields (Full Name, Phone, and Address) are required.');
        } else {
            $up = db()->prepare("UPDATE parents SET full_name = ?, phone = ?, address = ? WHERE id = ?");
            $up->execute([$fullName, $phone, $address, $parentId]);

            // Update active session name
            $_SESSION['parent_name'] = $fullName;
            $parent['full_name'] = $fullName;
            $parent['phone'] = $phone;
            $parent['address'] = $address;

            flash('success', 'Profile information updated successfully.');
            redirect('parent/profile.php');
        }
    }

    // Action 2: Change Password (requires verifying current password)
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Verification 1: Check that current password matches stored hash
        if (!password_verify($currentPassword, $parent['password_hash'])) {
            flash('error', 'Verification failed: Your current password was incorrect.');
        } elseif (strlen($newPassword) < 6) {
            flash('error', 'New password must be at least 6 characters in length.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation password do not match.');
        } else {
            // Hash the new password securely using Bcrypt (PASSWORD_DEFAULT)
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upPass = db()->prepare("UPDATE parents SET password_hash = ? WHERE id = ?");
            $upPass->execute([$newHash, $parentId]);

            flash('success', 'Password changed successfully! Please use your new password next time you log in.');
            redirect('parent/profile.php');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>My Profile &amp; Settings</h1>
  <p class="muted">Manage your personal contact details and account security credentials.</p>
</div>

<div class="grid2">
  <!-- Form 1: Edit Profile Details -->
  <section class="card reveal">
    <h2>Contact Information</h2>
    <form method="post" action="profile.php" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">

      <label>
        Account Email (Primary identifier):
        <input type="email" value="<?= e($parent['email']) ?>" disabled style="opacity:0.7; cursor:not-allowed;">
      </label>

      <label>
        Full Name:
        <input type="text" name="full_name" value="<?= e($parent['full_name']) ?>" required>
      </label>

      <label>
        Contact Phone:
        <input type="text" name="phone" value="<?= e($parent['phone']) ?>" required>
      </label>

      <label>
        Residential Address:
        <input type="text" name="address" value="<?= e($parent['address']) ?>" required>
      </label>

      <div style="margin-top:8px;">
        <button type="submit" class="btn">Save Profile Changes</button>
      </div>
    </form>
  </section>

  <!-- Form 2: Change Password -->
  <section class="card reveal">
    <h2>Change Password</h2>
    <p class="muted" style="font-size:0.9rem;">
      To change your login password, please enter your existing password for verification.
    </p>

    <form method="post" action="profile.php" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">

      <label>
        Current Password:
        <input type="password" name="current_password" required placeholder="Enter existing password">
      </label>

      <label>
        New Password (min 6 characters):
        <input type="password" name="new_password" minlength="6" required placeholder="Enter new password">
      </label>

      <label>
        Confirm New Password:
        <input type="password" name="confirm_password" minlength="6" required placeholder="Confirm new password">
      </label>

      <div style="margin-top:8px;">
        <button type="submit" class="btn btn-ghost">Update Password</button>
      </div>
    </form>

    <div style="margin-top:20px; padding-top:12px; border-top:1px solid var(--line); font-size:0.85rem; color:var(--muted);">
      <span>Member since: <?= e(date('F d, Y', strtotime($parent['created_at']))) ?></span>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
