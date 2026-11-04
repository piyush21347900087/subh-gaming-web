<?php
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Kolkata');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$db = db();
if ($db->connect_error) die('Database connection failed.');
$db->set_charset('utf8mb4');

$success = '';
$error = '';

/* ========== HANDLE MASS UPDATE ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_payouts'])) {
    $payouts = $_POST['payouts'] ?? [];
    
    if (!empty($payouts)) {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE markets SET 
                payout_single_digit = ?, 
                payout_jodi_digit = ?, 
                payout_single_pana = ?, 
                payout_double_pana = ?, 
                payout_triple_pana = ?, 
                payout_half_sangam = ?, 
                payout_full_sangam = ?
                WHERE id = ?");
                
            foreach ($payouts as $mId => $p) {
                $sd = (int)($p['sd'] ?? 0);
                $jd = (int)($p['jd'] ?? 0);
                $sp = (int)($p['sp'] ?? 0);
                $dp = (int)($p['dp'] ?? 0);
                $tp = (int)($p['tp'] ?? 0);
                $hs = (int)($p['hs'] ?? 0);
                $fs = (int)($p['fs'] ?? 0);
                $midInt = (int)$mId;
                
                $stmt->bind_param('iiiiiiii', $sd, $jd, $sp, $dp, $tp, $hs, $fs, $midInt);
                $stmt->execute();
            }
            $stmt->close();
            $db->commit();
            $success = 'All payouts updated successfully! ✅';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Update failed: ' . $e->getMessage();
        }
    } else {
        $error = 'No payout data to update.';
    }
}

/* ========== FETCH MARKETS ========== */
$markets = [];
$res = $db->query("SELECT id, market_name, payout_single_digit, payout_jodi_digit, payout_single_pana, payout_double_pana, payout_triple_pana, payout_half_sangam, payout_full_sangam FROM markets ORDER BY sort_order ASC, id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $markets[] = $row;
    }
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Payout Management | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1c1427;
            --accent-yellow: #ffc107;
            --accent-gold: #f1c40f;
            --bg: #f5f0e5;
            --card: #ffffff;
            --text: #1f2937;
            --border: #e5e7eb;
            --red: #c62828;
            --green: #2e7d32;
            --brown: #4e342e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            padding: 20px 16px;
        }

        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* HEADER BAR */
        .header-bar {
            background: var(--card);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--bg);
            border-radius: 50%;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .back-btn:hover {
            background: var(--border);
            transform: translateX(-2px);
        }

        .title-area h1 {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown);
        }

        .title-area span {
            font-size: 12px;
            color: #6b7280;
        }

        /* ALERTS */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #e8f5e9;
            color: var(--green);
            border: 1px solid #a5d6a7;
        }

        .alert-error {
            background: #ffebee;
            color: var(--red);
            border: 1px solid #ef9a9a;
        }

        /* CARD CONTAINER */
        .data-card {
            background: var(--card);
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 20px;
            overflow-x: auto;
        }

        .table-container {
            width: 100%;
            min-width: 1000px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background: #f9fafb;
            padding: 12px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
            text-align: left;
        }

        td {
            padding: 10px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 13px;
            font-weight: 600;
        }

        tr:nth-child(even) td {
            background: #fafbfb;
        }

        tr:hover td {
            background: #f3f4f6;
        }

        /* INPUT BOXES */
        .payout-input {
            width: 85px;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            outline: none;
            transition: 0.2s;
            text-align: center;
        }

        .payout-input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(241, 196, 15, 0.2);
            background: #fffbeb;
        }

        /* FOOTER SUBMIT */
        .submit-row {
            display: flex;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 2px solid var(--border);
        }

        .btn-submit {
            background: var(--brown);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(78, 52, 46, 0.3);
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #3e2723;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(78, 52, 46, 0.4);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        /* RESPONSIVE */
        @media(max-width: 768px) {
            .header-bar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div class="wrapper">
    
    <!-- HEADER -->
    <div class="header-bar">
        <div class="header-left">
            <a href="list_of_market.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="title-area">
                <h1>Market Payout Management</h1>
                <span>Mass update all payout rates easily.</span>
            </div>
        </div>
        <div>
            <a href="list_of_market.php" class="btn-submit" style="background:#6c757d; box-shadow:none; padding:8px 16px;">
                <i class="fas fa-list"></i> View Markets
            </a>
        </div>
    </div>

    <!-- MESSAGES -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- DATA CARD -->
    <div class="data-card">
        <form method="POST" autocomplete="off">
            <input type="hidden" name="update_all_payouts" value="1">
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">Market Name</th>
                            <th>Single Digit</th>
                            <th>Jodi Digit</th>
                            <th>Single Pana</th>
                            <th>Double Pana</th>
                            <th>Triple Pana</th>
                            <th>Half Sangam</th>
                            <th>Full Sangam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($markets)): ?>
                            <!-- MASS APPLY CONTROLS -->
                            <tr style="background: #fffbeb; border-bottom: 2px solid #fcd34d;">
                                <td style="color: #b45309; font-weight: 800;">
                                    <button type="button" onclick="applyAllMultipliers()" 
                                            style="background:#d97706; color:white; border:none; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 2px 4px rgba(0,0,0,0.15); display:inline-flex; align-items:center; gap:4px;">
                                        <i class="fas fa-bolt"></i> Apply All
                                    </button>
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_sd" placeholder="SD" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_jd" placeholder="JD" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_sp" placeholder="SP" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_dp" placeholder="DP" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_tp" placeholder="TP" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_hs" placeholder="HS" style="background:#fff; border-color:#fcd34d;">
                                </td>
                                <td>
                                    <input type="number" class="payout-input" id="mass_fs" placeholder="FS" style="background:#fff; border-color:#fcd34d;">
                                </td>
                            </tr>

                            <?php foreach ($markets as $m): ?>
                                <tr>
                                    <td style="color: var(--brown); font-weight: 700;">
                                        <?= htmlspecialchars($m['market_name']) ?>
                                    </td>
                                    <!-- Single Digit -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][sd]" 
                                               value="<?= (int)$m['payout_single_digit'] ?>" required>
                                    </td>
                                    <!-- Jodi Digit -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][jd]" 
                                               value="<?= (int)$m['payout_jodi_digit'] ?>" required>
                                    </td>
                                    <!-- Single Pana -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][sp]" 
                                               value="<?= (int)$m['payout_single_pana'] ?>" required>
                                    </td>
                                    <!-- Double Pana -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][dp]" 
                                               value="<?= (int)$m['payout_double_pana'] ?>" required>
                                    </td>
                                    <!-- Triple Pana -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][tp]" 
                                               value="<?= (int)$m['payout_triple_pana'] ?>" required>
                                    </td>
                                    <!-- Half Sangam -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][hs]" 
                                               value="<?= (int)($m['payout_half_sangam'] ?? 10000) ?>" required>
                                    </td>
                                    <!-- Full Sangam -->
                                    <td>
                                        <input type="number" class="payout-input" 
                                               name="payouts[<?= (int)$m['id'] ?>][fs]" 
                                               value="<?= (int)($m['payout_full_sangam'] ?? 100000) ?>" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px; color: #6b7280;">
                                    No markets found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($markets)): ?>
                <div class="submit-row">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update All Payouts
                    </button>
                </div>
            <?php endif; ?>

        </form>
    </div>

