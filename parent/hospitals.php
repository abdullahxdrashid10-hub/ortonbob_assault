<?php
/**
 * hospitals.php - Hospital Directory & Availability
 * 
 * 3B Item 1: Lists authorized healthcare facilities from `hospitals` table.
 * Supports keyword search across hospital name, city, area, and address.
 * Displays opening hours, daily capacity, phone contact, and live vaccine availability.
 */

$role = 'parent';
$title = 'Hospitals Directory';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();

$searchTerm = trim($_GET['q'] ?? '');
$cvId = (int)($_GET['child_vaccination_id'] ?? 0);

// Fetch matching active hospitals using centralized shared query
$hospitals = shared_search_hospitals($searchTerm);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Healthcare Facilities Directory</h1>
  <p class="muted">Find certified vaccination centers, check vaccine stock, and schedule child immunization visits.</p>
</div>

<!-- Search Form -->
<form method="get" action="hospitals.php" class="search reveal" style="margin-bottom:24px;">
  <input 
    type="text" 
    name="q" 
    placeholder="Search hospitals by name, city, area, or address..." 
    value="<?= e($searchTerm) ?>"
  >
  <?php if ($cvId > 0): ?>
    <input type="hidden" name="child_vaccination_id" value="<?= $cvId ?>">
  <?php endif; ?>
  <button type="submit" class="btn">Search</button>
  <?php if ($searchTerm !== ''): ?>
    <a href="hospitals.php<?= ($cvId > 0) ? '?child_vaccination_id=' . $cvId : '' ?>" class="btn btn-ghost">Reset</a>
  <?php endif; ?>
</form>

<!-- Hospital Results Cards -->
<div class="cards">
  <?php if (empty($hospitals)): ?>
    <div class="card reveal" style="grid-column: 1 / -1; text-align:center; padding:36px;">
      <p class="muted">No hospitals matched your search criteria: "<?= e($searchTerm) ?>"</p>
      <a class="btn btn-sm" href="hospitals.php">View All Facilities</a>
    </div>
  <?php else: ?>
    <?php foreach ($hospitals as $h): ?>
      <?php 
        $hospId = (int)$h['hospital_id'];
        $availableVaccines = shared_get_hospital_available_vaccines($hospId);
        $bookUrl = 'book.php?hospital_id=' . $hospId;
        if ($cvId > 0) {
            $bookUrl .= '&child_vaccination_id=' . $cvId;
        }
      ?>
      <article class="card hover reveal hosp-card">
        <div>
          <div class="hosp-head">
            <div>
              <h3><?= e($h['hospital_name']) ?></h3>
              <?php if (!empty($h['registration_no'])): ?>
                <div class="hosp-reg">Reg # <?= e($h['registration_no']) ?></div>
              <?php endif; ?>
            </div>
            <span class="badge badge-success">Active</span>
          </div>

          <div class="hosp-body">
            <div class="hosp-meta">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span><?= e($h['address']) ?>, <strong><?= e($h['city']) ?></strong><?= !empty($h['area']) ? ' (' . e($h['area']) . ')' : '' ?></span>
            </div>

            <?php if (!empty($h['phone'])): ?>
              <div class="hosp-meta">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span><?= e($h['phone']) ?></span>
              </div>
            <?php endif; ?>

            <div class="hosp-meta">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Hours: <strong><?= substr($h['opening_time'], 0, 5) ?> – <?= substr($h['closing_time'], 0, 5) ?></strong> &bull; <?= (int)$h['daily_capacity'] ?> slots/day</span>
            </div>

            <div class="hosp-vaccines">
              <span class="hosp-vaccines-label">Available Vaccines</span>
              <?php if (empty($availableVaccines)): ?>
                <p class="muted" style="font-size:0.85rem; margin:0;">No live stock reported currently.</p>
              <?php else: ?>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                  <?php foreach ($availableVaccines as $v): ?>
                    <span class="badge badge-primary" style="font-size:0.78rem;" title="<?= e($v['disease_prevented']) ?> (<?= (int)$v['quantity'] ?> in stock)">
                      <?= e($v['vaccine_name']) ?> (<?= (int)$v['quantity'] ?>)
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="hosp-foot">
          <a class="btn btn-sm" style="width:100%; text-align:center;" href="<?= $bookUrl ?>">
            Book Appointment Here
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/vaccine_guide.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
