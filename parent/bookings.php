<?php
/**
 * bookings.php - Parent Appointments & Bookings
 * 
 * 3B Item 3: Built for the team's official schema:
 * - Displays appointments submitted by the logged-in parent via `appointments` table.
 * - Table columns: Appointment ID, Date & Time, Child, Vaccine & Dose, Hospital, Status badge, Remarks.
 * - Supports cancellation of pending requests (shared_cancel_parent_appointment()) with CSRF protection.
 */

$role = 'parent';
$title = 'My Bookings';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];
$userId   = (int)$_SESSION['user_id'];

// Handle Appointment Cancellation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel') {
        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $ok = shared_cancel_parent_appointment($aptId, $parentId, $userId);
        if ($ok) {
            flash('success', "Appointment #$aptId has been cancelled. Your child's schedule dose is open for re-booking.");
        } else {
            flash('error', "Appointment could not be cancelled. Only 'pending' appointments belonging to your account can be cancelled.");
        }
        redirect('parent/bookings.php');
    }
}

// Fetch all appointments for this parent and their status history trails
$appointments  = shared_get_parent_appointments($parentId);
$historyByAppt = shared_get_appointment_history_for_parent($parentId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>My Bookings</h1>
      <p class="muted">Track the approval and administration status of your children's vaccination appointments.</p>
    </div>
    <div>
      <a class="btn btn-sm" href="book.php">+ Book Appointment</a>
    </div>
  </div>
</div>

<section class="card reveal">
  <?php if (empty($appointments)): ?>
    <div style="text-align:center; padding:36px 12px;">
      <p class="muted">You have no appointment bookings on record yet.</p>
      <a class="btn btn-sm" href="book.php">Book a Vaccination Visit</a>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Appt #</th>
            <th>Visit Date &amp; Time</th>
            <th>Child Info</th>
            <th>Vaccine &amp; Dose</th>
            <th>Healthcare Facility</th>
            <th>Status</th>
            <th>Notes / Remarks</th>
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
              </td>
              <td>
                <strong><?= e($a['vaccine_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  Dose <?= (int)$a['dose_number'] ?> &bull; <?= e($a['short_code']) ?>
                </span>
              </td>
              <td>
                <strong><?= e($a['hospital_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.8rem;">
                  <?= e($a['hospital_city']) ?> (<?= e($a['hospital_phone']) ?>)
                </span>
              </td>
              <td>
                <span class="badge <?= $badgeClass ?>">
                  <?= ucfirst($status) ?>
                </span>
                <?php $trail = $historyByAppt[(int)$a['appointment_id']] ?? []; ?>
                <?php if (!empty($trail)): ?>
                  <div class="history-trail" aria-label="Status history">
                    <?php foreach ($trail as $idx => $step): ?>
                      <?php if ($idx > 0): ?><span class="history-arrow">&rarr;</span><?php endif; ?>
                      <span class="history-step" title="<?= e(($step['remarks'] ? $step['remarks'] . ' · ' : '') . date('d-M-Y H:i', strtotime($step['changed_at']))) ?>">
                        <?= e(ucfirst($step['new_status'])) ?> (<?= date('d-M', strtotime($step['changed_at'])) ?>)
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="max-width:200px; font-size:0.85rem;">
                <?php if (!empty($a['parent_note'])): ?>
                  <div><em>Note:</em> <?= e($a['parent_note']) ?></div>
                <?php endif; ?>
                <?php if (!empty($a['admin_remarks'])): ?>
                  <div style="color:var(--primary); margin-top:2px;"><em>Remarks:</em> <?= e($a['admin_remarks']) ?></div>
                <?php endif; ?>
                <?php if (empty($a['parent_note']) && empty($a['admin_remarks'])): ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($status === 'pending'): ?>
                  <form method="post" action="bookings.php" onsubmit="return confirm('Are you sure you want to cancel appointment #<?= (int)$a['appointment_id'] ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger); border-color:var(--danger);">
                      Cancel
                    </button>
                  </form>
                <?php elseif ($status === 'completed'): ?>
                  <a href="report.php?child_id=<?= (int)$a['child_id'] ?>" class="btn btn-sm btn-ghost">
                    View Record
                  </a>
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