</div>

<script>
function applyAllMultipliers() {
    var sd = document.getElementById('mass_sd').value.trim();
    var jd = document.getElementById('mass_jd').value.trim();
    var sp = document.getElementById('mass_sp').value.trim();
    var dp = document.getElementById('mass_dp').value.trim();
    var tp = document.getElementById('mass_tp').value.trim();
    var hs = document.getElementById('mass_hs').value.trim();
    var fs = document.getElementById('mass_fs').value.trim();
    
    // Target all numeric payout inputs in rows
    var formInputs = document.querySelectorAll('.table-container tbody input.payout-input');
    
    var appliedCount = 0;
    
    formInputs.forEach(function(input) {
        var name = input.getAttribute('name');
        if (!name) return;
        
        if (name.endsWith('[sd]') && sd !== '') { input.value = sd; appliedCount++; }
        if (name.endsWith('[jd]') && jd !== '') { input.value = jd; appliedCount++; }
        if (name.endsWith('[sp]') && sp !== '') { input.value = sp; appliedCount++; }
        if (name.endsWith('[dp]') && dp !== '') { input.value = dp; appliedCount++; }
        if (name.endsWith('[tp]') && tp !== '') { input.value = tp; appliedCount++; }
        if (name.endsWith('[hs]') && hs !== '') { input.value = hs; appliedCount++; }
        if (name.endsWith('[fs]') && fs !== '') { input.value = fs; appliedCount++; }
    });

    if (appliedCount > 0) {
        alert('Multipliers successfully applied to all market rows! Make sure to click "Update All Payouts" at the bottom to save.');
    } else {
        alert('Please enter at least one value in the yellow top row to apply.');
    }
}
</script>

</body>
</html>
