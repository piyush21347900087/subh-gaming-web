<?php
session_start();
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$db = db();
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_condition'])) {
    $marketName = trim($_POST['market_name'] ?? '');
    $conditionType = $_POST['condition_type'] ?? 'low_payout';
    $profitPercentage = max(0, min(100, (int)($_POST['profit_percentage'] ?? 80)));
    $fixedValue = trim($_POST['fixed_value'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($marketName === '') {
        $msg = 'Please select a market.';
        $msgType = 'error';
    } else {
        $stmt = $db->prepare("SELECT id FROM starline4_conditions WHERE market_name=? LIMIT 1");
        $stmt->bind_param('s', $marketName);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $stmt = $db->prepare("UPDATE starline4_conditions SET condition_type=?, profit_percentage=?, fixed_value=?, status=? WHERE market_name=?");
            $stmt->bind_param('sisss', $conditionType, $profitPercentage, $fixedValue, $status, $marketName);
        } else {
            $stmt = $db->prepare("INSERT INTO starline4_conditions (market_name, condition_type, profit_percentage, fixed_value, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssiss', $marketName, $conditionType, $profitPercentage, $fixedValue, $status);
        }
        $stmt->execute();
        $stmt->close();
        $msg = 'Condition saved successfully for ' . ($marketName === 'ALL_MARKETS' ? 'ALL MARKETS' : $marketName) . '! Reloading in 3 seconds...';
        $msgType = 'success';
    }
}

$markets = [];
$resM = $db->query("SELECT market_name FROM markets4 ORDER BY sort_order ASC, id ASC");
if ($resM) while ($row = $resM->fetch_assoc()) $markets[] = $row['market_name'];

$conditions = [];
$resC = $db->query("SELECT * FROM starline4_conditions ORDER BY market_name ASC");
if ($resC) while ($row = $resC->fetch_assoc()) $conditions[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starline 4 Auto-Result Conditions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#3498db;--hover:#2980b9;--bg:#f3f4f6;--text:#1f2937;--muted:#6b7280;--success:#10b981;--error:#ef4444}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
        body{background:var(--bg);color:var(--text);padding:20px}.container{max-width:940px;margin:0 auto}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;gap:12px}.header h1{font-size:24px;font-weight:900;display:flex;align-items:center;gap:10px}.back-btn{padding:10px 18px;background:#fff;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:var(--text);font-weight:700}
        .alert{padding:14px;border-radius:10px;margin-bottom:18px;font-weight:700;display:flex;align-items:center;gap:10px}.alert.success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}.alert.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 4px 6px -1px rgba(0,0,0,.08);margin-bottom:22px}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;margin-bottom:18px}.form-group{display:flex;flex-direction:column;gap:8px}label{font-size:12px;font-weight:900;color:var(--muted);text-transform:uppercase}select,input{padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;outline:none}select:focus,input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(52,152,219,.12)}.btn-save{background:var(--primary);color:#fff;padding:12px 24px;border:0;border-radius:8px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px}.btn-save:hover{background:var(--hover)}
        .table-container{overflow-x:auto}table{width:100%;border-collapse:collapse;margin-top:10px}th{text-align:left;padding:12px;font-size:12px;font-weight:900;color:var(--muted);text-transform:uppercase;border-bottom:2px solid #f3f4f6}td{padding:14px 12px;border-bottom:1px solid #f3f4f6;font-size:14px}.status{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:900}.status.active{background:#d1fae5;color:#065f46}.status.inactive{background:#f3f4f6;color:#374151}.type{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:900;background:#e0f2fe;color:#075985}
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-magic"></i> Starline 4 Auto-Result</h1>
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-chevron-left"></i> Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert <?= htmlspecialchars($msgType) ?>"><i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom:18px;font-size:18px">Set Market Condition</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Select Market</label>
                    <select name="market_name" required>
                        <option value="">-- Choose Market --</option>
                        <option value="ALL_MARKETS">-- ALL MARKETS --</option>
                        <?php foreach ($markets as $m): ?><option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Condition Type</label>
                    <select name="condition_type" id="condition_type" onchange="toggleInputs()">
                        <option value="low_payout">Low Payout</option>
                        <option value="profit_percentage">Profit Percentage</option>
                        <option value="random">Random Pana</option>
                        <option value="fixed">Fixed Result</option>
                    </select>
                </div>
                <div class="form-group" id="percentage_input_group" style="display:none">
                    <label>Profit % Min</label>
                    <input type="number" name="profit_percentage" min="0" max="100" value="80">
                </div>
                <div class="form-group" id="fixed_input_group" style="display:none">
                    <label>Fixed Result (Pana or Pana-Digit)</label>
                    <input type="text" name="fixed_value" placeholder="123 or 123-6" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </div>
            </div>
            <button type="submit" name="save_condition" class="btn-save"><i class="fas fa-save"></i> Save Condition</button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-bottom:18px;font-size:18px">Active Conditions</h2>
        <div class="table-container">
            <table>
                <thead><tr><th>Market Name</th><th>Condition</th><th>Profit %</th><th>Fixed Value</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                <?php if (empty($conditions)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--muted)">No conditions set yet.</td></tr>
                <?php else: foreach ($conditions as $c): ?>
                    <tr>
                        <td style="font-weight:800"><?= htmlspecialchars($c['market_name']) ?></td>
                        <td><span class="type"><?= strtoupper(str_replace('_', ' ', $c['condition_type'])) ?></span></td>
                        <td><?= $c['condition_type'] === 'profit_percentage' ? (int)$c['profit_percentage'] . '%' : '-' ?></td>
                        <td><?= htmlspecialchars($c['fixed_value'] ?: '-') ?></td>
                        <td><span class="status <?= htmlspecialchars($c['status']) ?>"><?= strtoupper($c['status']) ?></span></td>
                        <td style="color:var(--muted);font-size:12px"><?= !empty($c['updated_at']) ? date('d M Y, h:i A', strtotime($c['updated_at'])) : '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleInputs(){
    const type=document.getElementById('condition_type').value;
    document.getElementById('fixed_input_group').style.display=type==='fixed'?'flex':'none';
    document.getElementById('percentage_input_group').style.display=type==='profit_percentage'?'flex':'none';
}

<?php if ($msg && $msgType === 'success'): ?>
setTimeout(function() {
    window.location.href = 'starline4_condition.php';
}, 3000);
<?php endif; ?>
</script>
</body>
</html>
