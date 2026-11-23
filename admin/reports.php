<?php
/**
 * reports.php - Admin Child-Wise & Date-Wise Vaccination Reports
 * 
 * Queries official v_vaccination_report view with filters for child and date range
 * (from_date .. to_date), plus printable report layout.
 */

$role = 'admin';
$title = 'Vaccination Reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');
$childId  = (int)($_GET['child_id'] ?? 0);

$children = shared_admin_get_all_children();
$records  = shared_admin_get_vaccination_report($fromDate ?: null, $toDate ?: null, $childId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal no-print">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Child &amp; Date-Wise Vaccination Reports</h1>
      <p class="muted">Official clinical records across all registered children and partner hospitals.</p>
    </div>
    <button type="button" class="btn" onclick="window.print()">&#128424; Print Report</button>
  </div>
</div>

<!-- Filters -->
<section class="card reveal no-print">
  <form method="get" action="reports.php" class="search" style="margin-bottom:0; align-items:end;">
    <label style="flex:1; min-width:180px;">
      Child:
      <select name="child_id">
        <option value="0">All Children</option>
        <?php foreach ($children as $c): ?>
          <option value="<?= (int)$c['child_id'] ?>" <?= ((int)$c['child_id'] === $childId) ? 'selected' : '' ?>>
            <?= e($c['child_name']) ?> (Parent: <?= e($c['parent_name']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      From Date:
      <input type="date" name="from" value="<?= e($fromDate) ?>">
    </label>
    <label>
      To Date:
      <input type="date" name="to" value="<?= e($toDate) ?>">
    </label>
    <button type="submit" class="btn">Apply Filters</button>
    <?php if ($childId > 0 || $fromDate !== '' || $toDate !== ''): ?>
      <a href="reports.php" class="btn btn-ghost">Reset</a>
    <?php endif; ?>
  </form>
</section>

<!-- Report Table -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <h2 style="margin:0;">Vaccination Records (<?= count($records) ?>)</h2>
    <span class="muted" style="font-size:0.85rem;">Generated: <?= date('d-M-Y H:i') ?></span>
  </div>

  <?php if (empty($records)): ?>
    <p class="muted" style="text-align:center; padding:28px 0;">No completed vaccination records match the selected filters.</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Record #</th>
            <th>Date &amp; Time</th>
            <th>Child &amp; Parent</th>
            <th>Vaccine &amp; Dose</th>
            <th>Hospital</th>
            <th>Batch #</th>
            <th>Administered By</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td>#<?= (int)$r['record_id'] ?></td>
              <td>
                <strong><?= date('d-M-Y', strtotime($r['vaccination_date'])) ?></strong>
                <?php if (!empty($r['vaccination_time'])): ?>
                  <span class="muted" style="display:block; font-size:0.8rem;"><?= substr($r['vaccination_time'], 0, 5) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($r['child_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.8rem;">Parent: <?= e($r['parent_name']) ?></span>
              </td>
              <td><?= e($r['vaccine_name']) ?> (Dose <?= (int)$r['dose_number'] ?>)</td>
              <td><?= e($r['hospital_name']) ?></td>
              <td><code><?= e($r['batch_number'] ?? 'N/A') ?></code></td>
              <td><?= e($r['administered_by'] ?? 'Staff') ?></td>
              <td class="muted" style="font-size:0.85rem;"><?= e($r['remarks'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
