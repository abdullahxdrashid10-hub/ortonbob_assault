<?php
/** Report of vaccinations taken (status = Vaccinated), optionally per child. */
$role = 'parent'; $title = 'Vaccination Report';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];
$cid = (int)($_GET['child'] ?? 0);

$k = db()->prepare('SELECT id, full_name FROM children WHERE parent_id=? ORDER BY full_name');
$k->execute([$pid]);
$kids = $k->fetchAll();

// SHARED TABLES: bookings, vaccines, hospitals.
$sql = "SELECT b.booking_date, c.full_name child, v.name vaccine, h.name hospital
  FROM bookings b JOIN children c ON c.id=b.child_id
  JOIN vaccines v ON v.id=b.vaccine_id JOIN hospitals h ON h.id=b.hospital_id
  WHERE c.parent_id=:p AND b.status='Vaccinated'" . ($cid ? ' AND c.id=:c' : '') . ' ORDER BY b.booking_date DESC';
$q = db()->prepare($sql);
$params = [':p' => $pid];
if ($cid) $params[':c'] = $cid;
$q->execute($params);
$rows = $q->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">Vaccination report</h1>
<form method="get" class="search reveal">
  <select name="child" onchange="this.form.submit()">
    <option value="0">All children</option>
    <?php foreach ($kids as $c): ?><option value="<?= $c['id'] ?>" <?= $cid === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option><?php endforeach; ?>
  </select>
  <button type="button" class="btn btn-ghost" onclick="window.print()">Print</button>
</form>
<section class="card reveal">
<?php if (!$rows): ?><p class="muted">No completed vaccinations recorded yet.</p>
<?php else: ?>
<table class="table">
  <tr><th>Date</th><th>Child</th><th>Vaccine</th><th>Hospital</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= e($r['booking_date']) ?></td><td><?= e($r['child']) ?></td><td><?= e($r['vaccine']) ?></td><td><?= e($r['hospital']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
