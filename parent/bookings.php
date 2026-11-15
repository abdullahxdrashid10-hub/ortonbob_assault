<?php
/** All bookings made by this parent, with live status. */
$role = 'parent'; $title = 'My Bookings';
require_once __DIR__ . '/../config/config.php';
require_parent();

// SHARED TABLES: bookings, vaccines, hospitals (read only).
$q = db()->prepare('SELECT b.*, c.full_name child, v.name vaccine, h.name hospital
  FROM bookings b
  JOIN children c ON c.id=b.child_id
  JOIN vaccines v ON v.id=b.vaccine_id
  JOIN hospitals h ON h.id=b.hospital_id
  WHERE c.parent_id=? ORDER BY b.booking_date DESC');
$q->execute([$_SESSION['parent_id']]);
$rows = $q->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">My bookings</h1>
<section class="card reveal">
<?php if (!$rows): ?><p class="muted">No bookings yet. <a href="hospitals.php">Find a hospital</a></p>
<?php else: ?>
<div class="scroll"><table class="table">
  <tr><th>Date</th><th>Child</th><th>Vaccine</th><th>Hospital</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= e($r['booking_date']) ?></td><td><?= e($r['child']) ?></td><td><?= e($r['vaccine']) ?></td><td><?= e($r['hospital']) ?></td><td><?= badge($r['status']) ?></td></tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
