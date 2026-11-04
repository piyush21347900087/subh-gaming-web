<?php
require_once 'includes/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
if (php_sapi_name() !== 'cli' && !isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (mysqli_sql_exception $e) {
    die('DB connection failed');
}

require_once 'process_starline4_wins.php';
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

$isEdit = false;
$market = [
    'id' => '',
    'market_name' => '',
    'open_time' => '10:00:00',
    'close_time' => '12:00:00',
    'today_closed' => 0,
    'saturday_closed' => 0,
    'sunday_closed' => 0,
    'is_published' => 1
];

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM markets4 WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $isEdit = true;
        $market = $row;
    }
    $stmt->close();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $market_name = strtoupper(trim($_POST['market_name'] ?? ''));
    $open_time = trim($_POST['open_time'] ?? '10:00:00');
    $close_time = trim($_POST['close_time'] ?? '12:00:00');
    $today_closed = isset($_POST['today_closed']) ? 1 : 0;
    $saturday_closed = isset($_POST['saturday_closed']) ? 1 : 0;
    $sunday_closed = isset($_POST['sunday_closed']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    if ($market_name === '') {
        $error = 'Market Name is required.';
    } else {
        if ($isEdit) {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE markets4 SET market_name=?, open_time=?, close_time=?, today_closed=?, saturday_closed=?, sunday_closed=?, is_published=? WHERE id=?");
            $stmt->bind_param('sssiiiii', $market_name, $open_time, $close_time, $today_closed, $saturday_closed, $sunday_closed, $is_published, $id);
            if ($stmt->execute()) {
                $_SESSION['flash_toast'] = ['type' => 'success', 'msg' => 'Starline 4 Market updated successfully!'];
                header('Location: list_of_market4.php');
                exit;
            } else {
                $error = 'Update failed: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $db->prepare("INSERT INTO markets4 (market_name, open_time, close_time, today_closed, saturday_closed, sunday_closed, is_published) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssiiii', $market_name, $open_time, $close_time, $today_closed, $saturday_closed, $sunday_closed, $is_published);
            if ($stmt->execute()) {
                $_SESSION['flash_toast'] = ['type' => 'success', 'msg' => 'Starline 4 Market created successfully!'];
                header('Location: list_of_market4.php');
                exit;
            } else {
                $error = 'Creation failed: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $isEdit ? 'Edit' : 'Create' ?> Starline 4 Market – SUBH GAMING</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #8b0000;
            --green: #2e7d32;
            --blue: #0277BD;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'Roboto', sans-serif; background: #f5f0e5; color: #333; }
        .wrapper { max-width: 600px; margin: 30px auto; padding: 0 16px; }
        .topbar { background: #fff; padding: 12px 16px; border-radius: 10px; box-shadow: 0 2px 8px var(--shadow); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .top-title { font-size: 18px; font-weight: 700; color: var(--red); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; }
        .btn-secondary { background: #eee; color: #333; }
        .btn-primary { background: var(--red); color: #fff; }
        .btn-primary:hover { background: #6a0000; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 3px 10px var(--shadow); padding: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; text-transform: uppercase; color: #555; }
        input[type="text"], input[type="time"] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .checkbox-group { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; }
        .checkbox-label { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; text-transform: none; cursor: pointer; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; font-weight: 600; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="wrapper">
        <div class="topbar">
            <a href="list_of_market4.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            <div class="top-title"><?= $isEdit ? 'Edit' : 'Create' ?> Starline 4 Market</div>
            <div></div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card">
            <form method="POST">
                <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$market['id'] ?>"><?php endif; ?>
                
                <div class="form-group">
                    <label>Market Name</label>
                    <input type="text" name="market_name" value="<?= htmlspecialchars($market['market_name']) ?>" required placeholder="e.g. TIME BAZAR, KALYAN, 01:00 PM">
                </div>

                <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div>
                        <label>Open Time</label>
                        <input type="time" name="open_time" value="<?= htmlspecialchars(date('H:i', strtotime($market['open_time'] ?? '10:00:00'))) ?>" required>
                    </div>
                    <div>
                        <label>Close Time (Result Time)</label>
                        <input type="time" name="close_time" value="<?= htmlspecialchars(date('H:i', strtotime($market['close_time'] ?? '12:00:00'))) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Holiday / Closure Days</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label"><input type="checkbox" name="today_closed" <?= !empty($market['today_closed']) ? 'checked' : '' ?>> Today Closed</label>
                        <label class="checkbox-label"><input type="checkbox" name="saturday_closed" <?= !empty($market['saturday_closed']) ? 'checked' : '' ?>> Saturday Closed</label>
                        <label class="checkbox-label"><input type="checkbox" name="sunday_closed" <?= !empty($market['sunday_closed']) ? 'checked' : '' ?>> Sunday Closed</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label class="checkbox-label"><input type="checkbox" name="is_published" <?= !empty($market['is_published']) ? 'checked' : '' ?>> <strong>Active / Published in App</strong></label>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 15px;">
                        <i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> Market
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
