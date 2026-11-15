<?php
/** Search hospitals by name/location. Optional ?child=&vaccine= carried to booking. */
$role = 'parent'; $title = 'Hospitals';
require_once __DIR__ . '/../config/config.php';
require_parent();
$s = trim($_GET['q'] ?? '');
$child = (int)($_GET['child'] ?? 0);
$vaccine = (int)($_GET['vaccine'] ?? 0);

// SHARED TABLE: hospitals (read only).
$q = db()->prepare('SELECT * FROM hospitals WHERE name LIKE :s OR location LIKE :s OR address LIKE :s ORDER BY name');
$q->execute([':s' => "%$s%"]);
$list = $q->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">Find a hospital</h1>
<form class="search reveal" method="get">
  <input name="q" placeholder="Search by name or city" value="<?= e($s) ?>">
  <?php if ($child): ?><input type="hidden" name="child" value="<?= $child ?>"><?php endif; ?>
  <?php if ($vaccine): ?><input type="hidden" name="vaccine" value="<?= $vaccine ?>"><?php endif; ?>
  <button class="btn">Search</button>
</form>
<div class="cards">
<?php if (!$list): ?><p class="muted">No hospitals found.</p><?php endif; ?>
<?php foreach ($list as $h): ?>
  <article class="card hover reveal">
    <h3><?= e($h['name']) ?></h3>
    <p class="muted">&#128205; <?= e($h['address']) ?>, <?= e($h['location']) ?></p>
    <?php if (!empty($h['phone'])): ?><p class="muted">&#128222; <?= e($h['phone']) ?></p><?php endif; ?>
    <a class="btn btn-sm" href="book.php?hospital=<?= (int)$h['id'] ?>&child=<?= $child ?>&vaccine=<?= $vaccine ?>">Book here</a>
  </article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
