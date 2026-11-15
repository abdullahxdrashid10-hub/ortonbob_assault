<?php
/**
 * schedule.php - Child Vaccination Schedule View
 * 
 * Allows a parent to select one of their registered children and view their
 * personalized vaccination timeline based on birth date and vaccine_schedule.
 * Calculates due dates and live statuses: Taken, Booked, Overdue, or Upcoming.
 */

$role = 'parent';
$title = 'Vaccination Schedule';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard: ensure only logged-in parents can access
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch all children belonging to this parent for the dropdown selector
// OWNERSHIP: Filtered strictly by parent_id
$stmt = db()->prepare("SELECT id, full_name, dob, gender FROM children WHERE parent_id = ? ORDER BY dob DESC");
$stmt->execute([$parentId]);
$children = $stmt->fetchAll();

// Determine selected child ID: default to query param or the first child in list
$selectedChildId = (int)($_GET['child_id'] ?? ($children[0]['id'] ?? 0));
$currentChild = null;

if ($selectedChildId > 0) {
    // OWNERSHIP VERIFICATION: Ensure child belongs to the logged-in parent
    foreach ($children as $c) {
        if ((int)$c['id'] === $selectedChildId) {
            $currentChild = $c;
            break;
        }
    }
    // If not found in parent's children list, unauthorized or invalid ID
    if (!$currentChild) {
        flash('error', 'Selected child not found or does not belong to your account.');
        redirect('parent/schedule.php');
    }
}

// Fetch schedule items with calculated status if a valid child is selected
$scheduleItems = [];
if ($currentChild) {
    // Calls centralized query in shared_queries.php
    $scheduleItems = shared_get_child_schedule_status($currentChild['id'], $currentChild['dob']);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Vaccination Schedule</h1>
  <p class="muted">View complete age-based immunization timeline and track doses taken, booked, or due.</p>
</div>

<?php if (empty($children)): ?>
  <!-- Empty state: Parent has not added any children yet -->
  <section class="card reveal">
    <h2>No Children Registered</h2>
    <p class="muted">You must add a child profile before viewing an immunization schedule.</p>
    <a class="btn" href="children.php">Add a Child</a>
  </section>
<?php else: ?>

  <!-- Child Selector Bar -->
  <section class="card reveal">
    <form method="get" action="schedule.php" class="select-bar">
      <label for="child-select" style="font-weight:600; color:var(--text); align-self:center;">Select Child:</label>
      <select id="child-select" name="child_id" onchange="this.form.submit()" style="max-width:320px;">
        <?php foreach ($children as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$currentChild['id'] ? 'selected' : '' ?>>
            <?= e($c['full_name']) ?> (DOB: <?= e($c['dob']) ?>, <?= e(age_text($c['dob'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm">View</button></noscript>
    </form>
    
    <div style="margin-top:10px; font-size:0.95rem;">
      <strong>Child:</strong> <?= e($currentChild['full_name']) ?> &middot;
      <strong>Gender:</strong> <?= e($currentChild['gender']) ?> &middot;
      <strong>DOB:</strong> <?= e($currentChild['dob']) ?> &middot;
      <strong>Current Age:</strong> <?= e(age_text($currentChild['dob'])) ?>
    </div>
  </section>

  <!-- Schedule Timeline Table -->
  <section class="card reveal">
    <h2>Recommended Vaccine Timeline</h2>
    <div class="scroll">
      <table class="table schedule-table">
        <thead>
          <tr>
            <th>Recommended Age</th>
            <th>Vaccine</th>
            <th>Target Description</th>
            <th>Calculated Due Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scheduleItems as $item): ?>
            <?php 
              $st = $item['computed_status']; 
              $canBook = ($st === 'Overdue' || $st === 'Upcoming');
            ?>
            <tr>
              <td>
                <?php 
                  $days = (int)$item['due_after_days'];
                  if ($days === 0) echo 'At Birth';
                  elseif ($days % 30 === 0) echo ($days / 30) . ' Months';
                  elseif ($days % 7 === 0) echo ($days / 7) . ' Weeks';
                  else echo $days . ' Days';
                ?>
              </td>
              <td><strong><?= e($item['vaccine_name']) ?></strong></td>
              <td class="muted"><?= e($item['description'] ?? 'Immunization dose') ?></td>
              <td><?= e($item['due_date']) ?></td>
              <td><?= badge($st) ?></td>
              <td>
                <?php if ($canBook && !empty($item['vaccine_id'])): ?>
                  <a class="btn btn-sm" href="book.php?child_id=<?= (int)$currentChild['id'] ?>&vaccine_id=<?= (int)$item['vaccine_id'] ?>">
                    Book
                  </a>
                <?php elseif ($st === 'Booked'): ?>
                  <a class="btn btn-sm btn-ghost" href="bookings.php">View Booking</a>
                <?php elseif ($st === 'Taken'): ?>
                  <span class="muted" style="font-size:0.85rem;">Completed</span>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
