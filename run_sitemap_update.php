<?php
ini_set('display_errors', 1);
require_once 'includes/db.php';

try {
  $db = db();
  $db->query("SET time_zone = '+05:30'");
  echo "DB Connected.\n";
} catch (Throwable $e) {
  die("Connection failed: " . $e->getMessage() . "\n");
}

function slug_variants(string $marketName): array
{
  $name = strtolower(trim($marketName));
  $slug = preg_replace('/[^a-z0-9]+/', '_', $name);
  $slug = trim($slug, '_');
  return ($slug === '') ? [] : [$slug];
}

/* ---------- REGULAR CHARTS TEMPLATE ---------- */
function get_regular_template()
{
  return <<<'TPL'
<?php
require_once '../includes/db.php';
/* Regular Market Chart Page */
/* LL_CHART_PAGE_V5 */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
/* (Optional) Ping includes if you record user activity */
// require_once '../includes/user_ping.php';

/* DB Connect */
$db = db();
if ($db->connect_error) die('DB error');
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

$marketName = '__MARKET_NAME__';

/* parse result helper */
function parse_result_parts(string $str): array {
  $str = trim($str);
  if (preg_match('/^([0-9*]{3})-([0-9*]{2})-([0-9*]{3})$/', $str, $m)) {
    return [
      'open_pana' => ($m[1] === '***') ? '' : $m[1],
      'jodi' => $m[2],
      'close_pana' => ($m[3] === '***') ? '' : $m[3],
    ];
  }
  return ['open_pana' => '', 'jodi' => $str, 'close_pana' => ''];
}

/* Date Filters: Show from 2022 by default or "full" chart */
$today = date('Y-m-d');
$from = '2022-01-01'; // "Full" chart starting point
$to = $today;

/* Fetch Results */
$stmt = $db->prepare("
  SELECT result_date, result_str
    FROM market_result
   WHERE market = ?
     AND result_date BETWEEN ? AND ?
   ORDER BY result_date DESC
");
$stmt->bind_param('sss', $marketName, $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$db->close();

/* Group by Weeks */
$weeks = [];
foreach ($rows as $row) {
  $date = $row['result_date'];
  $ts = strtotime($date);
  if (!$ts) continue;
  $weekday = (int)date('N', $ts); // 1=Mon..7=Sun
  $weekStart = date('Y-m-d', strtotime('-'.($weekday-1).' days', $ts));
  $weekEnd = date('Y-m-d', strtotime('+'.(7-$weekday).' days', $ts));

  if (!isset($weeks[$weekStart])) {
    $weeks[$weekStart] = ['start'=>$weekStart, 'end'=>$weekEnd, 'days'=>[]];
  }
  $weeks[$weekStart]['days'][$weekday] = [
    'date' => $date,
    'parts'=> parse_result_parts($row['result_str'])
  ];
}
krsort($weeks);

function fmt_range($s, $e) {
  $s = date('d/m/y', strtotime($s));
  $e = date('d/m/y', strtotime($e));
  return $s . "<br>to<br>" . $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($marketName) ?> Chart</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    :root { --red: #c62828; --white: #fff; --bg: #f5f0e5; }
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
    body{background:var(--bg);color:#333;min-height:100vh;display:flex;flex-direction:column}
    
    .header-bar {
      background: var(--red); color: var(--white); padding: 12px 16px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .header-bar h1 { font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .back-btn { color: var(--white); font-size: 20px; text-decoration: none; }

    .container { max-width: 800px; margin: 20px auto; padding: 0 10px; flex: 1; width: 100%; }

    .table-wrap {
      background: #fff; border-radius: 8px; overflow-x: auto;
      border: 2px solid var(--red); box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f3f3f3; color: #444; font-size: 11px; font-weight: 700; padding: 8px 2px; border-bottom: 2px solid #ddd; border-right: 1px solid #eee; }
    th:last-child { border-right: none; }
    td { border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f3f3f3; text-align: center; padding: 6px 1px; vertical-align: middle; }
    tr:nth-child(even) { background: #fafafa; }
    
    .date-col { font-size: 10px; color: var(--red); font-weight: 700; line-height: 1.2; width: 55px; white-space: nowrap; }
    
    .panel-cell { display: flex; flex-direction: column; align-items: center; font-size: 10px; line-height: 1.1; justify-content: center; min-height: 35px; }
    .pana { font-size: 9px; color: #555; display: block; }
    .jodi { font-size: 13px; font-weight: 800; color: #000; margin: 1px 0; display: block; }
    .sat .jodi { color: var(--red); }

    /* Mobile Responsive Tweaks */
    @media (max-width: 480px) {
        .container { padding: 0 2px; margin: 10px auto; }
        .header-bar h1 { font-size: 16px; }
        th { font-size: 9px; padding: 4px 1px; }
        td { padding: 4px 0; }
        .date-col { font-size: 9px; width: 45px; }
        .panel-cell { min-height: 30px; font-size: 9px; }
        .pana { font-size: 8px; letter-spacing: -0.5px; }
        .jodi { font-size: 12px; margin: 0; }
    }

    .footer {
      background: #2d1811; color: rgba(255,255,255,0.6);
      text-align: center; padding: 15px; font-size: 12px; margin-top: auto;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<header class="header-bar">
  <a href="../charts.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
  <h1><?= htmlspecialchars($marketName) ?> Chart</h1>
  <div style="width:24px"></div>
</header>

<div class="container">
  <div class="table-wrap">
    <?php if(empty($weeks)): ?>
      <div style="padding:20px;text-align:center;color:#888;">No data found for this period.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>DATE</th>
            <th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th><th>SUN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($weeks as $week): ?>
            <tr>
              <td class="date-col"><?= fmt_range($week['start'], $week['end']) ?></td>
              <?php for($d=1; $d<=7; $d++): 
                 $dayData = $week['days'][$d] ?? null;
                 $isSat = ($d===6);
              ?>
                <td>
                  <?php if($dayData): $p=$dayData['parts']; ?>
                    <div class="panel-cell <?= $isSat?'sat':'' ?>">
                      <span class="pana"><?= $p['open_pana'] ?></span>
                      <span class="jodi"><?= $p['jodi'] ?></span>
                      <span class="pana"><?= $p['close_pana'] ?></span>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<footer class="footer">
  &copy; <?= date('Y') ?> SUBH GAMING. All Rights Reserved.
</footer>

</body>
</html>
TPL;
}
function ensure_chart_page(string $marketName): void
{
  $variants = slug_variants($marketName);
  if (!$variants)
    return;

  $marketEsc = str_replace("'", "\\'", $marketName);
  $marker = 'LL_CHART_PAGE_V5';
  $template = get_regular_template();
  $newCode = str_replace('__MARKET_NAME__', $marketEsc, $template);

  if (!is_dir(__DIR__ . '/charts')) {
    mkdir(__DIR__ . '/charts', 0755, true);
  }

  foreach ($variants as $slug) {
    if (!$slug)
      continue;
    $filePath = __DIR__ . "/charts/{$slug}_full_chart.php";

    $shouldWrite = true;
    if (file_exists($filePath)) {
      $old = file_get_contents($filePath);
      if ($old !== false && strpos($old, $marker) !== false) {
        $shouldWrite = false;
      }
    }

    if ($shouldWrite) {
      if (file_put_contents($filePath, $newCode, LOCK_EX)) {
        echo "Updated Regular: $filePath\n";
      }
    }
  }
}

echo "Updating Regular Markets...\n";

// Use direct DB from db.php include
$res = $db->query("SELECT market_name FROM markets");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    if (!empty($row['market_name'])) {
      ensure_chart_page((string) $row['market_name']);
    }
  }
}

echo "Backfill Done.\n";

/* ---------- SITEMAP GENERATION ---------- */
echo "Generating Sitemap...\n";

$baseUrl = 'https://subhgaming.online';
$files = [
  'index.php',
  'user_login.php',
  'user_register.php',
  'user_forgot_password.php',
  'charts.php'
];

// Add chart files
$chartsDir = __DIR__ . '/charts';
if (is_dir($chartsDir)) {
  foreach (scandir($chartsDir) as $f) {
    if ($f !== '.' && $f !== '..' && strpos($f, '_full_chart.php') !== false) {
      $files[] = 'charts/' . $f;
    }
  }
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($files as $file) {
  $url = $baseUrl . '/' . $file;
  $xml .= "  <url>\n";
  $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
  $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
  $xml .= "    <changefreq>daily</changefreq>\n";
  $xml .= "    <priority>0.8</priority>\n";
  $xml .= "  </url>\n";
}

$xml .= '</urlset>';

if (file_put_contents(__DIR__ . '/sitemap.xml', $xml)) {
  echo "Sitemap generated: sitemap.xml\n";
} else {
  echo "Failed to generate sitemap.xml\n";
}
