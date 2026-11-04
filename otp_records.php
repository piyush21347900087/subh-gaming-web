<?php
// ── AJAX: toggle OTP ON/OFF — handle FIRST before any output ─────────────────
if (isset($_POST['ajax_toggle_otp'])) {
    // Suppress all errors so nothing corrupts the JSON response
    error_reporting(0);
    ini_set('display_errors', 0);

    // Clean any output buffers (ob_start from includes/db.php)
    while (ob_get_level() > 0) { ob_end_clean(); }

    require_once 'includes/db.php';
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    // Must be logged in as admin
    if (!isset($_SESSION['admin'])) {
        echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
        exit;
    }

    header('Content-Type: application/json');

    try {
        $db  = db();
        $statusStr = (isset($_POST['otp_enabled']) && (int)$_POST['otp_enabled'] === 1) ? 'ON' : 'OFF';

        // Ensure row exists
        $db->query("INSERT IGNORE INTO `otp_settings` (`id`, `status`) VALUES (1, 'ON')");

        $st = $db->prepare("UPDATE otp_settings SET status=? WHERE id=1");
        $st->bind_param('s', $statusStr);
        $ok = $st->execute();
        $st->close();

        echo json_encode(['ok' => (bool)$ok, 'otp_enabled' => ($statusStr === 'ON' ? 1 : 0)]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

require_once 'includes/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$db = db();

// ── Ensure otp_settings table exists ─────────────────────────────────────────
try {
    $db->query("CREATE TABLE IF NOT EXISTS `otp_settings` (
        `id`         INT PRIMARY KEY,
        `status`     VARCHAR(10) NOT NULL DEFAULT 'ON',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Only insert if row does NOT exist
    $db->query("INSERT IGNORE INTO `otp_settings` (`id`, `status`) VALUES (1, 'ON')");
} catch (Throwable $e) { /* silent */ }

// ── Read current OTP setting ──────────────────────────────────────────────────
try {
    $otpRes = $db->query("SELECT status FROM otp_settings WHERE id=1 LIMIT 1");
    $otpRow = ($otpRes && $otpRes !== false) ? $otpRes->fetch_assoc() : null;
} catch (Throwable $e) {
    $otpRow = null;
}
$otpEnabled = (isset($otpRow['status']) && strtoupper($otpRow['status']) === 'ON') ? 1 : 0;


// ── Pagination ────────────────────────────────────────────────────────────────
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_res  = $db->query("SELECT COUNT(*) FROM otp_records");
$total_rows = $total_res ? (int)$total_res->fetch_row()[0] : 0;
$total_pages = ceil($total_rows / $limit);

$records = [];
$res = $db->query("SELECT id, mobile, otp, type, status, created_at FROM otp_records ORDER BY id DESC LIMIT $limit OFFSET $offset");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $records[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Records | SUBH GAMING Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f0e5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            color: #4e342e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header h2 i { color: #607d8b; }
        .btn-back {
            background: #0277BD;
            color: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #01579b; }

        /* ── OTP Toggle Card ── */
        .otp-toggle-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            flex-wrap: wrap;
        }
        .otp-toggle-info { flex: 1; }
        .otp-toggle-info h3 {
            color: #fff;
            margin: 0 0 6px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .otp-toggle-info p {
            color: #adb5bd;
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
        }
        .otp-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .otp-status-badge.on  { background: #1de9b6; color: #003d30; }
        .otp-status-badge.off { background: #ff5252; color: #fff; }

        /* Toggle Switch */
        .toggle-wrap { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .toggle-wrap label { color: #adb5bd; font-size: 12px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
        .switch {
            position: relative;
            display: inline-block;
            width: 72px;
            height: 38px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #444;
            border-radius: 38px;
            transition: 0.3s;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 30px; width: 30px;
            left: 4px; bottom: 4px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        input:checked + .slider { background: #1de9b6; }
        input:checked + .slider:before { transform: translateX(34px); }
        .toggle-saving { color: #ffd54f; font-size: 12px; display: none; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f9f9f9;
            font-weight: 700;
            color: #333;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending  { background: #fff3e0; color: #e65100; }
        .status-verified { background: #e8f5e9; color: #2e7d32; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }
        .pagination a:hover { background: #eee; }
        .pagination .active {
            background: #607d8b;
            color: #fff;
            border-color: #607d8b;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<div class="container">

    <div class="header">
        <h2><i class="fas fa-key"></i> OTP Records</h2>
        <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- ══ OTP ON/OFF Toggle Card ══ -->
    <div class="otp-toggle-card">
        <div class="otp-toggle-info">
            <h3><i class="fas fa-shield-alt"></i> OTP Verification System</h3>
            <p>
    
            </p>
            <span class="otp-status-badge <?= $otpEnabled ? 'on' : 'off' ?>" id="otpStatusBadge">
                <i class="fas <?= $otpEnabled ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                OTP System: <strong><?= $otpEnabled ? 'ON' : 'OFF' ?></strong>
            </span>
        </div>
        <div class="toggle-wrap">
            <label>OTP System</label>
            <label class="switch">
                <input type="checkbox" id="otpToggle" <?= $otpEnabled ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
            <span class="toggle-saving" id="toggleSaving"><i class="fas fa-spinner fa-spin"></i> Saving...</span>
        </div>
    </div>

    <!-- ══ OTP Records Table ══ -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mobile Number</th>
                <th>OTP</th>
                <th>Type</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999;">No OTP records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['mobile']) ?></td>
                        <td><?= htmlspecialchars($row['otp']) ?></td>
                        <td><?= htmlspecialchars($row['type']) ?></td>
                        <td>
                            <?php if (strtolower($row['status']) === 'verified'): ?>
                                <span class="status-badge status-verified">Verified</span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><?= htmlspecialchars($row['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['created_at'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Prev</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 3);
            $end   = min($total_pages, $page + 3);
            if ($start > 1) {
                echo '<a href="?page=1">1</a>';
                if ($start > 2) echo '<span>...</span>';
            }
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span>...</span>';
                echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const toggle  = document.getElementById('otpToggle');
    const badge   = document.getElementById('otpStatusBadge');
    const saving  = document.getElementById('toggleSaving');

    toggle.addEventListener('change', function () {
        const val = this.checked ? 1 : 0;
        saving.style.display = 'inline';

        const fd = new FormData();
        fd.append('ajax_toggle_otp', '1');
        fd.append('otp_enabled', val);

        fetch('ajax_otp_toggle.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                saving.style.display = 'none';
                if (data.ok) {
                    if (data.otp_enabled == 1) {
                        badge.className = 'otp-status-badge on';
                        badge.innerHTML = '<i class="fas fa-check-circle"></i> OTP System: <strong>ON</strong>';
                    } else {
                        badge.className = 'otp-status-badge off';
                        badge.innerHTML = '<i class="fas fa-times-circle"></i> OTP System: <strong>OFF</strong>';
                    }
                } else {
                    alert('Error saving setting. Please try again.');
                    toggle.checked = !toggle.checked;
                }
            })
            .catch(() => {
                saving.style.display = 'none';
                alert('Network error. Please try again.');
                toggle.checked = !toggle.checked;
            });
    });
})();
</script>
</body>
</html>
