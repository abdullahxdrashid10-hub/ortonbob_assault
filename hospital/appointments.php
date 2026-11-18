<?php
/**
 * appointments.php - Hospital Appointments Management & Administration
 * 
 * 3B Item 8 & 9:
 * - Lists appointments assigned to this hospital with tabs: Approved (to do) and Completed.
 * - Displays child name, age, vaccine, dose number, parent name and phone, appointment date,
 *   time, and parent note.
 * - For Approved rows, provides a Complete form: batch_number (required), administered_by (required),
 *   remarks. Pre-checks in PHP that the appointment belongs to MY hospital and is approved,
 *   then calls sp_complete_appointment(appointment_id, session user_id, batch, administered_by, remarks).
 * - "Not Vaccinated" is protected behind FEATURE_NOT_VACCINATED = false (stubbed in shared_queries.php).
 */

$role = 'hospital';
$title = 'Hospital Appointments';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_hospital();
$hospitalId = (int)$_SESSION['hospital_id'];
$userId     = (int)$_SESSION['user_id'];

// Handle POST: Administer Vaccine / Complete Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'complete_appointment') {
        $aptId          = (int)($_POST['appointment_id'] ?? 0);
        $batchNumber    = trim($_POST['batch_number'] ?? '');
        $administeredBy = trim($_POST['administered_by'] ?? '');
        $remarks        = trim($_POST['remarks'] ?? '') ?: null;

        // Requirement 8: Pre-check in PHP that the appointment belongs to MY hospital and is approved
        $apt = shared_get_hospital_appointment_by_id($aptId, $hospitalId);

        if (!$apt) {
            flash('error', 'Appointment not found or does not belong to your healthcare facility.');
        } elseif ($apt['status'] !== 'approved') {
            flash('error', 'Only approved appointments can be completed.');
        } elseif ($batchNumber === '' || mb_strlen($batchNumber) > 60) {
            flash('error', 'Vaccine batch number is required and cannot exceed 60 characters.');
        } elseif ($administeredBy === '' || mb_strlen($administeredBy) > 120) {
            flash('error', 'Administrator name (nurse / doctor) is required and cannot exceed 120 characters.');
        } elseif ($remarks !== null && mb_strlen($remarks) > 255) {
            flash('error', 'Clinical remarks cannot exceed 255 characters.');
        } else {
            // Call official stored procedure sp_complete_appointment via shared query
            $res = shared_complete_appointment(
                $aptId,
                $userId,
                $batchNumber,
                $administeredBy,
                $remarks
            );

            if ($res['success']) {
                flash('success', $res['message']);
            } else {
                flash('error', $res['message']);
            }
        }
        redirect('hospital/appointments.php?f=' . urlencode($_POST['current_filter'] ?? 'approved'));
    } elseif ($action === 'not_vaccinated') {
        // Requirement 9: Call stub function
        try {
            shared_hospital_mark_not_vaccinated((int)($_POST['appointment_id'] ?? 0), $hospitalId);
        } catch (BadMethodCallException $e) {
            flash('error', $e->getMessage());
        }
        redirect('hospital/appointments.php?f=approved');
    }
}

// Active tab filter: Approved (to do) or Completed
$filter = strtolower(trim($_GET['f'] ?? 'approved'));
if (!in_array($filter, ['approved', 'completed'], true)) {
    $filter = 'approved';
}

