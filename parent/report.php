<?php
/**
 * report.php - Immunization Certificate & Records
 * 
 * 3B Item 4 & UX Upgrade #2: Built for the team's official schema:
 * - Queries official `vaccination_records` table joined with children, vaccines, and hospitals
 *   via shared_get_vaccination_records().
 * - Allows filtering by child or viewing all administered doses.
 * - Generates a deterministic certificate reference number from parent, child, and record IDs.
 * - Renders an official framed certificate layout with child details block, signature lines,
 *   issue date, and print stylesheet.
 */

$role = 'parent';
$title = 'Immunization Report';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();
$parentId   = (int)$_SESSION['parent_id'];
$parentName = $_SESSION['parent_name'] ?? 'Parent / Guardian';

// Fetch children for filter dropdown
$children = shared_get_parent_children($parentId);

$filteredChildId = (int)($_GET['child_id'] ?? 0);
$selectedChild = null;

if ($filteredChildId > 0) {
    foreach ($children as $c) {
        if ((int)$c['child_id'] === $filteredChildId) {
            $selectedChild = $c;
            break;
        }
    }
    if (!$selectedChild) {
        flash('error', 'Selected child profile not found or unauthorized.');
        redirect('parent/report.php');
    }
}

// Fetch official completed records
$records = shared_get_vaccination_records($parentId, $filteredChildId);

// Build a deterministic official reference number from the record IDs
$recordIds = array_map(static fn($r) => (int)$r['record_id'], $records);
$firstRec  = !empty($recordIds) ? min($recordIds) : 0;
$lastRec   = !empty($recordIds) ? max($recordIds) : 0;
$certRef   = sprintf(
    'VAX-%s-P%03d-%s-R%03d%s',
    date('Y'),
    $parentId,
    $selectedChild ? sprintf('C%03d', (int)$selectedChild['child_id']) : 'ALL',
    $firstRec,
    ($lastRec !== $firstRec) ? sprintf('-%03d', $lastRec) : ''
);
$issueDate = date('d-M-Y');

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal no-print">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Immunization Records &amp; Certificate</h1>
      <p class="muted">Official clinical verification of completed child vaccinations.</p>
    </div>
    <div>
      <button type="button" class="btn" onclick="window.print()">
        &#128424; Print Certificate / Save PDF
      </button>
    </div>
  </div>
</div>

