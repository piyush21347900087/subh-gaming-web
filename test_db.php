<?php
// Diagnostic Test Script
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Database Status Test</title><style>body{background:#0b0f19;color:#e2e8f0;font-family:monospace;padding:30px;line-height:1.6}h2{color:#a78bfa}.ok{color:#4ade80}.err{color:#f87171}</style></head><body>';
echo '<h2>🔍 Subh Gaming - Database Connection Diagnostic</h2>';

require_once __DIR__ . '/includes/config.php';

echo "<b>Configured constants:</b><br>";
echo "DB_HOST: " . htmlspecialchars(DB_HOST) . "<br>";
echo "DB_USER: " . htmlspecialchars(DB_USER) . "<br>";
echo "DB_NAME: " . htmlspecialchars(DB_NAME) . "<br><hr>";

$hosts = array_unique([DB_HOST, '127.0.0.1', 'localhost']);
$creds = [
    ['subh_user', 'subh123', 'subh_gaming'],
    [DB_USER, DB_PASS, DB_NAME],
    ['root', '', 'subh_gaming'],
    ['root', '', '']
];

$connected = false;
foreach ($hosts as $h) {
    foreach ($creds as $c) {
        $m = @new mysqli($h, $c[0], $c[1], $c[2]);
        if ($m->connect_error) {
            echo "<span class='err'>❌ Connection failed to [$h | {$c[0]} | {$c[2]}]: " . htmlspecialchars($m->connect_error) . "</span><br>";
        } else {
            echo "<span class='ok'>✅ CONNECTED SUCCESSFULLY to [$h | {$c[0]} | {$c[2]}]</span><br>";
            $res = $m->query("SHOW TABLES");
            $tables = [];
            if ($res) {
                while ($r = $res->fetch_array()) { $tables[] = $r[0]; }
            }
            echo "<b>Total Tables Found:</b> " . count($tables) . "<br>";
            if (!empty($tables)) {
                echo "<b>Sample Tables:</b> " . htmlspecialchars(implode(', ', array_slice($tables, 0, 15))) . "...<br>";
            } else {
                echo "<span class='err'>⚠️ Connected to database, but 0 tables found! Schema needs import.</span><br>";
            }
            $connected = true;
            $m->close();
            break 2;
        }
    }
}

if (!$connected) {
    echo "<br><span class='err'>❌ All connection attempts failed. Check MariaDB process inside container.</span>";
} else {
    echo "<br><h3 class='ok'>🎉 Database is alive and ready! You can now access <a href='admin_login.php' style='color:#60a5fa'>admin_login.php</a></h3>";
}

echo '</body></html>';
