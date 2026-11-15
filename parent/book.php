<?php
/**
 * book.php - Book Vaccination Appointment
 * 
 * Handles scheduling a vaccination slot:
 * - Child: Restrict strictly to the logged-in parent's verified children
 * - Vaccine: Restrict to active vaccines present in the child's bookable list
 * - Task 4: Server-side rejection if vaccine is not in bookable list or if its
 *   scheduled due date (dob + due_after_days) is more than 30 days in the future.
 * - Hospital: Select from approved facilities
 * - Preferred Date: Validated server-side to be today or a future date
 * - Duplicate Prevention: Rejects booking if child + vaccine already has status
 *   Pending, Approved, or Vaccinated.
 */

$role = 'parent';
$title = 'Book Appointment';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// 1. Fetch only children belonging to this parent (OWNERSHIP CHECK)
$stmtKids = db()->prepare("SELECT id, full_name, dob, gender FROM children WHERE parent_id = ? ORDER BY full_name ASC");
$stmtKids->execute([$parentId]);
$children = $stmtKids->fetchAll();

// Determine currently selected child
$selectedChildId = (int)($_POST['child_id'] ?? $_GET['child_id'] ?? $_GET['child'] ?? ($children[0]['id'] ?? 0));
$currentChild = null;
foreach ($children as $c) {
    if ((int)$c['id'] === $selectedChildId) {
        $currentChild = $c;
        break;
    }
}

// 2. Fetch all hospitals for hospital dropdown
$hospitals = shared_search_hospitals('');
$selectedHospitalId = (int)($_POST['hospital_id'] ?? $_GET['hospital_id'] ?? $_GET['hospital'] ?? 0);

// 3. Fetch bookable vaccines for the selected child (SHARED TABLE: vaccines, bookings)
$bookableVaccines = [];
if ($currentChild) {
    $bookableVaccines = shared_get_bookable_vaccines_for_child($currentChild['id']);
}
$selectedVaccineId = (int)($_POST['vaccine_id'] ?? $_GET['vaccine_id'] ?? $_GET['vaccine'] ?? 0);

// 4. Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $formChildId = (int)($_POST['child_id'] ?? 0);
    $formVaccineId = (int)($_POST['vaccine_id'] ?? 0);
    $formHospitalId = (int)($_POST['hospital_id'] ?? 0);
    $formDate = trim($_POST['booking_date'] ?? '');
    $formTime = trim($_POST['booking_time'] ?? '');
    $formTime = ($formTime !== '') ? $formTime : null;

    // Validate ownership: Child MUST belong to the logged-in parent
    $verifyChild = db()->prepare("SELECT id, full_name, dob FROM children WHERE id = ? AND parent_id = ?");
    $verifyChild->execute([$formChildId, $parentId]);
    $verifiedChild = $verifyChild->fetch();

    // Validate hospital existence (SHARED TABLE: hospitals)
    $verifiedHospital = shared_get_hospital_by_id($formHospitalId);

    // Validate appointment date format (YYYY-MM-DD) and ensure date is not in the past
    $today = date('Y-m-d');
    $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $formDate) && ($formDate >= $today);

    // Initial basic validations
    if (!$verifiedChild) {
        flash('error', 'Security error: Invalid child selected.');
    } elseif (!$verifiedHospital) {
        flash('error', 'Please select a valid hospital from the directory.');
    } elseif (!$isValidDate) {
        flash('error', 'Appointment date must be today or a future date.');
    } else {
        // Task 4: Server-side check that vaccine is in child's bookable list
        $childBookableList = shared_get_bookable_vaccines_for_child($formChildId);
        $matchedBookableVaccine = null;

        foreach ($childBookableList as $item) {
            if ((int)$item['id'] === $formVaccineId) {
                $matchedBookableVaccine = $item;
                break;
            }
        }

        if (!$matchedBookableVaccine) {
            flash('error', 'The selected vaccine is not currently eligible for booking for this child.');
        } else {
            // Task 4: Reject doses whose scheduled due date is more than 30 days in the future
            // due = dob + due_after_days
            $daysOffset = (int)$matchedBookableVaccine['due_after_days'];
            $calculatedDueDate = date('Y-m-d', strtotime($verifiedChild['dob'] . " + $daysOffset days"));
            $maxAllowedDueDate = date('Y-m-d', strtotime('+30 days'));

            if ($calculatedDueDate > $maxAllowedDueDate) {
                flash('error', 'This vaccine dose is scheduled for ' . $calculatedDueDate . ', which is more than 30 days away. You can only book vaccinations that are overdue or due within the next 30 days.');
            } else {
                // Enforce duplicate check (SHARED TABLE: bookings)
                $hasActiveBooking = shared_has_active_or_completed_booking($formChildId, $formVaccineId);

                if ($hasActiveBooking) {
                    flash('error', 'Booking rejected: This vaccine is already active (Pending/Approved) or completed (Vaccinated) for ' . $verifiedChild['full_name'] . '.');
                } else {
                    // Create new booking with status 'Pending' (SHARED TABLE: bookings)
                    shared_create_booking($formChildId, $formVaccineId, $formHospitalId, $formDate, $formTime);
                    flash('success', 'Appointment request submitted successfully! Your booking is currently Pending admin approval.');
                    redirect('parent/bookings.php');
                }
            }
        }
    }

    // Preserve selections if validation fails
    $selectedChildId = $formChildId;
    $selectedVaccineId = $formVaccineId;
    $selectedHospitalId = $formHospitalId;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Book Vaccination Appointment</h1>
  <p class="muted">Schedule an immunization slot at an accredited center. Bookings are confirmed upon admin approval.</p>
