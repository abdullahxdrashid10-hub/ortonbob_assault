<?php
/**
 * book.php - Book Vaccination Appointment
 * 
 * Task F5 & 3B Item 2: Rebuilt completely for the team's official database schema:
 * - Links an appointment directly to a child's scheduled dose (child_vaccinations row).
 * - Enforces the 30-day booking window rule: status in ('upcoming', 'missed') and due_date <= CURDATE() + 30 days.
 * - Restricts hospital dropdown strictly to active hospitals with stock for the specific vaccine.
 * - Validates appointment date (today or future) and time slot within hospital operating hours.
 * - Invokes official stored procedure sp_book_appointment() via shared_book_appointment().
 */

$role = 'parent';
$title = 'Book Appointment';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Get target child_vaccination_id from request
$cvId = (int)($_POST['child_vaccination_id'] ?? $_GET['child_vaccination_id'] ?? 0);
$selectedHospitalId = (int)($_POST['hospital_id'] ?? $_GET['hospital_id'] ?? 0);

// Load bookable doses (upcoming/missed within 30 days) across active children of this parent
$bookableDoses = shared_get_upcoming_doses_for_parent($parentId);

// If no dose specified in request, default to first available bookable dose if exists
if ($cvId <= 0 && !empty($bookableDoses)) {
    $cvId = (int)$bookableDoses[0]['child_vaccination_id'];
}

// Load and verify target dose
$dose = null;
if ($cvId > 0) {
    $dose = shared_get_child_vaccination_dose($cvId, $parentId);
}

// Server-side eligibility validation for the selected dose
$eligibilityError = null;
if ($cvId > 0 && !$dose) {
    $eligibilityError = 'The selected vaccination dose was not found or does not belong to your active children.';
} elseif ($dose) {
    $today = date('Y-m-d');
    $maxAllowedDate = date('Y-m-d', strtotime('+30 days'));

    if (!in_array($dose['status'], [CV_UPCOMING, CV_MISSED], true)) {
        $eligibilityError = "This dose cannot be booked because its current status is '" . ucfirst($dose['status']) . "'.";
    } elseif ($dose['due_date'] > $maxAllowedDate) {
        // Enforce 30-day eligibility rule
        $eligibilityError = "This dose is scheduled for " . date('d-M-Y', strtotime($dose['due_date'])) . 
                            ". Bookings only open 30 days before the due date.";
    }
}

