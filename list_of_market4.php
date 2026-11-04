<?php
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
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
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+05:30'");
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

/* ---------- Handle Sort Order Update (AJAX) ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'update_order') {
    if (!isset($_POST['order']) || !is_array($_POST['order'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    $order = $_POST['order'];
    $stmt = $db->prepare("UPDATE markets4 SET sort_order = ? WHERE id = ?");

    foreach ($order as $position => $id) {
        $id = (int) $id;
        $stmt->bind_param('ii', $position, $id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

/* ---------- Flash toast ---------- */
$flashToast = isset($_SESSION['flash_toast']) ? $_SESSION['flash_toast'] : null;
unset($_SESSION['flash_toast']);

function setFlash($type, $msg)
{
    $_SESSION['flash_toast'] = ['type' => $type, 'msg' => $msg];
}

/* ---------- DELETE market ---------- */
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $did = (int) $_GET['delete'];

    $stmt = $db->prepare("SELECT market_name FROM markets4 WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $did);
    $stmt->execute();
    $stmt->bind_result($mName);

    if ($stmt->fetch()) {
        $stmt->close();
        try {
            $stmt2 = $db->prepare("DELETE FROM markets4 WHERE id=? LIMIT 1");
            $stmt2->bind_param('i', $did);
            $stmt2->execute();
            $stmt2->close();

            // Also cleanup related starline4 tables
            $stmtD = $db->prepare("DELETE FROM bid4 WHERE UPPER(market)=UPPER(?)");
            if ($stmtD) { $stmtD->bind_param('s', $mName); $stmtD->execute(); $stmtD->close(); }
            $stmtR = $db->prepare("DELETE FROM market_result4 WHERE UPPER(market)=UPPER(?)");
            if ($stmtR) { $stmtR->bind_param('s', $mName); $stmtR->execute(); $stmtR->close(); }
            $stmtC = $db->prepare("DELETE FROM starline4_conditions WHERE UPPER(market_name)=UPPER(?)");
            if ($stmtC) { $stmtC->bind_param('s', $mName); $stmtC->execute(); $stmtC->close(); }

            setFlash('success', 'Starline 4 Market deleted successfully ✅');
        } catch (Exception $e) {
            setFlash('error', 'Delete failed: ' . $e->getMessage());
        }
    } else {
        $stmt->close();
        setFlash('error', 'Market not found.');
    }
    header('Location: list_of_market4.php');
    exit;
}

/* ---------- Publish / Hide toggle ---------- */
if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];

    $stmt = $db->prepare("SELECT is_published FROM markets4 WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($currPub);

    if ($stmt->fetch()) {
        $stmt->close();
        $newPublished = ($currPub == 1) ? 0 : 1;

        $stmt2 = $db->prepare("UPDATE markets4 SET is_published=? WHERE id=? LIMIT 1");
        $stmt2->bind_param('ii', $newPublished, $id);
        $stmt2->execute();
        $stmt2->close();
        setFlash('success', 'Status updated ✅');
    } else {
        $stmt->close();
        setFlash('error', 'Market not found.');
    }
    header('Location: list_of_market4.php');
    exit;
}

