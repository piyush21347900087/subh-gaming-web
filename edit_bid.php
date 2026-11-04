<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$db = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$msg = "";
// Fetch markets for dropdown
$mRes = $db->query("SELECT market_name FROM markets ORDER BY market_name ASC");
$allMarkets = [];
while($mr = $mRes->fetch_assoc()) $allMarkets[] = $mr['market_name'];

// Fetch available columns to know what we can update
$res = $db->query("DESC bid");
$cols = [];
while($r = $res->fetch_assoc()) $cols[] = $r['Field'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $updates = [];
    $types = "";
    $vals = [];
    $u_gameType = $_POST['game_type'] ?? '';
    $u_bidNum = $_POST['bid_number'] ?? '';

    // Field list for basic updates
    $potentialFields = [
        'user_id', 'market', 'game_type', 'session_type', 'amount', 'bid_time'
    ];
    
    foreach ($potentialFields as $f) {
        if (in_array($f, $cols) && isset($_POST[$f])) {
            $updates[] = "`$f` = ?";
            $types .= "s";
            $vals[] = $_POST[$f];
        }
    }

    // Bid Number Logic - Mapping to actual DB columns
    $digitCol = '';
    $gtU = strtoupper(trim((string)$u_gameType));
    
    // Normalize game type string for matching
    $gtU = str_replace(' ', '_', $gtU);

    if ($gtU === 'SINGLE_DIGIT') $digitCol = 'single_digit';
    elseif ($gtU === 'JODI_DIGIT' || $gtU === 'JODI') $digitCol = 'jodi_digit';
    elseif ($gtU === 'SINGLE_PANA') $digitCol = 'single_pana';
    elseif ($gtU === 'DOUBLE_PANA') $digitCol = 'double_pana';
    elseif ($gtU === 'TRIPLE_PANA') $digitCol = 'triple_pana';
    elseif ($gtU === 'HALF_SANGAM' || $gtU === 'FULL_SANGAM') {
        // Handle Sangams separately if they contain hyphens
        if (strpos($u_bidNum, '-') !== false) {
            $parts = explode('-', $u_bidNum);
            $p1 = trim($parts[0]);
            $p2 = trim($parts[1]);
            
            if ($gtU === 'FULL_SANGAM') {
                $updates[] = "`open_pana` = ?"; $types .= "s"; $vals[] = $p1;
                $updates[] = "`close_pana` = ?"; $types .= "s"; $vals[] = $p2;
            } else {
                // Half Sangam - guess which part is digit vs pana based on length
                if (strlen($p1) === 1) { $updates[] = "`open_digit` = ?"; $types .= "s"; $vals[] = $p1; }
                else { $updates[] = "`open_pana` = ?"; $types .= "s"; $vals[] = $p1; }
                
                if (strlen($p2) === 1) { $updates[] = "`close_digit` = ?"; $types .= "s"; $vals[] = $p2; }
                else { $updates[] = "`close_pana` = ?"; $types .= "s"; $vals[] = $p2; }
            }
        }
    }

    if ($digitCol && in_array($digitCol, $cols)) {
        $updates[] = "`$digitCol` = ?";
        $types .= "s";
        $vals[] = $u_bidNum;
    }

    // Always update bid_modified timestamp
    if (in_array('bid_modified', $cols)) {
        $updates[] = "`bid_modified` = CURRENT_TIMESTAMP";
    }

    if ($updates) {
        $sql = "UPDATE bid SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $vals[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        if ($stmt->execute()) {
            $msg = "✅ Bid #{$id} updated successfully!";
        } else {
            $msg = "❌ Update failed: " . $stmt->error;
        }
    }
}

