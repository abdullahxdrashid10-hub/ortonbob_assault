<?php
/**
 * Parent dashboard. "Notifications" = vaccines due within the next 30 days
 * or overdue, computed from child DOB + vaccine_schedule, excluding vaccines
 * already marked Vaccinated or already booked (Pending/Approved).
 */
$role = 'parent'; $title = 'Dashboard';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];

// SHARED TABLE: bookings + vaccines (matched by vaccine name).
$sql = "
SELECT c.id AS child_id, c.full_name, s.vaccine_name,
       DATE_ADD(c.dob, INTERVAL s.due_after_days DAY) AS due_date,
       (SELECT v.id FROM vaccines v WHERE v.name = s.vaccine_name LIMIT 1) AS vaccine_id
FROM children c
JOIN vaccine_schedule s
WHERE c.parent_id = :pid
  AND DATE_ADD(c.dob, INTERVAL s.due_after_days DAY) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND NOT EXISTS (
      SELECT 1 FROM bookings b JOIN vaccines v ON v.id = b.vaccine_id
      WHERE b.child_id = c.id AND v.name = s.vaccine_name
        AND b.status IN ('Vaccinated','Pending','Approved'))
ORDER BY due_date";
$q = db()->prepare($sql);
$q->execute([':pid' => $pid]);
$due = $q->fetchAll();

$kids = db()->prepare('SELECT COUNT(*) FROM children WHERE parent_id = ?');
$kids->execute([$pid]);
$childCount = $kids->fetchColumn();

// SHARED TABLE: bookings - count by status for this parent.
$st = db()->prepare("SELECT b.status, COUNT(*) n FROM bookings b JOIN children c ON c.id=b.child_id WHERE c.parent_id=? GROUP BY b.status");
$st->execute([$pid]);
$counts = array_column($st->fetchAll(), 'n', 'status');

require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">Welcome, <?= e($_SESSION['parent_name']) ?> &#128075;</h1>
<div class="stats">
  <div class="stat reveal"><b data-count="<?= (int)$childCount ?>">0</b><span>Children</span></div>
  <div class="stat reveal"><b data-count="<?= count($due) ?>">0</b><span>Vaccines due</span></div>
  <div class="stat reveal"><b data-count="<?= (int)($counts['Approved'] ?? 0) + (int)($counts['Pending'] ?? 0) ?>">0</b><span>Active bookings</span></div>
  <div class="stat reveal"><b data-count="<?= (int)($counts['Vaccinated'] ?? 0) ?>">0</b><span>Vaccinated</span></div>
</div>

<section class="card reveal">
  <h2>Upcoming &amp; overdue vaccinations</h2>
  <?php if ($childCount == 0): ?>
    <p class="muted">Add your child first. <a class="btn btn-sm" href="children.php">Add child</a></p>
  <?php elseif (!$due): ?>
    <p class="muted">All caught up &#127881; Nothing due in the next 30 days.</p>
  <?php else: ?>
  <table class="table">
    <tr><th>Child</th><th>Vaccine</th><th>Due date</th><th>Status</th><th></th></tr>
    <?php foreach ($due as $d): $over = $d['due_date'] < date('Y-m-d'); ?>
    <tr>
      <td><?= e($d['full_name']) ?></td>
      <td><?= e($d['vaccine_name']) ?></td>
      <td><?= e($d['due_date']) ?></td>
      <td><?= $over ? '<span class="badge b-rejected">Overdue</span>' : '<span class="badge b-pending">Upcoming</span>' ?></td>
      <td><a class="btn btn-sm" href="hospitals.php?child=<?= (int)$d['child_id'] ?>&vaccine=<?= (int)$d['vaccine_id'] ?>">Book</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
