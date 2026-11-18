<?php
/**
 * children.php - Manage Registered Children Profiles
 * 
 * Task C: Adapts to the team's real database schema:
 * - Columns: full_name, date_of_birth, gender (male/female/other), blood_group,
 *   birth_weight_kg, birth_certificate_no, allergies, medical_notes, is_active.
 * - Restricts listings strictly to active children (is_active = 1) of the logged-in parent.
 * - "Delete" performs a SOFT DELETE (is_active = 0) to preserve historical medical integrity.
 * - Verifies that the database AFTER INSERT trigger successfully generated child_vaccinations rows.
 */

$role = 'parent';
$title = 'My Children';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Allowed ENUM values from the official schema
$validGenders = ['male', 'female', 'other'];
$validBloodGroups = ['unknown', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Handle POST submissions (Add Child or Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Soft-Delete a Child Profile
    if ($action === 'delete') {
        $childId = (int)($_POST['id'] ?? 0);
        $result  = shared_soft_delete_child($childId, $parentId);

        // Guard: check if child has active appointments ('requested' or 'booked')
        if ($result === 'active_appointment') {
            flash('error', 'This child has an active appointment. It must be completed or rejected first.');
        } elseif ($result === true) {
            flash('success', 'Child profile removed from active records.');
        } else {
            flash('error', 'Child record could not be removed or unauthorized.');
        }
    }
    // Action 2: Add a New Child Profile
    else {
        $fullName   = trim($_POST['full_name'] ?? '');
        $dob        = trim($_POST['date_of_birth'] ?? '');
        $gender     = strtolower(trim($_POST['gender'] ?? ''));
        $bloodGroup = trim($_POST['blood_group'] ?? 'unknown');
        $weightRaw  = trim($_POST['birth_weight_kg'] ?? '');
        $certNo     = trim($_POST['birth_certificate_no'] ?? '') ?: null;
        $allergies  = trim($_POST['allergies'] ?? '') ?: null;
        $notes      = trim($_POST['medical_notes'] ?? '') ?: null;

        $weightKg = ($weightRaw !== '' && is_numeric($weightRaw)) ? (float)$weightRaw : null;

        // Task 0c: DOB validation using checkdate(), not in future, and not older than 13 years
        $isValidDate = false;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
            $year  = (int)$m[1];
            $month = (int)$m[2];
            $day   = (int)$m[3];
            if (checkdate($month, $day, $year)) {
                $today = date('Y-m-d');
                $minDate = date('Y-m-d', strtotime('-13 years'));
                if ($dob <= $today && $dob >= $minDate) {
                    $isValidDate = true;
                }
            }
        }

        // Task F3: Server-side validation matching database schema column constraints
        $hasError = true;
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            flash('error', 'Child full name is required and cannot exceed 120 characters.');
        } elseif (!$isValidDate) {
            flash('error', 'Date of birth must be a valid calendar date, not in the future, and child must not be older than 13 years.');
        } elseif (!in_array($gender, $validGenders, true)) {
            flash('error', 'Please select a valid gender option.');
        } elseif (!in_array($bloodGroup, $validBloodGroups, true)) {
            flash('error', 'Please select a valid blood group option.');
        } elseif ($weightRaw !== '' && (!is_numeric($weightRaw) || (float)$weightRaw < 0.30 || (float)$weightRaw > 9.99)) {
            flash('error', 'Birth weight must be a valid number between 0.30 kg and 9.99 kg.');
        } elseif ($certNo !== null && mb_strlen($certNo) > 60) {
            flash('error', 'Birth certificate number cannot exceed 60 characters.');
        } elseif ($allergies !== null && mb_strlen($allergies) > 255) {
            flash('error', 'Allergies description cannot exceed 255 characters.');
        } elseif ($notes !== null && mb_strlen($notes) > 1000) {
            flash('error', 'Medical notes cannot exceed 1000 characters.');
        } else {
            // Insert child profile via shared query
            $childId = shared_add_child(
                $parentId,
                $fullName,
                $dob,
                $gender,
                $bloodGroup,
                $weightKg,
                $certNo,
                $allergies,
                $notes
            );

            if ($childId <= 0) {
                flash('error', 'Child record could not be saved. Please verify your input details.');
            } else {
                // Verify trigger execution: trg_children_after_insert calls sp_generate_child_schedule()
                $scheduleCount = shared_count_child_vaccination_rows($childId);

                if ($scheduleCount === 0) {
                    flash('error', 'Child was registered, but automatic vaccination schedule generation failed. Please contact system support.');
                } else {
                    $hasError = false;
                    // Task 0b: Do not call e() on text passed to flash() - header already escapes it
                    flash('success', "Child profile for {$fullName} added successfully! $scheduleCount scheduled vaccination doses generated.");
                }
            }
        }
        if ($hasError) {
            $_SESSION['old_child'] = $_POST;
        }
    }
    redirect('parent/children.php');
}

