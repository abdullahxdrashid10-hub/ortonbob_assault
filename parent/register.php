<?php
/** Parent registration: validates input, stores a hashed password. */
$role = 'parent'; $title = 'Parent Register';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '' || $phone === '' || $addr === '') {
        flash('error', 'Please fill in all fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a valid email address.');
    } elseif (strlen($pass) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } else {
        // Reject duplicate email.
        $q = db()->prepare('SELECT id FROM parents WHERE email = ?');
        $q->execute([$email]);
        if ($q->fetch()) {
            flash('error', 'That email is already registered.');
        } else {
            db()->prepare('INSERT INTO parents (full_name,email,phone,address,password_hash) VALUES (?,?,?,?,?)')
                ->execute([$name, $email, $phone, $addr, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('success', 'Account created. Please log in.');
            redirect('parent/login.php');
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Create parent account</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Full name<input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></label>
    <label>Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Phone<input name="phone" required value="<?= e($_POST['phone'] ?? '') ?>"></label>
    <label>Address<input name="address" required value="<?= e($_POST['address'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" minlength="6" required></label>
    <button class="btn">Register</button>
    <p class="muted">Already registered? <a href="login.php">Log in</a></p>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
