<?php
/**
 * Hospital appointments: lists bookings assigned to THIS hospital that the
 * admin approved (or that were already completed) and lets staff mark them
 * Vaccinated / Not Vaccinated.
 * SHARED TABLE: bookings (status update only).
 */
$role = 'hospital'; $title = 'Appointments';
require_once __DIR__ . '/../config/config.php';
require_hospital();
$hid = $_SESSION['hospital_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, [ST_VACCINATED, ST_NOT_VACCINATED], true)) {
        // WHERE enforces: own hospital AND currently Approved (valid transition).
        $u = db()->prepare("UPDATE bookings SET status=? WHERE id=? AND hospital_id=? AND status='Approved'");
        $u->execute([$newStatus, (int)$_POST['id'], $hid]);
        flash($u->rowCount() ? 'success' : 'error', $u->rowCount() ? "Marked as $newStatus." : 'Appointment cannot be updated.');
    }
    redirect('hospital/appointments.php');
}

$filter = $_GET['f'] ?? 'Approved';
if (!in_array($filter, ['Approved', 'Vaccinated', 'Not Vaccinated'], true)) $filter = 'Approved';

// SHARED TABLES: bookings, children (ours), vaccines, parents (ours).
$q = db()->prepare('SELECT b.id, b.booking_date, b.status, c.full_name child, c.dob, c.gender, v.name vaccine,
    p.full_name parent, p.phone
  FROM bookings b
  JOIN children c ON c.id=b.child_id
  JOIN parents p ON p.id=c.parent_id
  JOIN vaccines v ON v.id=b.vaccine_id
  WHERE b.hospital_id=? AND b.status=? ORDER BY b.booking_date');
$q->execute([$hid, $filter]);
$rows = $q->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">Appointments</h1>
<div class="tabs reveal">
  <?php foreach (['Approved' => 'To do', 'Vaccinated' => 'Vaccinated', 'Not Vaccinated' => 'Not vaccinated'] as $k => $label): ?>
    <a class="tab <?= $filter === $k ? 'active' : '' ?>" href="?f=<?= urlencode($k) ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>
<section class="card reveal">
<?php if (!$rows): ?><p class="muted">No appointments here.</p>
<?php else: ?>
<div class="scroll"><table class="table">
  <tr><th>Date</th><th>Child</th><th>Age</th><th>Vaccine</th><th>Parent</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['booking_date']) ?></td>
    <td><?= e($r['child']) ?> (<?= e($r['gender']) ?>)</td>
    <td><?= e(age_text($r['dob'])) ?></td>
    <td><?= e($r['vaccine']) ?></td>
    <td><?= e($r['parent']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td>
    <td><?= badge($r['status']) ?></td>
    <td class="actions">
    <?php if ($r['status'] === ST_APPROVED): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="Vaccinated"><button class="btn btn-sm">&#10003; Vaccinated</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="Not Vaccinated"><button class="btn btn-sm btn-danger">&#10007; Not</button></form>
    <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
