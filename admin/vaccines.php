<?php
/**
 * vaccines.php - Admin Vaccine Availability & Hospital Stock Matrix
 * 
 * Uses vaccines + v_vaccine_availability + v_hospital_vaccine_status.
 * Allows Admin to toggle system-wide vaccine availability (is_active = 1/0)
 * and inspect live stock levels across every hospital.
 */

$role = 'admin';
$title = 'Vaccines & Availability';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action    = $_POST['action'] ?? '';
    $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
    $newState  = (int)($_POST['is_active'] ?? 1);

    if ($action === 'toggle_vaccine' && $vaccineId > 0) {
        $ok = shared_admin_toggle_vaccine_status($vaccineId, $newState);
        flash($ok ? 'success' : 'error', $ok ? 'Vaccine availability updated.' : 'Could not update vaccine.');
    }
    redirect('admin/vaccines.php');
}

$vaccines      = shared_admin_get_vaccines_overview();
$hospitalStock = shared_admin_get_hospital_vaccine_stock();

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Vaccines &amp; Availability</h1>
  <p class="muted">Manage the national vaccine catalog and monitor real-time hospital stock levels.</p>
</div>

<!-- System Vaccine Catalog -->
<section class="card reveal">
  <h2>System Vaccine Registry</h2>
  <div class="scroll">
    <table class="table">
      <thead>
        <tr>
          <th>Vaccine</th>
          <th>Disease Prevented</th>
          <th>Route &amp; Doses</th>
          <th>Hospitals Stocking</th>
          <th>Total Units</th>
          <th>System Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vaccines as $v): ?>
          <?php $active = (int)$v['is_active'] === 1; ?>
          <tr>
            <td>
              <strong><?= e($v['vaccine_name']) ?></strong>
              <?php if (!empty($v['short_code'])): ?>
                <span class="muted" style="font-size:0.8rem;">(<?= e($v['short_code']) ?>)</span>
              <?php endif; ?>
            </td>
            <td class="muted"><?= e($v['disease_prevented']) ?></td>
            <td><?= e($v['route'] ?? '—') ?> &middot; <?= (int)$v['total_doses'] ?> doses</td>
            <td><?= (int)$v['hospitals_available'] ?></td>
            <td><strong><?= (int)$v['total_quantity'] ?></strong></td>
            <td>
              <span class="badge <?= $active ? 'badge-success' : 'badge-danger' ?>">
                <?= $active ? 'Available' : 'Unavailable' ?>
              </span>
            </td>
            <td>
              <form method="post" action="vaccines.php" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_vaccine">
                <input type="hidden" name="vaccine_id" value="<?= (int)$v['vaccine_id'] ?>">
                <input type="hidden" name="is_active" value="<?= $active ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $active ? 'btn-ghost' : '' ?>">
                  <?= $active ? 'Mark Unavailable' : 'Mark Available' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Per-Hospital Stock Matrix -->
<section class="card reveal">
  <h2>Hospital-Wise Vaccine Stock (from <code>v_hospital_vaccine_status</code>)</h2>
  <?php if (empty($hospitalStock)): ?>
    <p class="muted">No hospital stock rows recorded yet.</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Hospital</th>
            <th>Vaccine</th>
            <th>Status</th>
            <th>Quantity</th>
            <th>Last Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hospitalStock as $hs): ?>
            <tr>
              <td><strong><?= e($hs['hospital_name']) ?></strong></td>
              <td><?= e($hs['vaccine_name']) ?></td>
              <td>
                <span class="badge <?= $hs['status'] === 'available' ? 'badge-success' : 'badge-danger' ?>">
                  <?= ucfirst($hs['status']) ?>
                </span>
              </td>
              <td><strong><?= (int)$hs['quantity'] ?></strong></td>
              <td class="muted" style="font-size:0.84rem;"><?= date('d-M-Y H:i', strtotime($hs['updated_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
