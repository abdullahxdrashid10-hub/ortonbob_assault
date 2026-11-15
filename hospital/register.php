<?php
/**
 * Hospital registration. Creates the hospital record (name/address/location)
 * and our own hospital_users login row linked to it.
 * SHARED TABLE: hospitals insert is isolated below - adapt when admin schema arrives.
 */
$role = 'hospital'; $title = 'Hospital Register';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $loc = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    if ($name === '' || $addr === '' || $loc === '') {
        flash('error', 'Name, address and location are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        flash('error', 'Valid email and a password of 6+ characters are required.');
    } else {
        $dup = db()->prepare('SELECT id FROM hospital_users WHERE email=?');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            flash('error', 'That email is already registered.');
        } else {
            $pdo = db();
            $pdo->beginTransaction(); // both inserts succeed or neither does
            // SHARED TABLE: hospitals
            $pdo->prepare('INSERT INTO hospitals (name,address,location,phone) VALUES (?,?,?,?)')
                ->execute([$name, $addr, $loc, $phone]);
            $hid = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO hospital_users (hospital_id,email,password_hash) VALUES (?,?,?)')
                ->execute([$hid, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $pdo->commit();
            flash('success', 'Hospital registered. Please log in.');
            redirect('hospital/login.php');
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="card auth reveal">
  <h1>Register hospital</h1>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Hospital name<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
    <label>Address<input name="address" required value="<?= e($_POST['address'] ?? '') ?>"></label>
    <label>Location / city<input name="location" required value="<?= e($_POST['location'] ?? '') ?>"></label>
    <label>Phone<input name="phone" value="<?= e($_POST['phone'] ?? '') ?>"></label>
    <label>Login email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" minlength="6" required></label>
    <button class="btn">Register</button>
    <p class="muted">Already registered? <a href="login.php">Log in</a></p>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
