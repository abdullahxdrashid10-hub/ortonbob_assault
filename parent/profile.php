<?php
/** Parent profile: update details and optionally change password. */
$role = 'parent'; $title = 'My Profile';
require_once __DIR__ . '/../config/config.php';
require_parent();
$pid = $_SESSION['parent_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $new = $_POST['new_password'] ?? '';
    if ($name === '' || $phone === '' || $addr === '') {
        flash('error', 'Name, phone and address are required.');
    } elseif ($new !== '' && strlen($new) < 6) {
        flash('error', 'New password must be at least 6 characters.');
    } else {
        db()->prepare('UPDATE parents SET full_name=?,phone=?,address=? WHERE id=?')->execute([$name, $phone, $addr, $pid]);
        if ($new !== '') {
            db()->prepare('UPDATE parents SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $pid]);
        }
        $_SESSION['parent_name'] = $name;
        flash('success', 'Profile updated.');
        redirect('parent/profile.php');
    }
}
$q = db()->prepare('SELECT * FROM parents WHERE id=?');
$q->execute([$pid]);
$p = $q->fetch();
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>My profile</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email (cannot be changed)<input value="<?= e($p['email']) ?>" disabled></label>
    <label>Full name<input name="full_name" value="<?= e($p['full_name']) ?>" required></label>
    <label>Phone<input name="phone" value="<?= e($p['phone']) ?>" required></label>
    <label>Address<input name="address" value="<?= e($p['address']) ?>" required></label>
    <label>New password (leave blank to keep)<input type="password" name="new_password" minlength="6"></label>
    <button class="btn">Save changes</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
