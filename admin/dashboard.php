<?php
/**
 * dashboard.php - Admin Command Overview
 * 
 * Displays system-wide KPIs, pending parent booking requests (quick approve/reject
 * via sp_review_appointment), and newly registered inactive hospitals awaiting approval.
 */

$role = 'admin';
$title = 'Admin Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();
$adminId = (int)$_SESSION['admin_id'];

$counts           = shared_admin_get_dashboard_counts();
$pendingBookings  = array_slice(shared_admin_get_bookings('pending'), 0, 8);
$allHospitals     = shared_admin_get_all_hospitals();
$inactiveHospitals = array_values(array_filter($allHospitals, static fn($h) => $h['status'] === 'inactive'));

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Welcome, <?= e($_SESSION['admin_name'] ?? 'Administrator') ?></h1>
  <p class="muted">System-wide immunization oversight: booking approvals, facility activation, vaccine inventory, and reports.</p>
</div>

<!-- System KPI Cards -->
<div class="stats">
  <div class="stat reveal">
    <b data-count="<?= $counts['pending_bookings'] ?>">0</b>
    <span>Pending Bookings</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['active_children'] ?>">0</b>
    <span>Active Children</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['active_hospitals'] ?>">0</b>
    <span>Active Hospitals</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['inactive_hospitals'] ?>">0</b>
    <span>Awaiting Hospital Approval</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['completed_records'] ?>">0</b>
    <span>Completed Vaccinations</span>
  </div>
</div>

<!-- Pending Appointment Requests -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="margin:0;">Pending Appointment Requests</h2>
    <a class="btn btn-sm btn-ghost" href="bookings.php">View All Bookings &rarr;</a>
  </div>

  <?php if (empty($pendingBookings)): ?>
    <p class="muted">No pending booking requests right now.</p>
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
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingBookings as $b): ?>
            <tr>
              <td><strong>#<?= (int)$b['appointment_id'] ?></strong></td>
              <td>
                <strong><?= date('d-M-Y', strtotime($b['appointment_date'])) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= substr($b['appointment_time'], 0, 5) ?></span>
              </td>
              <td>
                <strong><?= e($b['child_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= e($b['parent_name']) ?> (<?= e($b['parent_phone']) ?>)</span>
              </td>
              <td>
                <?= e($b['vaccine_name']) ?>
                <span class="muted" style="display:block; font-size:0.82rem;">Dose <?= (int)$b['dose_number'] ?></span>
              </td>
              <td><?= e($b['hospital_name']) ?> (<?= e($b['city']) ?>)</td>
              <td>
                <a class="btn btn-sm" href="bookings.php?f=pending">Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Hospitals Awaiting Activation -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="margin:0;">Hospitals Awaiting Activation</h2>
    <a class="btn btn-sm btn-ghost" href="hospitals.php">Manage All Hospitals &rarr;</a>
  </div>

  <?php if (empty($inactiveHospitals)): ?>
    <p class="muted">All registered hospitals are currently active.</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Hospital Name</th>
            <th>City &amp; Address</th>
            <th>Email &amp; Phone</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inactiveHospitals as $h): ?>
            <tr>
              <td><strong><?= e($h['hospital_name']) ?></strong></td>
              <td><?= e($h['city']) ?> — <span class="muted"><?= e($h['address']) ?></span></td>
              <td><?= e($h['email']) ?><br><span class="muted" style="font-size:0.82rem;"><?= e($h['phone'] ?? '—') ?></span></td>
              <td>
                <a class="btn btn-sm" href="hospitals.php?edit=<?= (int)$h['hospital_id'] ?>">Activate / Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
