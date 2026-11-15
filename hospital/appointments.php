<?php
/**
 * appointments.php - Hospital Appointments Management
 * 
 * Lists bookings assigned to the authenticated hospital.
 * Hospital staff can filter appointments by status and update 'Approved'
 * bookings to 'Vaccinated' or 'Not Vaccinated'.
 * 
 * Tasks 1 & 5: Status update sets vaccinated_on = NOW() for Vaccinated,
 * and calls centralized shared queries in shared_queries.php.
 */

$role = 'hospital';
$title = 'Appointments';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard: ensure user is a logged-in hospital
require_hospital();
$hospitalId = (int)$_SESSION['hospital_id'];

// Handle status update POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bookingId = (int)($_POST['id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    if (in_array($newStatus, [ST_VACCINATED, ST_NOT_VACCINATED], true)) {
        // Task 1 & 5: Calls centralized function with SHARED TABLE comment
        // Enforces: own hospital AND status='Approved'; sets vaccinated_on = NOW() if Vaccinated
        $updated = shared_hospital_update_booking_status($bookingId, $hospitalId, $newStatus);

        if ($updated) {
            flash('success', "Appointment #$bookingId successfully marked as $newStatus.");
        } else {
            flash('error', 'Appointment could not be updated. Verify that the booking is currently Approved and belongs to this hospital.');
        }
    } else {
        flash('error', 'Invalid status selection.');
    }
    redirect('hospital/appointments.php');
}

// Determine active status tab filter
$filter = $_GET['f'] ?? 'Approved';
if (!in_array($filter, ['Approved', 'Vaccinated', 'Not Vaccinated'], true)) {
    $filter = 'Approved';
}

// Task 5: Fetch appointments via centralized shared query
$rows = shared_get_hospital_appointments($hospitalId, $filter);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Hospital Appointments</h1>
  <p class="muted">Manage scheduled child vaccinations and confirm administration of doses.</p>
</div>

<!-- Status Filter Tabs -->
<div class="tabs reveal">
  <?php 
    $tabs = [
      'Approved'       => 'Pending Visit (Approved)',
      'Vaccinated'     => 'Completed (Vaccinated)',
      'Not Vaccinated' => 'Missed (Not Vaccinated)'
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
  <?php if (empty($rows)): ?>
    <div style="text-align:center; padding:30px 10px;">
      <p class="muted">No appointments found under "<?= e($tabs[$filter] ?? $filter) ?>".</p>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Appointment Date</th>
            <th>Child Info</th>
            <th>Child Age</th>
            <th>Vaccine</th>
            <th>Parent Contact</th>
            <th>Status</th>
            <?php if ($filter === 'Vaccinated'): ?>
              <th>Administered At</th>
            <?php endif; ?>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <strong><?= e($r['booking_date']) ?></strong>
                <?php if (!empty($r['booking_time'])): ?>
                  <span class="muted" style="display:block; font-size:0.85rem;">
                    Slot: <?= e(substr($r['booking_time'], 0, 5)) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($r['child']) ?></strong>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  <?= e($r['gender']) ?> &middot; DOB: <?= e($r['dob']) ?>
                </span>
              </td>
              <td><?= e(age_text($r['dob'])) ?></td>
              <td><strong><?= e($r['vaccine']) ?></strong></td>
              <td>
                <?= e($r['parent']) ?>
                <span class="muted" style="display:block; font-size:0.85rem;">
                  &#128222; <?= e($r['phone']) ?>
                </span>
              </td>
              <td><?= badge($r['status']) ?></td>
              <?php if ($filter === 'Vaccinated'): ?>
                <td class="muted" style="font-size:0.85rem;">
                  <?= !empty($r['vaccinated_on']) ? e($r['vaccinated_on']) : e($r['booking_date']) ?>
                </td>
              <?php endif; ?>
              <td class="actions">
                <?php if ($r['status'] === ST_APPROVED): ?>
                  <!-- Mark Vaccinated Form -->
                  <form method="post" action="appointments.php" style="display:inline; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status" value="Vaccinated">
                    <button type="submit" class="btn btn-sm">
                      &#10003; Vaccinated
                    </button>
                  </form>

                  <!-- Mark Not Vaccinated Form -->
                  <form method="post" action="appointments.php" style="display:inline; margin:0;" onsubmit="return confirm('Mark this appointment as Not Vaccinated?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status" value="Not Vaccinated">
                    <button type="submit" class="btn btn-sm btn-danger">
                      &#10007; Not Vaccinated
                    </button>
                  </form>
                <?php else: ?>
                  <span class="muted" style="font-size:0.85rem;">No action needed</span>
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
