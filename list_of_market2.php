<?php
require_once 'includes/db.php';
/* list_of_market2.php – For Gali Desawar (gali_markets table) */

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

$dbError = '';
$markets = [];

/* ---------- Handle Sort Order Update (AJAX) ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'update_order') {
    if (!isset($_POST['order']) || !is_array($_POST['order'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    $order = $_POST['order'];
    $db = db();
    $stmt = $db->prepare("UPDATE gali_markets SET sort_order = ? WHERE id = ?");

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

/* ---------- Helpers ---------- */
function setFlash($type, $msg)
{
    $_SESSION['flash_toast'] = ['type' => $type, 'msg' => $msg];
}

/* Helper: Slug variants for file deletion */
function marketSlugVariants($name)
{
    $raw = strtolower(trim((string) $name));
    $raw = preg_replace('/\s+/', ' ', $raw);
    $raw = preg_replace('/[^a-z0-9 _-]/', '', $raw);

    $underscore = preg_replace('/[\s-]+/', '_', $raw);
    $underscore = preg_replace('/_+/', '_', trim($underscore, '_'));

    $hyphen = preg_replace('/[\s_]+/', '-', $raw);
    $hyphen = preg_replace('/-+/', '-', trim($hyphen, '-'));

    $compact = preg_replace('/[\s_-]+/', '', $raw);

    $out = array_values(array_unique(array_filter([$underscore, $hyphen, $compact])));
    return $out;
}

function tryDeleteMarketFile($marketName)
{
    $slugs = marketSlugVariants($marketName);
    if (empty($slugs))
        return [false, 'Slug empty, file not resolved'];

    $baseDirs = [__DIR__]; // Check current dir primarily

    $deletedAny = false;
    $deletedList = [];
    $lastError = '';

    foreach ($baseDirs as $dir) {
        foreach ($slugs as $slug) {
            $path = $dir . '/' . $slug . '.php';
            if (file_exists($path)) {
                if (@unlink($path)) {
                    $deletedAny = true;
                    $deletedList[] = basename($path);
                } else {
                    $lastError = "unlink failed: " . basename($path);
                }
            }
        }
    }

    if ($deletedAny) {
        return [true, 'Deleted: ' . implode(', ', $deletedList)];
    }
    return [false, $lastError ?: 'File not found'];
}

