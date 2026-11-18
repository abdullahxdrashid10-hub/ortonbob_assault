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
      <article class="card hover reveal" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
            <h3 style="margin:0;"><?= e($h['hospital_name']) ?></h3>
            <span class="badge badge-success">Active</span>
          </div>

          <?php if (!empty($h['registration_no'])): ?>
            <p class="muted" style="font-size:0.85rem; margin:2px 0;">
              Reg #: <?= e($h['registration_no']) ?>
            </p>
          <?php endif; ?>

          <p class="muted" style="margin:6px 0;">
            &#128205; <?= e($h['address']) ?>, <strong><?= e($h['city']) ?></strong><?= !empty($h['area']) ? ' (' . e($h['area']) . ')' : '' ?>
          </p>

          <?php if (!empty($h['phone'])): ?>
            <p class="muted" style="margin:4px 0;">
              &#128222; <?= e($h['phone']) ?>
            </p>
          <?php endif; ?>

          <p class="muted" style="margin:4px 0;">
            &#128337; Hours: <?= substr($h['opening_time'], 0, 5) ?> - <?= substr($h['closing_time'], 0, 5) ?> 
            &bull; Daily Capacity: <?= (int)$h['daily_capacity'] ?> patients
          </p>

          <!-- In-Stock Vaccines -->
          <div style="margin-top:14px;">
            <strong style="font-size:0.9rem;">Available Vaccines:</strong>
            <?php if (empty($availableVaccines)): ?>
              <p class="muted" style="font-size:0.85rem; margin:4px 0;">No live stock reported currently.</p>
            <?php else: ?>
              <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                <?php foreach ($availableVaccines as $v): ?>
                  <span class="badge badge-primary" style="font-size:0.8rem;" title="<?= e($v['disease_prevented']) ?> (<?= (int)$v['quantity'] ?> in stock)">
                    <?= e($v['vaccine_name']) ?> (<?= (int)$v['quantity'] ?>)
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:20px;">
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
