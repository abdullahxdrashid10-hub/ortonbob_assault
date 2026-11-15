<?php
/**
 * schedule.php - Child Vaccination Schedule View
 * 
 * Task D: Displays the official child_vaccinations schedule for an active child
 * belonging to the authenticated parent.
 * Joins child_vaccinations with vaccines:
 * - Shows vaccine name, dose_number, target disease, route, due_date, and status badge.
 * - Displays a "Book" link ONLY for 'upcoming' or 'missed' doses, passing child_vaccination_id.
 */

$role = 'parent';
$title = 'Vaccination Schedule';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch all active children belonging to this parent
$children = shared_get_parent_children($parentId);

// Determine selected child with strict ownership validation
$selectedChildId = (int)($_GET['child_id'] ?? ($children[0]['child_id'] ?? 0));
$currentChild = null;

if ($selectedChildId > 0) {
    foreach ($children as $c) {
        if ((int)$c['child_id'] === $selectedChildId) {
            $currentChild = $c;
            break;
        }
    }
    // If child ID was supplied but does not belong to this parent
    if (!$currentChild && !empty($children)) {
        flash('error', 'Selected child not found or does not belong to your account.');
        redirect('parent/schedule.php');
    }
}

// Fetch official schedule from child_vaccinations table
$scheduleItems = [];
if ($currentChild) {
    $scheduleItems = shared_get_child_vaccination_schedule((int)$currentChild['child_id'], $parentId);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Vaccination Schedule</h1>
  <p class="muted">Age-specific immunization schedule generated from the national pediatric guidelines.</p>
</div>

<?php if (empty($children)): ?>
  <section class="card reveal">
    <h2>No Active Children Found</h2>
    <p class="muted">You must add a child profile before viewing an immunization schedule.</p>
    <a class="btn" href="children.php">Add a Child Profile</a>
  </section>
<?php else: ?>

  <!-- Child Selection Bar -->
  <section class="card reveal">
    <form method="get" action="schedule.php" class="select-bar">
      <label for="child_select" style="font-weight:600; color:var(--text); align-self:center;">
        Select Child:
      </label>
      <select id="child_select" name="child_id" onchange="this.form.submit()" style="max-width:320px;">
        <?php foreach ($children as $c): ?>
          <option value="<?= (int)$c['child_id'] ?>" <?= ((int)$c['child_id'] === (int)$currentChild['child_id']) ? 'selected' : '' ?>>
            <?= e($c['full_name']) ?> (DOB: <?= e($c['date_of_birth']) ?>, <?= e(age_text($c['date_of_birth'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm">View Schedule</button></noscript>
    </form>

    <div style="margin-top:12px; font-size:0.95rem; line-height:1.6;">
      <strong>Child:</strong> <?= e($currentChild['full_name']) ?> &middot;
      <strong>Gender:</strong> <?= e(ucfirst($currentChild['gender'])) ?> &middot;
      <strong>DOB:</strong> <?= e($currentChild['date_of_birth']) ?> &middot;
      <strong>Current Age:</strong> <?= e(age_text($currentChild['date_of_birth'])) ?>
      <?php if (!empty($currentChild['blood_group']) && $currentChild['blood_group'] !== 'unknown'): ?>
        &middot; <strong>Blood:</strong> <?= e($currentChild['blood_group']) ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Vaccination Schedule Timeline Table -->
  <section class="card reveal">
    <h2>Immunization Doses Timeline (<?= count($scheduleItems) ?> Doses Scheduled)</h2>

    <?php if (empty($scheduleItems)): ?>
      <p class="muted">No scheduled doses found for this child profile.</p>
    <?php else: ?>
      <div class="scroll">
        <table class="table schedule-table">
          <thead>
            <tr>
              <th>Dose #</th>
              <th>Vaccine Name</th>
              <th>Disease Prevented</th>
              <th>Route</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scheduleItems as $dose): ?>
              <?php 
                $st = strtolower($dose['status']); 
                $canBook = ($st === CV_UPCOMING || $st === CV_MISSED);
              ?>
              <tr>
                <td><strong>Dose <?= (int)$dose['dose_number'] ?></strong></td>
                <td>
                  <strong><?= e($dose['vaccine_name']) ?></strong>
                  <?php if (!empty($dose['short_code'])): ?>
                    <span class="muted" style="font-size:0.8rem;">(<?= e($dose['short_code']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="muted"><?= e($dose['disease_prevented']) ?></td>
                <td class="muted"><?= e($dose['route'] ?? 'Standard') ?></td>
                <td>
                  <strong><?= e($dose['due_date']) ?></strong>
                  <?php if ($st === CV_COMPLETED && !empty($dose['completed_date'])): ?>
                    <span class="muted" style="display:block; font-size:0.8rem; color:var(--ok);">
                      Given: <?= e($dose['completed_date']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= badge($st) ?></td>
                <td>
                  <?php if ($canBook): ?>
                    <!-- Task D: Book link passing child_vaccination_id (booking page in 3B) -->
                    <a class="btn btn-sm" href="book.php?child_vaccination_id=<?= (int)$dose['child_vaccination_id'] ?>">
                      Book
                    </a>
                  <?php elseif ($st === CV_BOOKED || $st === CV_REQUESTED): ?>
                    <span class="badge b-approved">Active</span>
                  <?php elseif ($st === CV_COMPLETED): ?>
                    <span class="badge b-done">&#10003; Done</span>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