/* ====== DB schema helpers (PHP 5.6 compatible) ====== */
function rk_has_table($db, $table)
{
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $st->bind_param('s', $table);
        $st->execute();
        $c = 0;
        $st->bind_result($c);
        $st->fetch();
        $st->close();
        return ((int) $c) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function rk_has_column($db, $table, $col)
{
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $st->bind_param('ss', $table, $col);
        $st->execute();
        $c = 0;
        $st->bind_result($c);
        $st->fetch();
        $st->close();
        return ((int) $c) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function rk_cleanup_market_everywhere($db, $marketName)
{
    $mUpper = strtoupper(trim($marketName));
    $stats = [
        'market_result' => 0,
        'gali_result' => 0,
        'wining' => 0,
        'wins_run_lock' => 0,
        'bid' => 0,
        'bids' => 0,
        'bid_history' => 0,
    ];

    $targets = [
        'markets' => ['market_name'],
        'gali_markets' => ['market_name'],
        'market_result' => ['market', 'market_name'],
        'gali_result' => ['market', 'market_name'],
        'wining' => ['market', 'market_name'],
        'wins_run_lock' => ['market'],
        'bid' => ['market', 'market_name'],
        'bids' => ['market', 'market_name'],
        'bid_history' => ['market', 'market_name'],
    ];

    foreach ($targets as $table => $cols) {
        if (!rk_has_table($db, $table))
            continue;

        $colToUse = '';
        foreach ($cols as $c) {
            if (rk_has_column($db, $table, $c)) {
                $colToUse = $c;
                break;
            }
        }
        if ($colToUse === '')
            continue;

        try {
            $sql = "DELETE FROM `$table` WHERE UPPER(TRIM(`$colToUse`)) = ?";
            $st = $db->prepare($sql);
            $st->bind_param('s', $mUpper);
            $st->execute();
            $stats[$table] = (int) $st->affected_rows;
            $st->close();
        } catch (Exception $e) {
            // ignore
        }
    }

    return $stats;
}

/* ---------- DB connect ---------- */
$db = db();
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+05:30'");

/* Ensure gali_markets table exists (matching create_new_market2.php) */
$db->query("CREATE TABLE IF NOT EXISTS gali_markets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  market_name VARCHAR(100) NOT NULL,
  default_result VARCHAR(50) DEFAULT '**',
  result_time TIME,
  close_time_start TIME DEFAULT '23:45',
  close_time_end TIME DEFAULT '00:00',
  today_closed TINYINT(1) DEFAULT 0,
  saturday_closed TINYINT(1) DEFAULT 0,
  sunday_closed TINYINT(1) DEFAULT 0,
  custom_close_text VARCHAR(255),
  payout_left_digit INT DEFAULT 100,
  payout_right_digit INT DEFAULT 100,
  payout_jodi_digit INT DEFAULT 1000,
  is_published TINYINT(1) DEFAULT 1,
  status VARCHAR(20) DEFAULT 'active',
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* Ensure sort_order column exists (schema upgrade) */
try {
    $db->query("ALTER TABLE gali_markets ADD COLUMN sort_order INT DEFAULT 0");
} catch (Exception $e) {
    // Column likely exists
}

/* Ensure other gali system tables exist */
$db->query("CREATE TABLE IF NOT EXISTS gali_bids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  market_id INT NOT NULL,
  market_name VARCHAR(100) NOT NULL,
  game_type VARCHAR(50) NOT NULL,
  digit VARCHAR(10) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->query("CREATE TABLE IF NOT EXISTS gali_result (
  id INT AUTO_INCREMENT PRIMARY KEY,
  market VARCHAR(100) NOT NULL,
  result_date DATE NOT NULL,
  jodi VARCHAR(10) DEFAULT NULL,
  result_str VARCHAR(50) DEFAULT NULL,
  declared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->query("CREATE TABLE IF NOT EXISTS gali_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_name VARCHAR(50) NOT NULL,
  rate INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Placeholder rates if empty
$chkRates = $db->query("SELECT COUNT(*) FROM gali_rates");
if ($chkRates && $chkRates->fetch_row()[0] == 0) {
    $db->query("INSERT INTO gali_rates (game_name, rate) VALUES ('Left Digit', 9), ('Right Digit', 9), ('Jodi Digit', 90)");
}

/* ---------- DELETE market (and file) ---------- */
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $did = (int) $_GET['delete'];

    $stmt = $db->prepare("SELECT market_name FROM gali_markets WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $did);
    $stmt->execute();
    $stmt->bind_result($mName);

    if ($stmt->fetch()) {
        $stmt->close();
        try {
            $stmt2 = $db->prepare("DELETE FROM gali_markets WHERE id=? LIMIT 1");
            $stmt2->bind_param('i', $did);
            $stmt2->execute();
            $stmt2->close();

            $cleanup = rk_cleanup_market_everywhere($db, $mName);
            $cleanDetails = "Results: " . ($cleanup['market_result'] + $cleanup['gali_result']) . ", Bids: " . ($cleanup['bid'] + $cleanup['bids']) . ", Wins: " . $cleanup['wining'];

            $delRes = tryDeleteMarketFile($mName);
            setFlash('success', 'Gali Market deleted ✅ (' . $cleanDetails . ') ' . ($delRes[0] ? $delRes[1] : ''));

        } catch (Exception $e) {
            setFlash('error', 'Delete failed: ' . $e->getMessage());
        }
    } else {
        $stmt->close();
        setFlash('error', 'Market not found.');
    }
    header('Location: list_of_market2.php');
    exit;
}

/* ---------- Publish / Hide toggle ---------- */
if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];

    $stmt = $db->prepare("SELECT is_published FROM gali_markets WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($currPub);

    if ($stmt->fetch()) {
        $stmt->close();
        $newPublished = ($currPub == 1) ? 0 : 1;
        $newStatus = $newPublished ? 'active' : 'inactive';

        $stmt2 = $db->prepare("UPDATE gali_markets SET is_published=?, status=? WHERE id=? LIMIT 1");
        $stmt2->bind_param('isi', $newPublished, $newStatus, $id);
        $stmt2->execute();
        $stmt2->close();
        setFlash('success', 'Status updated ✅');
    } else {
        $stmt->close();
        setFlash('error', 'Market not found.');
    }
    header('Location: list_of_market2.php');
    exit;
}

$markets = [];
$res = $db->query("SELECT * FROM gali_markets ORDER BY sort_order ASC, id ASC");
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
    <title>Gali Market List 2 – DESAWAR BOSS Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c62828;
            --green: #2e7d32;
            --blue: #0277BD;
            --orange: #F57C00;
            --brown: #4e342e;
            --shadow: rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background: #f5f0e5;
            color: #333;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .wrapper {
            max-width: 1200px;
            margin: 20px auto 30px;
            padding: 0 16px;
        }

        .topbar {
            background: #fff;
            padding: 10px 14px;
            border-radius: 10px;
            box-shadow: 0 2px 8px var(--shadow);
            justify-content: space-between;
            display: flex;
            align-items: center;
        }

        .top-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--brown);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary {
            background: #eeeeee;
            color: #333;
            border: none;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
        }

        .btn-primary:hover {
            background: #b71c1c;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px var(--shadow);
            padding: 12px;
            overflow-x: auto;
            margin-top: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 800px;
        }

        th {
            background: #f7f3ea;
            padding: 8px;
            position: sticky;
            top: 0;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        tr:nth-child(even) td {
            background: #faf7f1;
        }

        .status-active {
            color: var(--green);
            font-weight: 700;
        }

        .status-inactive {
            color: var(--red);
            font-weight: 700;
        }

        .actions a {
            margin: 0 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }

        .actions .edit {
            background: #e3f2fd;
            color: #1565c0;
        }

        .actions .delete {
            background: #ffebee;
            color: #c62828;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
        }

        .pagination a {
            padding: 8px 12px;
            background: #ddd;
            border-radius: 4px;
        }

        .pagination .active {
            background: var(--blue);
            color: #fff;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: var(--green);
        }

        .toast.error {
            background: var(--red);
        }

        }

        /* Drag Handle Cursor */
        tbody tr {
            cursor: move;
        }
    </style>
    <!-- jQuery & jQuery UI for Drag & Drop -->
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
                    // Show save button when order changes
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
                url: 'list_of_market2.php',
                type: 'POST',
                data: {
                    action: 'update_order',
                    order: order
                },
                success: function (response) {
                    alert('Order Saved Successfully!');
                    $('#saveOrderBtn').hide();
                },
                error: function (xhr, status, error) {
                    console.error("Error: " + error);
                    alert('Failed to update order');
                }
            });
        }
    </script>
    <style>
        #saveOrderBtn {
            display: none;
            margin-right: 10px;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <?php
    require_once 'includes/db.php';
    if ($flashToast): ?>
        <div id="toast" class="toast <?php echo  $flashToast['type'] ?>">
            <?php echo  htmlspecialchars($flashToast['msg']) ?>
        </div>
        <?php
        require_once 'includes/db.php';
    endif; ?>

    <div class="wrapper">
        <div class="topbar">
            <div style="flex:1;">
                <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Home</a>
            </div>
            <div class="top-title" style="flex:2; text-align:center;">Gali Market List</div>
            <div style="flex:1; text-align:right;">
                <button id="saveOrderBtn" class="btn btn-primary" style="background: var(--green);"
                    onclick="saveOrder()">
                    <i class="fas fa-save"></i> Save Order
                </button>
                <a href="create_new_market2.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Gali
                    Market</a>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Result Time</th>
                        <th>Close Range</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="marketTableBody">
                    <?php
                    require_once 'includes/db.php';
                    if (empty($markets)): ?>
                        <tr>
                            <td colspan="7" style="padding:20px;">No Gali markets found.</td>
                        </tr>
                        <?php
                        require_once 'includes/db.php';
                    else: ?>
                        <?php
                        require_once 'includes/db.php';
                        foreach ($markets as $m): ?>
                            <tr>
                                <input type="hidden" name="market_ids[]" value="<?php echo  $m['id'] ?>">
                                <td>
                                    <?php echo  $m['id'] ?>
                                </td>
                                <td><strong>
                                        <?php echo  htmlspecialchars($m['market_name']) ?>
                                    </strong></td>
                                <td>
                                    <?php echo  date('h:i A', strtotime($m['result_time'])) ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo  date('h:i A', strtotime($m['close_time_start'] ?? '23:45')) ?> -
                                        <?php echo  date('h:i A', strtotime($m['close_time_end'] ?? '00:00')) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo  htmlspecialchars($m['default_result']) ?>
                                </td>
                                <td>
                                    <a href="?toggle=<?php echo  $m['id'] ?>"
                                        class="<?php echo  $m['is_published'] ? 'status-active' : 'status-inactive' ?>">
                                        <?php echo  $m['is_published'] ? 'Active' : 'Hidden' ?>
                                    </a>
                                </td>
                                <td class="actions">
                                    <a href="create_new_market2.php?id=<?php echo  $m['id'] ?>" class="edit"><i
                                            class="fas fa-edit"></i></a>
                                    <a href="?delete=<?php echo  $m['id'] ?>" class="delete"
                                        onclick="return confirm('Delete this market?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php
                            require_once 'includes/db.php';
                        endforeach; ?>
                        <?php
                        require_once 'includes/db.php';
                    endif; ?>
                </tbody>
            </table>
        </div>

        <?php /* Pagination Removed for Drag & Drop support */ ?>

    </div>
</body>

</html>
