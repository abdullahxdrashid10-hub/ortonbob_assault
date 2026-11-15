<?php
/** Hospital login using hospital_users credentials. */
$role = 'hospital'; $title = 'Hospital Login';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // SHARED TABLE: hospitals (join for display name).
    $q = db()->prepare('SELECT u.*, h.name FROM hospital_users u JOIN hospitals h ON h.id=u.hospital_id WHERE u.email=?');
    $q->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $u = $q->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['hospital_id'] = (int)$u['hospital_id'];
        $_SESSION['hospital_name'] = $u['name'];
        redirect('hospital/appointments.php');
    }
    flash('error', 'Invalid email or password.');
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Hospital login</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn">Log in</button>
    <p class="muted">New hospital? <a href="register.php">Register</a></p>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