// Retrieve preserved form values if validation failed on previous POST
$oldChild = $_SESSION['old_child'] ?? [];
unset($_SESSION['old_child']);

// Fetch all active children belonging to this parent
$kids = shared_get_parent_children($parentId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>My Children</h1>
  <p class="muted">Register child profiles to automatically calculate immunization schedules and track life-saving doses.</p>
</div>

<div class="grid2">
  <!-- Form: Register Child -->
  <section class="card reveal">
    <h2>Add a Child Profile</h2>
    <form method="post" action="children.php" class="form">
      <?= csrf_field() ?>

      <label>
        Child Full Name:
        <input type="text" name="full_name" required maxlength="120" placeholder="e.g. Ayaan Khan" value="<?= e($oldChild['full_name'] ?? '') ?>">
      </label>

      <label>
        Date of Birth:
        <input type="date" name="date_of_birth" min="<?= date('Y-m-d', strtotime('-13 years')) ?>" max="<?= date('Y-m-d') ?>" required value="<?= e($oldChild['date_of_birth'] ?? '') ?>">
      </label>

      <label>
        Gender:
        <select name="gender" required>
          <option value="male" <?= (($oldChild['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
          <option value="female" <?= (($oldChild['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
          <option value="other" <?= (($oldChild['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
        </select>
      </label>

      <label>
        Blood Group:
        <select name="blood_group">
          <?php foreach ($validBloodGroups as $bg): ?>
            <option value="<?= e($bg) ?>" <?= (($oldChild['blood_group'] ?? 'unknown') === $bg) ? 'selected' : '' ?>>
              <?= e(strtoupper($bg)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Birth Weight in KG (Optional):
        <input type="number" step="0.01" min="0.30" max="9.99" name="birth_weight_kg" placeholder="e.g. 3.25" value="<?= e($oldChild['birth_weight_kg'] ?? '') ?>">
      </label>

      <label>
        Birth Certificate / Registration No (Optional):
        <input type="text" name="birth_certificate_no" maxlength="60" placeholder="e.g. B-123456" value="<?= e($oldChild['birth_certificate_no'] ?? '') ?>">
      </label>

      <label>
        Allergies (Optional):
        <input type="text" name="allergies" maxlength="255" placeholder="e.g. None known, Penicillin" value="<?= e($oldChild['allergies'] ?? '') ?>">
      </label>

      <label>
        Medical Notes (Optional):
        <textarea name="medical_notes" rows="2" maxlength="1000" placeholder="Any medical conditions or pediatrician instructions"><?= e($oldChild['medical_notes'] ?? '') ?></textarea>
      </label>

      <div style="margin-top:8px;">
        <button type="submit" class="btn">Register Child</button>
      </div>
    </form>
  </section>

  <!-- List: Active Registered Children -->
  <section class="card reveal">
    <h2>Registered Profiles</h2>
    <?php if (empty($kids)): ?>
      <p class="muted">No active children registered under this account yet. Use the form to add your child.</p>
    <?php else: ?>
      <?php foreach ($kids as $k): ?>
        <div class="row">
          <div>
            <b><?= e($k['full_name']) ?></b><br>
            <span class="muted">
              <?= e(ucfirst($k['gender'])) ?> &middot; 
              <?= e(age_text($k['date_of_birth'])) ?> &middot; 
              DOB: <?= e($k['date_of_birth']) ?>
              <?php if ($k['blood_group'] !== 'unknown'): ?>
                &middot; <?= e($k['blood_group']) ?>
              <?php endif; ?>
              <?php if (!empty($k['birth_weight_kg'])): ?>
                &middot; <?= e($k['birth_weight_kg']) ?> kg
              <?php endif; ?>
            </span>
            <?php if (!empty($k['allergies'])): ?>
              <div class="muted" style="font-size:0.8rem; color:var(--warn); margin-top:2px;">
                Allergies: <?= e($k['allergies']) ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="actions">
            <a class="btn btn-sm btn-ghost" href="schedule.php?child_id=<?= (int)$k['child_id'] ?>">Schedule</a>
            <a class="btn btn-sm" href="child_edit.php?id=<?= (int)$k['child_id'] ?>">Edit</a>
            <!-- Soft Delete form: sets is_active = 0 -->
            <form method="post" action="children.php" onsubmit="return confirm('Remove this child profile from active records? Historical vaccinations will be retained.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$k['child_id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