</div>

<section class="card auth reveal" style="max-width:560px;">
  <?php if (empty($children)): ?>
    <div style="text-align:center; padding:20px 0;">
      <p class="muted">You must register at least one child before booking an appointment.</p>
      <a class="btn" href="children.php">Register a Child</a>
    </div>
  <?php elseif (empty($hospitals)): ?>
    <div style="text-align:center; padding:20px 0;">
      <p class="muted">No hospitals are currently registered in the system. Please check back later.</p>
    </div>
  <?php else: ?>

    <form method="post" action="book.php" class="form">
      <?= csrf_field() ?>

      <!-- 1. Select Child (Restricted to logged-in parent) -->
      <label>
        Child:
        <select name="child_id" id="book_child_id" required onchange="window.location.href='book.php?child_id=' + this.value + '&hospital_id=<?= $selectedHospitalId ?>';">
          <?php foreach ($children as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedChildId) ? 'selected' : '' ?>>
              <?= e($c['full_name']) ?> (<?= e($c['gender']) ?>, <?= e(age_text($c['dob'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- 2. Select Vaccine (Available vaccines in child's schedule not yet completed/booked) -->
      <label>
        Vaccine:
        <?php if (empty($bookableVaccines)): ?>
          <select disabled>
            <option>All scheduled vaccines are already booked or completed for this child.</option>
          </select>
          <span class="muted" style="font-size:0.85rem; color:var(--warn);">
            No pending doses found for this child's schedule. Check <a href="schedule.php?child_id=<?= $selectedChildId ?>">Child Schedule</a>.
          </span>
        <?php else: ?>
          <select name="vaccine_id" required>
            <option value="">-- Choose Vaccine --</option>
            <?php foreach ($bookableVaccines as $bv): ?>
              <?php 
                // Calculate due date for display
                $dOffset = (int)$bv['due_after_days'];
                $itemDue = date('Y-m-d', strtotime($currentChild['dob'] . " + $dOffset days"));
                $isFarFuture = ($itemDue > date('Y-m-d', strtotime('+30 days')));
              ?>
              <option value="<?= (int)$bv['id'] ?>" <?= ((int)$bv['id'] === $selectedVaccineId) ? 'selected' : '' ?>>
                <?= e($bv['name']) ?> &middot; Due: <?= e($itemDue) ?> <?= $isFarFuture ? '(>30 days away)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </label>

      <!-- 3. Select Hospital -->
      <label>
        Hospital / Vaccination Center:
        <select name="hospital_id" required>
          <option value="">-- Choose Hospital --</option>
          <?php foreach ($hospitals as $h): ?>
            <option value="<?= (int)$h['id'] ?>" <?= ((int)$h['id'] === $selectedHospitalId) ? 'selected' : '' ?>>
              <?= e($h['name']) ?> (<?= e($h['location']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- 4. Preferred Date -->
      <label>
        Preferred Date:
        <input 
          type="date" 
          name="booking_date" 
          id="booking_date" 
          min="<?= date('Y-m-d') ?>" 
          value="<?= e($_POST['booking_date'] ?? date('Y-m-d')) ?>" 
          required
        >
      </label>

      <!-- 5. Preferred Time (Optional slot preference) -->
      <label>
        Preferred Time Slot (Optional):
        <input 
          type="time" 
          name="booking_time" 
          value="<?= e($_POST['booking_time'] ?? '') ?>"
        >
      </label>

      <div style="margin-top:8px;">
        <button 
          type="submit" 
          class="btn" 
          style="width:100%;" 
          <?= empty($bookableVaccines) ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : '' ?>
        >
          Confirm &amp; Request Booking
        </button>
      </div>

      <p class="muted" style="font-size:0.85rem; text-align:center; margin-top:12px;">
        Note: The booking will be marked as <strong>Pending</strong> until confirmed by healthcare administrators.
      </p>
    </form>

  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
