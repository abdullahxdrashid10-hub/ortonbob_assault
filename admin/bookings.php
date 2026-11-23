<?php
/**
 * bookings.php - Admin Appointment Requests & Booking Details
 * 
 * Lists all bookings from v_booking_details filtered by status.
 * Allows Admin to Approve or Reject pending requests by invoking official
 * stored procedure sp_review_appointment(appointment_id, admin_id, decision, remarks).
 */

$role = 'admin';
$title = 'Manage Bookings';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();
$adminId = (int)$_SESSION['admin_id'];

// Handle Approve / Reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action'] ?? '';
    $aptId    = (int)($_POST['appointment_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($action === 'review' && $aptId > 0 && in_array($decision, ['approved', 'rejected'], true)) {
        if (mb_strlen($remarks) > 255) {
            flash('error', 'Admin remarks cannot exceed 255 characters.');
        } else {
            $res = shared_admin_review_appointment($aptId, $adminId, $decision, $remarks);
            flash($res['success'] ? 'success' : 'error', $res['message']);
        }
    } else {
        flash('error', 'Invalid review action.');
    }
    redirect('admin/bookings.php?f=' . urlencode($_POST['current_filter'] ?? 'pending'));
}

$filter = strtolower(trim($_GET['f'] ?? 'pending'));
$validFilters = ['pending', 'approved', 'completed', 'rejected', 'cancelled', 'all'];
if (!in_array($filter, $validFilters, true)) {
    $filter = 'pending';
}

$bookings = shared_admin_get_bookings($filter);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Booking Requests &amp; Details</h1>
  <p class="muted">Review parent appointment requests, approve or reject slots, and monitor completion across hospitals.</p>
</div>

<!-- Status Tabs -->
<div class="tabs reveal">
  <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $lbl): ?>
    <a class="tab <?= $filter === $k ? 'active' : '' ?>" href="?f=<?= urlencode($k) ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<section class="card reveal">
  <?php if (empty($bookings)): ?>
    <p class="muted" style="text-align:center; padding:28px 0;">No bookings found under "<?= ucfirst($filter) ?>".</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Appt #</th>
            <th>Date &amp; Time</th>
            <th>Child &amp; Parent</th>
            <th>Vaccine &amp; Dose</th>
            <th>Hospital</th>
            <th>Status</th>
            <th>Notes / Remarks</th>
            <th>Review Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <?php
              $st = strtolower($b['status']);
              $badgeCls = match($st) {
                  'approved'  => 'badge-primary',
                  'completed' => 'badge-success',
                  'rejected'  => 'badge-danger',
                  'cancelled' => 'badge-muted',
                  default     => 'badge-warning',
              };
            ?>
            <tr>
              <td><strong>#<?= (int)$b['appointment_id'] ?></strong></td>
              <td>
                <strong><?= date('d-M-Y', strtotime($b['appointment_date'])) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= substr($b['appointment_time'], 0, 5) ?></span>
              </td>
              <td>
                <strong><?= e($b['child_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;">Parent: <?= e($b['parent_name']) ?> (<?= e($b['parent_phone']) ?>)</span>
              </td>
              <td>
                <strong><?= e($b['vaccine_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;">Dose <?= (int)$b['dose_number'] ?></span>
              </td>
              <td>
                <?= e($b['hospital_name']) ?>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= e($b['city']) ?></span>
              </td>
              <td><span class="badge <?= $badgeCls ?>"><?= ucfirst($st) ?></span></td>
              <td style="max-width:190px; font-size:0.85rem;">
                <?php if (!empty($b['parent_note'])): ?><div><em>Parent:</em> <?= e($b['parent_note']) ?></div><?php endif; ?>
                <?php if (!empty($b['admin_remarks'])): ?><div style="color:var(--primary);"><em>Admin:</em> <?= e($b['admin_remarks']) ?></div><?php endif; ?>
                <?php if (empty($b['parent_note']) && empty($b['admin_remarks'])): ?><span class="muted">—</span><?php endif; ?>
              </td>
              <td>
                <?php if ($st === 'pending'): ?>
                  <details style="position:relative;">
                    <summary class="btn btn-sm" style="list-style:none; cursor:pointer;">Review</summary>
                    <div class="action-pop">
                      <form method="post" action="bookings.php" class="form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="review">
                        <input type="hidden" name="appointment_id" value="<?= (int)$b['appointment_id'] ?>">
                        <input type="hidden" name="current_filter" value="<?= e($filter) ?>">

                        <label style="font-size:0.85rem;">
                          Remarks (Optional for approve, recommended for reject):
                          <input type="text" name="remarks" maxlength="255" placeholder="e.g. Slot confirmed / Choose another date">
                        </label>

                        <div style="display:flex; gap:8px; margin-top:6px;">
                          <button type="submit" name="decision" value="approved" class="btn btn-sm" style="flex:1;">Approve</button>
                          <button type="submit" name="decision" value="rejected" class="btn btn-sm btn-danger" style="flex:1;" onclick="return confirm('Reject appointment #<?= (int)$b['appointment_id'] ?>?');">Reject</button>
                        </div>
                      </form>
                    </div>
                  </details>
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
