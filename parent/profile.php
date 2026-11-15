<?php
/**
 * profile.php - Parent Profile & Account Security Management
 * 
 * Task G: Built against the team's unified schema:
 * 1. Edit Contact Information: full_name, phone, address, city, cnic in `parents` table.
 * 2. Password Security: updates password_hash in `users` table after verifying current password.
 */

$role = 'parent';
$title = 'My Profile';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];
$userId   = (int)$_SESSION['user_id'];

// Load profile joining parents and users
$profile = shared_get_parent_profile($parentId);
if (!$profile) {
    flash('error', 'Profile record not found.');
    redirect('parent/login.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Parent Contact Details in `parents` table
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $city     = trim($_POST['city'] ?? '');
        $cnic     = trim($_POST['cnic'] ?? '') ?: null;

        if ($fullName === '' || $phone === '') {
            flash('error', 'Full Name and Phone Number are required fields.');
        } else {
            try {
                shared_update_parent_profile(
                    $parentId,
                    $fullName,
                    $phone,
                    $address ?: null,
                    $city ?: null,
                    $cnic
                );

                // Update active session name
                $_SESSION['parent_name'] = $fullName;
                flash('success', 'Profile contact details updated successfully.');
                redirect('parent/profile.php');
            } catch (PDOException $e) {
                if ($e->errorInfo[1] === 1062) {
                    flash('error', 'That CNIC is already registered to another parent profile.');
                } else {
                    flash('error', 'Failed to update profile details.');
                }
            }
        }
    }

    // Action 2: Change Password in `users` table
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Verify that current password matches stored password_hash
        if (!password_verify($currentPassword, $profile['password_hash'])) {
            flash('error', 'Verification failed: Your current password was incorrect.');
        } elseif (strlen($newPassword) < 6) {
            flash('error', 'New password must be at least 6 characters in length.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation password do not match.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            shared_update_user_password($userId, $newHash);

            flash('success', 'Password updated successfully! Please use your new password next time you log in.');
            redirect('parent/profile.php');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>My Profile &amp; Settings</h1>
  <p class="muted">Manage your personal profile and account credentials.</p>
</div>

<div class="grid2">
  <!-- Contact Details Form -->
  <section class="card reveal">
    <h2>Contact Information</h2>
    <form method="post" action="profile.php" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">

      <label>
        Account Email (Permanent Identifier):
        <input type="email" value="<?= e($profile['email']) ?>" disabled style="opacity:0.7; cursor:not-allowed;">
      </label>

      <label>
        Full Name:
        <input type="text" name="full_name" required value="<?= e($profile['full_name']) ?>">
      </label>

      <label>
        Contact Phone:
        <input type="text" name="phone" required value="<?= e($profile['phone']) ?>">
      </label>

      <label>
        City:
        <input type="text" name="city" value="<?= e($profile['city'] ?? '') ?>" placeholder="e.g. Karachi">
      </label>

      <label>
        Residential Address:
        <input type="text" name="address" value="<?= e($profile['address'] ?? '') ?>" placeholder="e.g. Block 5, Gulshan-e-Iqbal">
      </label>

      <label>
        CNIC:
        <input type="text" name="cnic" value="<?= e($profile['cnic'] ?? '') ?>" placeholder="e.g. 42101-1234567-8">
      </label>

      <div style="margin-top:8px;">
        <button type="submit" class="btn">Save Profile Changes</button>
      </div>
    </form>
  </section>

  <!-- Change Password Form -->
  <section class="card reveal">
    <h2>Change Password</h2>
    <p class="muted" style="font-size:0.9rem;">
      Enter your current password to authorize a password change.
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
      <span>Member since: <?= e(date('F d, Y', strtotime($profile['parent_created_at'] ?? $profile['user_created_at']))) ?></span>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
