<?php
/**
 * dashboard.php - Parent Dashboard Overview
 * 
 * Task E: Built against the team's real database schema:
 * 1. Metric Cards: Active Children, Doses Due/Missed, Active Bookings, Completed Doses.
 * 2. Upcoming Vaccinations: Doses with status in ('upcoming', 'missed') and due_date <= today + 30 days,
 *    across all active children of the parent, grouped by child with direct Book links.
 * 3. Notifications: 5 latest notifications for this user with unread highlights and a CSRF-protected
 *    Mark-as-read action.
 */

$role = 'parent';
$title = 'Parent Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId = (int)$_SESSION['parent_id'];
$userId   = (int)$_SESSION['user_id'];

// Handle notification mark-as-read POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        // Ownership check: updates only where user_id matches session
        shared_mark_notification_read($notifId, $userId);
        flash('success', 'Notification marked as read.');
    }
    redirect('parent/dashboard.php');
}

// 1. Fetch dashboard KPI counts
$counts = shared_get_parent_dashboard_counts($parentId);

// 2. Fetch upcoming & overdue doses due within 30 days
$dueDoses = shared_get_upcoming_doses_for_parent($parentId);

// Group doses by child for clean display
$groupedDoses = [];
foreach ($dueDoses as $dose) {
    $cId = (int)$dose['child_id'];
    if (!isset($groupedDoses[$cId])) {
        $groupedDoses[$cId] = [
            'child_id'   => $cId,
            'child_name' => $dose['child_name'],
            'dob'        => $dose['date_of_birth'],
            'gender'     => $dose['gender'],
            'items'      => []
        ];
    }
    $groupedDoses[$cId]['items'][] = $dose;
}

// 3. Fetch latest 5 notifications
$notifications = shared_get_parent_notifications($userId, 5);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Welcome, <?= e($_SESSION['parent_name']) ?> &#128075;</h1>
  <p class="muted">Family vaccination overview, immunization milestones, and system updates.</p>
</div>

<!-- Key Metric Indicator Cards -->
<div class="stats">
  <div class="stat reveal">
    <b data-count="<?= $counts['active_children'] ?>">0</b>
    <span>Active Children</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['due_or_missed'] ?>">0</b>
    <span>Doses Due / Missed</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['active_bookings'] ?>">0</b>
    <span>Requested / Booked</span>
  </div>
  <div class="stat reveal">
    <b data-count="<?= $counts['completed_doses'] ?>">0</b>
    <span>Doses Completed</span>
  </div>
</div>

<!-- Upcoming & Overdue Vaccinations Section -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
    <h2 style="margin:0;">Upcoming &amp; Due Vaccinations (Next 30 Days)</h2>
    <a class="btn btn-sm btn-ghost" href="schedule.php">View Complete Schedules &rarr;</a>
  </div>

  <?php if ($counts['active_children'] === 0): ?>
    <div style="text-align:center; padding:30px 10px;">
      <p class="muted">You have no active children registered under this account.</p>
      <a class="btn" href="children.php">Add a Child Profile</a>
    </div>

  <?php elseif (empty($groupedDoses)): ?>
    <div style="text-align:center; padding:30px 10px;">
      <p style="font-size:1.15rem; color:var(--ok); font-weight:600;">&#127881; All caught up!</p>
      <p class="muted">None of your children have doses due in the next 30 days or overdue.</p>
      <a class="btn btn-sm btn-ghost" href="schedule.php">Check Long-Term Timelines</a>
    </div>

  <?php else: ?>
    <?php foreach ($groupedDoses as $group): ?>
      <div class="group-title">
        <?= e($group['child_name']) ?> 
        <span class="muted" style="font-size:0.9rem; font-weight:normal;">
          (<?= e(ucfirst($group['gender'])) ?>, Age: <?= e(age_text($group['dob'])) ?>)
        </span>
      </div>

      <div class="scroll">
        <table class="table schedule-table">
          <thead>
            <tr>
              <th>Dose #</th>
              <th>Vaccine Name</th>
              <th>Target Disease</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($group['items'] as $item): ?>
              <?php 
                $isMissed = ($item['status'] === CV_MISSED || $item['due_date'] < date('Y-m-d'));
              ?>
              <tr>
                <td>Dose <?= (int)$item['dose_number'] ?></td>
                <td>
                  <strong><?= e($item['vaccine_name']) ?></strong>
                  <?php if (!empty($item['short_code'])): ?>
                    <span class="muted" style="font-size:0.8rem;">(<?= e($item['short_code']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="muted"><?= e($item['disease_prevented']) ?></td>
                <td>
                  <?= e($item['due_date']) ?>
                  <?php if ($isMissed): ?>
                    <span class="muted" style="display:block; font-size:0.8rem; color:var(--bad);">
                      (Overdue by <?= abs((int)$item['days_remaining']) ?> days)
                    </span>
                  <?php else: ?>
                    <span class="muted" style="display:block; font-size:0.8rem;">
                      (Due in <?= (int)$item['days_remaining'] ?> days)
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= badge($item['status']) ?></td>
                <td>
                  <a class="btn btn-sm" href="book.php?child_vaccination_id=<?= (int)$item['child_vaccination_id'] ?>">
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

<!-- Notifications Section -->
<section class="card reveal">
  <h2>Recent Notifications</h2>

  <?php if (empty($notifications)): ?>
    <p class="muted">No notifications received yet.</p>
  <?php else: ?>
    <div style="display:grid; gap:12px;">
      <?php foreach ($notifications as $n): ?>
        <?php $isUnread = ((int)$n['is_read'] === 0); ?>
        <div style="padding:14px; border-radius:12px; border:1px solid <?= $isUnread ? 'var(--a)' : 'var(--line)' ?>; background:<?= $isUnread ? 'rgba(56,189,248,0.08)' : 'rgba(0,0,0,0.15)' ?>; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
          <div>
            <div style="display:flex; align-items:center; gap:8px;">
              <strong style="color:var(--text);"><?= e($n['title']) ?></strong>
              <?php if ($isUnread): ?>
                <span class="badge b-approved" style="font-size:0.7rem; padding:2px 8px;">New</span>
              <?php endif; ?>
              <span class="muted" style="font-size:0.8rem;">
                <?= e(date('M d, Y h:i A', strtotime($n['created_at']))) ?>
              </span>
            </div>
            <p style="margin:6px 0 0; color:var(--muted); font-size:0.95rem;">
              <?= e($n['message']) ?>
            </p>
          </div>

          <?php if ($isUnread): ?>
            <div>
              <form method="post" action="dashboard.php" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id'] ?>">
                <button type="submit" class="btn btn-sm btn-ghost">Mark Read</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
