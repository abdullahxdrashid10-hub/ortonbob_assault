<?php
/**
 * child_edit.php - Edit Child Profile
 * 
 * Task C: Modifies an existing child's profile using the official database fields:
 * - full_name, date_of_birth, gender, blood_group, birth_weight_kg, birth_certificate_no, allergies, medical_notes
 * - Strictly enforces that the target child belongs to the logged-in parent and is active.
 */

$role = 'parent';
$title = 'Edit Child Profile';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];
$childId  = (int)($_GET['id'] ?? 0);

// Load target child and verify ownership
$child = shared_get_parent_child_by_id($childId, $parentId);
if (!$child) {
    flash('error', 'Child record not found or unauthorized.');
    redirect('parent/children.php');
}

$validGenders = ['male', 'female', 'other'];
$validBloodGroups = ['unknown', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $fullName   = trim($_POST['full_name'] ?? '');
    $gender     = strtolower(trim($_POST['gender'] ?? ''));
    $bloodGroup = trim($_POST['blood_group'] ?? 'unknown');
    $weightRaw  = trim($_POST['birth_weight_kg'] ?? '');
    $certNo     = trim($_POST['birth_certificate_no'] ?? '') ?: null;
    $allergies  = trim($_POST['allergies'] ?? '') ?: null;
    $notes      = trim($_POST['medical_notes'] ?? '') ?: null;

    $weightKg = ($weightRaw !== '' && is_numeric($weightRaw)) ? (float)$weightRaw : null;

    if ($fullName === '') {
        flash('error', 'Please enter the child\'s full name.');
    } elseif (!in_array($gender, $validGenders, true)) {
        flash('error', 'Please select a valid gender option.');
    } elseif (!in_array($bloodGroup, $validBloodGroups, true)) {
        flash('error', 'Please select a valid blood group option.');
    } else {
        // Date of birth is read-only and preserved permanently to prevent stale schedule doses
        shared_update_child(
            $childId,
            $parentId,
            $fullName,
            $gender,
            $bloodGroup,
            $weightKg,
            $certNo,
            $allergies,
            $notes
        );

        flash('success', 'Child profile updated successfully.');
        redirect('parent/children.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="card auth reveal" style="max-width:540px;">
  <h1>Edit Child Profile</h1>
  <p class="muted">Update medical details and contact information for <?= e($child['full_name']) ?>.</p>

  <form method="post" action="child_edit.php?id=<?= $childId ?>" class="form">
    <?= csrf_field() ?>

    <label>
      Child Full Name:
      <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? $child['full_name']) ?>">
    </label>

    <label>
      Date of Birth (Permanent, cannot be modified):
      <input type="date" value="<?= e($child['date_of_birth']) ?>" readonly disabled style="opacity:0.75; cursor:not-allowed;">
    </label>

    <label>
      Gender:
      <select name="gender" required>
        <?php foreach ($validGenders as $g): ?>
          <option value="<?= $g ?>" <?= (($_POST['gender'] ?? $child['gender']) === $g) ? 'selected' : '' ?>>
            <?= ucfirst($g) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      Blood Group:
      <select name="blood_group">
        <?php foreach ($validBloodGroups as $bg): ?>
          <option value="<?= e($bg) ?>" <?= (($_POST['blood_group'] ?? $child['blood_group']) === $bg) ? 'selected' : '' ?>>
            <?= e(strtoupper($bg)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      Birth Weight in KG (Optional):
      <input type="number" step="0.01" min="0.5" max="15" name="birth_weight_kg" value="<?= e($_POST['birth_weight_kg'] ?? $child['birth_weight_kg'] ?? '') ?>">
    </label>

    <label>
      Birth Certificate / Registration No (Optional):
      <input type="text" name="birth_certificate_no" value="<?= e($_POST['birth_certificate_no'] ?? $child['birth_certificate_no'] ?? '') ?>">
    </label>

    <label>
      Allergies (Optional):
      <input type="text" name="allergies" value="<?= e($_POST['allergies'] ?? $child['allergies'] ?? '') ?>">
    </label>

    <label>
      Medical Notes (Optional):
      <textarea name="medical_notes" rows="2" style="padding:12px; border-radius:10px; border:1px solid var(--line); background:rgba(0,0,0,.25); color:var(--text); font-family:inherit;"><?= e($_POST['medical_notes'] ?? $child['medical_notes'] ?? '') ?></textarea>
    </label>

    <div style="margin-top:8px; display:flex; gap:10px;">
      <button type="submit" class="btn">Save Changes</button>
      <a class="btn btn-ghost" href="children.php">Cancel</a>
    </div>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
