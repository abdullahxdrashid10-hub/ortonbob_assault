<?php
/**
 * children.php - Admin All Child Details & Upcoming Vaccination Dates
 * 
 * Uses official schema views:
 * - v_all_children: full directory of active children and their parents
 * - v_upcoming_vaccinations: upcoming/overdue dose schedule across all children
 */

$role = 'admin';
$title = 'All Children & Schedule';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();

$search    = trim($_GET['q'] ?? '');
$dueFilter = $_GET['due'] ?? 'due_30';
if (!in_array($dueFilter, ['due_30', 'overdue', 'all'], true)) {
    $dueFilter = 'due_30';
}

$children = shared_admin_get_all_children($search);
$upcoming = shared_admin_get_upcoming_vaccinations($dueFilter);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>All Children &amp; Vaccination Dates</h1>
  <p class="muted">System-wide registry of active children and upcoming immunization milestones.</p>
</div>

<!-- Search Children -->
<form method="get" action="children.php" class="search reveal">
  <input type="text" name="q" placeholder="Search by child name, parent name, city, or phone..." value="<?= e($search) ?>">
  <input type="hidden" name="due" value="<?= e($dueFilter) ?>">
  <button type="submit" class="btn">Search</button>
  <?php if ($search !== ''): ?>
    <a href="children.php?due=<?= e($dueFilter) ?>" class="btn btn-ghost">Reset</a>
  <?php endif; ?>
</form>

<!-- All Children Table -->
<section class="card reveal">
  <h2>Registered Children (<?= count($children) ?>)</h2>
  <?php if (empty($children)): ?>
    <p class="muted">No active children matched your query.</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Child Name</th>
            <th>DOB &amp; Age</th>
            <th>Gender / Blood</th>
            <th>Birth Wt / Allergies</th>
            <th>Parent &amp; City</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($children as $c): ?>
            <tr>
              <td>#<?= (int)$c['child_id'] ?></td>
              <td><strong><?= e($c['child_name']) ?></strong></td>
              <td>
                <?= date('d-M-Y', strtotime($c['date_of_birth'])) ?>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= (int)$c['age_in_months'] ?> months</span>
              </td>
              <td><?= ucfirst(e($c['gender'])) ?> &middot; <?= e($c['blood_group']) ?></td>
              <td style="font-size:0.85rem;">
                <?= !empty($c['birth_weight_kg']) ? e($c['birth_weight_kg']) . ' kg' : '—' ?>
                <?php if (!empty($c['allergies'])): ?>
                  <span class="badge badge-danger" style="display:block; margin-top:4px;"><?= e($c['allergies']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($c['parent_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.82rem;"><?= e($c['parent_phone']) ?> &bull; <?= e($c['city'] ?? '') ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Upcoming Vaccination Dates Across All Children -->
<section class="card reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="margin:0;">Upcoming &amp; Overdue Vaccination Dates</h2>
    <div class="tabs" style="margin:0;">
      <a class="tab <?= $dueFilter === 'due_30' ? 'active' : '' ?>" href="?q=<?= urlencode($search) ?>&due=due_30">Due &le; 30 Days</a>
      <a class="tab <?= $dueFilter === 'overdue' ? 'active' : '' ?>" href="?q=<?= urlencode($search) ?>&due=overdue">Overdue</a>
      <a class="tab <?= $dueFilter === 'all' ? 'active' : '' ?>" href="?q=<?= urlencode($search) ?>&due=all">All Scheduled</a>
    </div>
  </div>

  <?php if (empty($upcoming)): ?>
    <p class="muted">No scheduled doses match this filter.</p>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Due Date</th>
            <th>Child</th>
            <th>Parent Contact</th>
            <th>Vaccine &amp; Dose</th>
            <th>Status</th>
            <th>Timing</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $u): ?>
            <tr>
              <td><strong><?= date('d-M-Y', strtotime($u['due_date'])) ?></strong></td>
              <td><?= e($u['child_name']) ?></td>
              <td style="font-size:0.85rem;"><?= e($u['parent_name']) ?> (<?= e($u['parent_phone']) ?>)</td>
              <td><?= e($u['vaccine_name']) ?> (Dose <?= (int)$u['dose_number'] ?>)</td>
              <td><?= badge($u['status']) ?></td>
              <td class="muted" style="font-size:0.85rem;">
                <?php $days = (int)$u['days_remaining']; ?>
                <?= $days < 0 ? abs($days) . ' days overdue' : ($days === 0 ? 'Today' : "In $days days") ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
