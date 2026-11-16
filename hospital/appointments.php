<?php
/**
 * appointments.php - Hospital Appointments & Vaccination Administration
 * 
 * 3B Items 8 & 9: Built for the team's official schema:
 * - Lists appointments assigned to this hospital facility (filtered by approved, completed, all).
 * - Action: For approved appointments, hospital staff records vaccine administration by calling
 *   official stored procedure sp_complete_appointment() with batch_number, administered_by, and remarks.
 * - Enforces CSRF check and strict column length validation.
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

        if ($aptId <= 0) {
            flash('error', 'Invalid appointment selected.');
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
    }
}

// Active filter tab
$filter = $_GET['f'] ?? 'approved';
if (!in_array($filter, ['approved', 'completed', 'all'], true)) {
    $filter = 'approved';
}

// Fetch appointments for this hospital
$appointments = shared_get_hospital_appointments_list($hospitalId, $filter);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Hospital Appointments</h1>
      <p class="muted">Manage scheduled patient visits and record clinical vaccine administration.</p>
    </div>
    <div>
      <a href="inventory.php" class="btn btn-sm btn-ghost">&#128230; Manage Vaccine Stock</a>
    </div>
  </div>
</div>

<!-- Filter Tabs -->
<div class="tabs reveal">
  <?php 
    $tabs = [
      'approved'  => 'Approved (Pending Visit)',
      'completed' => 'Completed (Administered)',
      'all'       => 'All Appointments'
    ];
    foreach ($tabs as $k => $label): 
  ?>
    <a class="tab <?= $filter === $k ? 'active' : '' ?>" href="?f=<?= urlencode($k) ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Appointments Table Card -->
<section class="card reveal">
  <?php if (empty($appointments)): ?>
    <div style="text-align:center; padding:36px 12px;">
      <p class="muted">No appointments found under "<?= e($tabs[$filter] ?? $filter) ?>".</p>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Appt #</th>
            <th>Scheduled Visit</th>
            <th>Child Info</th>
            <th>Vaccine &amp; Dose</th>
            <th>Parent Contact</th>
            <th>Status</th>
            <th>Action / Details</th>
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
                  DOB: <?= date('d-M-Y', strtotime($a['date_of_birth'])) ?> (<?= ucfirst($a['gender']) ?>)
                </span>
                <?php if (!empty($a['allergies'])): ?>
                  <span class="badge badge-danger" style="font-size:0.75rem; margin-top:2px;">
                    Allergies: <?= e($a['allergies']) ?>
                  </span>
                <?php endif; ?>
              </td>
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
              <td>
                <span class="badge <?= $badgeClass ?>">
                  <?= ucfirst($status) ?>
                </span>
              </td>
              <td>
                <?php if ($status === 'approved'): ?>
                  <!-- Expandable Form to Record Administration -->
                  <details style="position:relative;">
                    <summary class="btn btn-sm" style="list-style:none; cursor:pointer;">
                      &#128137; Administer
                    </summary>
                    <div style="background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:12px; margin-top:8px; min-width:260px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                      <form method="post" action="appointments.php" class="form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete_appointment">
                        <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                        <input type="hidden" name="current_filter" value="<?= e($filter) ?>">

                        <label style="font-size:0.85rem;">
                          Batch Number:
                          <input type="text" name="batch_number" required maxlength="60" placeholder="e.g. BATCH-2026-90" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <label style="font-size:0.85rem; margin-top:6px;">
                          Administered By:
                          <input type="text" name="administered_by" required maxlength="120" value="<?= e($_SESSION['hospital_name'] ?? 'Staff Nurse') ?>" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <label style="font-size:0.85rem; margin-top:6px;">
                          Clinical Remarks (optional):
                          <input type="text" name="remarks" maxlength="255" placeholder="e.g. Well-tolerated, no adverse signs" style="padding:6px; font-size:0.85rem;">
                        </label>

                        <div style="margin-top:10px;">
                          <button type="submit" class="btn btn-sm" style="width:100%;">Confirm Vaccination</button>
                        </div>
                      </form>
                    </div>
                  </details>
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