// Load hospitals currently stocking this vaccine
$stockingHospitals = [];
if ($dose && !$eligibilityError) {
    $stockingHospitals = shared_get_hospitals_stocking_vaccine((int)$dose['vaccine_id']);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($eligibilityError) {
        flash('error', $eligibilityError);
    } else {
        $hospitalId      = (int)($_POST['hospital_id'] ?? 0);
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');
        $parentNote      = trim($_POST['parent_note'] ?? '') ?: null;

        if (strlen($appointmentTime) === 5) {
            $appointmentTime .= ':00';
        }

        // Validate selected hospital
        $hospitalValid = false;
        $chosenHospital = null;
        foreach ($stockingHospitals as $h) {
            if ((int)$h['hospital_id'] === $hospitalId) {
                $hospitalValid = true;
                $chosenHospital = $h;
                break;
            }
        }

        $today = date('Y-m-d');
        if ($hospitalId <= 0 || !$hospitalValid) {
            flash('error', 'Please select an authorized hospital facility with confirmed vaccine stock.');
        } elseif ($appointmentDate === '' || $appointmentDate < $today) {
            flash('error', 'Appointment date cannot be in the past. Please select today or a future date.');
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $appointmentTime)) {
            flash('error', 'Please specify a valid appointment time.');
        } elseif ($parentNote !== null && mb_strlen($parentNote) > 255) {
            flash('error', 'Special instructions or notes cannot exceed 255 characters.');
        } else {
            // Book appointment calling official stored procedure
            $res = shared_book_appointment(
                (int)$dose['child_vaccination_id'],
                $hospitalId,
                $appointmentDate,
                $appointmentTime,
                $parentNote
            );

            if ($res['success']) {
                flash('success', $res['message']);
                redirect('parent/bookings.php');
            } else {
                flash('error', $res['message']);
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Book Vaccination Appointment</h1>
  <p class="muted">Schedule a child immunization visit with an authorized healthcare center holding certified vaccine stock.</p>
</div>

<?php if (empty($bookableDoses) && !$dose): ?>
  <div class="card reveal" style="text-align:center; padding:36px 20px;">
    <h3>No Vaccines Currently Due for Booking</h3>
    <p class="muted" style="max-width:540px; margin:10px auto 20px auto;">
      None of your active children have vaccination doses due within the next 30 days. Doses become bookable 30 days prior to their due date.
    </p>
    <a class="btn" href="schedule.php">View Immunization Schedules</a>
  </div>
<?php else: ?>

  <?php if ($eligibilityError): ?>
    <div class="alert alert-error reveal" style="margin-bottom:20px;">
      <strong>Eligibility Notice:</strong> <?= e($eligibilityError) ?>
    </div>
  <?php endif; ?>

  <div class="grid2">
    <!-- Left Column: Dose Summary & Dose Selector -->
    <section class="card reveal">
      <h2>1. Selected Vaccination Dose</h2>

      <?php if (count($bookableDoses) > 1): ?>
        <form method="get" action="book.php" style="margin-bottom:20px;">
          <label>
            Select Child &amp; Due Dose:
            <select name="child_vaccination_id" onchange="this.form.submit()">
              <?php foreach ($bookableDoses as $bd): ?>
                <option value="<?= (int)$bd['child_vaccination_id'] ?>" <?= ((int)$bd['child_vaccination_id'] === $cvId) ? 'selected' : '' ?>>
                  <?= e($bd['child_name']) ?> — <?= e($bd['vaccine_name']) ?> (Dose <?= (int)$bd['dose_number'] ?>) [Due: <?= date('d-M-Y', strtotime($bd['due_date'])) ?>]
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
      <?php endif; ?>

      <?php if ($dose): ?>
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:12px; padding:16px;">
          <h3 style="margin-top:0; color:var(--primary);"><?= e($dose['vaccine_name']) ?> (Dose <?= (int)$dose['dose_number'] ?>)</h3>
          <p style="margin:4px 0;"><strong>Child:</strong> <?= e($dose['child_name']) ?> (DOB: <?= date('d-M-Y', strtotime($dose['date_of_birth'])) ?>, <?= ucfirst($dose['gender']) ?>)</p>
          <p style="margin:4px 0;"><strong>Protects Against:</strong> <?= e($dose['disease_prevented']) ?></p>
          <p style="margin:4px 0;"><strong>Scheduled Due Date:</strong> <?= date('d-M-Y', strtotime($dose['due_date'])) ?></p>
          <p style="margin:4px 0;">
            <strong>Status:</strong> 
            <span class="badge badge-<?= $dose['status'] === 'missed' ? 'danger' : 'warning' ?>">
              <?= ucfirst($dose['status']) ?>
            </span>
          </p>
        </div>
      <?php endif; ?>

      <div style="margin-top:20px;">
        <h4>Eligibility Guidelines</h4>
        <ul class="muted" style="font-size:0.9rem; line-height:1.6; padding-left:20px;">
          <li>Appointments can be requested for doses marked <strong>Upcoming</strong> or <strong>Missed</strong>.</li>
          <li>System rules open booking <strong>30 days before</strong> the child's scheduled due date.</li>
          <li>Appointments must be confirmed by the healthcare center administrator.</li>
        </ul>
      </div>
    </section>

    <!-- Right Column: Booking Form -->
    <section class="card reveal">
      <h2>2. Appointment Details</h2>

      <?php if ($eligibilityError): ?>
        <p class="muted">Please select an eligible dose from the schedule to proceed with appointment booking.</p>
        <a class="btn" href="schedule.php">Return to Schedule</a>
      <?php elseif (empty($stockingHospitals)): ?>
        <div style="background:#fff3cd; color:#856404; padding:16px; border-radius:8px; border:1px solid #ffeeba;">
          <strong>Out of Stock:</strong> No certified hospitals currently have <em><?= e($dose['vaccine_name'] ?? 'this vaccine') ?></em> available in stock. Hospital staff frequently update inventory; please check back shortly.
        </div>
      <?php else: ?>
        <form method="post" action="book.php" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="child_vaccination_id" value="<?= (int)$dose['child_vaccination_id'] ?>">

          <label>
            Healthcare Facility / Hospital (Stock Confirmed):
            <select name="hospital_id" required>
              <option value="">-- Choose an authorized hospital --</option>
              <?php foreach ($stockingHospitals as $h): ?>
                <option value="<?= (int)$h['hospital_id'] ?>" <?= ($selectedHospitalId === (int)$h['hospital_id']) ? 'selected' : '' ?>>
                  <?= e($h['hospital_name']) ?> — <?= e($h['city']) ?> (Hours: <?= substr($h['opening_time'], 0, 5) ?> - <?= substr($h['closing_time'], 0, 5) ?>, <?= (int)$h['stock_quantity'] ?> doses in stock)
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <div class="grid2">
            <label>
              Preferred Appointment Date:
              <input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['appointment_date'] ?? date('Y-m-d')) ?>">
            </label>
            <label>
              Preferred Time:
              <input type="time" name="appointment_time" required value="<?= e($_POST['appointment_time'] ?? '10:00') ?>">
            </label>
          </div>

          <label>
            Parent Notes / Special Requests (optional, max 255 chars):
            <textarea name="parent_note" rows="3" maxlength="255" placeholder="Mention any recent fever, specific doctor requests, or accessibility needs..."><?= e($_POST['parent_note'] ?? '') ?></textarea>
          </label>

          <div style="margin-top:12px;">
            <button type="submit" class="btn" style="width:100%;">Confirm Appointment Request</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
