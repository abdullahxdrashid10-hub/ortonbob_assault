<?php
/**
 * hospitals.php - Hospital Directory & Search
 * 
 * Lists approved vaccination centers/hospitals.
 * Supports keyword search across hospital name, city/location, and address
 * using parameterized PDO queries. Preserves selected child/vaccine context
 * for seamless transition to appointment booking.
 */

$role = 'parent';
$title = 'Hospitals Directory';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_parent();

// Search keyword from GET parameter
$searchTerm = trim($_GET['q'] ?? '');

// Contextual booking parameters (optional, forwarded from dashboard or schedule)
$childId = (int)($_GET['child_id'] ?? $_GET['child'] ?? 0);
$vaccineId = (int)($_GET['vaccine_id'] ?? $_GET['vaccine'] ?? 0);

// Fetch matching hospitals using centralized shared query (SHARED TABLE: hospitals)
$hospitals = shared_search_hospitals($searchTerm);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Vaccination Centers &amp; Hospitals</h1>
  <p class="muted">Find certified healthcare facilities offering infant and adolescent immunization.</p>
</div>

<!-- Search Form -->
<form method="get" action="hospitals.php" class="search reveal">
  <input 
    type="text" 
    name="q" 
    placeholder="Search hospitals by name, city, or address..." 
    value="<?= e($searchTerm) ?>"
  >
  <?php if ($childId > 0): ?>
    <input type="hidden" name="child_id" value="<?= $childId ?>">
  <?php endif; ?>
  <?php if ($vaccineId > 0): ?>
    <input type="hidden" name="vaccine_id" value="<?= $vaccineId ?>">
  <?php endif; ?>
  <button type="submit" class="btn">Search</button>
  <?php if ($searchTerm !== ''): ?>
    <a href="hospitals.php<?= ($childId || $vaccineId) ? '?child_id=' . $childId . '&vaccine_id=' . $vaccineId : '' ?>" class="btn btn-ghost">Reset</a>
  <?php endif; ?>
</form>

<!-- Hospital Results Grid -->
<div class="cards">
  <?php if (empty($hospitals)): ?>
    <div class="card reveal" style="grid-column: 1 / -1; text-align:center; padding:30px;">
      <p class="muted">No hospitals matched your search criteria: "<?= e($searchTerm) ?>"</p>
      <a class="btn btn-sm" href="hospitals.php">View All Hospitals</a>
    </div>
  <?php else: ?>
    <?php foreach ($hospitals as $h): ?>
      <article class="card hover reveal" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <h3 style="margin-bottom:8px;"><?= e($h['name']) ?></h3>
          <p class="muted" style="margin:4px 0;">
            &#128205; <?= e($h['address']) ?>, <strong><?= e($h['location']) ?></strong>
          </p>
          <?php if (!empty($h['phone'])): ?>
            <p class="muted" style="margin:4px 0;">
              &#128222; <?= e($h['phone']) ?>
            </p>
          <?php endif; ?>
        </div>

        <div style="margin-top:16px;">
          <?php 
            $bookUrl = 'book.php?hospital_id=' . (int)$h['id'];
            if ($childId > 0) $bookUrl .= '&child_id=' . $childId;
            if ($vaccineId > 0) $bookUrl .= '&vaccine_id=' . $vaccineId;
          ?>
          <a class="btn btn-sm" style="width:100%; text-align:center;" href="<?= $bookUrl ?>">
            Book Appointment
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
