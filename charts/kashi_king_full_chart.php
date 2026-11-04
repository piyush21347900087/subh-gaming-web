<?php
require_once '../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$db = db();
if ($db->connect_error) die('DB error');
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

$marketName = 'KASHI KING';

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

$today = date('Y-m-d');
$from = '2022-01-01'; 
$to = $today;

$stmt = $db->prepare("SELECT result_date, result_str FROM market_result WHERE market = ? AND result_date BETWEEN ? AND ? ORDER BY result_date DESC");
$stmt->bind_param('sss', $marketName, $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$db->close();

$weeks = [];
foreach ($rows as $row) {
    $date = $row['result_date'];
    $ts = strtotime($date);
    if (!$ts) continue;
    $weekday = (int)date('N', $ts);
    $weekStart = date('Y-m-d', strtotime('-'.($weekday-1).' days', $ts));
    if (!isset($weeks[$weekStart])) {
        $weeks[$weekStart] = ['start'=>$weekStart, 'days'=>[]];
    }
    $weeks[$weekStart]['days'][$weekday] = ['parts'=> parse_result_parts($row['result_str'])];
}
krsort($weeks);

function fmt_range($s) {
    $ts = strtotime($s);
    $e = date('d/m/y', strtotime('+6 days', $ts));
    return date('d/m/y', $ts) . "<br>to<br>" . $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($marketName) ?> Chart | SHREE JI MATKA</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;900&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
                <style>
        :root {
            --primary: #000081; /* Blue */
            --accent: #ff005c; /* Pink */
            --bg: #f8f9fa;
            --text: #1a1a24;
            --border: rgba(0, 0, 0, 0.08);
            --surface: #ffffff;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); padding: 20px 10px; overflow-x: hidden; }
        
        .header { text-align: center; margin-bottom: 25px; padding: 10px; }
        .header h1 { font-size: 28px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); text-shadow: 0 2px 4px rgba(0,0,129,0.1); }
        .header span { background: var(--accent); color: #fff; padding: 4px 12px; border-radius: 8px; font-size: 16px; box-shadow: 0 4px 10px rgba(255, 0, 92, 0.2); }
        
        .container { max-width: 1100px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.06); }
        
        .table-wrap { width: 100%; overflow-x: hidden; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 0; }
        
        th { background: var(--primary); color: #ffffff; font-size: 12px; font-weight: 800; padding: 14px 4px; border: 1px solid rgba(255,255,255,0.15); text-transform: uppercase; letter-spacing: 0.5px; overflow: hidden; }
        td { border: 1px solid var(--border); text-align: center; padding: 10px 2px; vertical-align: middle; background: #fff; transition: background 0.2s; overflow: hidden; }
        tr:hover td { background: #fafbfc; }
        
        .date-cell { font-size: 11px; font-weight: 800; color: var(--primary); background: #f4f6f9; width: 16%; line-height: 1.4; border-right: 2px solid var(--border); }
        .pana { font-size: 10px; color: #6c757d; display: block; font-weight: 600; line-height: 1.2; margin: 2px 0; }
        .jodi { font-size: 16px; font-weight: 900; color: var(--text); display: block; margin: 2px 0; line-height: 1.1; }
        .sat .jodi, .sun .jodi { color: var(--accent); }
        
        .footer { text-align: center; margin-top: 40px; padding: 20px; font-size: 12px; font-weight: 700; color: #adb5bd; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Fit entirely on mobile */
        @media (max-width: 600px) {
            body { padding: 8px 3px; }
            .header { margin-bottom: 12px; }
            .header h1 { font-size: 17px; line-height: 1.3; margin-bottom: 6px; }
            .header span { padding: 2px 6px; font-size: 11px; }
            .container { border-radius: 8px; }
            table { min-width: 0; width: 100%; }
            th { font-size: 7px; padding: 6px 1px; letter-spacing: 0; word-break: break-all; }
            td { padding: 4px 1px; }
            .date-cell { font-size: 7.5px; width: 19%; word-break: normal; }
            .jodi { font-size: 11.5px; }
            .pana { font-size: 7px; }
            .footer { margin-top: 15px; padding: 10px; font-size: 9px; }
        }
  <?php if(isset($_GET['type']) && $_GET['type'] === 'jodi') echo '.pana { display: none !important; }'; ?>
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <!-- LL_CHART_PAGE_V11 -->
    <header class="header">
        <h1><?= htmlspecialchars($marketName) ?> <span>Full Chart</span></h1>
    </header>

    <div class="container">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="date-cell">DATE</th>
                        <th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th><th>SUN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($weeks as $week): ?>
                    <tr>
                        <td class="date-cell"><?= fmt_range($week['start']) ?></td>
                        <?php for($d=1; $d<=7; $d++): 
                             $dayData = $week['days'][$d] ?? null;
                             $isSat = ($d===6);
                        ?>
                        <td>
                            <?php if($dayData): $p=$dayData['parts']; ?>
                            <div class="<?= $isSat?'sat':'' ?>">
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
        </div>
    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> SHREE JI MATKA - All Rights Reserved
    </div>
</body>
</html>