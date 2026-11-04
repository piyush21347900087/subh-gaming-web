<?php

// includes/db.php - Robust Database connection for SUBH GAMING



function db(): mysqli

{

    mysqli_report(MYSQLI_REPORT_OFF);



    // Defined constants fallback

    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {

        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if (!$db->connect_error) {

            $db->set_charset('utf8mb4');

            $db->query("SET time_zone = '+05:30'");

            return $db;

        }

    }



    // Credentials loaded from .env via config.php (no hardcoded secrets)

    require_once __DIR__ . '/config.php';
    $hosts = [DB_HOST, 'localhost', '127.0.0.1'];
    $credentials = [
        // Primary: from .env file

        [DB_USER, DB_PASS, DB_NAME],

        // Local dev fallbacks (no password)

        ['root', '', 'subh_gaming'],

        ['root', '', 'subhgaming'],

        ['root', '', 'u243882542_subh_gaming'],

        ['root', '', 'u243882542_mahadevking'],

    ];



    foreach ($hosts as $host) {

        foreach ($credentials as $cred) {

            $db = @new mysqli($host, $cred[0], $cred[1], $cred[2]);

            if (!$db->connect_error) {

                $db->set_charset('utf8mb4');

                $db->query("SET time_zone = '+05:30'");

                return $db;

            }

        }

    }



    // Dynamic database discovery for local/custom environments

    foreach ($hosts as $host) {

        $db = @new mysqli($host, 'root', '', '');

        if (!$db->connect_error) {

            $res = $db->query("SHOW DATABASES");

            if ($res) {

                while ($row = $res->fetch_array()) {

                    $dbname = $row[0];

                    if (

                        stripos($dbname, 'subh') !== false ||

                        stripos($dbname, 'subhgaming') !== false ||

                        stripos($dbname, 'mahadev') !== false ||

                        stripos($dbname, 'kismat') !== false ||

                        stripos($dbname, '555') !== false ||

                        stripos($dbname, 'SUBHGAMING') !== false ||

                        stripos($dbname, 'kalyan') !== false ||

                        stripos($dbname, 'shreeji') !== false ||

                        stripos($dbname, 'satta') !== false ||

                        stripos($dbname, 'ankraj') !== false ||

                        stripos($dbname, 'mtka') !== false ||

                        stripos($dbname, 'sara') !== false ||

                        stripos($dbname, 'royal') !== false ||

                        stripos($dbname, 'king') !== false

                    ) {

                        $db->select_db($dbname);

                        $db->set_charset('utf8mb4');

                        $db->query("SET time_zone = '+05:30'");

                        return $db;

                    }

                }

            }

        }

    }



    // Clean user-friendly fallback screen instead of unhandled fatal exception

    if (php_sapi_name() !== 'cli') {

        http_response_code(500);

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Subh Gaming - Database Connection</title>';

        echo '<style>body{background:#070b14;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box;}';

        echo '.box{background:#0f172a;border:2px solid #8b5cf6;padding:30px;border-radius:20px;max-width:500px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,0.5);}';

        echo 'h2{color:#a78bfa;margin-top:0;}p{color:#94a3b8;line-height:1.5;}code{background:#1e1b4b;color:#c4b5fd;padding:4px 8px;border-radius:6px;font-size:13px;display:inline-block;margin-top:10px;}</style><link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">

</head><body>';

        echo '<div class="box"><h2>Subh Gaming Database Notice</h2>';

        echo '<p>Unable to connect to MySQL database.</p>';

        echo '<p>Please import <code>subh_gaming_clean.sql</code> into your phpMyAdmin or check credentials in <code>includes/db.php</code>.</p>';

        echo '</div></body></html>';

        exit;

    }



    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    throw new Exception("Database connection failed. Please verify MySQL credentials.");

}



/**

 * Normalizes a user ID by adding an "SG" or "MK" prefix and padding for numeric-only inputs

 */