// Fetch appointments for this hospital + Today KPI summary strip
$appointments = shared_get_hospital_appointments_list($hospitalId, $filter);
$todaySummary = shared_get_hospital_today_summary($hospitalId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Hospital Appointments</h1>
      <p class="muted">Manage scheduled patient visits and record clinical vaccine administration.</p>
    </div>
    <div>
      <a href="stock.php" class="btn btn-sm btn-ghost">&#128230; Vaccine Stock</a>
    </div>
  </div>
</div>

<!-- Hospital "Today" Summary Strip -->
<div class="stats" style="margin-top:16px;">
  <div class="stat reveal">
    <b data-count="<?= $todaySummary['approved_today'] ?>">0</b>
    <span>Today's Approved Visits</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $todaySummary['completed_today'] ?>">0</b>
    <span>Completed Today</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $todaySummary['low_stock_count'] ?>">0</b>
    <span>
      Low / Out-of-Stock (&lt;10)
      <?php if ($todaySummary['low_stock_count'] > 0): ?>
        &middot; <a href="stock.php">Restock &rarr;</a>
      <?php endif; ?>
    </span>
  </div>
</div>

<!-- Tabs: Approved (to do) and Completed -->
<div class="tabs reveal">
  <a class="tab <?= $filter === 'approved' ? 'active' : '' ?>" href="?f=approved">
    Approved (To Do)
  </a>
  <a class="tab <?= $filter === 'completed' ? 'active' : '' ?>" href="?f=completed">
    Completed
  </a>
</div>

<!-- Appointments Table Card -->
<section class="card reveal">
  <?php if (empty($appointments)): ?>
    <div style="text-align:center; padding:36px 12px;">
      <p class="muted">No appointments found under "<?= ucfirst($filter) ?>".</p>
      <?php if ($filter === 'approved'): ?>
        <p class="muted" style="font-size:0.88rem; margin-bottom:16px;">Approved visits appear here once an administrator confirms parent bookings. Make sure your vaccine stock is up to date.</p>
        <a class="btn btn-sm" href="stock.php">Check Vaccine Stock &rarr;</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Appt #</th>
            <th>Date &amp; Time</th>
            <th>Child Name</th>
            <th>Age</th>
            <th>Vaccine &amp; Dose</th>
            <th>Parent Contact</th>
            <th>Parent Note</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <?php 
              $status = strtolower($a['status']);
              $badgeClass = match($status) {
                  'approved'  => 'badge-primary',
                  'completed' => 'badge-success',
                  'rejected'  => 'badge-danger',
                  'cancelled' => 'badge-muted',
                  default     => 'badge-warning',
              };

              // Compute age human-readably
              $dob = new DateTime($a['date_of_birth']);
              $now = new DateTime();
              $diff = $now->diff($dob);
              if ($diff->y > 0) {
                  $ageStr = $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m > 0 ? ', ' . $diff->m . ' mo' : '');
              } elseif ($diff->m > 0) {
                  $ageStr = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
              } else {
                  $ageStr = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
              }
            ?>
            <tr>
              <td><strong>#<?= (int)$a['appointment_id'] ?></strong></td>
              <td>
                <strong><?= date('d-M-Y', strtotime($a['appointment_date'])) ?></strong>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  <?= substr($a['appointment_time'], 0, 5) ?>
                </span>
              </td>
              <td>
                <strong><?= e($a['child_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.8rem;">
                  (<?= ucfirst($a['gender']) ?>, <?= e($a['blood_group'] ?? 'unknown') ?>)
                </span>
                <?php if (!empty($a['allergies'])): ?>
                  <span class="badge badge-danger" style="font-size:0.75rem; margin-top:2px;">
                    Allergies: <?= e($a['allergies']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td><?= e($ageStr) ?></td>
              <td>
                <strong><?= e($a['vaccine_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  Dose <?= (int)$a['dose_number'] ?> &bull; <?= e($a['disease_prevented']) ?>
                </span>
              </td>
              <td>
                <?= e($a['parent_name']) ?>
                <span class="muted" style="display:block; font-size:0.8rem;">
                  &#128222; <?= e($a['parent_phone']) ?>
                </span>
              </td>
              <td style="max-width:180px; font-size:0.85rem;">
                <?= !empty($a['parent_note']) ? e($a['parent_note']) : '<span class="muted">—</span>' ?>
              </td>
              <td>
                <span class="badge <?= $badgeClass ?>">
                  <?= ucfirst($status) ?>
                </span>
              </td>
              <td>
                <?php if ($status === 'approved'): ?>
                  <!-- Complete Form for Approved Rows -->
                  <details style="position:relative;">
                    <summary class="btn btn-sm" style="list-style:none; cursor:pointer;">
                      &#128137; Complete
                    </summary>
                    <div style="background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:12px; margin-top:8px; min-width:270px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                      <form method="post" action="appointments.php" class="form" onsubmit="return confirm('Mark appointment #<?= (int)$a['appointment_id'] ?> as completed and deduct 1 dose from stock?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete_appointment">
                        <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                        <input type="hidden" name="current_filter" value="<?= e($filter) ?>">

                        <label style="font-size:0.85rem;">
                          Batch Number (Required):
                          <input type="text" name="batch_number" required maxlength="60" placeholder="e.g. BATCH-2026-90" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <label style="font-size:0.85rem; margin-top:6px;">
                          Administered By (Required):
                          <input type="text" name="administered_by" required maxlength="120" value="<?= e($_SESSION['hospital_name'] ?? 'Staff Nurse') ?>" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <label style="font-size:0.85rem; margin-top:6px;">
                          Clinical Remarks (Optional):
                          <input type="text" name="remarks" maxlength="255" placeholder="e.g. Well-tolerated, no adverse signs" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <div style="margin-top:10px;">
                          <button type="submit" class="btn btn-sm" style="width:100%;">Record Completed Vaccination</button>
                        </div>
                      </form>
                    </div>
                  </details>

                  <?php if (FEATURE_NOT_VACCINATED): ?>
                    <form method="post" action="appointments.php" style="display:inline; margin-left:4px;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="not_vaccinated">
                      <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger); border-color:var(--danger);">Not Vaccinated</button>
                    </form>
                  <?php endif; ?>

                <?php elseif ($status === 'completed'): ?>
                  <span class="muted" style="font-size:0.85rem;">
                    Completed <?= !empty($a['completed_at']) ? date('d-M-Y', strtotime($a['completed_at'])) : '' ?>
                  </span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
