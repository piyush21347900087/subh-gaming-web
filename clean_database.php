<?php
/**
 * SUBH GAMING - ONE-CLICK DATABASE CLEANER / RESET UTILITY
 * Safely clears all transactional user data, bids, winnings, deposits, withdrawals,
 * chats, logs, and resets auto-increment counters while preserving markets, game rates,
 * and admin configuration.
 *
 * Usage:
 *   Browser: https://yourdomain.com/clean_database.php?confirm=yes&key=SubhGamingClean2026
 *   CLI: php clean_database.php --confirm
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/app_brand.php';

header('Content-Type: text/html; charset=utf-8');

$isCli = (php_sapi_name() === 'cli');
$confirm = false;

if ($isCli) {
    global $argv;
    $confirm = in_array('--confirm', $argv ?? []);
} else {
    $key = $_GET['key'] ?? '';
    $confirmParam = $_GET['confirm'] ?? '';
    if ($confirmParam === 'yes' && ($key === 'SubhGamingClean2026' || isset($_SESSION['admin']))) {
        $confirm = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subh Gaming - Database Reset Utility</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #070b14;
            --panel: #0f172a;
            --border: #1e293b;
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.3);
            --danger: #ef4444;
            --success: #10b981;
            --text: #f8fafc;
            --text-dim: #94a3b8;
        }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            max-width: 640px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        h1 {
            font-size: 24px;
            margin-top: 0;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge {
            background: var(--primary);
            color: #fff;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        p {
            color: var(--text-dim);
            font-size: 14px;
            line-height: 1.6;
        }
        .table-list {
            background: #020617;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #1e293b;
        }
        .row-item {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            border: none;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.45);
        }
        .btn-home {
            background: #1e293b;
            color: #f8fafc;
            margin-left: 10px;
            box-shadow: none;
        }
        .status-ok { color: var(--success); font-weight: bold; }
        .status-skip { color: #f59e0b; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<div class="container">
    <h1>Subh Gaming <span class="badge">DB Cleaner</span></h1>
    <p>This utility wipes all transactional user activity (bids, wins, deposits, withdrawals, KYC records, chats, OTP logs, tokens) while safely retaining your <b>markets, payout rates, admin credentials, and game rules</b>.</p>

    <?php
    $tablesToWipe = [
        'users',
        'bid',
        'bid4',
        'gali_bids',
        'bid_refunds',
        'bid_refund_batches',
        'deposit',
        'deposit_history',
        'withdrawal',
        'wining',
        'recent_winners',
        'market_result',
        'market_result4',
        'gali_result',
        'chat_messages',
        'admin_chat_seen',
        'chat_seen',
        'chat_seen_user',
        'otp_records',
        'send_otp',
        'bank_kyc',
        'crypto_kyc',
        'upi_kyc',
        'upi_numbers',
        'user_fcm_tokens',
        'user_status',
        'page_views',
        'referrals',
        'downloads',
        'market_reverse_log',
        'wins_run_lock'
    ];

    if ($confirm) {
        try {
            $db = db();
            echo '<div class="table-list">';
            foreach ($tablesToWipe as $tbl) {
                $check = $db->query("SHOW TABLES LIKE '{$tbl}'");
                if ($check && $check->num_rows > 0) {
                    $db->query("SET FOREIGN_KEY_CHECKS = 0");
                    $db->query("TRUNCATE TABLE `{$tbl}`");
                    $db->query("ALTER TABLE `{$tbl}` AUTO_INCREMENT = 1");
                    $db->query("SET FOREIGN_KEY_CHECKS = 1");
                    echo "<div class='row-item'><span>Table <b>{$tbl}</b></span> <span class='status-ok'>[CLEANED & RESET]</span></div>";
                } else {
                    echo "<div class='row-item'><span>Table {$tbl}</span> <span class='status-skip'>[NOT FOUND]</span></div>";
                }
            }
            // Ensure admin account exists
            $adminCheck = $db->query("SELECT id FROM admin LIMIT 1");
            if (!$adminCheck || $adminCheck->num_rows === 0) {
                $db->query("INSERT INTO `admin` (`id`, `admin_id`, `name`, `email`, `password`, `role`) VALUES (1, 'ADM0001', 'SUBH GAMING', 'subhgaming@gmail.com', 'Subh@76265', 'master')");
                echo "<div class='row-item'><span>Master Admin Account</span> <span class='status-ok'>[CREATED: subhgaming@gmail.com / Subh@76265]</span></div>";
            }
            echo '</div>';
            echo '<p style="color:#10b981; font-weight:700;">✅ Success! All database tables have been wiped and reset. Subh Gaming is 100% clean and ready for play.</p>';
            echo '<a href="admin_login.php" class="btn" style="background:#8b5cf6;">Go to Admin Panel</a>';
            echo '<a href="index.php" class="btn btn-home">Go to App</a>';
        } catch (Throwable $e) {
            echo '<p style="color:#ef4444;">Error connecting to database: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        echo '<div class="table-list">';
        foreach ($tablesToWipe as $tbl) {
            echo "<div class='row-item'><span>{$tbl}</span> <span style='color:#94a3b8;'>Targeted for wipe</span></div>";
        }
        echo '</div>';
        echo '<p style="color:#f59e0b;">⚠️ Warning: This action cannot be undone. Are you sure you want to completely empty the database?</p>';
        echo '<a href="clean_database.php?confirm=yes&key=SubhGamingClean2026" class="btn">Confirm & Wipe Database Now</a>';
        echo '<a href="admin_dashboard.php" class="btn btn-home">Cancel</a>';
    }
    ?>
</div>
</body>
</html>