if (!function_exists('normalize_user_id')) {

    function normalize_user_id(string $uid): string

    {

        $u = strtoupper(trim($uid));

        // If numeric only (e.g. "123" -> "SG123")

        if (preg_match('/^\d+$/', $u)) {

            return "SG" . str_pad($u, 2, "0", STR_PAD_LEFT);

        }

        // If prefix + number (e.g. "SG1" -> "SG01" or "MK1" -> "MK01")

        if (preg_match('/^([A-Z]+)(\d+)$/', $u, $m)) {

            return $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);

        }

        return $u;

    }

}



/**

 * Calculates a user's Total Balance using EXACTLY the same formula as bio_data.php

 * (Deposit + Bonus + Winnings) - (Withdrawals + All Bids), floored at 0.

 * $id = users.id (primary key), $rawUserId = users.user_id (e.g. "MK252").

 * Also syncs users.wallet_balance for that exact users.id row.

 */

if (!function_exists('bio_total_balance')) {

    function bio_total_balance(mysqli $db, int $id, string $rawUserId, bool $syncWallet = true): float

    {

        $one = function (string $sql, string $types, array $params) use ($db): float {

            $st = $db->prepare($sql);

            if (!$st) return 0.0;

            $st->bind_param($types, ...$params);

            if (!$st->execute()) { $st->close(); return 0.0; }

            $r = $st->get_result();

            $row = $r ? $r->fetch_assoc() : null;

            $st->close();

            return (float) ($row['s'] ?? 0);

        };

        $idStr = (string) $id;



        $hasBonusCol = false;

        $chk = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");

        if ($chk && $chk->num_rows > 0) $hasBonusCol = true;



        // Deposits (same as bio_data.php)

        $sumDep = $one("SELECT IFNULL(SUM(amount),0) AS s FROM deposit WHERE user_id = ? AND LOWER(status) = 'done' AND method <> 'REFUND'", 's', [$rawUserId]);

        $sumBonus = $hasBonusCol

            ? $one("SELECT IFNULL(SUM(bonus_amount),0) AS s FROM deposit WHERE user_id = ? AND LOWER(status) = 'done' AND method <> 'REFUND'", 's', [$rawUserId])

            : 0.0;



        // Withdrawals

        $sumWithAll = $one("SELECT IFNULL(SUM(amount),0) AS s FROM withdrawal WHERE user_id = ? AND LOWER(status) IN ('pending','approved','done') AND method NOT LIKE '%bid%'", 's', [$rawUserId]);



        // Winnings

        $sumWin = $one("SELECT IFNULL(SUM(wining_balance),0) AS s FROM wining WHERE (user_id = ? OR user_id = ?)", 'ss', [$rawUserId, $idStr]);

        $paddedId = ($id > 0) ? ("DM" . str_pad($idStr, 5, "0", STR_PAD_LEFT)) : $rawUserId;

        foreach (['wining3', 'wining4'] as $t) {

            if (hasTable($db, $t)) {

                $sumWin += $one("SELECT IFNULL(SUM(wining_balance),0) AS s FROM `{$t}` WHERE (user_id = ? OR user_id = ? OR user_id = ?) AND LOWER(status)='win'", 'sss', [$rawUserId, $idStr, $paddedId]);

            }

        }



        // Bids

        $bidding = $one("SELECT IFNULL(SUM(amount),0) AS s FROM bid b WHERE (user_id = ? OR user_id = ?) AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))", 'ss', [$rawUserId, $idStr]);

        if (hasTable($db, 'gali_bids')) {

            $bidding += $one("SELECT IFNULL(SUM(amount),0) AS s FROM gali_bids g WHERE user_id IN (?, ?) AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))", 'ss', [$idStr, $rawUserId]);

        }

        foreach (['bid3', 'bid4'] as $t) {

            if (hasTable($db, $t)) {

                $bidding += $one("SELECT IFNULL(SUM(amount),0) AS s FROM `{$t}` WHERE (user_id = ? OR user_id = ?) AND COALESCE(is_cancelled, 0) = 0", 'ss', [$rawUserId, $idStr]);

            }

        }



        $total = max(0.00, ($sumDep + $sumBonus + $sumWin) - ($sumWithAll + $bidding));

        $total = round($total, 2);



        // Sync wallet_balance ONLY for this exact row (by primary key)

        if ($syncWallet && $id > 0) {

            $upd = $db->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");

            if ($upd) {

                $upd->bind_param('di', $total, $id);

                $upd->execute();

                $upd->close();

            }

        }

        return $total;

    }

}



