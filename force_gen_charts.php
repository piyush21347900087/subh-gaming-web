<?php
$markets = ['kalyan', 'kalyan_morning', 'kalyan_night', 'karnataka_day', 'karnataka_night', 'madhur_day', 'madhur_morning', 'madhur_night', 'main_bazar', 'milan_day', 'milan_morning', 'milan_night', 'rajdhani_day', 'rajdhani_night', 'sridevi', 'sridevi_night', 'supreme_day', 'supreme_night', 'time_bazar'];

$template = <<<'TPL'
<?php
require_once '../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$db = db();
if ($db->connect_error) die('DB error');
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

$marketName = 'MARKET_NAME_PLACEHOLDER';

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
<!-- LL_CHART_PAGE_V7 -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($marketName) ?> Chart | SUBH GAMING</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #D32F2F;
            --accent: #FFB300;
            --bg: #FFFFFF;
            --card-bg: #F8FAFC;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border: #E2E8F0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-main); overflow-x: hidden; }

        .navbar {
            position: fixed; top: 0; width: 100%; z-index: 2000;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--border);
            padding: 10px 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .nav-logo img { height: 40px; }
        .nav-center { display: flex; gap: 20px; }
        .nav-center a { color: var(--text-muted); text-decoration: none; font-weight: 700; font-size: 14px; }
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .btn-nav { padding: 8px 16px; border-radius: 8px; font-weight: 800; text-decoration: none; font-size: 13px; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-dl { background: var(--primary); color: #fff; }
        .btn-login { background: #f1f5f9; color: var(--text-main); }
        .btn-reg { background: var(--accent); color: #fff; }
        
        .burger { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 5px; }
        .burger span { width: 25px; height: 3px; background: var(--text-main); border-radius: 5px; transition: 0.3s; }

        .mobile-nav {
            position: fixed; top: 0; right: -100%; width: 280px; height: 100vh;
            background: #fff; z-index: 2500; padding: 80px 30px;
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
            transition: 0.4s ease;
            display: flex; flex-direction: column; gap: 20px;
        }
        .mobile-nav.open { right: 0; }
        .mobile-nav a { font-size: 18px; font-weight: 700; color: var(--text-main); text-decoration: none; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }

        .header-banner {
            margin-top: 70px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #fff;
            padding-bottom: 20px;
        }
        .model-full { width: 100%; max-width: 1200px; height: auto; display: block; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.1)); }

        .chart-section { padding: 30px 8px 70px; max-width: 1100px; margin: 0 auto; }
        .chart-card { background: #fff; border: 2px solid var(--primary); border-radius: 14px; overflow-x: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.06); }
        .chart-table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 0; }
        .chart-table th { background: #F1F5F9; color: var(--text-main); font-size: 9px; font-weight: 800; padding: 9px 2px; border-bottom: 2px solid var(--border); border-right: 1px solid var(--border); overflow: hidden; }
        .chart-table td { border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); text-align: center; padding: 7px 1px; vertical-align: middle; overflow: hidden; }
        .chart-table tr:nth-child(even) { background: #FDFDFD; }

        .date-cell { font-size: 9px; font-weight: 800; color: var(--primary); line-height: 1.2; width: 16%; }
        .pana { font-size: 9px; color: #64748B; display: block; font-weight: 500; line-height: 1.1; }
        .jodi { font-size: 14px; font-weight: 900; color: #000; display: block; margin: 1px 0; line-height: 1.1; }
        .sat .jodi { color: var(--primary); }

        footer { padding: 60px 24px; text-align: center; border-top: 1px solid var(--border); }
        .f-logo { height: 40px; margin-bottom: 24px; opacity: 0.9; }

        @media (max-width: 1000px) {
            .nav-center { display: none; }
            .burger { display: flex; }
            .btn-nav span { display: none; }
        }

        @media (max-width: 600px) {
            .navbar { padding: 10px 15px; }
            .nav-right { gap: 6px; }
            .header-banner { margin-top: 60px; }
            .chart-section { padding: 16px 3px 40px; }
            .chart-card { border-radius: 10px; }
            .chart-table th { font-size: 8px; padding: 7px 1px; }
            .chart-table td { padding: 6px 1px; }
            .pana { font-size: 7px; }
            .jodi { font-size: 11px; }
            .date-cell { font-size: 7px; width: 18%; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="nav-logo"><img src="../images/logo.png" alt="SUBH GAMING"></div>
            <div class="nav-center">
                <a href="../index.php">Home</a>
                <a href="../charts.php" class="active">Charts</a>
                <a href="../privacy_policy.php">Privacy</a>
            </div>
        </div>
        <div class="nav-right">
            <a href="../index.php?apk=1" class="btn-nav btn-dl"><i class="fas fa-download"></i><span>Download</span></a>
            <a href="../user_login.php" class="btn-nav btn-login"><i class="fas fa-sign-in-alt"></i><span>Login</span></a>
            <a href="../user_register.php" class="btn-nav btn-reg"><i class="fas fa-user-plus"></i><span>Register</span></a>
            <div class="burger" id="burger">
                <span></span><span></span><span></span>
            </div>
        </div>
    </nav>

    <div class="mobile-nav" id="mobileNav">
        <a href="../index.php">Home</a>
        <a href="../charts.php">Charts</a>
        <a href="../privacy_policy.php">Privacy Policy</a>
    </div>

    <div class="header-banner">
        <img src="../images/model.png" class="model-full" alt="Main Banner">
    </div>

    <header style="text-align:center; padding: 40px 24px 20px;">
        <h1 style="font-family:'Outfit', sans-serif; font-size:42px; font-weight:900;"><?= htmlspecialchars($marketName) ?> <span>Full Chart</span></h1>
    </header>

    <section class="chart-section">
        <div class="chart-card">
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>DATE</th>
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
    </section>

    <footer>
        <img src="../images/logo.png" class="f-logo" alt="SUBH GAMING">
        <p style="color:var(--text-muted); font-size:14px; font-weight:700;">&copy; 2026 SUBH GAMING Official. All Rights Reserved.</p>
    </footer>

    <script>
        var burger = document.getElementById('burger');
        var mobileNav = document.getElementById('mobileNav');
        burger.addEventListener('click', function() {
            burger.classList.toggle('active');
            mobileNav.classList.toggle('open');
            var spans = burger.querySelectorAll('span');
            if(burger.classList.contains('active')) {
                spans[0].style.transform = 'translateY(8px) rotate(45deg)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'translateY(-8px) rotate(-45deg)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });
    </script>
</body>
</html>
TPL;

foreach ($markets as $m) {
    $mName = strtoupper(str_replace('_', ' ', $m));
    $path = __DIR__ . "/charts/{$m}_full_chart.php";
    $content = str_replace('MARKET_NAME_PLACEHOLDER', $mName, $template);
    file_put_contents($path, $content);
    echo "Generated: $path\n";
}
