<?php
require_once 'includes/db.php';
// admin_views.php  (SUBH GAMING - Page Views Report + Map Analytics)
// ✅ Two tabs: List of Data + Map Analytics
// ✅ Shows ALL columns: id, page_name, ip_address, country, region, city, device, os, platform, browser, repeat_user, user_agent, created_at
// ✅ Fixes "Query failed (list)" by safe bind + mysqlnd fallback
// ✅ Auto backfill (safe, limited): fills missing device/os/platform/browser from user_agent
// ✅ Repeat User shown as CALCULATED count (same IP + same device) even if DB column not updated
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
/* =========================
   ✅ ADMIN ACCESS CHECK
========================= */
$isAdmin = false;
if (isset($_SESSION['admin']) && is_array($_SESSION['admin']))
  $isAdmin = true;
if (isset($_SESSION['admin_id']) || isset($_SESSION['admin']['admin_id']) || isset($_SESSION['admin']['id']))
  $isAdmin = true;
if (isset($_SESSION['role']) && in_array(strtolower((string) $_SESSION['role']), ['admin', 'master'], true))
  $isAdmin = true;

if (!$isAdmin) {
  http_response_code(403);
  echo "Access Denied";
  exit;
}

/* =========================
   ✅ ERRORS (TEMP)
========================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================
   ✅ DB CONNECT (Your format)
========================= */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = db();
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB Connection Failed";
  exit;
}

/* =========================
   ✅ Helpers
========================= */
function h($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $db, string $table, string $col): bool
{
  try {
    $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->bind_param('s', $col);
    $st->execute();
    $res = $st->get_result();
    $ok = ($res && $res->num_rows > 0);
    $st->close();
    return $ok;
  } catch (Throwable $e) {
    return false;
  }
}

/** ✅ Bind params safely (by-reference bind_param fix) */
function stmt_bind_params(mysqli_stmt $st, string $types, array $params): void
{
  if ($types === '')
    return;

  $refs = [];
  foreach ($params as $k => $v) {
    $refs[$k] = &$params[$k];
  }
  array_unshift($refs, $types);
  call_user_func_array([$st, 'bind_param'], $refs);
}

/** ✅ Fetch all rows (mysqlnd optional) */
function stmt_fetch_all_assoc(mysqli_stmt $st): array
{
  $rows = [];

  if (method_exists($st, 'get_result')) {
    $res = $st->get_result();
    if ($res)
      while ($r = $res->fetch_assoc())
        $rows[] = $r;
    return $rows;
  }

  $meta = $st->result_metadata();
  if (!$meta)
    return $rows;

  $fields = [];
  $row = [];
  $bindArgs = [];
  while ($f = $meta->fetch_field()) {
    $fields[] = $f->name;
    $row[$f->name] = null;
    $bindArgs[] = &$row[$f->name];
  }
  $meta->free();

  call_user_func_array([$st, 'bind_result'], $bindArgs);

  while ($st->fetch()) {
    $copy = [];
    foreach ($fields as $name)
      $copy[$name] = $row[$name];
    $rows[] = $copy;
  }
  return $rows;
}

/** ✅ UA parse (same logic as views_index.php) */
function pv_parse_ua_for_display(string $ua): array
{
  $u = strtolower($ua);

  $device = 'Desktop';
  if (strpos($u, 'ipad') !== false || strpos($u, 'tablet') !== false) {
    $device = 'Tablet';
  } elseif (strpos($u, 'mobile') !== false || strpos($u, 'android') !== false || strpos($u, 'iphone') !== false) {
    $device = 'Mobile';
  }

  $platform = 'Unknown';
  if (strpos($u, 'android') !== false)
    $platform = 'Android';
  elseif (strpos($u, 'iphone') !== false || strpos($u, 'ipad') !== false || strpos($u, 'ios') !== false)
    $platform = 'iOS';
  elseif (strpos($u, 'windows') !== false)
    $platform = 'Windows';
  elseif (strpos($u, 'macintosh') !== false || strpos($u, 'mac os') !== false)
    $platform = 'Mac';
  elseif (strpos($u, 'linux') !== false)
    $platform = 'Linux';

  $os = $platform;

  $browser = 'Unknown';
  if (strpos($u, 'edg/') !== false)
    $browser = 'Edge';
  elseif (strpos($u, 'opr/') !== false || strpos($u, 'opera') !== false)
    $browser = 'Opera';
  elseif (strpos($u, 'chrome/') !== false && strpos($u, 'safari/') !== false && strpos($u, 'edg/') === false)
    $browser = 'Chrome';
  elseif (strpos($u, 'safari/') !== false && strpos($u, 'chrome/') === false)
    $browser = 'Safari';
  elseif (strpos($u, 'firefox/') !== false)
    $browser = 'Firefox';

  return [$device, $os, $platform, $browser];
}