/**

 * Fetches the total balance for a user code (resolves the users row, then uses bio_total_balance)

 */

if (!function_exists('get_total_balance')) {

    function get_total_balance(mysqli $db, string $userCode): float

    {

        $code = trim($userCode);

        $row = null;

        foreach (array_unique([$code, normalize_user_id($code)]) as $try) {

            $st = $db->prepare("SELECT id, user_id FROM users WHERE user_id = ? LIMIT 1");

            if ($st) {

                $st->bind_param('s', $try);

                $st->execute();

                $r = $st->get_result();

                $row = $r ? $r->fetch_assoc() : null;

                $st->close();

            }

            if ($row) break;

        }

        if (!$row) return 0.0;

        return bio_total_balance($db, (int) $row['id'], trim((string) $row['user_id']));

    }

}



/**

 * Logged-in user's wallet for USER-SIDE display.

 * Recalculates with the bio_data.php formula, saves into users.wallet_balance,

 * and returns that same value — so every user page shows exactly users.wallet_balance.

 */

if (!function_exists('current_user_wallet')) {

    function current_user_wallet($db = null): float

    {

        $uid = (int) ($_SESSION['user']['id'] ?? 0);

        if ($uid <= 0) return 0.0;



        $conn = null;

        try {

            if ($db instanceof mysqli && @$db->ping()) $conn = $db;

        } catch (Throwable $e) {

            $conn = null;

        }

        $own = false;

        if (!$conn) { $conn = db(); $own = true; }



        $total = 0.0;

        try {

            $st = $conn->prepare("SELECT user_id FROM users WHERE id = ? LIMIT 1");

            if ($st) {

                $st->bind_param('i', $uid);

                $st->execute();

                $r = $st->get_result();

                $row = $r ? $r->fetch_assoc() : null;

                $st->close();

                if ($row) {

                    $total = bio_total_balance($conn, $uid, trim((string) $row['user_id']));

                }

            }

        } catch (Throwable $e) {

        }

        if ($own) { try { $conn->close(); } catch (Throwable $e) {} }



        return $total;

    }

}



/**

 * Fetches the minimum bid amount

 */

if (!function_exists('getMinBid')) {

    function getMinBid(mysqli $db, string $marketName = ''): int

    {

        if ($marketName === '' && isset($_GET['market'])) {

            $marketName = trim($_GET['market']);

        }



        if ($marketName !== '') {

            try {

                $st = $db->prepare("SELECT min_bid FROM markets WHERE LOWER(market_name) = LOWER(?) LIMIT 1");

                if ($st) {

                    $st->bind_param('s', $marketName);

                    if ($st->execute()) {

                        $res = $st->get_result();

                        if ($res && $row = $res->fetch_assoc()) {

                            $st->close();

                            return (int) ($row['min_bid'] ?? 10);

                        }

                    }

                    $st->close();

                }

            } catch (Throwable $e) {

            }

        }



        try {

            $res = $db->query("SELECT min_bid FROM admin_payment_details WHERE id = 1 LIMIT 1");

            if ($res && $row = $res->fetch_assoc()) {

                return (int) ($row['min_bid'] ?? 10);

            }

        } catch (Throwable $e) {

        }

        return 10;

    }

}



/**

 * Checks if a table exists in the database

 */

if (!function_exists('hasTable')) {

    function hasTable(mysqli $db, string $table): bool

    {

        try {

            $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");

            return ($res && $res->num_rows > 0);

        } catch (Throwable $e) {

            return false;

        }

    }

}



/**

 * Fetches a single value from database query safely

 */

if (!function_exists('fetch_one_val')) {

    function fetch_one_val($db, $sql, $types, $params)

    {

        try {

            $stmt = $db->prepare($sql);

            if (!$stmt) return null;

            $stmt->bind_param($types, ...$params);

            $stmt->execute();

            $val = null;

            $stmt->bind_result($val);

            $out = $stmt->fetch() ? $val : null;

            $stmt->close();

            return $out;

        } catch (Throwable $e) {

            return null;

        }

    }

}





