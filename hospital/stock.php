<?php
/**
 * stock.php - Hospital Vaccine Inventory & Stock Management
 * 
 * 3B Item 10: Lists all active vaccines with this hospital's current status and quantity
 * (via LEFT JOIN hospital_vaccine_stock).
 * Form to update vaccine stock via CALL sp_update_vaccine_status(hospital_id, vaccine_id, status, quantity, user_id).
 * In PHP: rejects unknown or inactive vaccines, and forces 'unavailable' when quantity is 0.
 */

$role = 'hospital';
$title = 'Vaccine Stock';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

// Authentication guard
require_hospital();
$hospitalId = (int)$_SESSION['hospital_id'];
$userId     = (int)$_SESSION['user_id'];

// Handle Stock Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
    $status    = strtolower(trim($_POST['status'] ?? 'unavailable'));
    $quantity  = (int)($_POST['quantity'] ?? 0);

    // Rule 1: In PHP: reject unknown or inactive vaccines
    if ($vaccineId <= 0 || !shared_is_vaccine_active($vaccineId)) {
        flash('error', 'Unknown or inactive vaccine selected.');
    } elseif ($quantity < 0 || $quantity > 50000) {
        flash('error', 'Stock quantity must be a non-negative number up to 50,000.');
    } else {
        // Rule 2: Force 'unavailable' when quantity is 0
        if ($quantity === 0) {
            $status = 'unavailable';
        } elseif (!in_array($status, ['available', 'unavailable'], true)) {
            $status = 'available';
        }

        // Call stored procedure sp_update_vaccine_status via shared query
        $ok = shared_update_hospital_stock($hospitalId, $vaccineId, $status, $quantity, $userId);

        if ($ok) {
            flash('success', 'Vaccine stock updated successfully.');
        } else {
            flash('error', 'Failed to update vaccine stock.');
        }
    }
    redirect('hospital/stock.php');
}

// Fetch all active vaccines with this hospital's stock
$stockItems = shared_get_hospital_inventory($hospitalId);

require __DIR__ . '/../includes/header.php';
?>

<div class="reveal">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Vaccine Stock Inventory</h1>
      <p class="muted">Manage real-time supply levels for required child immunization doses at this healthcare facility.</p>
    </div>
    <div>
      <a href="appointments.php" class="btn btn-sm btn-ghost">View Appointments</a>
    </div>
  </div>
</div>

<section class="card reveal">
  <div class="scroll">
    <table class="table">
      <thead>
        <tr>
          <th>Vaccine Name</th>
          <th>Protects Against</th>
          <th>Total Doses</th>
          <th>Current Stock</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th>Update Stock</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stockItems as $item): ?>
          <?php 
            $isAvail = ($item['stock_status'] === 'available' && (int)$item['quantity'] > 0);
          ?>
          <tr>
            <td>
              <strong><?= e($item['vaccine_name']) ?></strong>
              <span class="muted" style="display:block; font-size:0.8rem;">
                Code: <?= e($item['short_code']) ?>
              </span>
            </td>
            <td><?= e($item['disease_prevented']) ?></td>
            <td><span class="badge badge-primary"><?= (int)$item['total_doses'] ?> doses</span></td>
            <td>
              <span style="font-size:1.1rem; font-weight:700; color:<?= $isAvail ? 'var(--primary)' : 'var(--danger)' ?>;">
                <?= (int)$item['quantity'] ?>
              </span> units
            </td>
            <td>
              <span class="badge <?= $isAvail ? 'badge-success' : 'badge-danger' ?>">
                <?= ucfirst($item['stock_status']) ?>
              </span>
            </td>
            <td style="font-size:0.85rem;" class="muted">
              <?= !empty($item['updated_at']) ? date('d-M-Y H:i', strtotime($item['updated_at'])) : 'Never updated' ?>
            </td>
            <td style="min-width:240px;">
              <form method="post" action="stock.php" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="vaccine_id" value="<?= (int)$item['vaccine_id'] ?>">

                <input 
                  type="number" 
                  name="quantity" 
                  min="0" 
                  max="50000" 
                  value="<?= (int)$item['quantity'] ?>" 
                  required 
                  style="width:75px; padding:5px; font-size:0.85rem;"
                  title="Dose count in stock"
                >

                <select name="status" style="width:115px; padding:5px; font-size:0.85rem;">
                  <option value="available" <?= ($item['stock_status'] === 'available' && (int)$item['quantity'] > 0) ? 'selected' : '' ?>>Available</option>
                  <option value="unavailable" <?= ($item['stock_status'] === 'unavailable' || (int)$item['quantity'] === 0) ? 'selected' : '' ?>>Unavailable</option>
                </select>

                <button type="submit" class="btn btn-sm" style="padding:5px 10px;">Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
