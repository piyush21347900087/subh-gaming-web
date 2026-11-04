<?php
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Kolkata');

session_start();
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}
$adminName = $_SESSION['admin'];
$db = db();

// Auto-create table if not exists
$db->query("CREATE TABLE IF NOT EXISTS jackpot_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    market_name VARCHAR(100) NOT NULL,
    condition_type ENUM('low_payout', 'profit_percentage', 'fixed', 'random') DEFAULT 'low_payout',
    profit_percentage INT DEFAULT 80,
    fixed_value VARCHAR(10) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Update existing table schema if needed
$db->query("ALTER TABLE jackpot_conditions MODIFY COLUMN condition_type ENUM('low_payout', 'profit_percentage', 'fixed', 'random') DEFAULT 'low_payout'");

$checkCol = $db->query("SHOW COLUMNS FROM jackpot_conditions LIKE 'profit_percentage'");
if ($checkCol && $checkCol->num_rows === 0) {
    $db->query("ALTER TABLE jackpot_conditions ADD COLUMN profit_percentage INT DEFAULT 80 AFTER condition_type");
}

$msg = '';
$msgType = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_condition'])) {
        $market_name = $_POST['market_name'] ?? '';
        $condition_type = $_POST['condition_type'] ?? 'low_payout';
        $profit_percentage = (int)($_POST['profit_percentage'] ?? 80);
        $fixed_value = $_POST['fixed_value'] ?? null;
        $status = $_POST['status'] ?? 'active';

        if (empty($market_name)) {
            $msg = "Please select a market.";
            $msgType = "error";
        } else {
            // If market_name is 'ALL_MARKETS', we update/insert the global setting
            $stmt = $db->prepare("SELECT id FROM jackpot_conditions WHERE market_name = ?");
            $stmt->bind_param("s", $market_name);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt_up = $db->prepare("UPDATE jackpot_conditions SET condition_type = ?, profit_percentage = ?, fixed_value = ?, status = ? WHERE market_name = ?");
                $stmt_up->bind_param("sisss", $condition_type, $profit_percentage, $fixed_value, $status, $market_name);
                $stmt_up->execute();
            } else {
                $stmt_in = $db->prepare("INSERT INTO jackpot_conditions (market_name, condition_type, profit_percentage, fixed_value, status) VALUES (?, ?, ?, ?, ?)");
                $stmt_in->bind_param("ssiss", $market_name, $condition_type, $profit_percentage, $fixed_value, $status);
                $stmt_in->execute();
            }
            $msg = "Condition saved successfully for " . ($market_name === 'ALL_MARKETS' ? 'ALL MARKETS' : $market_name) . "!";
            $msgType = "success";
        }
    }
}

// Fetch all Jackpot 3 markets
$markets = [];
$resM = $db->query("SELECT market_name FROM markets3 ORDER BY sort_order ASC, id ASC");
if ($resM) {
    while ($row = $resM->fetch_assoc()) {
        $markets[] = $row['market_name'];
    }
}

// Fetch existing conditions
$conditions = [];
$resC = $db->query("SELECT * FROM jackpot_conditions");
if ($resC) {
    while ($row = $resC->fetch_assoc()) {
        $conditions[$row['market_name']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jackpot 3 Auto-Result Conditions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --success: #10b981;
            --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text-main); padding: 20px; }

        .container { max-width: 900px; margin: 0 auto; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .back-btn { padding: 10px 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: var(--text-main); font-weight: 600; transition: 0.2s; }
        .back-btn:hover { background: #f9fafb; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .card { background: var(--card-bg); border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        select, input { padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: 0.2s; }
        select:focus, input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .btn-save { background: var(--primary); color: #fff; padding: 12px 25px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: var(--primary-hover); }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid #f3f4f6; }
        td { padding: 15px 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-badge.active { background: #d1fae5; color: #065f46; }
        .status-badge.inactive { background: #f3f4f6; color: #374151; }

        .type-badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #e0e7ff; color: #3730a3; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-magic text-primary"></i> Jackpot 3 Auto-Result</h1>
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-chevron-left"></i> Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>">
            <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 18px;">Set Market Condition</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Select Market</label>
                    <select name="market_name" required>
                        <option value="">-- Choose Market --</option>
                        <option value="ALL_MARKETS" style="font-weight: 800; color: var(--primary);">-- ALL MARKETS --</option>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Condition Type</label>
                    <select name="condition_type" id="condition_type" onchange="toggleInputs()">
                        <option value="low_payout">Low Payout (Profitable)</option>
                        <option value="profit_percentage">Profit Percentage</option>
                        <option value="random">Random Number</option>
                        <option value="fixed">Fixed Number</option>
                    </select>
                </div>
                <div class="form-group" id="percentage_input_group" style="display: none;">
                    <label>Profit % (Min)</label>
                    <input type="number" name="profit_percentage" placeholder="e.g. 80" min="0" max="100" value="80">
                </div>
                <div class="form-group" id="fixed_input_group" style="display: none;">
                    <label>Fixed Number (2 Digits)</label>
                    <input type="text" name="fixed_value" placeholder="e.g. 25" maxlength="2">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="save_condition" class="btn-save">
                <i class="fas fa-save"></i> Save Condition
            </button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 18px;">Active Conditions</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Market Name</th>
                        <th>Condition Type</th>
                        <th>Profit %</th>
                        <th>Fixed Value</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($conditions)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No conditions set yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($conditions as $c): ?>
                            <?php $isAll = ($c['market_name'] === 'ALL_MARKETS'); ?>
                            <tr style="<?= $isAll ? 'background: #fdf2f2;' : '' ?>">
                                <td style="font-weight: 700;">
                                    <?= $isAll ? '<span style="color:#ef4444;"><i class="fas fa-globe"></i> ALL MARKETS</span>' : htmlspecialchars($c['market_name']) ?>
                                </td>
                                <td><span class="type-badge"><?= strtoupper(str_replace('_', ' ', $c['condition_type'])) ?></span></td>
                                <td style="font-weight: 700; color: var(--success);"><?= $c['condition_type'] === 'profit_percentage' ? $c['profit_percentage'] . '%' : '-' ?></td>
                                <td><?= $c['fixed_value'] ?: '-' ?></td>
                                <td><span class="status-badge <?= $c['status'] ?>"><?= strtoupper($c['status']) ?></span></td>
                                <td style="color: var(--text-muted); font-size: 12px;"><?= date('d M Y, h:i A', strtotime($c['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleInputs() {
        const type = document.getElementById('condition_type').value;
        const fixedGroup = document.getElementById('fixed_input_group');
        const percentGroup = document.getElementById('percentage_input_group');
        
        fixedGroup.style.display = (type === 'fixed') ? 'flex' : 'none';
        percentGroup.style.display = (type === 'profit_percentage') ? 'flex' : 'none';
    }
</script>

</body>
</html>
