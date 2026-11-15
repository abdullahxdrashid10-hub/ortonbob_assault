<?php
/**
 * Book a vaccination slot (the single "book / request hospital" flow).
 * Creates a bookings row with status Pending; admin approves/rejects.
 */
$role = 'parent'; $title = 'Book Vaccination';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];

// SHARED TABLE: hospitals.
$h = db()->prepare('SELECT * FROM hospitals WHERE id=?');
$h->execute([(int)($_GET['hospital'] ?? $_POST['hospital_id'] ?? 0)]);
$hospital = $h->fetch();
if (!$hospital) { flash('error', 'Choose a hospital first.'); redirect('parent/hospitals.php'); }

// Only this parent's children.
$k = db()->prepare('SELECT id, full_name FROM children WHERE parent_id=? ORDER BY full_name');
$k->execute([$pid]);
$kids = $k->fetchAll();

// SHARED TABLE: vaccines - only available ones can be booked.
$vaccines = db()->query('SELECT id, name FROM vaccines WHERE available=1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid = (int)$_POST['child_id'];
    $vid = (int)$_POST['vaccine_id'];
    $date = $_POST['booking_date'] ?? '';
    // Ownership check: child must belong to this parent.
    $own = db()->prepare('SELECT id FROM children WHERE id=? AND parent_id=?');
    $own->execute([$cid, $pid]);
    $va = db()->prepare('SELECT id FROM vaccines WHERE id=? AND available=1');
    $va->execute([$vid]);

    if (!$own->fetch() || !$va->fetch()) {
        flash('error', 'Invalid child or vaccine selection.');
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
        flash('error', 'Pick today or a future date.');
    } else {
        // Block duplicates: same child + vaccine already active or done.
        $dup = db()->prepare("SELECT id FROM bookings WHERE child_id=? AND vaccine_id=? AND status IN ('Pending','Approved','Vaccinated')");
        $dup->execute([$cid, $vid]);
        if ($dup->fetch()) {
            flash('error', 'This vaccine is already booked or completed for that child.');
        } else {
            // SHARED TABLE: bookings (insert as Pending).
            db()->prepare("INSERT INTO bookings (child_id,vaccine_id,hospital_id,booking_date,status) VALUES (?,?,?,?, 'Pending')")
                ->execute([$cid, $vid, $hospital['id'], $date]);
            flash('success', 'Booking submitted. Waiting for admin approval.');
            redirect('parent/bookings.php');
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Book at <?= e($hospital['name']) ?></h1>
  <p class="muted">&#128205; <?= e($hospital['address']) ?>, <?= e($hospital['location']) ?></p>
  <?php if (!$kids): ?>
    <p>You need to <a href="children.php">add a child</a> first.</p>
  <?php else: ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="hospital_id" value="<?= (int)$hospital['id'] ?>">
    <label>Child<select name="child_id" required>
      <?php foreach ($kids as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($_GET['child'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Vaccine<select name="vaccine_id" required>
      <?php foreach ($vaccines as $v): ?><option value="<?= $v['id'] ?>" <?= (int)($_GET['vaccine'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Preferred date<input type="date" name="booking_date" min="<?= date('Y-m-d') ?>" required></label>
    <button class="btn">Submit booking</button>
  </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
