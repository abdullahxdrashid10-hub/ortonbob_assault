<?php
/** Edit a child's details (ownership verified by parent_id). */
$role = 'parent'; $title = 'Edit Child';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];

$q = db()->prepare('SELECT * FROM children WHERE id=? AND parent_id=?');
$q->execute([(int)($_GET['id'] ?? 0), $pid]);
$k = $q->fetch();
if (!$k) { flash('error', 'Child not found.'); redirect('parent/children.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    if ($name === '' || !in_array($gender, ['Male', 'Female', 'Other'], true)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || $dob > date('Y-m-d')) {
        flash('error', 'Please check the entered details.');
    } else {
        db()->prepare('UPDATE children SET full_name=?,dob=?,gender=?,blood_group=?,notes=? WHERE id=? AND parent_id=?')
            ->execute([$name, $dob, $gender, trim($_POST['blood_group'] ?? '') ?: null, trim($_POST['notes'] ?? '') ?: null, $k['id'], $pid]);
        flash('success', 'Child updated.');
        redirect('parent/children.php');
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Edit child</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Full name<input name="full_name" value="<?= e($k['full_name']) ?>" required></label>
    <label>Date of birth<input type="date" name="dob" value="<?= e($k['dob']) ?>" max="<?= date('Y-m-d') ?>" required></label>
    <label>Gender<select name="gender">
      <?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $k['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
    </select></label>
    <label>Blood group<input name="blood_group" maxlength="5" value="<?= e($k['blood_group']) ?>"></label>
    <label>Notes<input name="notes" maxlength="255" value="<?= e($k['notes']) ?>"></label>
    <button class="btn">Save</button> <a class="btn btn-ghost" href="children.php">Cancel</a>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