$stmt = $db->prepare("SELECT * FROM bid WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$bid = $stmt->get_result()->fetch_assoc();
if (!$bid) die("Bid not found");

// --- Game Type Inference ---
$gt = strtoupper(trim((string)($bid['game_type'] ?? '')));
$gameLabel = $gt;

$hasVal = function($v) {
    return $v !== "" && $v !== null && $v !== false;
};

// Determine existing number
$currentBidNum = "";
if ($gt === 'HALF_SANGAM' || $gt === 'FULL_SANGAM') {
    $p1 = $bid['open_pana'] ?: $bid['open_digit'];
    $p2 = $bid['close_pana'] ?: $bid['close_digit'];
    $currentBidNum = $p1 . '-' . $p2;
} elseif ($hasVal($bid['single_digit'])) $currentBidNum = $bid['single_digit'];
elseif ($hasVal($bid['jodi_digit'])) $currentBidNum = $bid['jodi_digit'];
elseif ($hasVal($bid['single_pana'])) $currentBidNum = $bid['single_pana'];
elseif ($hasVal($bid['double_pana'])) $currentBidNum = $bid['double_pana'];
elseif ($hasVal($bid['triple_pana'])) $currentBidNum = $bid['triple_pana'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>Edit Bid #<?= $id ?> | MATKA KING</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --pdark: #4e342e; --plight: #795548; --blue: #0277bd; }
        body { font-family: 'Poppins', sans-serif; background: #f3f4f7; padding: 24px 15px; margin: 0; }
        .container { max-width: 600px; margin: auto; }
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { font-size: 20px; color: var(--pdark); margin: 0; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
        label { font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; text-transform: uppercase; }
        input, select { 
            padding: 12px; border: 1.5px solid #eee; border-radius: 10px; font-size: 14px; 
            background: #fafafa; transition: all 0.2s; width: 100%; box-sizing: border-box;
        }
        input:focus { border-color: var(--pdark); background: #fff; outline: none; }
        .btn-save { 
            background: var(--pdark); color: #fff; border: none; padding: 15px; border-radius: 10px; 
            font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 20px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .toast { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; }
        .msg-ok { background: #e8f5e9; color: #2e7d32; }
        .msg-err { background: #ffebee; color: #c62828; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: var(--blue); text-decoration: none; font-size: 14px; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Edit Bid #<?= $id ?></h1>
            </div>

            <?php if($msg): ?>
                <div class="toast <?= strpos($msg, '✅') !== false ? 'msg-ok' : 'msg-err' ?>"><?= $msg ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="save">
                
                <div class="form-grid">
                    <div>
                        <label>User ID</label>
                        <input type="text" name="user_id" value="<?= htmlspecialchars($bid['user_id']) ?>">
                    </div>

                    <div>
                        <label>Market</label>
                        <select name="market">
                            <?php foreach($allMarkets as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= ($bid['market'] === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Game Type</label>
                        <select name="game_type">
                            <?php 
                            $types = ['SINGLE_DIGIT', 'JODI_DIGIT', 'SINGLE_PANA', 'DOUBLE_PANA', 'TRIPLE_PANA', 'HALF_SANGAM', 'FULL_SANGAM'];
                            foreach($types as $t):
                            ?>
                            <option value="<?= $t ?>" <?= ($gt === $t) ? 'selected' : '' ?>><?= str_replace('_', ' ', $t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Session</label>
                        <select name="session_type">
                            <option value="Open" <?= ($bid['session_type'] === 'Open') ? 'selected' : '' ?>>Open</option>
                            <option value="Close" <?= ($bid['session_type'] === 'Close') ? 'selected' : '' ?>>Close</option>
                        </select>
                    </div>

                    <div>
                        <label>Bid Number (Digit/Pana)</label>
                        <input type="text" name="bid_number" value="<?= htmlspecialchars($currentBidNum) ?>" placeholder="Enter number or Pana">
                    </div>

                    <div>
                        <label>Amount</label>
                        <input type="number" name="amount" value="<?= (int)$bid['amount'] ?>">
                    </div>

                    <div>
                        <label>Bid Time</label>
                        <input type="text" name="bid_time" value="<?= htmlspecialchars($bid['bid_time']) ?>">
                    </div>
                </div>

                <button class="btn-save" type="submit">
                    <i class="fas fa-save"></i> Update Bid
                </button>
            </form>
        </div>
        <a href="all_bid_data.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to All Bids</a>
    </div>
</body>
</html>
