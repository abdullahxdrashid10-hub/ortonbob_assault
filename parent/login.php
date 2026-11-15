<?php
/** Parent login: verifies hashed password, starts the session. */
$role = 'parent'; $title = 'Parent Login';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $q = db()->prepare('SELECT * FROM parents WHERE email = ?');
    $q->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $p = $q->fetch();
    if ($p && password_verify($_POST['password'] ?? '', $p['password_hash'])) {
        session_regenerate_id(true); // prevent session fixation
        $_SESSION['parent_id'] = (int)$p['id'];
        $_SESSION['parent_name'] = $p['full_name'];
        redirect('parent/dashboard.php');
    }
    flash('error', 'Invalid email or password.');
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Parent login</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn">Log in</button>
    <p class="muted">New here? <a href="register.php">Create an account</a></p>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