/* =========================
   ✅ Ensure page_views table + required columns
   Required columns by you:
   id, page_name, ip_address, country, region, created_at, device, os, browser,
   repeat_user, user_agent, city, platform
========================= */
try {
  $db->query("
        CREATE TABLE IF NOT EXISTS `page_views` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `page_name` VARCHAR(80) NOT NULL DEFAULT 'index',
            `ip_address` VARCHAR(64) NOT NULL,
            `country` VARCHAR(120) DEFAULT NULL,
            `region` VARCHAR(120) DEFAULT NULL,
            `city` VARCHAR(120) DEFAULT NULL,
            `device` VARCHAR(60) DEFAULT NULL,
            `os` VARCHAR(60) DEFAULT NULL,
            `platform` VARCHAR(60) DEFAULT NULL,
            `browser` VARCHAR(60) DEFAULT NULL,
            `repeat_user` INT NOT NULL DEFAULT 1,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_country` (`country`),
            KEY `idx_region` (`region`),
            KEY `idx_page_date` (`page_name`, `created_at`),
            KEY `idx_ip_device` (`ip_address`, `device`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

  $colsToAdd = [
    'page_name' => "ALTER TABLE `page_views` ADD COLUMN `page_name` VARCHAR(80) NOT NULL DEFAULT 'index' AFTER `id`",
    'country' => "ALTER TABLE `page_views` ADD COLUMN `country` VARCHAR(120) DEFAULT NULL AFTER `ip_address`",
    'region' => "ALTER TABLE `page_views` ADD COLUMN `region`  VARCHAR(120) DEFAULT NULL AFTER `country`",
    'city' => "ALTER TABLE `page_views` ADD COLUMN `city`    VARCHAR(120) DEFAULT NULL AFTER `region`",
    'device' => "ALTER TABLE `page_views` ADD COLUMN `device`  VARCHAR(60)  DEFAULT NULL AFTER `city`",
    'os' => "ALTER TABLE `page_views` ADD COLUMN `os`      VARCHAR(60)  DEFAULT NULL AFTER `device`",
    'platform' => "ALTER TABLE `page_views` ADD COLUMN `platform` VARCHAR(60) DEFAULT NULL AFTER `os`",
    'browser' => "ALTER TABLE `page_views` ADD COLUMN `browser` VARCHAR(60)  DEFAULT NULL AFTER `platform`",
    'repeat_user' => "ALTER TABLE `page_views` ADD COLUMN `repeat_user` INT NOT NULL DEFAULT 1 AFTER `browser`",
    'user_agent' => "ALTER TABLE `page_views` ADD COLUMN `user_agent` VARCHAR(255) DEFAULT NULL AFTER `repeat_user`",
    'created_at' => "ALTER TABLE `page_views` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `user_agent`",
  ];
  foreach ($colsToAdd as $c => $sql) {
    if (!hasColumn($db, 'page_views', $c)) {
      try {
        $db->query($sql);
      } catch (Throwable $e) { /* ignore */
      }
    }
  }

  try {
    $db->query("CREATE INDEX `idx_ip_device` ON `page_views` (`ip_address`,`device`)");
  } catch (Throwable $e) { /* ignore */
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo "Table Setup Failed";
  exit;
}

/* =========================
   ✅ OPTIONAL: Auto Backfill missing device/os/platform/browser
   (Fix: when views_index.php did not insert these, admin page still shows)
   Safe + limited to last 400 rows only.
========================= */
try {
  $st = $db->prepare("
        SELECT id, user_agent, device, os, platform, browser
        FROM page_views
        WHERE (device IS NULL OR device='' OR os IS NULL OR os='' OR platform IS NULL OR platform='' OR browser IS NULL OR browser='')
          AND user_agent IS NOT NULL AND user_agent <> ''
        ORDER BY id DESC
        LIMIT 400
    ");
  $st->execute();
  $needFix = stmt_fetch_all_assoc($st);
  $st->close();

  if (!empty($needFix)) {
    $up = $db->prepare("UPDATE page_views SET device=?, os=?, platform=?, browser=? WHERE id=?");
    foreach ($needFix as $r) {
      $id = (int) $r['id'];
      $ua = (string) ($r['user_agent'] ?? '');
      if ($ua === '')
        continue;

      list($d, $o, $p, $b) = pv_parse_ua_for_display($ua);

      // if already exists, keep old (don’t override)
      $finalD = ($r['device'] ?? '') ? (string) $r['device'] : $d;
      $finalO = ($r['os'] ?? '') ? (string) $r['os'] : $o;
      $finalP = ($r['platform'] ?? '') ? (string) $r['platform'] : $p;
      $finalB = ($r['browser'] ?? '') ? (string) $r['browser'] : $b;

      $up->bind_param("ssssi", $finalD, $finalO, $finalP, $finalB, $id);
      $up->execute();
    }
    $up->close();
  }
} catch (Throwable $e) {
  // ignore backfill errors
}

/* =========================
   ✅ Filters
========================= */
$range = $_GET['range'] ?? '7';
$range = in_array($range, ['today', '7', '15', '30', 'all', 'custom'], true) ? $range : '7';

$start = null;
$end = null;

if ($range === 'today') {
  $start = date('Y-m-d 00:00:00');
  $end = date('Y-m-d 23:59:59');
} elseif ($range === '7') {
  $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
  $end = date('Y-m-d 23:59:59');
} elseif ($range === '15') {
  $start = date('Y-m-d 00:00:00', strtotime('-14 days'));
  $end = date('Y-m-d 23:59:59');
} elseif ($range === '30') {
  $start = date('Y-m-d 00:00:00', strtotime('-29 days'));
  $end = date('Y-m-d 23:59:59');
} elseif ($range === 'custom') {
  $sd = $_GET['start'] ?? '';
  $ed = $_GET['end'] ?? '';
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ed)) {
    $start = $sd . ' 00:00:00';
    $end = $ed . ' 23:59:59';
  } else {
    $range = '7';
    $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
    $end = date('Y-m-d 23:59:59');
  }
} // 'all' => no date filter

$pageFilter = $_GET['page'] ?? 'all';
$pageFilter = in_array($pageFilter, ['all', 'index', 'dashboard'], true) ? $pageFilter : 'all';

/* Quick search (optional): ip/country/region/device/browser */
$search = trim((string) ($_GET['q'] ?? ''));

/* =========================
   ✅ Pagination
========================= */
$perPage = 10;
$pageNo = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$offset = ($pageNo - 1) * $perPage;

/* =========================
   ✅ WHERE builder
========================= */
$where = "1=1";
$types = "";
$params = [];

if ($pageFilter !== 'all') {
  $where .= " AND page_name = ?";
  $types .= "s";
  $params[] = $pageFilter;
}
if ($start && $end) {
  $where .= " AND created_at BETWEEN ? AND ?";
  $types .= "ss";
  $params[] = $start;
  $params[] = $end;
}
if ($search !== '') {
  $where .= " AND (
        ip_address LIKE ?
        OR country LIKE ?
        OR region LIKE ?
        OR city LIKE ?
        OR device LIKE ?
        OR os LIKE ?
        OR platform LIKE ?
        OR browser LIKE ?
    )";
  $like = '%' . $search . '%';
  $types .= "ssssssss";
  array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

/* =========================
   ✅ Total count
========================= */
$totalRows = 0;
try {
  $st = $db->prepare("SELECT COUNT(DISTINCT ip_address) AS cnt FROM page_views WHERE $where");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $rc = stmt_fetch_all_assoc($st);
  $totalRows = (int) ($rc[0]['cnt'] ?? 0);
  $st->close();
} catch (Throwable $e) {
  error_log("admin_views count error: " . $e->getMessage());
  http_response_code(500);
  echo "Query failed (count).";
  exit;
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($pageNo > $totalPages)
  $pageNo = $totalPages;

/* =========================
   ✅ List rows (DESC)
========================= */
$rows = [];
try {
  $sqlList = "
        SELECT
            MAX(id) AS id, MAX(page_name) AS page_name, ip_address, 
            MAX(country) AS country, MAX(region) AS region, MAX(city) AS city,
            MAX(device) AS device, MAX(os) AS os, MAX(platform) AS platform, 
            MAX(browser) AS browser, COUNT(*) AS repeat_user_calc, 
            MAX(user_agent) AS user_agent, MAX(created_at) AS created_at
        FROM page_views
        WHERE $where
        GROUP BY ip_address
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ";
  $st = $db->prepare($sqlList);

  $types2 = $types . "ii";
  $params2 = $params;
  $params2[] = (int) $perPage;
  $params2[] = (int) $offset;

  stmt_bind_params($st, $types2, $params2);
  $st->execute();
  $rows = stmt_fetch_all_assoc($st);
  $st->close();

} catch (Throwable $e) {
  error_log("admin_views list error: " . $e->getMessage());
  http_response_code(500);
  echo "Query failed (list).";
  exit;
}

/* =========================
   ✅ Repeat User (same IP + same device) - FAST (one query)
   We show CALCULATED count so it works even if repeat_user column not updated.
========================= */
foreach ($rows as &$r) {
    if ((empty($r['device']) || empty($r['os']) || empty($r['platform']) || empty($r['browser'])) && !empty($r['user_agent'])) {
        list($d, $o, $p, $b) = pv_parse_ua_for_display((string) $r['user_agent']);
        if (empty($r['device']))
            $r['device'] = $d;
        if (empty($r['os']))
            $r['os'] = $o;
        if (empty($r['platform']))
            $r['platform'] = $p;
        if (empty($r['browser']))
            $r['browser'] = $b;
    }
}
unset($r);

/* =========================
   ✅ Top stats + Map data
========================= */
$topCountry = ['name' => 'N/A', 'total' => 0];
$topRegion = ['name' => 'N/A', 'total' => 0];
$countryCounts = [];
$topDevices = [];
$topBrowsers = [];

try {
  $st = $db->prepare("
        SELECT COALESCE(NULLIF(country,''),'Unknown') AS name, COUNT(*) AS total
        FROM page_views WHERE $where
        GROUP BY name ORDER BY total DESC LIMIT 1
    ");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $r = stmt_fetch_all_assoc($st);
  $st->close();
  if (!empty($r))
    $topCountry = ['name' => $r[0]['name'], 'total' => (int) $r[0]['total']];

  $st = $db->prepare("
        SELECT COALESCE(NULLIF(region,''),'Unknown') AS name, COUNT(*) AS total
        FROM page_views WHERE $where
        GROUP BY name ORDER BY total DESC LIMIT 1
    ");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $r = stmt_fetch_all_assoc($st);
  $st->close();
  if (!empty($r))
    $topRegion = ['name' => $r[0]['name'], 'total' => (int) $r[0]['total']];

  $st = $db->prepare("
        SELECT COALESCE(NULLIF(country,''),'Unknown') AS country, COUNT(*) AS total
        FROM page_views WHERE $where
        GROUP BY country ORDER BY total DESC
    ");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $countryCounts = stmt_fetch_all_assoc($st);
  $st->close();

  $st = $db->prepare("
        SELECT COALESCE(NULLIF(device,''),'Unknown') AS name, COUNT(*) AS total
        FROM page_views WHERE $where
        GROUP BY name ORDER BY total DESC LIMIT 5
    ");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $topDevices = stmt_fetch_all_assoc($st);
  $st->close();

  $st = $db->prepare("
        SELECT COALESCE(NULLIF(browser,''),'Unknown') AS name, COUNT(*) AS total
        FROM page_views WHERE $where
        GROUP BY name ORDER BY total DESC LIMIT 5
    ");
  stmt_bind_params($st, $types, $params);
  $st->execute();
  $topBrowsers = stmt_fetch_all_assoc($st);
  $st->close();

} catch (Throwable $e) {
  // ignore
}

/* =========================
   ✅ URL builder
========================= */
function buildUrl(array $over = []): string
{
  $q = $_GET;
  foreach ($over as $k => $v) {
    if ($v === null)
      unset($q[$k]);
    else
      $q[$k] = $v;
  }
  return 'admin_views.php?' . http_build_query($q);
}

/* Page number range display */
$show = [];
$startP = max(1, $pageNo - 2);
$endP = min($totalPages, $pageNo + 2);
for ($i = $startP; $i <= $endP; $i++)
  $show[] = $i;

/* Map data for Google GeoChart */
$geoData = [["Country", "Views"]];
foreach ($countryCounts as $cc) {
  $cname = (string) ($cc['country'] ?? 'Unknown');
  $total = (int) ($cc['total'] ?? 0);
  if ($cname === '' || strtolower($cname) === 'unknown')
    continue;
  $geoData[] = [$cname, $total];
}
?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Page Views Report</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --brown: #c62828;
      --brown2: #a61f1f;
      --bg: #ffffff;
      --text: #111111;
      --muted: #666;
      --line: rgba(0, 0, 0, .12);
      --soft: rgba(198, 40, 40, .06);
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: Poppins, system-ui, -apple-system, Segoe UI, Roboto, Arial
    }

    a {
      color: inherit;
      text-decoration: none
    }

    .wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 12px
    }

    .header {
      background: var(--brown);
      color: #fff;
      border-radius: 14px;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .leftHead {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0
    }

    .back {
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .35);
      color: #fff;
      padding: 8px 11px;
      border-radius: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .titleBox {
      min-width: 0
    }

    .title {
      font-weight: 700;
      font-size: 15px;
      line-height: 1.15
    }

    .sub {
      font-size: 12px;
      opacity: .92;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-weight: 500
    }

    .chips {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end
    }

    .chip {
      border: 1px solid rgba(255, 255, 255, .35);
      background: rgba(255, 255, 255, .12);
      padding: 7px 10px;
      border-radius: 999px;
      font-size: 12px;
      color: #fff;
      font-weight: 500;
    }

    .panel {
      margin-top: 12px;
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 12px;
      background: #fff;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .05);
    }

    .filters {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: end
    }

    label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      font-weight: 600;
      margin-bottom: 6px
    }

    select,
    input {
      padding: 10px 10px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: #fff;
      color: #111;
      outline: none;
      min-width: 160px;
      font-weight: 500;
    }

    .searchBox {
      min-width: 220px
    }

    .btn {
      padding: 10px 14px;
      border-radius: 12px;
      border: 1px solid var(--brown);
      background: var(--brown);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
    }

    .btn:hover {
      background: var(--brown2)
    }

    .switchRow {
      margin-top: 12px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .switchBtn {
      border: 1px solid rgba(0, 0, 0, .12);
      background: #fff;
      color: #111;
      padding: 9px 12px;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
    }

    .switchBtn.active {
      border-color: var(--brown);
      background: var(--soft);
      color: var(--brown);
    }

    .stats {
      margin-top: 12px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    @media (max-width:900px) {
      .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .stat {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 10px 12px;
      background: #fff;
    }

    .stat .k {
      font-size: 12px;
      color: var(--muted);
      font-weight: 600
    }

    .stat .v {
      margin-top: 6px;
      font-size: 18px;
      font-weight: 700;
      color: var(--brown)
    }

    .sectionTitle {
      margin-top: 14px;
      font-size: 13px;
      font-weight: 700;
      color: #111;
    }

    .tableWrap {
      margin-top: 10px;
      overflow: auto;
      border-radius: 14px;
      border: 1px solid rgba(0, 0, 0, .08);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff
    }

    th,
    td {
      padding: 10px 8px;
      border-bottom: 1px solid rgba(0, 0, 0, .07);
      font-size: 12.5px;
      vertical-align: top
    }

    th {
      background: var(--soft);
      color: #111;
      font-weight: 700;
      white-space: nowrap
    }

    .badge {
      display: inline-block;
      padding: 4px 9px;
      border-radius: 999px;
      background: var(--brown);
      color: #fff;
      font-weight: 600;
      font-size: 12px;
      white-space: nowrap;
    }

    .muted {
      color: var(--muted)
    }

    .repeat {
      font-weight: 700;
      color: var(--brown)
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      font-size: 14px;
    }

    .pagination a,
    .pagination span {
      padding: 8px 16px;
      border-radius: 8px;
      background: #e0d7ce;
      color: #333;
      text-decoration: none;
      font-weight: 600;
      min-width: 100px;
      text-align: center;
    }

    .pagination .disabled {
      background: #e0e0e0;
      color: #9e9e9e;
      cursor: default;
      pointer-events: none;
    }

    .pagination .info {
      background: transparent;
      padding: 0;
      color: var(--brown);
      font-weight: 700;
      min-width: auto;
    }

    .grid2 {
      display: grid;
      grid-template-columns: 1.2fr .8fr;
      gap: 10px;
      margin-top: 10px
    }

    @media (max-width:900px) {
      .grid2 {
        grid-template-columns: 1fr
      }
    }

    .card {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 12px;
      background: #fff;
    }

    .smallList {
      margin: 8px 0 0 0;
      padding-left: 16px
    }

    .smallList li {
      font-size: 12px;
      color: #111;
      margin: 4px 0;
      font-weight: 500
    }

    #mapBox {
      width: 100%;
      height: 420px
    }

    .hint {
      font-size: 12px;
      color: var(--muted);
      margin-top: 8px;
      font-weight: 500
    }

    .ua {
      max-width: 340px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }
  </style>

  <script src="https://www.gstatic.com/charts/loader.js"></script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <div class="wrap">

    <div class="header">
      <div class="leftHead">
        <a class="back" href="admin_dashboard.php">← Back to Admin</a>
        <div class="titleBox">
          <div class="title">Page Views Report</div>
          <div class="sub">
            Showing <b><?= h($totalRows) ?></b> views
            <?= $pageFilter !== 'all' ? ' • Page: <b>' . h(strtoupper($pageFilter)) . '</b>' : '' ?>
            <?= ($start && $end) ? ' • <span>' . h($start) . ' to ' . h($end) . '</span>' : ' • <span>All time</span>' ?>
            <?= $search !== '' ? ' • Search: <b>' . h($search) . '</b>' : '' ?>
          </div>
        </div>
      </div>
      <div class="chips">
        <div class="chip">Top Country: <b><?= h($topCountry['name']) ?></b> (<?= h($topCountry['total']) ?>)</div>
        <div class="chip">Top Region: <b><?= h($topRegion['name']) ?></b> (<?= h($topRegion['total']) ?>)</div>
      </div>
    </div>

    <div class="panel">
      <form method="get" class="filters">
        <div>
          <label>Range</label>
          <select name="range" onchange="toggleCustom(this.value)">
            <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7" <?= $range === '7' ? 'selected' : '' ?>>7 Days</option>
            <option value="15" <?= $range === '15' ? 'selected' : '' ?>>15 Days</option>
            <option value="30" <?= $range === '30' ? 'selected' : '' ?>>30 Days</option>
            <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom</option>
          </select>
        </div>

        <div>
          <label>Page</label>
          <select name="page">
            <option value="all" <?= $pageFilter === 'all' ? 'selected' : '' ?>>All Pages</option>
            <option value="index" <?= $pageFilter === 'index' ? 'selected' : '' ?>>Index</option>
            <option value="dashboard" <?= $pageFilter === 'dashboard' ? 'selected' : '' ?>>Dashboard</option>
          </select>
        </div>

        <div id="customBox" style="<?= $range === 'custom' ? '' : 'display:none' ?>">
          <label>Start Date</label>
          <input type="date" name="start" value="<?= h($_GET['start'] ?? '') ?>">
        </div>

        <div id="customBox2" style="<?= $range === 'custom' ? '' : 'display:none' ?>">
          <label>End Date</label>
          <input type="date" name="end" value="<?= h($_GET['end'] ?? '') ?>">
        </div>

        <div class="searchBox">
          <label>Search</label>
          <input type="text" name="q" placeholder="IP / country / device / browser..." value="<?= h($search) ?>">
        </div>

        <div>
          <button class="btn" type="submit">Apply</button>
        </div>
      </form>

      <div class="switchRow">
        <button type="button" class="switchBtn" id="btnList" onclick="showTab('list')">List of Data</button>
        <button type="button" class="switchBtn" id="btnMap" onclick="showTab('map')">Map Analytics</button>
      </div>

      <div class="stats">
        <div class="stat">
          <div class="k">Total Views</div>
          <div class="v"><?= h($totalRows) ?></div>
        </div>
        <div class="stat">
          <div class="k">Current Page Filter</div>
          <div class="v"><?= $pageFilter === 'all' ? 'ALL' : h(strtoupper($pageFilter)) ?></div>
        </div>
        <div class="stat">
          <div class="k">Rows Per Page</div>
          <div class="v"><?= h($perPage) ?></div>
        </div>
        <div class="stat">
          <div class="k">Total Pages</div>
          <div class="v"><?= h($totalPages) ?></div>
        </div>
      </div>

      <!-- ===== LIST TAB ===== -->
      <div id="tabList" style="margin-top:10px;">
        <div class="sectionTitle">Latest Views (Descending)</div>

        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Page</th>
                <th>IP</th>
                <th>Country</th>
                <th>Region</th>
                <th>City</th>
                <th>Device</th>
                <th>OS</th>
                <th>Platform</th>
                <th>Browser</th>
                <th>Repeat</th>
                <th>User Agent</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php
              require_once 'includes/db.php';
              if (!$rows): ?>
                <tr>
                  <td colspan="13" class="muted">No views found for this filter.</td>
                </tr>
                <?php
                require_once 'includes/db.php';
              else: ?>
                <?php
                require_once 'includes/db.php';
                foreach ($rows as $r): ?>
                  <tr>
                    <td class="muted"><?= h($r['id']) ?></td>
                    <td><span class="badge"><?= h($r['page_name'] ?: '-') ?></span></td>
                    <td><?= h($r['ip_address'] ?: '-') ?></td>
                    <td><?= h($r['country'] ?: 'Unknown') ?></td>
                    <td><?= h($r['region'] ?: 'Unknown') ?></td>
                    <td><?= h($r['city'] ?: '-') ?></td>
                    <td><?= h($r['device'] ?: '-') ?></td>
                    <td><?= h($r['os'] ?: '-') ?></td>
                    <td><?= h($r['platform'] ?: '-') ?></td>
                    <td><?= h($r['browser'] ?: '-') ?></td>
                    <td class="repeat"><?= h((int) ($r['repeat_user_calc'] ?? 1)) ?></td>
                    <td><span class="ua" title="<?= h($r['user_agent'] ?? '') ?>"><?= h($r['user_agent'] ?: '-') ?></span>
                    </td>
                    <td><?= h($r['created_at'] ?: '-') ?></td>
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

        <?php if ($totalPages >= 1): ?>
          <div class="pagination">
            <?php
            $prevUrl = buildUrl(['p' => max(1, $pageNo - 1)]);
            $nextUrl = buildUrl(['p' => min($totalPages, $pageNo + 1)]);
            ?>

            <?php if ($pageNo > 1): ?>
              <a href="<?= h($prevUrl) ?>">&laquo; Previous</a>
            <?php else: ?>
              <span class="disabled">&laquo; Previous</span>
            <?php endif; ?>

            <span class="info">Page <?= $pageNo ?> of <?= $totalPages ?></span>

            <?php if ($pageNo < $totalPages): ?>
              <a href="<?= h($nextUrl) ?>">Next &raquo;</a>
            <?php else: ?>
              <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="hint">
          ✅ Device/OS/Platform/Browser is shown from DB, and if missing it is auto-derived from User-Agent for display +
          auto-backfilled (last 400 rows).
        </div>
      </div>

      <!-- ===== MAP TAB ===== -->
      <div id="tabMap" style="margin-top:10px; display:none;">
        <div class="sectionTitle">Traffic Map (Country Wise)</div>

        <div class="grid2">
          <div class="card">
            <div id="mapBox"></div>
            <div class="hint">Map uses Google GeoChart (CDN). It shows only valid country names.</div>
          </div>

          <div class="card">
            <div class="sectionTitle" style="margin-top:0;">Top Devices / Browsers</div>

            <div class="sectionTitle" style="margin-top:10px;">Top Devices</div>
            <ul class="smallList">
              <?php
              require_once 'includes/db.php';
              if (!$topDevices): ?>
                <li class="muted">No data</li>
                <?php
                require_once 'includes/db.php';
              else:
                foreach ($topDevices as $t): ?>
                  <li><?= h($t['name']) ?> — <b><?= h($t['total']) ?></b></li>
                  <?php
                  require_once 'includes/db.php';
                endforeach;
              endif; ?>
            </ul>

            <div class="sectionTitle" style="margin-top:10px;">Top Browsers</div>
            <ul class="smallList">
              <?php
              require_once 'includes/db.php';
              if (!$topBrowsers): ?>
                <li class="muted">No data</li>
                <?php
                require_once 'includes/db.php';
              else:
                foreach ($topBrowsers as $t): ?>
                  <li><?= h($t['name']) ?> — <b><?= h($t['total']) ?></b></li>
                  <?php
                  require_once 'includes/db.php';
                endforeach;
              endif; ?>
            </ul>
          </div>
        </div>

        <div class="sectionTitle">Country List (Descending)</div>
        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Country</th>
                <th>Views</th>
              </tr>
            </thead>
            <tbody>
              <?php
              require_once 'includes/db.php';
              if (!$countryCounts): ?>
                <tr>
                  <td colspan="2" class="muted">No data</td>
                </tr>
                <?php
                require_once 'includes/db.php';
              else:
                foreach ($countryCounts as $c): ?>
                  <tr>
                    <td><?= h($c['country']) ?></td>
                    <td><b><?= h($c['total']) ?></b></td>
                  </tr>
                  <?php
                  require_once 'includes/db.php';
                endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script>
    function toggleCustom(v) {
      var a = document.getElementById('customBox');
      var b = document.getElementById('customBox2');
      if (!a || !b) return;
      if (v === 'custom') { a.style.display = 'block'; b.style.display = 'block'; }
      else { a.style.display = 'none'; b.style.display = 'none'; }
    }

    function showTab(tab) {
      var list = document.getElementById('tabList');
      var map = document.getElementById('tabMap');
      var bL = document.getElementById('btnList');
      var bM = document.getElementById('btnMap');

      if (tab === 'map') {
        list.style.display = 'none';
        map.style.display = 'block';
        bM.classList.add('active');
        bL.classList.remove('active');
        drawGeoChart();
      } else {
        map.style.display = 'none';
        list.style.display = 'block';
        bL.classList.add('active');
        bM.classList.remove('active');
      }
    }
    showTab('list');

    // ===== Google GeoChart =====
    var geoDrawn = false;
    function drawGeoChart() {
      if (geoDrawn) return;
      geoDrawn = true;

      var raw = <?php
      require_once 'includes/db.php';
      echo json_encode($geoData, JSON_UNESCAPED_UNICODE); ?>;

      google.charts.load('current', { 'packages': ['geochart'] });
      google.charts.setOnLoadCallback(function () {
        try {
          var data = google.visualization.arrayToDataTable(raw);
          var chart = new google.visualization.GeoChart(document.getElementById('mapBox'));
          var opts = {
            legend: 'none',
            backgroundColor: '#ffffff',
            datalessRegionColor: '#f3f3f3'
          };
          chart.draw(data, opts);

          window.addEventListener('resize', function () {
            chart.draw(data, opts);
          });
        } catch (e) {
          document.getElementById('mapBox').innerHTML = '<div class="muted">Map not available (no valid country data).</div>';
        }
      });
    }
  </script>
</body>

</html>




