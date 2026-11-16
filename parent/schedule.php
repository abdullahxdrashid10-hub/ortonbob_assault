<?php
/**
 * schedule.php - Child Vaccination Schedule & Visual Timeline
 * 
 * Task D & UX Upgrade #1: Displays the official child_vaccinations schedule for an active child
 * belonging to the authenticated parent as a visual progress ring + colour-coded vertical timeline.
 * Joins child_vaccinations with vaccines:
 * - Shows progress ring (X of Y doses completed · Z%) and status legend.
 * - Shows vaccine name, dose_number, target disease, route, due_date, and status badge.
 * - Displays a "Book" link ONLY for 'upcoming' or 'missed' doses due within 30 days.
 */

$role = 'parent';
$title = 'Vaccination Schedule';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch all active children belonging to this parent
$children = shared_get_parent_children($parentId);

// Determine selected child with strict ownership validation
$selectedChildId = (int)($_GET['child_id'] ?? ($children[0]['child_id'] ?? 0));
$currentChild = null;

if ($selectedChildId > 0) {
    foreach ($children as $c) {
        if ((int)$c['child_id'] === $selectedChildId) {
            $currentChild = $c;
            break;
        }
    }
    // If child ID was supplied but does not belong to this parent
    if (!$currentChild && !empty($children)) {
        flash('error', 'Selected child not found or does not belong to your account.');
        redirect('parent/schedule.php');
    }
}

// Fetch official schedule from child_vaccinations table and compute milestone counts
$scheduleItems  = [];
$totalDoses     = 0;
$completedCount = 0;
$bookedCount    = 0;
$dueSoonCount   = 0;
$missedCount    = 0;
$laterCount     = 0;
$progressPct    = 0;
$today          = date('Y-m-d');
$maxBookingDate = date('Y-m-d', strtotime('+30 days'));

