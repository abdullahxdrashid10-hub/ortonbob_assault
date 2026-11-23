<?php
/**
 * hospitals.php - Admin Hospital CRUD & Activation
 * 
 * Allows Admin to add new hospitals, edit hospital details, activate/deactivate
 * self-registered hospitals (syncing users.status & hospitals.status), and safely
 * delete or deactivate hospitals.
 */

$role = 'admin';
$title = 'Manage Hospitals';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

require_admin();
$adminId = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hospital') {
        $name     = trim($_POST['name'] ?? '');
        $regNo    = trim($_POST['registration_no'] ?? '') ?: null;
        $address  = trim($_POST['address'] ?? '');
        $city     = trim($_POST['city'] ?? '');
        $area     = trim($_POST['area'] ?? '') ?: null;
        $phone    = trim($_POST['phone'] ?? '') ?: null;
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $open     = trim($_POST['opening_time'] ?? '09:00');
        $close    = trim($_POST['closing_time'] ?? '17:00');
        $cap      = (int)($_POST['daily_capacity'] ?? 50);
        $pass     = $_POST['password'] ?? '';

        if (strlen($open) === 5) $open .= ':00';
        if (strlen($close) === 5) $close .= ':00';

        if ($name === '' || mb_strlen($name) > 150) {
            flash('error', 'Hospital name is required (max 150 chars).');
        } elseif ($address === '' || mb_strlen($address) > 255) {
            flash('error', 'Address is required (max 255 chars).');
        } elseif ($city === '' || mb_strlen($city) > 80) {
            flash('error', 'City is required (max 80 chars).');
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            flash('error', 'Valid login email is required (max 150 chars).');
        } elseif ($open >= $close) {
            flash('error', 'Closing time must be after opening time.');
        } elseif ($cap < 1 || $cap > 1000) {
            flash('error', 'Daily capacity must be between 1 and 1,000.');
        } elseif (strlen($pass) < 6 || strlen($pass) > 72) {
            flash('error', 'Password must be between 6 and 72 characters.');
        } else {
            try {
                shared_admin_add_hospital(
                    $adminId,
                    $name,
                    $email,
                    password_hash($pass, PASSWORD_DEFAULT),
                    $address,
                    $city,
                    $area,
                    $phone,
                    $regNo,
                    $open,
                    $close,
                    $cap,
                    'active'
                );
                flash('success', "Hospital '$name' added and activated.");
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (($e->errorInfo[1] ?? 0) === 1062 || str_contains($msg, 'Duplicate entry')) {
                    flash('error', 'A hospital with that email or registration number already exists.');
                } else {
                    flash('error', 'Could not add hospital.');
                }
            }
        }
        redirect('admin/hospitals.php');
    } elseif ($action === 'update_hospital') {
        $hospId  = (int)($_POST['hospital_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $regNo   = trim($_POST['registration_no'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '');
        $city    = trim($_POST['city'] ?? '');
        $area    = trim($_POST['area'] ?? '') ?: null;
        $phone   = trim($_POST['phone'] ?? '') ?: null;
        $open    = trim($_POST['opening_time'] ?? '09:00');
        $close   = trim($_POST['closing_time'] ?? '17:00');
        $cap     = (int)($_POST['daily_capacity'] ?? 50);
        $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if (strlen($open) === 5) $open .= ':00';
        if (strlen($close) === 5) $close .= ':00';

        if ($hospId <= 0 || $name === '' || $address === '' || $city === '') {
            flash('error', 'Hospital name, address, and city are required.');
        } elseif ($open >= $close) {
            flash('error', 'Closing time must be after opening time.');
        } else {
            $ok = shared_admin_update_hospital($hospId, $name, $address, $city, $area, $phone, $regNo, $open, $close, $cap, $status);
            flash($ok ? 'success' : 'error', $ok ? 'Hospital updated successfully.' : 'Failed to update hospital.');
        }
        redirect('admin/hospitals.php');
    } elseif ($action === 'delete_hospital') {
        $hospId = (int)($_POST['hospital_id'] ?? 0);
        $res = shared_admin_delete_hospital($hospId);
        if ($res === 'deleted') {
            flash('success', 'Hospital deleted permanently.');
        } elseif ($res === 'deactivated') {
            flash('success', 'Hospital has existing appointments, so it was deactivated (set inactive) instead of deleted.');
        } else {
            flash('error', 'Could not remove hospital.');
        }
        redirect('admin/hospitals.php');
    }
}

$search    = trim($_GET['q'] ?? '');
$hospitals = shared_admin_get_all_hospitals($search);
$editId    = (int)($_GET['edit'] ?? 0);
$editRow   = null;
foreach ($hospitals as $h) {
    if ((int)$h['hospital_id'] === $editId) {
        $editRow = $h;
        break;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <h1>Manage Hospitals</h1>
  <p class="muted">Add new vaccination centers, activate self-registered hospitals, or update facility capacity and hours.</p>
</div>

<div class="grid2">
  <!-- Left: Add or Edit Hospital Form -->
  <section class="card reveal">
    <?php if ($editRow): ?>
      <h2>Edit Hospital #<?= (int)$editRow['hospital_id'] ?></h2>
      <form method="post" action="hospitals.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_hospital">
        <input type="hidden" name="hospital_id" value="<?= (int)$editRow['hospital_id'] ?>">

        <label>Hospital Name:<input type="text" name="name" required maxlength="150" value="<?= e($editRow['hospital_name']) ?>"></label>
        <div class="grid2">
          <label>Reg #:<input type="text" name="registration_no" maxlength="60" value="<?= e($editRow['registration_no'] ?? '') ?>"></label>
          <label>Phone:<input type="text" name="phone" maxlength="20" value="<?= e($editRow['phone'] ?? '') ?>"></label>
        </div>
        <label>Address:<input type="text" name="address" required maxlength="255" value="<?= e($editRow['address']) ?>"></label>
        <div class="grid2">
          <label>City:<input type="text" name="city" required maxlength="80" value="<?= e($editRow['city']) ?>"></label>
          <label>Area:<input type="text" name="area" maxlength="100" value="<?= e($editRow['area'] ?? '') ?>"></label>
        </div>
        <div class="grid3">
          <label>Open:<input type="time" name="opening_time" required value="<?= e(substr($editRow['opening_time'], 0, 5)) ?>"></label>
          <label>Close:<input type="time" name="closing_time" required value="<?= e(substr($editRow['closing_time'], 0, 5)) ?>"></label>
          <label>Capacity:<input type="number" name="daily_capacity" min="1" max="1000" required value="<?= (int)$editRow['daily_capacity'] ?>"></label>
        </div>
        <label>
          Status:
          <select name="status">
            <option value="active" <?= $editRow['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $editRow['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </label>
        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn" style="flex:1;">Save Changes</button>
          <a href="hospitals.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <h2>Add New Hospital</h2>
      <form method="post" action="hospitals.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_hospital">

        <label>Hospital Name:<input type="text" name="name" required maxlength="150" placeholder="e.g. Indus Children Center"></label>
        <div class="grid2">
          <label>Reg #:<input type="text" name="registration_no" maxlength="60" placeholder="REG-KHI-009"></label>
          <label>Phone:<input type="text" name="phone" maxlength="20" placeholder="021-34560000"></label>
        </div>
        <label>Address:<input type="text" name="address" required maxlength="255" placeholder="Street & Block"></label>
        <div class="grid2">
          <label>City:<input type="text" name="city" required maxlength="80" placeholder="Karachi"></label>
          <label>Area:<input type="text" name="area" maxlength="100" placeholder="Korangi"></label>
        </div>
        <div class="grid3">
          <label>Open:<input type="time" name="opening_time" required value="09:00"></label>
          <label>Close:<input type="time" name="closing_time" required value="17:00"></label>
          <label>Capacity:<input type="number" name="daily_capacity" min="1" max="1000" required value="50"></label>
        </div>
        <label>Login Email:<input type="email" name="email" required maxlength="150" placeholder="admin@hospital.pk"></label>
        <label>Initial Password:<input type="password" name="password" required minlength="6" maxlength="72" placeholder="Min 6 chars"></label>
        <button type="submit" class="btn">Add &amp; Activate Hospital</button>
      </form>
    <?php endif; ?>
  </section>

  <!-- Right: Hospitals List -->
  <section class="card reveal">
    <h2>Registered Facilities (<?= count($hospitals) ?>)</h2>
    <form method="get" action="hospitals.php" class="search" style="margin-bottom:14px;">
      <input type="text" name="q" placeholder="Filter hospitals..." value="<?= e($search) ?>">
      <button type="submit" class="btn btn-sm">Filter</button>
    </form>

    <?php foreach ($hospitals as $h): ?>
      <div class="row">
        <div>
          <strong><?= e($h['hospital_name']) ?></strong>
          <span class="badge <?= $h['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>" style="margin-left:6px;">
            <?= ucfirst($h['status']) ?>
          </span>
          <div class="muted" style="font-size:0.84rem; margin-top:4px;">
            <?= e($h['city']) ?><?= !empty($h['area']) ? ' (' . e($h['area']) . ')' : '' ?> &middot;
            <?= substr($h['opening_time'], 0, 5) ?>–<?= substr($h['closing_time'], 0, 5) ?> &middot;
            Cap: <?= (int)$h['daily_capacity'] ?>/day
          </div>
          <div class="muted" style="font-size:0.8rem;"><?= e($h['email']) ?></div>
        </div>
        <div class="actions">
          <a href="hospitals.php?edit=<?= (int)$h['hospital_id'] ?>" class="btn btn-sm btn-ghost">Edit</a>
          <form method="post" action="hospitals.php" onsubmit="return confirm('Delete or deactivate <?= e($h['hospital_name']) ?>?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_hospital">
            <input type="hidden" name="hospital_id" value="<?= (int)$h['hospital_id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
