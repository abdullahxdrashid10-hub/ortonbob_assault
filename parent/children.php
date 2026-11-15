<?php
/** My Children: list, add and delete (own children only). */
$role = 'parent'; $title = 'My Children';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        // parent_id in WHERE ensures a parent can only delete their own child.
        db()->prepare('DELETE FROM children WHERE id=? AND parent_id=?')->execute([(int)$_POST['id'], $pid]);
        flash('success', 'Child removed.');
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $valid = $name !== '' && in_array($gender, ['Male', 'Female', 'Other'], true)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) && $dob <= date('Y-m-d');
        if (!$valid) {
            flash('error', 'Enter a name, a valid gender and a date of birth that is not in the future.');
        } else {
            db()->prepare('INSERT INTO children (parent_id,full_name,dob,gender,blood_group,notes) VALUES (?,?,?,?,?,?)')
                ->execute([$pid, $name, $dob, $gender, trim($_POST['blood_group'] ?? '') ?: null, trim($_POST['notes'] ?? '') ?: null]);
            flash('success', 'Child added.');
        }
    }
    redirect('parent/children.php');
}
$q = db()->prepare('SELECT * FROM children WHERE parent_id=? ORDER BY dob DESC');
$q->execute([$pid]);
$kids = $q->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1 class="reveal">My children</h1>
<div class="grid2">
<section class="card reveal">
  <h2>Add a child</h2>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Full name<input name="full_name" required></label>
    <label>Date of birth<input type="date" name="dob" max="<?= date('Y-m-d') ?>" required></label>
    <label>Gender<select name="gender" required><option>Male</option><option>Female</option><option>Other</option></select></label>
    <label>Blood group (optional)<input name="blood_group" maxlength="5"></label>
    <label>Notes / allergies (optional)<input name="notes" maxlength="255"></label>
    <button class="btn">Add child</button>
  </form>
</section>
<section class="card reveal">
  <h2>Registered</h2>
  <?php if (!$kids): ?><p class="muted">No children yet.</p><?php endif; ?>
  <?php foreach ($kids as $k): ?>
  <div class="row">
    <div><b><?= e($k['full_name']) ?></b><br><span class="muted"><?= e($k['gender']) ?> &middot; <?= e(age_text($k['dob'])) ?> &middot; <?= e($k['dob']) ?></span></div>
    <div class="actions">
      <a class="btn btn-sm btn-ghost" href="schedule.php?child_id=<?= (int)$k['id'] ?>">Schedule</a>
      <a class="btn btn-sm" href="child_edit.php?id=<?= (int)$k['id'] ?>">Edit</a>
      <form method="post" onsubmit="return confirm('Remove this child?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
        <button class="btn btn-sm btn-danger">Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
