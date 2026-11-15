<?php
/**
 * dashboard.php - Parent Dashboard Overview
 * 
 * Provides an executive summary of the parent's account:
 * - Summary metric cards (Registered Children, Due/Overdue Doses, Active Bookings, Vaccinations Taken)
 * - "Upcoming Vaccinations" card listing all doses due within 30 days or overdue,
 *   grouped by child, with quick "Book" action links.
 */

$role = 'parent';
$title = 'Parent Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];

// Fetch total number of children registered by this parent
// OWNERSHIP: Filtered by parent_id
$stmtKids = db()->prepare("SELECT COUNT(*) FROM children WHERE parent_id = ?");
$stmtKids->execute([$parentId]);
$childCount = (int)$stmtKids->fetchColumn();

// Fetch booking counts by status (Pending, Approved, Vaccinated, etc.)
// Centralized in shared_queries.php (SHARED TABLE: bookings)
$bookingCounts = shared_get_parent_booking_counts($parentId);
$activeBookingsCount = (int)($bookingCounts['Pending'] ?? 0) + (int)($bookingCounts['Approved'] ?? 0);
$completedCount = (int)($bookingCounts['Vaccinated'] ?? 0);

// Fetch upcoming (within 30 days) and overdue vaccine doses across ALL children of this parent
// Centralized in shared_queries.php (SHARED TABLE: vaccines, bookings)
$dueVaccines = shared_get_upcoming_and_overdue_vaccines_all_children($parentId);
$dueCount = count($dueVaccines);

// Group vaccines by child ID for organized display
$groupedVaccines = [];
foreach ($dueVaccines as $item) {
    $cId = (int)$item['child_id'];
    if (!isset($groupedVaccines[$cId])) {
        $groupedVaccines[$cId] = [
            'child_id' => $cId,
            'child_name' => $item['child_name'],
            'child_dob' => $item['child_dob'],
            'items' => []
        ];
    }
    $groupedVaccines[$cId]['items'][] = $item;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Welcome, <?= e($_SESSION['parent_name'] ?? 'Parent') ?> &#128075;</h1>
  <p class="muted">Monitor immunization schedules, upcoming appointments, and vaccination history for your family.</p>
</div>

<!-- Key Performance Indicators / Statistics Row -->
<div class="stats">
  <div class="stat reveal">
    <b data-count="<?= $childCount ?>">0</b>
    <span>Registered Children</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $dueCount ?>">0</b>
    <span>Vaccines Due / Overdue</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $activeBookingsCount ?>">0</b>
    <span>Active Bookings</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $completedCount ?>">0</b>
    <span>Vaccines Completed</span>
  </div>
</div>

<!-- Upcoming & Overdue Vaccinations Section -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
    <h2 style="margin:0;">Upcoming &amp; Overdue Vaccinations</h2>
    <a class="btn btn-sm btn-ghost" href="schedule.php">View Full Schedules &rarr;</a>
  </div>

  <?php if ($childCount === 0): ?>
    <!-- Case 1: No children added yet -->
    <div style="text-align:center; padding:30px 10px;">
      <p class="muted">You have not added any children to your account yet.</p>
      <a class="btn" href="children.php">Add Your First Child</a>
    </div>

  <?php elseif (empty($groupedVaccines)): ?>
    <!-- Case 2: Children exist, but all up to date -->
    <div style="text-align:center; padding:30px 10px;">
      <p style="font-size:1.15rem; color:var(--ok); font-weight:600;">&#127881; All caught up!</p>
      <p class="muted">No doses are overdue or due within the next 30 days for any of your children.</p>
      <a class="btn btn-sm btn-ghost" href="schedule.php">Check Long-Term Timelines</a>
    </div>

  <?php else: ?>
    <!-- Case 3: List grouped by child -->
    <?php foreach ($groupedVaccines as $group): ?>
      <div class="group-title">
        <?= e($group['child_name']) ?> 
        <span class="muted" style="font-size:0.9rem; font-weight:normal;">
          (Age: <?= e(age_text($group['child_dob'])) ?>)
        </span>
      </div>

      <div class="scroll">
        <table class="table schedule-table">
          <thead>
            <tr>
              <th>Vaccine Name</th>
              <th>Target / Purpose</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($group['items'] as $vac): ?>
              <?php 
                $isOverdue = ($vac['due_date'] < date('Y-m-d'));
                $statusLabel = $isOverdue ? 'Overdue' : 'Upcoming';
              ?>
              <tr>
                <td><strong><?= e($vac['vaccine_name']) ?></strong></td>
                <td class="muted"><?= e($vac['description'] ?? 'Immunization dose') ?></td>
                <td>
                  <?= e($vac['due_date']) ?>
                  <?php if ($isOverdue): ?>
                    <span class="muted" style="color:var(--bad); font-size:0.85rem;">
                      (<?= abs((int)$vac['days_left']) ?> days late)
                    </span>
                  <?php else: ?>
                    <span class="muted" style="font-size:0.85rem;">
                      (in <?= (int)$vac['days_left'] ?> days)
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= badge($statusLabel) ?></td>
                <td>
                  <a class="btn btn-sm" href="book.php?child_id=<?= (int)$group['child_id'] ?>&vaccine_id=<?= (int)$vac['vaccine_id'] ?>">
                    Book
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