<!-- Child Filter -->
<section class="card reveal no-print" style="margin-bottom:20px;">
  <form method="get" action="report.php" class="select-bar" style="margin-bottom:0;">
    <label for="child-select" style="font-weight:600; color:var(--text); margin:0;">
      Filter by Child:
    </label>
    <select id="child-select" name="child_id" onchange="this.form.submit()" style="max-width:320px;">
      <option value="0">All Children</option>
      <?php foreach ($children as $c): ?>
        <option value="<?= (int)$c['child_id'] ?>" <?= ((int)$c['child_id'] === $filteredChildId) ? 'selected' : '' ?>>
          <?= e($c['full_name']) ?> (DOB: <?= date('d-M-Y', strtotime($c['date_of_birth'])) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($filteredChildId > 0): ?>
      <a href="report.php" class="btn btn-sm btn-ghost">Reset Filter</a>
    <?php endif; ?>
  </form>
</section>

<!-- Official Certificate Card -->
<section class="card reveal certificate-sheet" style="padding:28px;">
  <div class="cert-ref-row">
    <span>Certificate Ref: <span class="cert-ref-code"><?= e($certRef) ?></span></span>
    <span>Issue Date: <strong><?= e($issueDate) ?></strong></span>
  </div>

  <div class="cert-header">
    <h2 style="margin:0 0 6px 0; color:var(--primary); letter-spacing:1.2px; text-transform:uppercase;">
      Child Immunization Record Certificate
    </h2>
    <p class="muted" style="margin:0; font-size:0.9rem;">
      VaxCare Immunization Registry &bull; Verified by Authorized Healthcare Providers
    </p>
  </div>

  <!-- Structured Subject / Guardian Metadata Block -->
  <div class="cert-child-grid">
    <?php if ($selectedChild): ?>
      <div>
        <small>Child Full Name</small>
        <strong><?= e($selectedChild['full_name']) ?></strong>
      </div>
      <div>
        <small>Date of Birth &amp; Age</small>
        <strong><?= date('d-M-Y', strtotime($selectedChild['date_of_birth'])) ?> (<?= e(age_text($selectedChild['date_of_birth'])) ?>)</strong>
      </div>
      <div>
        <small>Gender &amp; Blood Group</small>
        <strong><?= ucfirst(e($selectedChild['gender'])) ?> &middot; <?= e($selectedChild['blood_group'] ?? 'unknown') ?></strong>
      </div>
      <?php if (!empty($selectedChild['birth_certificate_no'])): ?>
        <div>
          <small>Birth Certificate #</small>
          <strong><?= e($selectedChild['birth_certificate_no']) ?></strong>
        </div>
      <?php endif; ?>
      <div>
        <small>Parent / Guardian</small>
        <strong><?= e($parentName) ?></strong>
      </div>
    <?php else: ?>
      <div>
        <small>Parent / Guardian</small>
        <strong><?= e($parentName) ?></strong>
      </div>
      <div>
        <small>Scope</small>
        <strong>All Registered Children (<?= count($children) ?>)</strong>
      </div>
      <div>
        <small>Verified Doses</small>
        <strong><?= count($records) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($records)): ?>
    <div style="text-align:center; padding:36px 12px;">
      <p class="muted">No administered vaccination records found<?= $selectedChild ? ' for ' . e($selectedChild['full_name']) : '' ?>.</p>
      <a class="btn btn-sm" href="schedule.php">View Schedule &amp; Book Doses</a>
    </div>
  <?php else: ?>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <?php if (!$selectedChild): ?>
              <th>Child Name</th>
            <?php endif; ?>
            <th>Vaccine</th>
            <th>Dose</th>
            <th>Administering Hospital</th>
            <th>Batch #</th>
            <th>Administered By</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td>
                <strong><?= date('d-M-Y', strtotime($r['vaccination_date'])) ?></strong>
                <?php if (!empty($r['vaccination_time'])): ?>
                  <span class="muted" style="display:block; font-size:0.8rem;">
                    <?= substr($r['vaccination_time'], 0, 5) ?>
                  </span>
                <?php endif; ?>
              </td>
              <?php if (!$selectedChild): ?>
                <td>
                  <strong><?= e($r['child_name']) ?></strong>
                </td>
              <?php endif; ?>
              <td>
                <strong><?= e($r['vaccine_name']) ?></strong>
                <span class="muted" style="display:block; font-size:0.8rem;">
                  <?= e($r['disease_prevented'] ?? '') ?>
                </span>
              </td>
              <td><span class="badge badge-primary">Dose <?= (int)$r['dose_number'] ?></span></td>
              <td>
                <strong><?= e($r['hospital_name']) ?></strong>
                <?php if (!empty($r['hospital_city'])): ?>
                  <span class="muted" style="display:block; font-size:0.8rem;">
                    <?= e($r['hospital_city']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td><code><?= e($r['batch_number'] ?? 'N/A') ?></code></td>
              <td><?= e($r['administered_by'] ?? 'Healthcare Staff') ?></td>
              <td style="font-size:0.85rem; max-width:180px;">
                <?= e($r['remarks'] ?? 'Normal') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Summary & Signature Block -->
    <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:0.85rem;" class="muted">
      <span>Total Verified Administered Doses: <strong><?= count($records) ?></strong></span>
      <span>Generated: <?= date('d-M-Y H:i') ?></span>
    </div>

    <div class="cert-signatures">
      <div class="cert-sig-box">
        <div class="cert-sig-line">Authorized Medical Officer / Hospital Seal</div>
      </div>
      <div class="cert-seal">
        <div>
          VaxCare<br>Verified<br>Record
        </div>
      </div>
      <div class="cert-sig-box">
        <div class="cert-sig-line">Parent / Guardian Signature</div>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
