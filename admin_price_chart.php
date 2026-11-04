<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}
require_once 'includes/db.php';
$db = db();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rates']) && is_array($_POST['rates'])) {
        $stmt = $db->prepare("UPDATE game_rates SET win_amount = ? WHERE game_type = ?");
        $ok = true;
        foreach ($_POST['rates'] as $type => $amount) {
            $amount = (int)$amount;
            $stmt->bind_param("is", $amount, $type);
            if (!$stmt->execute()) {
                $ok = false;
            }
        }
        $stmt->close();
        if ($ok) {
            $success = "Rates updated successfully!";
        } else {
            $error = "Some rates failed to update.";
        }
    }
}

// Fetch current rates
$rates = [];
$res = $db->query("SELECT * FROM game_rates ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rates[] = $row;
    }
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Rates Management | Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #c62828;
            --green: #2e7d32;
            --blue: #0277BD;
            --orange: #F57C00;
            --brown: #4e342e;
            --radius: 14px;
            --shadow: rgba(0, 0, 0, 0.10);
            --cardShadow: 0 6px 18px rgba(0, 0, 0, 0.10);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f0e5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--cardShadow);
        }
        
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #eceff1;
            padding-bottom: 15px;
        }
        
        h1 {
            font-size: 24px;
            color: var(--brown);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }
        
        h1 i {
            color: #ffb300;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none !important;
            color: #fff;
            background: var(--blue);
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(2, 119, 189, 0.2);
        }
        
        .back-btn:hover {
            background: #01579b;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .rates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .rate-card {
            background: #fdfbf7;
            border: 1px solid #e0d5c1;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .rate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: var(--brown);
        }
        
        .game-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .play-amount {
            font-size: 14px;
            color: #666;
            background: #eceff1;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }
        
        .input-group span {
            font-size: 18px;
            font-weight: 700;
            color: var(--brown);
        }
        
        input[type="number"] {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            width: 120px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            color: #333;
            transition: border-color 0.3s ease;
        }
        
        input[type="number"]:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(2, 119, 189, 0.1);
        }
        
        .btn-submit {
            display: block;
            width: 100%;
            background: var(--green);
            color: #fff;
            border: none;
            padding: 15px;
            font-size: 18px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        
        .btn-submit:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(46, 125, 50, 0.4);
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .header-row {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            .back-btn {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="container">
        <div class="header-row">
            <h1><i class="fas fa-tags"></i> Game Rates Management</h1>
            <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="rates-grid">
                <?php foreach ($rates as $rate): ?>
                    <div class="rate-card">
                        <div class="game-title"><?= htmlspecialchars($rate['game_type']) ?></div>
                        <div class="play-amount">Play: ₹<?= htmlspecialchars($rate['play_amount']) ?></div>
                        <div class="input-group">
                            <span>₹</span>
                            <input type="number" name="rates[<?= htmlspecialchars($rate['game_type']) ?>]" value="<?= htmlspecialchars($rate['win_amount']) ?>" required min="1">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</body>
</html>

