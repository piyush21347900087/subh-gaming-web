<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success = '';
$error = '';
$shouldReload = false;

$createSql = "CREATE TABLE IF NOT EXISTS bonus_rate (
  id INT AUTO_INCREMENT PRIMARY KEY,
  min_amount DECIMAL(12,2) NOT NULL UNIQUE,
  bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

function ensure_bonus_rate_table_admin(mysqli $db): void
{
  global $createSql;
  $db->query($createSql);
  $db->query("CREATE TABLE IF NOT EXISTS bonus_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    bonus_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->query("INSERT IGNORE INTO bonus_settings (id, bonus_enabled) VALUES (1, 1)");

  $count = (int) ($db->query("SELECT COUNT(*) FROM bonus_rate")->fetch_row()[0] ?? 0);
  if ($count === 0) {
    $defaults = [[1000, 150], [2000, 400], [5000, 2000], [10000, 4000], [20000, 6000], [30000, 15000]];
    $stmt = $db->prepare("INSERT INTO bonus_rate (min_amount, bonus_amount, is_active) VALUES (?, ?, 1)");
    foreach ($defaults as $row) {
      $min = (float) $row[0];
      $bonus = (float) $row[1];
      $stmt->bind_param('dd', $min, $bonus);
      $stmt->execute();
    }
    $stmt->close();
  }
}

try {
  $db = db();
  ensure_bonus_rate_table_admin($db);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_bonus_system') {
      $enabled = (int) ($_POST['bonus_enabled'] ?? 0) === 1 ? 1 : 0;
      $stmt = $db->prepare("INSERT INTO bonus_settings (id, bonus_enabled) VALUES (1, ?) ON DUPLICATE KEY UPDATE bonus_enabled=VALUES(bonus_enabled)");
      $stmt->bind_param('i', $enabled);
      $stmt->execute();
      $stmt->close();
      $success = $enabled ? 'Bonus system enable ho gaya.' : 'Bonus system disable ho gaya.';
      $shouldReload = true;
    }

    if ($action === 'save') {
      $id = (int) ($_POST['id'] ?? 0);
      $minAmount = (float) ($_POST['min_amount'] ?? 0);
      $bonusAmount = (float) ($_POST['bonus_amount'] ?? 0);
      $isActive = isset($_POST['is_active']) ? 1 : 0;

      if ($minAmount <= 0) {
        throw new Exception('Minimum amount 0 se bada hona chahiye.');
      }
      if ($bonusAmount < 0) {
        throw new Exception('Bonus amount negative nahi ho sakta.');
      }

      if ($id > 0) {
        $stmt = $db->prepare("UPDATE bonus_rate SET min_amount=?, bonus_amount=?, is_active=? WHERE id=?");
        $stmt->bind_param('ddii', $minAmount, $bonusAmount, $isActive, $id);
        $stmt->execute();
        $stmt->close();
        $success = 'Bonus rate update ho gaya.';
        $shouldReload = true;
      } else {
        $stmt = $db->prepare("INSERT INTO bonus_rate (min_amount, bonus_amount, is_active) VALUES (?, ?, ?)");
        $stmt->bind_param('ddi', $minAmount, $bonusAmount, $isActive);
        $stmt->execute();
        $stmt->close();
        $success = 'Bonus rate add ho gaya.';
        $shouldReload = true;
      }
    }

    if ($action === 'delete') {
      $id = (int) ($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM bonus_rate WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $success = 'Bonus rate delete ho gaya.';
        $shouldReload = true;
      }
    }
  }

  $edit = null;
  if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM bonus_rate WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }

  $rates = [];
  $res = $db->query("SELECT * FROM bonus_rate ORDER BY min_amount ASC");
  while ($res && $row = $res->fetch_assoc()) {
    $rates[] = $row;
  }

  $bonusSystemEnabled = 1;
  $resSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
  if ($resSet && $rowSet = $resSet->fetch_assoc()) {
    $bonusSystemEnabled = (int) $rowSet['bonus_enabled'];
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
  $rates = [];
  $edit = null;
  $bonusSystemEnabled = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bonus Rates | SUBH GAMING</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Poppins, sans-serif; background: #f4f7fb; color: #263238; }
    .topbar { background: #0066cc; color: #fff; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .topbar h1 { font-size: 18px; margin: 0; font-weight: 800; text-transform: uppercase; }
    .topbar a { color: #0066cc; background: #fff; padding: 9px 13px; border-radius: 8px; text-decoration: none; font-weight: 800; font-size: 13px; }
    .wrap { max-width: 980px; margin: 18px auto; padding: 0 14px; }
    .card { background: #fff; border: 1px solid #dce8f7; border-radius: 12px; box-shadow: 0 8px 22px rgba(0, 102, 204, .08); padding: 16px; margin-bottom: 16px; }
    .system-card { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .system-title { font-size: 15px; font-weight: 800; color: #263238; text-transform: uppercase; }
    .system-sub { font-size: 12px; color: #607d8b; font-weight: 700; margin-top: 3px; }
    .switch-row { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; color: #0066cc; text-transform: uppercase; }
    .switch-row input { width: 20px; height: 20px; accent-color: #0066cc; }
    .grid { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) auto; gap: 12px; align-items: end; }
    label { display: block; font-size: 12px; font-weight: 800; color: #51606d; margin-bottom: 6px; text-transform: uppercase; }
    input[type="number"] { width: 100%; border: 1px solid #cbd9e8; border-radius: 9px; padding: 11px; font-size: 14px; font-weight: 700; outline: none; }
    input[type="number"]:focus { border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.12); }
    .bonus-field-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
    .bonus-field-head label { margin-bottom: 0; }
    .check { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-size: 12px; font-weight: 800; color: #0066cc; white-space: nowrap; text-transform: uppercase; }
    .btn { border: 0; border-radius: 9px; padding: 11px 16px; min-height: 42px; font-weight: 800; cursor: pointer; background: #0066cc; color: #fff; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; white-space: nowrap; }
    .btn.danger { background: #c62828; }
    .btn.light { background: #eaf3ff; color: #0066cc; }
    .msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-weight: 700; }
    .ok { background: #e8f5e9; color: #1b5e20; }
    .err { background: #ffebee; color: #b71c1c; }
    table { width: 100%; border-collapse: collapse; overflow: hidden; border-radius: 10px; }
    th, td { padding: 12px 10px; border-bottom: 1px solid #edf2f7; text-align: left; font-size: 14px; }
    th { background: #0066cc; color: #fff; text-transform: uppercase; font-size: 12px; }
    tr:last-child td { border-bottom: 0; }
    .status { font-weight: 800; color: #2e7d32; }
    .status.off { color: #c62828; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    @media (max-width: 760px) {
      .grid { grid-template-columns: 1fr 1fr; }
      .grid .btn { grid-column: 1 / -1; width: 100%; }
      .bonus-field-head { align-items: flex-end; }
      th, td { font-size: 12px; padding: 10px 7px; }
      .actions { flex-direction: column; }
      .btn { justify-content: center; }
    }
    @media (max-width: 520px) {
      .system-card { align-items: stretch; }
      .system-card .btn { width: 100%; }
      .grid { grid-template-columns: 1fr; }
      .bonus-field-head { align-items: center; }
      th, td { font-size: 12px; padding: 10px 7px; }
      .actions { flex-direction: column; }
      .btn { justify-content: center; }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
  <div class="topbar">
    <h1><i class="fas fa-gift"></i> Bonus Rates</h1>
    <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  <div class="wrap">
    <?php if ($success): ?><div class="msg ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
      <form method="post" class="system-card">
        <input type="hidden" name="action" value="toggle_bonus_system">
        <input type="hidden" name="bonus_enabled" value="0">
        <div>
          <div class="system-title">Bonus System</div>
          <div class="system-sub"><?= $bonusSystemEnabled ? 'Users ko active bonus rates ke hisab se bonus milega.' : 'Bonus disabled hai, users ko bonus credit nahi hoga.' ?></div>
        </div>
        <label class="switch-row">
          <input type="checkbox" name="bonus_enabled" value="1" <?= $bonusSystemEnabled ? 'checked' : '' ?>>
          <?= $bonusSystemEnabled ? 'Enabled' : 'Disabled' ?>
        </label>
        <button class="btn" type="submit"><i class="fas fa-power-off"></i>Update Status</button>
      </form>
    </div>

    <div class="card">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="grid">
          <div>
            <label>Deposit Amount From</label>
            <input type="number" step="0.01" min="1" name="min_amount" value="<?= htmlspecialchars((string) ($edit['min_amount'] ?? '')) ?>" placeholder="1000" required>
          </div>
          <div>
            <div class="bonus-field-head">
              <label>Bonus Amount</label>
              <label class="check"><input type="checkbox" name="is_active" <?= !isset($edit) || (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Active</label>
            </div>
            <input type="number" step="0.01" min="0" name="bonus_amount" value="<?= htmlspecialchars((string) ($edit['bonus_amount'] ?? '')) ?>" placeholder="150" required>
          </div>
          <button class="btn" type="submit"><i class="fas fa-save"></i><?= $edit ? 'Update' : 'Add' ?></button>
        </div>
      </form>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Amount From</th>
            <th>Bonus</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rates): foreach ($rates as $idx => $rate): ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td>₹<?= number_format((float) $rate['min_amount'], 2) ?></td>
              <td>₹<?= number_format((float) $rate['bonus_amount'], 2) ?></td>
              <td>
                <?php if (!$bonusSystemEnabled): ?>
                  <span class="status off">Disabled</span>
                <?php else: ?>
                  <span class="status <?= (int) $rate['is_active'] === 1 ? '' : 'off' ?>"><?= (int) $rate['is_active'] === 1 ? 'Active' : 'Off' ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions">
                  <a class="btn light" href="?edit=<?= (int) $rate['id'] ?>"><i class="fas fa-pen"></i>Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this bonus rate?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $rate['id'] ?>">
                    <button class="btn danger" type="submit"><i class="fas fa-trash"></i>Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5">No bonus rates found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($shouldReload): ?>
    <script>
      setTimeout(function () {
        window.location.href = 'admin_bonus_rates.php';
      }, 3000);
    </script>
  <?php endif; ?>
</body>
</html>

