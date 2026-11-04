<?php
require_once __DIR__ . '/includes/security.php';
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (isset($_GET['apk']) && $_GET['apk'] === '1') {
    try {
        $db = db();
        $db->query("
            INSERT INTO downloads (id, download_count, created_at)
            VALUES (1, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                download_count = download_count + 1,
                created_at = NOW()
        ");
        $db->close();
    } catch (Exception $e) {}
    $file = __DIR__ . '/app/mahadevking.apk';
    if (!is_file($file)) {
        $file = __DIR__ . '/app/mahadevking.apk';
    }
    if (is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="mahadevking.apk"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        readfile($file);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'APK file not found.';
    }
    exit;
}

$markets = [];
try {
    $db = db();
    $res = $db->query("SELECT * FROM markets ORDER BY id ASC");
    $stmtLatest = $db->prepare("SELECT result_str FROM market_result WHERE market = ? ORDER BY result_date DESC LIMIT 1");
    while ($row = $res->fetch_assoc()) {
        $slug = $row['market'] ?? $row['market_name'] ?? '';
        $label = $row['market_name'] ?? $row['market'] ?? '';
        if ($slug === '' || $label === '') continue;
        $latestResult = '***-**-***';
        if ($stmtLatest) {
            $stmtLatest->bind_param('s', $label);
            $stmtLatest->execute();
            $stmtLatest->bind_result($r);
            if ($stmtLatest->fetch() && $r !== null && $r !== '') { $latestResult = $r; }
            $stmtLatest->free_result();
        }
        $markets[] = ['slug' => $slug, 'label' => $label, 'latest' => $latestResult];
    }
    if ($stmtLatest) $stmtLatest->close();
    $db->close();
} catch (Exception $e) { $markets = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Charts | SUBH GAMING</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --LL-red: #b71c1c; --LL-red-dark: #7f1111; --LL-gold: #f9b237; --LL-bg: #f5f0e5; --LL-text-main: #1f130d; --LL-text-muted: #6b5545; --LL-card: #ffffff; --LL-border-soft: #e5d5c4; --LL-footer-bg: #2d1811; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: #FFFFFF; color: var(--LL-text-main); min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        .navbar { position: sticky; top: 0; z-index: 100; background: #fff; border-bottom: 1px solid #eee; height: 70px; display: flex; align-items: center; }
        .nav-inner { max-width: 1100px; width: 100%; margin: 0 auto; padding: 0 16px; display: flex; justify-content: space-between; align-items: center; }
        .brand-logo img { height: 45px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-link { font-weight: 500; color: #666; text-decoration: none; }
        .nav-link.active { color: var(--LL-red); }
        .nav-download-btn { padding: 8px 20px; border-radius: 50px; background: linear-gradient(90deg, #0066cc, #0088ff); color: #fff; font-weight: 700; border: none; cursor: pointer; }
        .page-wrap { max-width: 1100px; margin: 0 auto; padding: 40px 16px; flex: 1; width: 100%; }
        .market-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
        .market-card { background: #fff; border-radius: 12px; padding: 15px; border: 1px solid #eee; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .market-result { font-weight: 700; color: var(--LL-red); }
        .chart-btn { padding: 6px 15px; border-radius: 50px; background: #c62828; color: #fff; text-decoration: none; font-size: 12px; font-weight: 700; }
        .footer { background: var(--LL-footer-bg); padding: 20px; color: #fff; text-align: center; font-size: 13px; }
        .scroll-top-btn { position: fixed; bottom: 30px; right: 25px; width: 48px; height: 48px; background: linear-gradient(135deg, #0066cc, #004d99); color: #fff; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 1000; }
        .scroll-top-btn.show { opacity: 1; visibility: visible; }
        
        /* --- Responsive Mobile View --- */
        @media (max-width: 768px) {
            .navbar { height: auto; padding: 12px 0; }
            .nav-inner { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .brand-logo { margin-bottom: 5px; }
            .nav-links { width: 100%; justify-content: center; flex-wrap: wrap; gap: 15px; font-size: 14px; }
            .nav-download-btn { width: 100%; max-width: 250px; text-align: center; margin-top: 5px; }
            .page-wrap { padding: 20px 12px; }
            .market-grid { grid-template-columns: 1fr; gap: 12px; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <header class="navbar"><div class="nav-inner"><a href="index.php" class="brand-logo"><img src="images/logo.png" alt="Logo"></a><nav class="nav-links"><a href="index.php" class="nav-link">Home</a><a href="charts.php" class="nav-link active">Charts</a><a href="privacy_policy.php" class="nav-link">Privacy Policy</a></nav><button class="nav-download-btn" onclick="window.location.href='charts.php?apk=1'">Download</button></div></header>
    <main class="page-wrap">
        <div class="market-grid">
            <?php foreach ($markets as $m): ?>
                <div class="market-card">
                    <div><div style="font-weight:600;"><?php echo htmlspecialchars(strtoupper($m['label'])); ?></div><div class="market-result"><?php echo htmlspecialchars($m['latest']); ?></div></div>
                    <a href="charts/<?php echo htmlspecialchars(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($m['slug'])), '_')); ?>_full_chart.php" class="chart-btn">Chart</a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    <footer class="footer">© <?php echo date("Y"); ?> SUBH GAMING</footer>
    <button class="scroll-top-btn" id="scrollTopBtn"><i class="fa-solid fa-chevron-up"></i></button>
    <script>
        var btn = document.getElementById("scrollTopBtn");
        window.addEventListener("scroll", function() { if (window.pageYOffset > 400) btn.classList.add("show"); else btn.classList.remove("show"); });
        btn.addEventListener("click", function() { window.scrollTo({ top: 0, behavior: "smooth" }); });
    </script>
</body>
</html>

