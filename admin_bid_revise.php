<?php
require_once 'includes/db.php';
// admin_bid_revise.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Asia/Kolkata');


$DEBUG = (isset($_GET['debug']) && $_GET['debug'] === '1');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = db();
} catch (Throwable $e) {
  http_response_code(500);
  if ($DEBUG) {
    echo "<pre style='padding:16px;color:#b71c1c;background:#fff3f3;border:1px solid #f2b8b8;border-radius:10px;max-width:1050px;margin:20px auto;'>";
    echo "DB CONNECT ERROR:\n" . htmlspecialchars($e->getMessage());
    echo "</pre>";
  } else {
    echo "Temporary server issue. Please try again.";
  }
  exit;
}

function hasColumn(mysqli $db, string $table, string $col): bool
{
  $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $db->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  $res = $st->get_result();
  $ok = ($res && $res->num_rows > 0);
  $st->close();
  return $ok;
}

function indexExists(mysqli $db, string $table, string $keyName): bool
{
  $sql = "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1";
  $st = $db->prepare($sql);
  $st->bind_param('ss', $table, $keyName);
  $st->execute();
  $res = $st->get_result();
  $ok = ($res && $res->num_rows > 0);
  $st->close();
  return $ok;
}

function safeQuery(mysqli $db, string $sql): void
{
  try {
    $db->query($sql);
  } catch (Throwable $e) {
  }
}

function adminIdFromSession(): string
{
  if (!isset($_SESSION['admin']))
    return 'ADMIN';
  if (is_array($_SESSION['admin'])) {
    $a = $_SESSION['admin'];
    $v = (string) ($a['admin_id'] ?? ($a['id'] ?? ($a['username'] ?? 'ADMIN')));
    return $v !== '' ? $v : 'ADMIN';
  }
  return 'ADMIN';
}

