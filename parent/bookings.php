<?php
/**
 * bookings.php - My Appointments & Bookings
 * 
 * Displays the complete history of vaccination bookings submitted by the parent.
 * Includes child name, vaccine, assigned hospital, appointment date/time, and
 * live lifecycle status (Pending, Approved, Rejected, Vaccinated, Not Vaccinated)
 * rendered with standardized color badges.
 */

$role = 'parent';
$title = 'My Bookings';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch all bookings for this parent's children using centralized query
// Centralized in shared_queries.php (SHARED TABLE: bookings, vaccines, hospitals)
$bookings = shared_get_parent_bookings($parentId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>My Bookings</h1>
      <p class="muted">Track status updates for your children's scheduled vaccination visits.</p>
    </div>
    <div>
      <a class="btn btn-sm" href="book.php">+ Book New Appointment</a>
    </div>
  </div>
</div>

<section class="card reveal">
  <?php if (empty($bookings)): ?>
    <div style="text-align:center; padding:30px 10px;">
      <p class="muted">You have no vaccination bookings on record yet.</p>
      <a class="btn btn-sm" href="hospitals.php">Find a Hospital &amp; Book</a>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Appointment Date</th>
            <th>Child</th>
            <th>Vaccine</th>
            <th>Hospital &amp; Location</th>
            <th>Status</th>
            <th>Submitted On</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td>#<?= (int)$b['id'] ?></td>
              <td>
                <strong><?= e($b['booking_date']) ?></strong>
                <?php if (!empty($b['booking_time'])): ?>
                  <span class="muted" style="display:block; font-size:0.85rem;">
                    <?= e(substr($b['booking_time'], 0, 5)) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td><strong><?= e($b['child_name']) ?></strong></td>
              <td><?= e($b['vaccine_name']) ?></td>
              <td>
                <?= e($b['hospital_name']) ?>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  <?= e($b['hospital_location']) ?>
                </span>
              </td>
              <td>
                <?= badge($b['status']) ?>
                <?php if ($b['status'] === ST_PENDING): ?>
                  <span class="muted" style="display:block; font-size:0.75rem;">Awaiting Admin</span>
                <?php elseif ($b['status'] === ST_APPROVED): ?>
                  <span class="muted" style="display:block; font-size:0.75rem; color:var(--a);">Ready for Visit</span>
                <?php elseif ($b['status'] === ST_REJECTED): ?>
                  <span class="muted" style="display:block; font-size:0.75rem; color:var(--bad);">Slot Unavailable</span>
                <?php elseif ($b['status'] === ST_VACCINATED): ?>
                  <span class="muted" style="display:block; font-size:0.75rem; color:var(--ok);">Administered</span>
                <?php endif; ?>
              </td>
              <td class="muted" style="font-size:0.85rem;">
                <?= e(date('M d, Y', strtotime($b['created_at']))) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
