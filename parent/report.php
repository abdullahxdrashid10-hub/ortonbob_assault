<?php
/**
 * report.php - Completed Immunization Report & Certificate
 * 
 * Generates an official report of all completed vaccinations (status = 'Vaccinated')
 * for the parent's children.
 * Features:
 * - Filter records by specific child or all children
 * - Displays vaccine, administering hospital, and completion timestamp (vaccinated_on)
 * - Printable layout optimized via print stylesheet
 */

$role = 'parent';
$title = 'Vaccination Report';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch children belonging to this parent for the filter dropdown
// OWNERSHIP: Filtered strictly by parent_id
$stmtKids = db()->prepare("SELECT id, full_name, dob, gender FROM children WHERE parent_id = ? ORDER BY full_name ASC");
$stmtKids->execute([$parentId]);
$children = $stmtKids->fetchAll();

// Child filter from GET request
$filteredChildId = (int)($_GET['child_id'] ?? $_GET['child'] ?? 0);
$selectedChild = null;

if ($filteredChildId > 0) {
    // OWNERSHIP CHECK: Ensure child belongs to the logged-in parent
    foreach ($children as $c) {
        if ((int)$c['id'] === $filteredChildId) {
            $selectedChild = $c;
            break;
        }
    }
    if (!$selectedChild) {
        flash('error', 'Selected child not found or unauthorized.');
        redirect('parent/report.php');
    }
}

// Fetch completed records using centralized shared query
// Centralized in shared_queries.php (SHARED TABLE: bookings, vaccines, hospitals)
$completedVaccinations = shared_get_vaccination_report($parentId, $filteredChildId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Immunization Report</h1>
      <p class="muted">Official record of administered vaccines for healthcare and educational submissions.</p>
    </div>
    <div class="actions">
      <button type="button" class="btn btn-ghost" onclick="window.print()">
        &#128424; Print Report
      </button>
    </div>
  </div>
</div>

<!-- Child Filter Selector -->
<section class="card reveal" style="margin-bottom:20px;">
  <form method="get" action="report.php" class="select-bar">
    <label for="report-child-select" style="font-weight:600; color:var(--text); align-self:center;">
      Filter by Child:
    </label>
    <select id="report-child-select" name="child_id" onchange="this.form.submit()" style="max-width:300px;">
      <option value="0">-- All Registered Children --</option>
      <?php foreach ($children as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $filteredChildId) ? 'selected' : '' ?>>
          <?= e($c['full_name']) ?> (DOB: <?= e($c['dob']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm">Filter</button></noscript>
  </form>

  <?php if ($selectedChild): ?>
    <div style="font-size:0.95rem; margin-top:8px;">
      <strong>Filtered Record:</strong> <?= e($selectedChild['full_name']) ?> &middot;
      <strong>Gender:</strong> <?= e($selectedChild['gender']) ?> &middot;
      <strong>DOB:</strong> <?= e($selectedChild['dob']) ?> &middot;
      <strong>Age:</strong> <?= e(age_text($selectedChild['dob'])) ?>
    </div>
  <?php endif; ?>
</section>

<!-- Report Table / Official Certificate View -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:12px;">
    <h2>Administered Vaccines Record</h2>
    <span class="muted" style="font-size:0.85rem;">Generated: <?= date('F d, Y') ?></span>
  </div>

  <?php if (empty($completedVaccinations)): ?>
    <div style="text-align:center; padding:30px 10px;">
      <p class="muted">No completed vaccination records found for the selected criteria.</p>
      <p class="muted" style="font-size:0.85rem;">
        Once an appointment is marked as "Vaccinated" by the healthcare center, it will appear here.
      </p>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Date Administered</th>
            <th>Child Name</th>
            <th>Vaccine</th>
            <th>Administering Hospital</th>
            <th>Facility Location</th>
            <th>Official Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($completedVaccinations as $rec): ?>
            <?php 
              // Use vaccinated_on timestamp, or fallback to booking_date
              $dateAdministered = !empty($rec['vaccinated_on']) 
                  ? date('Y-m-d H:i', strtotime($rec['vaccinated_on']))
                  : $rec['booking_date'];
            ?>
            <tr>
              <td><strong><?= e($dateAdministered) ?></strong></td>
              <td>
                <strong><?= e($rec['child_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.8rem;">
                  DOB: <?= e($rec['child_dob']) ?>
                </span>
              </td>
              <td><strong><?= e($rec['vaccine_name']) ?></strong></td>
              <td><?= e($rec['hospital_name']) ?></td>
              <td class="muted"><?= e($rec['hospital_location']) ?></td>
              <td><span class="badge b-done">&#10003; Vaccinated</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Official Stamp Notice for printout -->
    <div style="margin-top:24px; padding-top:12px; border-top:1px dashed var(--line); font-size:0.85rem; color:var(--muted); display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
      <span>Parent: <?= e($_SESSION['parent_name'] ?? '') ?></span>
      <span>System: VaxCare E-Vaccination Management</span>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
