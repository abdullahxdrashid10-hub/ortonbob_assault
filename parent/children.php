<?php
/**
 * children.php - Manage Registered Children
 * 
 * Allows a parent to view all their registered children, add a new child profile,
 * navigate to a child's immunization schedule, and remove a child.
 * 
 * Task 2: Rejects deletion of any child who has associated booking records,
 * using shared_child_has_bookings($childId) in shared_queries.php.
 */

$role = 'parent';
$title = 'My Children';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Handle POST actions (Add Child or Delete Child)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Delete a child profile
    if ($action === 'delete') {
        $childId = (int)($_POST['id'] ?? 0);

        // Ownership verification: ensure child belongs to the logged-in parent
        $checkChild = db()->prepare('SELECT id, full_name FROM children WHERE id = ? AND parent_id = ?');
        $checkChild->execute([$childId, $parentId]);
        $targetChild = $checkChild->fetch();

        if (!$targetChild) {
            flash('error', 'Child record not found or unauthorized.');
        } else {
            // Task 2: Guard check using shared_child_has_bookings() (SHARED TABLE: bookings)
            if (shared_child_has_bookings($childId)) {
                flash('error', 'This child has vaccination records and cannot be removed.');
            } else {
                $del = db()->prepare('DELETE FROM children WHERE id = ? AND parent_id = ?');
                $del->execute([$childId, $parentId]);
                flash('success', 'Child profile removed successfully.');
            }
        }
    } 
    // Action 2: Add a new child profile
    else {
        $name = trim($_POST['full_name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $bloodGroup = trim($_POST['blood_group'] ?? '') ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        $isValid = ($name !== '') 
            && in_array($gender, ['Male', 'Female', 'Other'], true)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) 
            && ($dob <= date('Y-m-d'));

        if (!$isValid) {
            flash('error', 'Please provide a valid child name, gender, and date of birth that is not in the future.');
        } else {
            $ins = db()->prepare('INSERT INTO children (parent_id, full_name, dob, gender, blood_group, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->execute([$parentId, $name, $dob, $gender, $bloodGroup, $notes]);
            flash('success', 'Child profile added successfully!');
        }
    }
    redirect('parent/children.php');
}

// Fetch all registered children for this parent
$stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY dob DESC');
$stmt->execute([$parentId]);
$kids = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>My Children</h1>
  <p class="muted">Register and maintain your children's profiles for accurate age-based immunization scheduling.</p>
</div>

<div class="grid2">
  <!-- Form: Add Child -->
  <section class="card reveal">
    <h2>Add a Child Profile</h2>
    <form method="post" action="children.php" class="form">
      <?= csrf_field() ?>
      <label>
        Child Full Name:
        <input type="text" name="full_name" required placeholder="e.g. Zainab Khan">
      </label>
      <label>
        Date of Birth:
        <input type="date" name="dob" max="<?= date('Y-m-d') ?>" required>
      </label>
      <label>
        Gender:
        <select name="gender" required>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </label>
      <label>
        Blood Group (Optional):
        <input type="text" name="blood_group" maxlength="5" placeholder="e.g. B+, O-">
      </label>
      <label>
        Medical Notes / Allergies (Optional):
        <input type="text" name="notes" maxlength="255" placeholder="e.g. Penicillin allergy">
      </label>
      <div style="margin-top:8px;">
        <button type="submit" class="btn">Register Child</button>
      </div>
    </form>
  </section>

  <!-- List: Registered Children -->
  <section class="card reveal">
    <h2>Registered Profiles</h2>
    <?php if (empty($kids)): ?>
      <p class="muted">No children registered under this account yet. Add a profile using the form.</p>
    <?php else: ?>
      <?php foreach ($kids as $k): ?>
        <div class="row">
          <div>
            <b><?= e($k['full_name']) ?></b><br>
            <span class="muted">
              <?= e($k['gender']) ?> &middot; 
              <?= e(age_text($k['dob'])) ?> &middot; 
              DOB: <?= e($k['dob']) ?>
              <?php if (!empty($k['blood_group'])): ?>
                &middot; <?= e($k['blood_group']) ?>
              <?php endif; ?>
            </span>
          </div>
          <div class="actions">
            <a class="btn btn-sm btn-ghost" href="schedule.php?child_id=<?= (int)$k['id'] ?>">Schedule</a>
            <a class="btn btn-sm" href="child_edit.php?id=<?= (int)$k['id'] ?>">Edit</a>
            <!-- Task 2: Delete form protected with CSRF and server-side booking guard -->
            <form method="post" action="children.php" onsubmit="return confirm('Are you sure you want to delete this child profile?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