if ($currentChild) {
    $scheduleItems = shared_get_child_vaccination_schedule((int)$currentChild['child_id'], $parentId);
    $totalDoses    = count($scheduleItems);

    foreach ($scheduleItems as $item) {
        $st = strtolower($item['status']);
        if ($st === CV_COMPLETED) {
            $completedCount++;
        } elseif ($st === CV_BOOKED || $st === CV_REQUESTED) {
            $bookedCount++;
        } elseif ($st === CV_MISSED) {
            $missedCount++;
        } elseif ($st === CV_UPCOMING && $item['due_date'] <= $maxBookingDate) {
            $dueSoonCount++;
        } else {
            $laterCount++;
        }
    }

    if ($totalDoses > 0) {
        $progressPct = (int)round(($completedCount / $totalDoses) * 100);
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Vaccination Schedule</h1>
  <p class="muted">Sample immunization schedule (from the system's vaccine schedule table)</p>
</div>

<?php if (empty($children)): ?>
  <section class="card reveal">
    <h2>No Active Children Found</h2>
    <p class="muted">You must add a child profile before viewing an immunization schedule.</p>
    <a class="btn" href="children.php">Add a Child Profile</a>
  </section>
<?php else: ?>

  <!-- Child Selection & Progress Ring Card -->
  <section class="card reveal">
    <form method="get" action="schedule.php" class="select-bar">
      <label for="child_select" style="font-weight:600; color:var(--text); align-self:center;">
        Select Child:
      </label>
      <select id="child_select" name="child_id" onchange="this.form.submit()" style="max-width:340px;">
        <?php foreach ($children as $c): ?>
          <option value="<?= (int)$c['child_id'] ?>" <?= ((int)$c['child_id'] === (int)$currentChild['child_id']) ? 'selected' : '' ?>>
            <?= e($c['full_name']) ?> (DOB: <?= e($c['date_of_birth']) ?>, <?= e(age_text($c['date_of_birth'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm">View Schedule</button></noscript>
    </form>

    <div class="progress-summary">
      <!-- Pure CSS conic-gradient progress ring -->
      <div class="progress-ring" style="--pct: <?= $progressPct ?>;" aria-label="<?= $progressPct ?>% completed">
        <div class="progress-ring-val">
          <strong><?= $progressPct ?>%</strong>
          <small><?= $completedCount ?> / <?= $totalDoses ?> doses</small>
        </div>
      </div>

      <div style="flex:1; min-width:240px;">
        <h2 style="margin-bottom:6px;"><?= e($currentChild['full_name']) ?>'s Immunization Progress</h2>
        <div style="font-size:0.95rem; line-height:1.6;">
          <strong>Gender:</strong> <?= e(ucfirst($currentChild['gender'])) ?> &middot;
          <strong>DOB:</strong> <?= e($currentChild['date_of_birth']) ?> &middot;
          <strong>Age:</strong> <?= e(age_text($currentChild['date_of_birth'])) ?>
          <?php if (!empty($currentChild['blood_group']) && $currentChild['blood_group'] !== 'unknown'): ?>
            &middot; <strong>Blood:</strong> <?= e($currentChild['blood_group']) ?>
          <?php endif; ?>
        </div>

        <!-- Colour-coded milestone legend -->
        <div class="tl-legend">
          <span class="tl-legend-item"><i class="tl-dot tl-dot-done"></i> Done (<?= $completedCount ?>)</span>
          <span class="tl-legend-item"><i class="tl-dot tl-dot-booked"></i> Booked/Req (<?= $bookedCount ?>)</span>
          <span class="tl-legend-item"><i class="tl-dot tl-dot-soon"></i> Due &le; 30d (<?= $dueSoonCount ?>)</span>
          <span class="tl-legend-item"><i class="tl-dot tl-dot-missed"></i> Missed (<?= $missedCount ?>)</span>
          <span class="tl-legend-item"><i class="tl-dot tl-dot-later"></i> Later (<?= $laterCount ?>)</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Vertical Immunization Timeline -->
  <section class="card reveal">
    <h2>Immunization Timeline (<?= $totalDoses ?> Doses Scheduled)</h2>

    <?php if (empty($scheduleItems)): ?>
      <p class="muted">No scheduled doses found for this child profile.</p>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($scheduleItems as $dose): ?>
          <?php 
            $st = strtolower($dose['status']); 
            $isDueSoonOrOverdue = ($dose['due_date'] <= $maxBookingDate);
            $isEligibleStatus   = ($st === CV_UPCOMING || $st === CV_MISSED);
            $canBook            = ($isEligibleStatus && $isDueSoonOrOverdue);

            // Determine timeline node colour class
            if ($st === CV_COMPLETED) {
                $nodeClass = 'is-done';
            } elseif ($st === CV_BOOKED || $st === CV_REQUESTED) {
                $nodeClass = 'is-booked';
            } elseif ($st === CV_MISSED) {
                $nodeClass = 'is-missed';
            } elseif ($isDueSoonOrOverdue) {
                $nodeClass = 'is-soon';
            } else {
                $nodeClass = 'is-later';
            }

            // Compute friendly relative timing text
            $daysDiff = (int)round((strtotime($dose['due_date']) - strtotime($today)) / 86400);
            if ($st === CV_COMPLETED && !empty($dose['completed_date'])) {
                $timingText = 'Given on ' . date('d-M-Y', strtotime($dose['completed_date']));
            } elseif ($daysDiff < 0) {
                $timingText = abs($daysDiff) . ' day' . (abs($daysDiff) === 1 ? '' : 's') . ' overdue';
            } elseif ($daysDiff === 0) {
                $timingText = 'Due today';
            } else {
                $timingText = 'In ' . $daysDiff . ' day' . ($daysDiff === 1 ? '' : 's');
            }
          ?>
          <article class="tl-item <?= $nodeClass ?>">
            <div class="tl-main">
              <div class="tl-title">
                <span class="badge badge-muted">Dose <?= (int)$dose['dose_number'] ?></span>
                <strong><?= e($dose['vaccine_name']) ?></strong>
                <?php if (!empty($dose['short_code'])): ?>
                  <span class="muted" style="font-size:0.82rem;">(<?= e($dose['short_code']) ?>)</span>
                <?php endif; ?>
              </div>
              <div class="tl-meta">
                Protects against: <?= e($dose['disease_prevented']) ?> &middot;
                Route: <?= e($dose['route'] ?? 'Standard') ?>
              </div>
            </div>

            <div class="tl-right">
              <div class="tl-date">
                <strong><?= date('d-M-Y', strtotime($dose['due_date'])) ?></strong>
                <small><?= e($timingText) ?></small>
              </div>

              <?= badge($st) ?>

              <div>
                <?php if ($canBook): ?>
                  <!-- Task 0e: Book link passing child_vaccination_id when status is upcoming/missed and due <= 30 days -->
                  <a class="btn btn-sm" href="book.php?child_vaccination_id=<?= (int)$dose['child_vaccination_id'] ?>">
                    Book
                  </a>
                <?php elseif ($isEligibleStatus && !$isDueSoonOrOverdue): ?>
                  <span class="muted" style="font-size:0.85rem;">Not yet due</span>
                <?php elseif ($st === CV_BOOKED || $st === CV_REQUESTED): ?>
                  <span class="badge b-approved">Active</span>
                <?php elseif ($st === CV_COMPLETED): ?>
                  <span class="badge b-done">&#10003; Done</span>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