try {
  $db->query("
        CREATE TABLE IF NOT EXISTS bid_refund_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            market VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
            from_dt DATETIME NULL,
            to_dt DATETIME NULL,
            filter_label VARCHAR(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Custom',
            note VARCHAR(255) COLLATE utf8mb4_general_ci NULL,
            total_bids INT NOT NULL DEFAULT 0,
            total_users INT NOT NULL DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            admin_id VARCHAR(50) COLLATE utf8mb4_general_ci NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_market (market),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

  $db->query("
        CREATE TABLE IF NOT EXISTS bid_refunds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bid_id BIGINT NOT NULL,
            batch_id BIGINT UNSIGNED NOT NULL,
            user_id VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
            market VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            bid_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bid_id (bid_id),
            INDEX idx_batch_id (batch_id),
            INDEX idx_user_id (user_id),
            INDEX idx_market (market)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

  if (!hasColumn($db, 'bid_refund_batches', 'filter_label')) {
    safeQuery($db, "ALTER TABLE bid_refund_batches ADD COLUMN filter_label VARCHAR(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Custom' AFTER to_dt");
  }
  if (!hasColumn($db, 'bid_refund_batches', 'note')) {
    safeQuery($db, "ALTER TABLE bid_refund_batches ADD COLUMN note VARCHAR(255) COLLATE utf8mb4_general_ci NULL AFTER filter_label");
  }
  if (!hasColumn($db, 'bid_refund_batches', 'admin_id')) {
    safeQuery($db, "ALTER TABLE bid_refund_batches ADD COLUMN admin_id VARCHAR(50) COLLATE utf8mb4_general_ci NULL AFTER total_amount");
  }

  if (!hasColumn($db, 'bid_refunds', 'source_table')) {
    safeQuery($db, "ALTER TABLE bid_refunds ADD COLUMN source_table VARCHAR(20) NOT NULL DEFAULT 'bid' AFTER bid_id");
    if (indexExists($db, 'bid_refunds', 'uniq_bid_id')) {
      safeQuery($db, "ALTER TABLE bid_refunds DROP INDEX uniq_bid_id");
    }
    if (!indexExists($db, 'bid_refunds', 'uniq_bid_source')) {
      safeQuery($db, "ALTER TABLE bid_refunds ADD UNIQUE KEY uniq_bid_source (bid_id, source_table)");
    }
  }

  safeQuery($db, "ALTER TABLE bid_refund_batches CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
  safeQuery($db, "ALTER TABLE bid_refunds CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

  if (indexExists($db, 'bid_refunds', 'uq_batch_id')) {
    safeQuery($db, "ALTER TABLE bid_refunds DROP INDEX uq_batch_id");
  }
  if (indexExists($db, 'bid_refunds', 'uniq_batch_id')) {
    safeQuery($db, "ALTER TABLE bid_refunds DROP INDEX uniq_batch_id");
  }

  safeQuery($db, "
        ALTER TABLE bid_refunds
        ADD CONSTRAINT fk_bid_refunds_batch
        FOREIGN KEY (batch_id) REFERENCES bid_refund_batches(id)
        ON DELETE CASCADE
    ");

  safeQuery($db, "ALTER TABLE deposit MODIFY COLUMN method ENUM('BANK','UPI','UPI_INTENT','CRYPTO','ADMIN','REFUND') NOT NULL");

} catch (Throwable $e) {
  http_response_code(500);
  if ($DEBUG) {
    echo "<pre style='padding:16px;color:#b71c1c;background:#fff3f3;border:1px solid #f2b8b8;border-radius:10px;max-width:1050px;margin:20px auto;'>";
    echo "TABLE/SCHEMA ERROR:\n" . htmlspecialchars($e->getMessage());
    echo "</pre>";
  } else {
    echo "Temporary server issue. Please try again.";
  }
  exit;
}

if (empty($_SESSION['csrf_bid_refund'])) {
  $_SESSION['csrf_bid_refund'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_bid_refund'];

$markets = [];
try {
  $res = $db->query("SELECT market_name FROM markets ORDER BY sort_order ASC, market_name ASC");
  while ($row = $res->fetch_assoc())
    $markets[] = (string) $row['market_name'];
} catch (Throwable $e) {
  try {
    $res = $db->query("SELECT DISTINCT market FROM bid ORDER BY market ASC");
    while ($row = $res->fetch_assoc())
      $markets[] = (string) $row['market'];
  } catch (Throwable $e2) {
    $markets = [];
  }
}



function rangeFromFilter(string $filter, ?string $fromDate, ?string $toDate): array
{
  $filter = strtolower(trim($filter));

  if ($filter === 'today') {
    $start = new DateTime('today 00:00:00');
    $end = new DateTime('today 23:59:59');
    return [$start, $end, 'Today'];
  }
  if ($filter === '7') {
    $start = (new DateTime('today 00:00:00'))->modify('-6 days');
    $end = new DateTime('today 23:59:59');
    return [$start, $end, '7 Days'];
  }
  if ($filter === '15') {
    $start = (new DateTime('today 00:00:00'))->modify('-14 days');
    $end = new DateTime('today 23:59:59');
    return [$start, $end, '15 Days'];
  }
  if ($filter === '30') {
    $start = (new DateTime('today 00:00:00'))->modify('-29 days');
    $end = new DateTime('today 23:59:59');
    return [$start, $end, '30 Days'];
  }
  if ($filter === 'all') {
    return [null, null, 'All Time'];
  }

  $label = 'Custom';
  $start = null;
  $end = null;
  if ($fromDate)
    $start = DateTime::createFromFormat('Y-m-d', $fromDate);
  if ($toDate)
    $end = DateTime::createFromFormat('Y-m-d', $toDate);
  if ($start)
    $start->setTime(0, 0, 0);
  if ($end)
    $end->setTime(23, 59, 59);
  return [$start, $end, $label];
}

function buildWhere(string $market, ?DateTime $start, ?DateTime $end): array
{
  $mCol = 'market';
  $tCol = 'bid_time';
  $src = 'bid';

  $where = " WHERE b.$mCol COLLATE utf8mb4_general_ci = ? ";
  $types = "s";
  $params = [$market];

  $where .= " AND NOT EXISTS (SELECT 1 FROM bid_refunds r WHERE r.bid_id = b.id AND r.source_table = ?) ";
  $types .= "s";
  $params[] = $src;

  if ($start && $end) {
    $where .= " AND b.$tCol BETWEEN ? AND ? ";
    $types .= "ss";
    $params[] = $start->format('Y-m-d H:i:s');
    $params[] = $end->format('Y-m-d H:i:s');
  }
  return [$where, $types, $params];
}

$selectedMarket = trim($_POST['market'] ?? $_GET['market'] ?? '');
$timeFilter = trim($_POST['time_filter'] ?? $_GET['time_filter'] ?? 'today');
$fromDate = trim($_POST['from_date'] ?? $_GET['from_date'] ?? '');
$toDate = trim($_POST['to_date'] ?? $_GET['to_date'] ?? '');
$note = trim($_POST['note'] ?? '');

[$rangeStart, $rangeEnd, $rangeLabel] = rangeFromFilter($timeFilter, $fromDate ?: null, $toDate ?: null);

$previewRows = [];
$previewTotalBids = 0;
$previewTotalUsers = 0;
$previewTotalAmount = 0.00;

$flash = '';
$action = $_POST['action'] ?? '';

if ($action === 'preview') {
  if ($selectedMarket === '') {
    $flash = 'Please select a market.';
  } else {
    try {
      $tbl = 'bid';
      $userCol = 'b.user_id';
      $join = "";

      [$where, $types, $params] = buildWhere($selectedMarket, $rangeStart, $rangeEnd);

      $sqlTotal = "
                SELECT COUNT(*) AS bids, COUNT(DISTINCT $userCol) AS users, IFNULL(SUM(b.amount),0) AS amount
                FROM $tbl b
                $join
                $where
            ";
      $st = $db->prepare($sqlTotal);
      $st->bind_param($types, ...$params);
      $st->execute();
      $tot = $st->get_result()->fetch_assoc();
      $st->close();

      $previewTotalBids = (int) ($tot['bids'] ?? 0);
      $previewTotalUsers = (int) ($tot['users'] ?? 0);
      $previewTotalAmount = (float) ($tot['amount'] ?? 0);

      $sqlList = "
                SELECT $userCol as user_id,
                       COUNT(*) AS bids,
                       IFNULL(SUM(b.amount),0) AS amount
                FROM $tbl b
                $join
                $where
                GROUP BY $userCol
                ORDER BY amount DESC
                LIMIT 500
            ";
      $st = $db->prepare($sqlList);
      $st->bind_param($types, ...$params);
      $st->execute();
      $res = $st->get_result();
      while ($r = $res->fetch_assoc())
        $previewRows[] = $r;
      $st->close();

      if ($previewTotalBids === 0)
        $flash = 'No refundable bids found in this range.';
    } catch (Throwable $e) {
      $flash = $DEBUG ? ('Preview error: ' . $e->getMessage()) : 'Preview failed. Try again.';
    }
  }
}

if ($action === 'refund') {
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($CSRF, $csrf)) {
    $flash = 'Security token mismatch. Refresh and try again.';
  } elseif ($selectedMarket === '') {
    $flash = 'Please select a market.';
  } else {
    try {
      $tbl = 'bid';
      $userCol = 'b.user_id';
      $join = "";
      $timeCol = 'b.bid_time';

      [$where, $types, $params] = buildWhere($selectedMarket, $rangeStart, $rangeEnd);

      $db->begin_transaction();

      $sqlTotal = "
                SELECT COUNT(*) AS bids, COUNT(DISTINCT $userCol) AS users, IFNULL(SUM(b.amount),0) AS amount
                FROM $tbl b
                $join
                $where
            ";
      $st = $db->prepare($sqlTotal);
      $st->bind_param($types, ...$params);
      $st->execute();
      $tot = $st->get_result()->fetch_assoc();
      $st->close();

      $totalBids = (int) ($tot['bids'] ?? 0);
      $totalUsers = (int) ($tot['users'] ?? 0);
      $totalAmount = (float) ($tot['amount'] ?? 0);

      if ($totalBids <= 0) {
        $db->rollback();
        $flash = 'No refundable bids found (maybe already refunded).';
      } else {
        $adminId = adminIdFromSession();
        $fromDt = $rangeStart ? $rangeStart->format('Y-m-d H:i:s') : null;
        $toDt = $rangeEnd ? $rangeEnd->format('Y-m-d H:i:s') : null;

        $st = $db->prepare("
                    INSERT INTO bid_refund_batches
                    (market, from_dt, to_dt, filter_label, note, total_bids, total_users, total_amount, admin_id)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ");
        $st->bind_param(
          'sssssiids',
          $selectedMarket,
          $fromDt,
          $toDt,
          $rangeLabel,
          $note,
          $totalBids,
          $totalUsers,
          $totalAmount,
          $adminId
        );
        $st->execute();
        $batchId = (int) $st->insert_id;
        $st->close();

        $sqlBids = "
                    SELECT b.id, $userCol as user_id, b.amount, $timeCol as bid_time
                    FROM $tbl b
                    $join
                    $where
                    ORDER BY b.id ASC
                ";
        $st = $db->prepare($sqlBids);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();

        $userSums = [];
        $bidRows = [];
        $bidIds = [];
        while ($r = $res->fetch_assoc()) {
          $bidId = (int) $r['id'];
          $u = trim((string) $r['user_id']);
          $amt = (float) $r['amount'];
          $bt = (string) $r['bid_time'];

          if ($bidId <= 0 || $u === '' || $amt <= 0)
            continue;

          $userSums[$u] = ($userSums[$u] ?? 0) + $amt;
          $bidRows[] = [$bidId, $u, $amt, $bt];
          $bidIds[] = $bidId;
        }
        $st->close();

        if (!$bidRows) {
          $db->rollback();
          $flash = 'No refundable bids found (empty selection).';
        } else {

          $ins = $db->prepare("
                        INSERT IGNORE INTO bid_refunds
                        (bid_id, source_table, batch_id, user_id, market, amount, bid_time)
                        VALUES (?,?,?,?,?,?,?)
                    ");
          foreach ($bidRows as $br) {
            [$bidId, $u, $amt, $bt] = $br;
            $ins->bind_param('isissds', $bidId, $tbl, $batchId, $u, $selectedMarket, $amt, $bt);
            $ins->execute();
          }
          $ins->close();

          $upd = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ? COLLATE utf8mb4_general_ci");
          $creditedUsers = 0;
          foreach ($userSums as $u => $sumAmt) {
            $u = trim((string) $u);
            $sumAmt = round((float) $sumAmt, 2);
            if ($u === '' || $sumAmt <= 0)
              continue;

            $upd->bind_param('ds', $sumAmt, $u);
            $upd->execute();
            if ($upd->affected_rows > 0)
              $creditedUsers++;
          }
          $upd->close();

          $findUserDb = $db->prepare("SELECT id FROM users WHERE user_id = ? COLLATE utf8mb4_general_ci LIMIT 1");

          $txnCol = '';
          if (hasColumn($db, 'deposit', 'txn_id'))
            $txnCol = 'txn_id';
          elseif (hasColumn($db, 'deposit', 'transaction_id'))
            $txnCol = 'transaction_id';

          if ($txnCol !== '') {
            $insDep = $db->prepare("
                            INSERT INTO deposit
                            (user_db_id, user_id, amount, screenshot, method, status, date_time, admin_id, order_id, {$txnCol}, credited)
                            VALUES (?, ?, ?, '', 'REFUND', 'Done', NOW(), ?, ?, ?, 1)
                        ");
          } else {
            $insDep = $db->prepare("
                            INSERT INTO deposit
                            (user_db_id, user_id, amount, screenshot, method, status, date_time, admin_id, order_id, credited)
                            VALUES (?, ?, ?, '', 'REFUND', 'Done', NOW(), ?, ?, 1)
                        ");
          }

          $batchOrderPrefix = "BIDREFUND-" . $batchId . "-";
          foreach ($userSums as $u => $sumAmt) {
            $u = trim((string) $u);
            $sumAmt = round((float) $sumAmt, 2);
            if ($u === '' || $sumAmt <= 0)
              continue;

            $findUserDb->bind_param('s', $u);
            $findUserDb->execute();
            $uidRes = $findUserDb->get_result()->fetch_assoc();
            $userDbId = (int) ($uidRes['id'] ?? 0);

            $orderId = $batchOrderPrefix . $u;
            $txnId = "REFUND-" . $batchId . "-" . substr(md5($u . '|' . $sumAmt), 0, 12);

            if ($txnCol !== '') {
              $insDep->bind_param('isdsss', $userDbId, $u, $sumAmt, $adminId, $orderId, $txnId);
            } else {
              $insDep->bind_param('isdss', $userDbId, $u, $sumAmt, $adminId, $orderId);
            }
            $insDep->execute();
          }
          $findUserDb->close();
          $insDep->close();

          $chunkSize = 500;
          for ($i = 0; $i < count($bidIds); $i += $chunkSize) {
            $chunk = array_slice($bidIds, $i, $chunkSize);
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $typesDel = str_repeat('i', count($chunk));
            $sqlDel = "DELETE FROM $tbl WHERE id IN ($placeholders)";
            $del = $db->prepare($sqlDel);
            $del->bind_param($typesDel, ...$chunk);
            $del->execute();
            $del->close();
          }

          $db->commit();

          $flash = "✅ Refunded Successfully • Bids: {$totalBids} • Users: {$totalUsers} • Amount: ₹" . number_format($totalAmount, 2)
            . " • Batch: #{$batchId}";

          $previewRows = [];
          $previewTotalBids = 0;
          $previewTotalUsers = 0;
          $previewTotalAmount = 0.00;
        }
      }
    } catch (Throwable $e) {
      try {
        $db->rollback();
      } catch (Throwable $x) {
      }
      $flash = $DEBUG ? ('Refund error: ' . $e->getMessage()) : 'Refund failed. Please try again.';
    }
  }
}

$recentBatches = [];
try {
  $res = $db->query("
        SELECT id, market, total_bids, total_users, total_amount, created_at, admin_id
        FROM bid_refund_batches
        ORDER BY id DESC
        LIMIT 10
    ");
  while ($r = $res->fetch_assoc())
    $recentBatches[] = $r;
} catch (Throwable $e) {
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Bid Revise</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
    rel="stylesheet">

  <style>
    :root {
      --bg: #f6f3ee;
      --card: #ffffff;
      --text: #1f130d;
      --muted: #6b5545;
      --border: #eadfce;
      --red: #b71c1c;
      --brown: #4e342e;
      --green: #2e7d32;
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Poppins', 'Roboto', system-ui, -apple-system, Segoe UI, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    .header {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #fff;
      border-bottom: 1px solid var(--border);
    }

    .header .wrap {
      max-width: 1050px;
      margin: 0 auto;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .title {
      font-size: 20px;
      font-weight: 700;
      color: var(--red);
      margin: 0;
    }

    .subtitle {
      margin: 3px 0 0;
      font-size: 12px;
      color: var(--muted);
      font-weight: 600;
    }

    .back-btn {
      text-decoration: none;
      padding: 10px 14px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--brown);
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .back-btn:hover {
      transform: translateY(-1px)
    }

    .container {
      max-width: 1050px;
      margin: 16px auto 32px;
      padding: 0 16px;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
    }

    .grid {
      display: grid;
      grid-template-columns: 1.3fr 1.7fr;
      gap: 14px;
    }

    @media(max-width:860px) {
      .grid {
        grid-template-columns: 1fr
      }
    }

    .label {
      font-weight: 700;
      color: var(--brown);
      font-size: 13px;
      margin-bottom: 6px;
    }

    select,
    input {
      width: 100%;
      padding: 11px 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      outline: none;
      font-family: 'Roboto', 'Poppins', sans-serif;
      font-weight: 600;
    }

    .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .pills {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .pill {
      border: 1.5px solid var(--red);
      color: var(--red);
      background: #fff;
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 800;
      cursor: pointer;
    }

    .pill.active {
      background: var(--red);
      color: #fff;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .btn {
      border: none;
      border-radius: 14px;
      padding: 11px 16px;
      font-weight: 900;
      cursor: pointer;
      font-family: 'Poppins', 'Roboto', sans-serif;
    }

    .btn.preview {
      background: #3e2723;
      color: #fff;
    }

    .btn.refund {
      background: linear-gradient(135deg, #ffb3b3, #d32f2f);
      color: #fff;
    }

    .btn:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    .note {
      font-size: 12px;
      color: var(--muted);
      margin-top: 10px;
      line-height: 1.35;
    }

    .alert {
      margin: 12px 0 0;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid #ffd6d6;
      background: #fff3f3;
      color: #8a1111;
      font-weight: 700;
    }

    .ok {
      border-color: #c8e6c9;
      background: #e8f5e9;
      color: #1b5e20;
    }

    .small {
      font-size: 12px;
      font-weight: 800;
      color: var(--muted);
      margin-top: 8px;
    }

    .table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      overflow: hidden;
      border-radius: 14px;
      border: 1px solid var(--border);
      margin-top: 14px;
    }

    .table th {
      background: #2b1b14;
      color: #fff;
      padding: 10px;
      font-size: 13px;
      text-align: left;
    }

    .table td {
      padding: 10px;
      border-top: 1px solid var(--border);
      font-family: 'Roboto', 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 13px;
    }

    .badge {
      display: inline-flex;
      padding: 4px 10px;
      border-radius: 999px;
      font-weight: 900;
      font-size: 12px;
    }

    .b-green {
      background: #e8f5e9;
      color: #1b5e20;
      border: 1px solid #c8e6c9;
    }

    .b-red {
      background: #ffebee;
      color: #b71c1c;
      border: 1px solid #ffcdd2;
    }

    .flex-between {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="header">
    <div class="wrap">
      <div>
        <div class="title">Admin Bid Revise</div>
        <div class="subtitle">Select Market + Time Filter → Preview → Refund (wallet_balance + deposit REFUND + delete
          bids)</div>
      </div>
      <a class="back-btn" href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
  </div>

  <div class="container">

    <?php
    require_once 'includes/db.php';
    if ($flash): ?>
      <div class="alert <?= (strpos($flash, '✅') !== false) ? 'ok' : '' ?>">
        <?= htmlspecialchars($flash) ?>
        <?php
        require_once 'includes/db.php';
        if ($DEBUG): ?>
          <div class="small">Debug ON (remove ?debug=1 for production)</div>
          <?php
          require_once 'includes/db.php';
        endif; ?>
      </div>
      <?php
      require_once 'includes/db.php';
    endif; ?>

    <div class="card">
      <form method="POST" id="frm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
        <input type="hidden" name="time_filter" id="time_filter" value="<?= htmlspecialchars($timeFilter) ?>">
        <input type="hidden" name="action" id="action" value="preview">

        <div class="grid">
          <div>
            <div class="label">Market</div>
            <select name="market" required onchange="autoPreview()">
              <option value="">-- Select Market --</option>
              <?php
              require_once 'includes/db.php';
              foreach ($markets as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= ($selectedMarket === $m ? 'selected' : '') ?>>
                  <?= htmlspecialchars($m) ?>
                </option>
                <?php
                require_once 'includes/db.php';
              endforeach; ?>
            </select>

            <div class="label" style="margin-top:12px;">Note (optional)</div>
            <input type="text" name="note" placeholder="e.g. Market closed - refund bids"
              value="<?= htmlspecialchars($note) ?>">

            <div class="actions">
              <button type="submit" class="btn preview" onclick="setAction('preview')">Preview</button>
              <button type="submit" class="btn refund" onclick="setAction('refund')" <?= ($previewTotalBids <= 0 ? 'disabled' : '') ?>>Refund Now</button>
            </div>

            <div class="note">
              ✅ Refund does 4 things:<br>
              1) Insert bid_refunds (unique bid_id) • 2) Credit users.wallet_balance • 3) Insert deposit method
              <b>REFUND</b> status Done • 4) Delete bids from bid table.<br><br>
              ⚠️ If you need reverse wins/settlements, do that from your market-reversal workflow.
            </div>
          </div>

          <div>
            <div class="label">Time Filter</div>
            <div class="pills">
              <button type="button" class="pill" data-v="today" onclick="pickFilter('today')">Today</button>
              <button type="button" class="pill" data-v="7" onclick="pickFilter('7')">7 Days</button>
              <button type="button" class="pill" data-v="15" onclick="pickFilter('15')">15 Days</button>
              <button type="button" class="pill" data-v="30" onclick="pickFilter('30')">30 Days</button>
              <button type="button" class="pill" data-v="all" onclick="pickFilter('all')">All Time</button>
              <button type="button" class="pill" data-v="custom" onclick="pickFilter('custom')">Custom</button>
            </div>

            <div id="customBox" style="margin-top:12px; display:none;">
              <div class="row">
                <div style="flex:1;min-width:200px;">
                  <div class="label">From</div>
                  <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
                </div>
                <div style="flex:1;min-width:200px;">
                  <div class="label">To</div>
                  <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
                </div>
              </div>
            </div>

            <div class="small" style="margin-top:12px;">
              Current Range:
              <b><?= htmlspecialchars($rangeLabel) ?></b>
              <?php
              require_once 'includes/db.php';
              if ($rangeStart && $rangeEnd): ?>
                (<?= htmlspecialchars($rangeStart->format('Y-m-d H:i:s')) ?> to
                <?= htmlspecialchars($rangeEnd->format('Y-m-d H:i:s')) ?>)
                <?php
                require_once 'includes/db.php';
              else: ?>
                (All Time)
                <?php
                require_once 'includes/db.php';
              endif; ?>
            </div>

            <?php
            require_once 'includes/db.php';
            if ($action === 'preview' && $selectedMarket !== ''): ?>
              <div class="small" style="margin-top:10px;">
                Summary:
                <span class="badge b-green">Bids: <?= (int) $previewTotalBids ?></span>
                <span class="badge b-green">Users: <?= (int) $previewTotalUsers ?></span>
                <span class="badge b-green">Amount: ₹<?= number_format((float) $previewTotalAmount, 2) ?></span>
              </div>
              <?php
              require_once 'includes/db.php';
            endif; ?>
          </div>
        </div>
      </form>

      <?php
      require_once 'includes/db.php';
      if ($action === 'preview' && $previewTotalBids > 0): ?>
        <table class="table">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Bids</th>
              <th>Refund Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
            require_once 'includes/db.php';
            foreach ($previewRows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['user_id']) ?></td>
                <td><?= (int) $r['bids'] ?></td>
                <td>₹<?= number_format((float) $r['amount'], 2) ?></td>
              </tr>
              <?php
              require_once 'includes/db.php';
            endforeach; ?>
          </tbody>
        </table>
        <?php
        require_once 'includes/db.php';
      endif; ?>
    </div>

    <div class="card" style="margin-top:14px;">
      <div class="flex-between">
        <div style="font-weight:800;color:#2b1b14;">Recent Refund Batches</div>
        <div class="small">Tip: Running refund again won’t double-credit because bid_id is unique in bid_refunds.</div>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>Batch</th>
            <th>Market</th>
            <th>Bids</th>
            <th>Users</th>
            <th>Amount</th>
            <th>Time</th>
            <th>Admin</th>
          </tr>
        </thead>
        <tbody>
          <?php
          require_once 'includes/db.php';
          if (count($recentBatches)): ?>
            <?php
            require_once 'includes/db.php';
            foreach ($recentBatches as $b): ?>
              <tr>
                <td>#<?= (int) $b['id'] ?></td>
                <td><?= htmlspecialchars($b['market']) ?></td>
                <td><?= (int) $b['total_bids'] ?></td>
                <td><?= (int) $b['total_users'] ?></td>
                <td>₹<?= number_format((float) $b['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars($b['created_at']) ?></td>
                <td><?= htmlspecialchars($b['admin_id'] ?? '-') ?></td>
              </tr>
              <?php
              require_once 'includes/db.php';
            endforeach; ?>
            <?php
            require_once 'includes/db.php';
          else: ?>
            <tr>
              <td colspan="7">No batches found.</td>
            </tr>
            <?php
            require_once 'includes/db.php';
          endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <script>
    function setAction(a) {
      document.getElementById('action').value = a;
    }
    function pickFilter(v) {
      document.getElementById('time_filter').value = v;
      document.querySelectorAll('.pill').forEach(function(p) {
        p.classList.toggle('active', p.dataset.v === v);
      });
      var box = document.getElementById('customBox');
      box.style.display = (v === 'custom') ? 'block' : 'none';
    }
    function autoPreview() {
      setAction('preview');
      document.getElementById('frm').submit();
    }
    (function () {
      var v = document.getElementById('time_filter').value || 'today';
      pickFilter(v);
    })();
  </script>

</body>

</html>
<?php
require_once 'includes/db.php';
$db->close(); 