$markets = [];
$res = $db->query("SELECT * FROM markets4 ORDER BY sort_order ASC, close_time ASC, id ASC");
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
    <meta charset="UTF-8">
    <title>Starline 4 Market List – SUBH GAMING Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #8b0000;
            --green: #2e7d32;
            --blue: #0277BD;
            --orange: #F57C00;
            --brown: #4e342e;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: 'Roboto', sans-serif;
            background: #f5f0e5; color: #333;
        }
        a { text-decoration: none; color: inherit; }
        .wrapper { max-width: 1200px; margin: 20px auto 30px; padding: 0 16px; }
        .topbar {
            background: #fff; padding: 12px 16px; border-radius: 10px;
            box-shadow: 0 2px 8px var(--shadow); justify-content: space-between;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .top-title { font-size: 18px; font-weight: 700; color: var(--red); display: flex; align-items: center; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
            border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; border: none;
        }
        .btn-secondary { background: #eeeeee; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-primary { background: var(--red); color: #fff; }
        .btn-primary:hover { background: #6a0000; }
        .btn-condition { background: #673ab7; color: #fff; }
        .btn-condition:hover { background: #512da8; }
        .card {
            background: #fff; border-radius: 10px; box-shadow: 0 3px 10px var(--shadow);
            padding: 12px; overflow-x: auto; margin-top: 16px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 700px; }
        th { background: #f7f3ea; padding: 10px; position: sticky; top: 0; text-align: center; }
        td { padding: 10px 8px; border-bottom: 1px solid #eee; text-align: center; }
        tr:nth-child(even) td { background: #faf7f1; }
        .status-active { color: var(--green); font-weight: 700; }
        .status-inactive { color: var(--red); font-weight: 700; }
        .actions a { margin: 0 4px; padding: 6px 10px; border-radius: 4px; font-size: 12px; }
        .actions .edit { background: #e3f2fd; color: #1565c0; }
        .actions .delete { background: #ffebee; color: #c62828; }
        .toast {
            position: fixed; bottom: 20px; right: 20px; padding: 12px 20px;
            border-radius: 8px; color: #fff; font-weight: 600; z-index: 999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); animation: slideIn 0.3s ease;
        }
        .toast.success { background: var(--green); }
        .toast.error { background: var(--red); }
        tbody tr { cursor: move; }
        #saveOrderBtn { display: none; margin-right: 8px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        setTimeout(function () {
            var t = document.getElementById('toast');
            if (t) t.style.display = 'none';
        }, 4000);

        $(document).ready(function () {
            $("#marketTableBody").sortable({
                helper: function (e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function (index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                update: function (event, ui) {
                    $('#saveOrderBtn').show();
                }
            }).disableSelection();
        });

        function saveOrder() {
            var order = [];
            $('input[name="market_ids[]"]').each(function () {
                order.push($(this).val());
            });

            $.ajax({
                url: 'list_of_market4.php',
                type: 'POST',
                data: { action: 'update_order', order: order },
                success: function (response) {
                    alert('Order Saved Successfully!');
                    $('#saveOrderBtn').hide();
                },
                error: function () {
                    alert('Failed to update order');
                }
            });
        }
    </script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <?php if ($flashToast): ?>
        <div id="toast" class="toast <?= htmlspecialchars($flashToast['type']) ?>">
            <?= htmlspecialchars($flashToast['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="wrapper">
        <div class="topbar">
            <div style="display:flex; gap:8px; align-items:center;">
                <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Home</a>
                <a href="starline4_condition.php" class="btn btn-condition"><i class="fas fa-magic"></i> Auto Conditions</a>
            </div>
            <div class="top-title"><i class="fas fa-list"></i> Starline 4 Market List</div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button id="saveOrderBtn" class="btn btn-primary" style="background: var(--green);" onclick="saveOrder()">
                    <i class="fas fa-save"></i> Save Order
                </button>
                <a href="create_new_market4.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Market</a>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Market Name</th>
                        <th>Open Time</th>
                        <th>Close Time</th>
                        <th>Holidays</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="marketTableBody">
                    <?php if (empty($markets)): ?>
                        <tr><td colspan="7" style="padding:20px;">No Starline 4 markets found.</td></tr>
                    <?php else: foreach ($markets as $m): ?>
                        <tr>
                            <input type="hidden" name="market_ids[]" value="<?= $m['id'] ?>">
                            <td><?= (int)$m['id'] ?></td>
                            <td><strong><?= htmlspecialchars($m['market_name']) ?></strong></td>
                            <td><?= date('h:i A', strtotime($m['open_time'] ?? '10:00:00')) ?></td>
                            <td><strong style="color:var(--red);"><?= date('h:i A', strtotime($m['close_time'] ?? '12:00:00')) ?></strong></td>
                            <td>
                                <?php
                                $hols = [];
                                if (!empty($m['today_closed'])) $hols[] = 'Today';
                                if (!empty($m['saturday_closed'])) $hols[] = 'Sat';
                                if (!empty($m['sunday_closed'])) $hols[] = 'Sun';
                                echo !empty($hols) ? '<span style="color:#d32f2f; font-weight:700;">' . implode(', ', $hols) . '</span>' : '<span style="color:#888;">None</span>';
                                ?>
                            </td>
                            <td>
                                <a href="?toggle=<?= (int)$m['id'] ?>" class="<?= !empty($m['is_published']) ? 'status-active' : 'status-inactive' ?>">
                                    <?= !empty($m['is_published']) ? 'Active' : 'Hidden' ?>
                                </a>
                            </td>
                            <td class="actions">
                                <a href="create_new_market4.php?id=<?= (int)$m['id'] ?>" class="edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= (int)$m['id'] ?>" class="delete" title="Delete" onclick="return confirm('Delete this Starline 4 market?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
