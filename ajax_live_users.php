<?php
require_once 'includes/db.php';
/*
  ajax_live_users.php
  - Online users (status='online') + pagination
  - Recently offline users (last 10 rows)
  - recent = 1  => active in last 20 sec
            0  => not active in last 20 sec
*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

// --- AJAX Only (for admin) ---
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Not authorized']);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = db();
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'DB connection failed']);
    exit;
}

/* -------------------------------------------------
   SETTINGS
------------------------------------------------- */
$seconds = 20;   // recent activity window
$rows    = max(1, min(50, intval($_GET['rows'] ?? 10)));

$online_page  = max(1, intval($_GET['online_page'] ?? 1));
$offline_page = max(1, intval($_GET['offline_page'] ?? 1));

/* -------------------------------------------------
   TOTAL COUNTS
   (online = status='online' only, no time condition)
------------------------------------------------- */
$online_count = $db->query("
    SELECT COUNT(*) FROM live 
    WHERE status='online'
")->fetch_row()[0] ?? 0;

$offline_count = $db->query("
    SELECT COUNT(*) FROM live 
    WHERE status='offline'
")->fetch_row()[0] ?? 0;

/* -------------------------------------------------
   PAGINATION
------------------------------------------------- */
$online_pages  = max(1, ceil($online_count / $rows));
$offline_pages = max(1, ceil($offline_count / $rows));

$online_page  = min($online_page,  $online_pages);
$offline_page = min($offline_page, $offline_pages);

$online_start  = ($online_page - 1)  * $rows;
$offline_start = ($offline_page - 1) * $rows;

/* -------------------------------------------------
   ONLINE USERS (status='online')
   + recent flag: last_seen >= NOW()-INTERVAL ? SECOND
------------------------------------------------- */
$online = [];
$stmt = $db->prepare("
    SELECT 
        user_id,
        name,
        last_seen,
        (last_seen >= NOW() - INTERVAL ? SECOND) AS recent
    FROM live
    WHERE status='online'
    ORDER BY recent DESC, last_seen DESC
    LIMIT ?, ?
");
$stmt->bind_param('iii', $seconds, $online_start, $rows);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['last_seen']);
    $row['last_seen'] = $dt ? $dt->format('d-m-Y h:i:s A') : $row['last_seen'];
    $row['recent']    = (int)$row['recent']; // 1 or 0
    $online[] = $row;
}
$stmt->close();

/* -------------------------------------------------
   LAST 10 OFFLINE USERS
------------------------------------------------- */
$offline = [];
$stmt2 = $db->prepare("
    SELECT user_id, name, last_seen
    FROM live
    WHERE status='offline'
    ORDER BY last_seen DESC
    LIMIT 10
");
$stmt2->execute();
$r2 = $stmt2->get_result();
while ($row = $r2->fetch_assoc()) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['last_seen']);
    $row['last_seen'] = $dt ? $dt->format('d-m-Y h:i:s A') : $row['last_seen'];
    $offline[] = $row;
}
$stmt2->close();

$db->close();

/* -------------------------------------------------
   JSON OUTPUT
------------------------------------------------- */
echo json_encode([
    'success'       => true,
    'online_count'  => (int)$online_count,
    'online_pages'  => (int)$online_pages,
    'online_start'  => (int)$online_start,
    'online'        => $online,

    'offline_count' => (int)$offline_count,
    'offline_pages' => (int)$offline_pages,
    'offline_start' => (int)$offline_start,
    'offline'       => $offline
], JSON_UNESCAPED_UNICODE);
