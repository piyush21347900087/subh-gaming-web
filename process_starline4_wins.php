<?php
/**
 * process_starline4_wins.php
 * Core helper library for Starline 4 game.
 * Provides: schema setup, market seeding, market state, win processing.
 */

// ---------------------------------------------------------------------------
// 1. SCHEMA — Create all required tables if they don't exist
// ---------------------------------------------------------------------------
function starline4_ensure_schema(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS `markets4` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `market_name`      VARCHAR(100) NOT NULL UNIQUE,
        `open_time`        TIME NOT NULL DEFAULT '10:00:00',
        `close_time`       TIME NOT NULL DEFAULT '12:00:00',
        `is_published`     TINYINT(1) NOT NULL DEFAULT 1,
        `today_closed`     TINYINT(1) NOT NULL DEFAULT 0,
        `saturday_closed`  TINYINT(1) NOT NULL DEFAULT 0,
        `sunday_closed`    TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order`       INT NOT NULL DEFAULT 0,
        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS `bid4` (
        `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`         VARCHAR(30) NOT NULL,
        `market`          VARCHAR(100) NOT NULL,
        `close_time`      VARCHAR(20) NOT NULL DEFAULT '',
        `game_type`       VARCHAR(30) NOT NULL DEFAULT 'SINGLE_DIGIT',
        `single_digit`    VARCHAR(5)  DEFAULT NULL,
        `single_pana`     VARCHAR(5)  DEFAULT NULL,
        `double_pana`     VARCHAR(5)  DEFAULT NULL,
        `triple_pana`     VARCHAR(5)  DEFAULT NULL,
        `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
        `bid_time`        DATETIME NOT NULL,
        `win_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
        `result_declared` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_bid4_user`   (`user_id`),
        INDEX `idx_bid4_market` (`market`, `bid_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS `market_result4` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `market`      VARCHAR(100) NOT NULL,
        `result_date` DATE NOT NULL,
        `result_str`  VARCHAR(20) NOT NULL DEFAULT '***-*',
        `declared_at` DATETIME DEFAULT NULL,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_market_date` (`market`, `result_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS `starline4_conditions` (
        `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `market_name`       VARCHAR(100) NOT NULL UNIQUE,
        `condition_type`    ENUM('low_payout','profit_percentage','random','fixed') NOT NULL DEFAULT 'low_payout',
        `profit_percentage` INT NOT NULL DEFAULT 80,
        `fixed_value`       VARCHAR(20) NOT NULL DEFAULT '',
        `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ---------------------------------------------------------------------------
// 2. SEED — Insert default Starline markets (only if table is empty)
// ---------------------------------------------------------------------------
function starline4_seed_default_markets(mysqli $db): void
{
    $res = $db->query("SELECT COUNT(*) AS cnt FROM `markets4`");
    if (!$res) return;
    $row = $res->fetch_assoc();
    if ((int)($row['cnt'] ?? 0) > 0) return;

    $defaults = [
        ['TIME BAZAR',     '10:00:00', '11:30:00', 0],
        ['MILAN DAY',      '13:00:00', '14:30:00', 1],
        ['RAJDHANI DAY',   '15:00:00', '16:30:00', 2],
        ['KALYAN',         '17:30:00', '19:00:00', 3],
        ['MILAN NIGHT',    '20:30:00', '22:00:00', 4],
        ['RAJDHANI NIGHT', '21:00:00', '22:30:00', 5],
        ['MAIN BAZAR',     '21:30:00', '23:00:00', 6],
    ];

    $stmt = $db->prepare(
        "INSERT IGNORE INTO `markets4` (`market_name`,`open_time`,`close_time`,`sort_order`,`is_published`) VALUES (?,?,?,?,1)"
    );
    if (!$stmt) return;
    foreach ($defaults as [$name, $open, $close, $sort]) {
        $stmt->bind_param('sssi', $name, $open, $close, $sort);
        $stmt->execute();
    }
    $stmt->close();
}

// ---------------------------------------------------------------------------
// 3. MARKET STATE — Returns current open/closed state for a market
// ---------------------------------------------------------------------------
function starline4_market_state(mysqli $db, string $market, DateTimeImmutable $now): array
{
    $stmt = $db->prepare(
        "SELECT * FROM `markets4` WHERE UPPER(market_name)=UPPER(?) AND is_published=1 LIMIT 1"
    );
    if (!$stmt) return ['ok' => false, 'page_disabled' => true, 'message' => 'DB error'];
    $stmt->bind_param('s', $market);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['ok' => false, 'page_disabled' => true, 'message' => 'Market not found'];

    $dow = (int)$now->format('N');
    $isHoliday = !empty($row['today_closed'])
              || ($dow === 6 && !empty($row['saturday_closed']))
              || ($dow === 7 && !empty($row['sunday_closed']));

    if ($isHoliday) {
        return ['ok' => false, 'page_disabled' => true, 'message' => 'Market is closed today (Holiday)', 'now_sql' => $now->format('Y-m-d H:i:s')];
    }

    $nowTime   = $now->format('H:i:s');
    $closeTime = $row['close_time'];

    if ($nowTime >= $closeTime) {
        return ['ok' => false, 'page_disabled' => false, 'message' => 'Market is closed for today.', 'now_sql' => $now->format('Y-m-d H:i:s')];
    }

    return ['ok' => true, 'page_disabled' => false, 'message' => '', 'now_sql' => $now->format('Y-m-d H:i:s')];
}

// ---------------------------------------------------------------------------
// 4. WIN PROCESSING — Declare result and credit winners
// ---------------------------------------------------------------------------
function starline4_pana_digit(string $pana): string
{
    if (!preg_match('/^\d{3}$/', $pana)) return '*';
    $d = str_split($pana);
    return (string)(((int)$d[0] + (int)$d[1] + (int)$d[2]) % 10);
}

function starline4_pana_type(string $pana): string
{
    if (!preg_match('/^\d{3}$/', $pana)) return 'unknown';
    $s = str_split($pana); sort($s);
    $u = count(array_unique($s));
    if ($u === 1) return 'triple_pana';
    if ($u === 2) return 'double_pana';
    return 'single_pana';
}

function starline4_payout(string $gameType): float
{
    return ['SINGLE_DIGIT' => 10.0, 'SINGLE_PANA' => 160.0, 'DOUBLE_PANA' => 320.0, 'TRIPLE_PANA' => 900.0][$gameType] ?? 10.0;
}

function starline4_get_all_panas(): array
{
    static $allPanas = null;
    if ($allPanas !== null) return $allPanas;

    $res = [];
    for ($d1 = 0; $d1 <= 9; $d1++) {
        for ($d2 = 0; $d2 <= 9; $d2++) {
            for ($d3 = 0; $d3 <= 9; $d3++) {
                // In Matka pana, digits are in non-decreasing order where 0 is treated as 10 (highest)
                $v1 = ($d1 === 0) ? 10 : $d1;
                $v2 = ($d2 === 0) ? 10 : $d2;
                $v3 = ($d3 === 0) ? 10 : $d3;
                if ($v1 <= $v2 && $v2 <= $v3) {
                    $res[] = "{$d1}{$d2}{$d3}";
                }
            }
        }
    }
    $allPanas = $res;
    return $allPanas;
}

function starline4_calculate_payout_for_pana(array $bids, string $pana): float
{
    $digit = starline4_pana_digit($pana);
    $panaType = starline4_pana_type($pana);
    $totalPayout = 0.0;

    foreach ($bids as $bid) {
        $won = false;
        $winAmount = 0.0;
        $gt = strtoupper($bid['game_type']);

        if ($gt === 'SINGLE_DIGIT' && (string)$bid['single_digit'] === $digit) {
            $won = true;
            $winAmount = (float)$bid['amount'] * starline4_payout('SINGLE_DIGIT');
        } elseif ($gt === 'SINGLE_PANA' && (string)$bid['single_pana'] === $pana && $panaType === 'single_pana') {
            $won = true;
            $winAmount = (float)$bid['amount'] * starline4_payout('SINGLE_PANA');
        } elseif ($gt === 'DOUBLE_PANA' && (string)$bid['double_pana'] === $pana && $panaType === 'double_pana') {
            $won = true;
            $winAmount = (float)$bid['amount'] * starline4_payout('DOUBLE_PANA');
        } elseif ($gt === 'TRIPLE_PANA' && (string)$bid['triple_pana'] === $pana && $panaType === 'triple_pana') {
            $won = true;
            $winAmount = (float)$bid['amount'] * starline4_payout('TRIPLE_PANA');
        }

        if ($won) {
            $totalPayout += $winAmount;
        }
    }

    return $totalPayout;
}

function starline4_pick_best_result(mysqli $db, string $market, string $date): string
{
    $allPanas = starline4_get_all_panas();

    // 1. Fetch condition for this market or ALL_MARKETS
    $cond = null;
    $stmtC = $db->prepare("SELECT * FROM starline4_conditions WHERE (UPPER(market_name)=UPPER(?) OR market_name='ALL_MARKETS') AND status='active' ORDER BY (market_name != 'ALL_MARKETS') DESC LIMIT 1");
    if ($stmtC) {
        $stmtC->bind_param('s', $market);
        $stmtC->execute();
        $cond = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();
    }

    $condType = $cond['condition_type'] ?? 'low_payout';
    $profitPct = (int)($cond['profit_percentage'] ?? 80);
    $fixedVal = trim((string)($cond['fixed_value'] ?? ''));

    // Handle fixed result
    if ($condType === 'fixed' && $fixedVal !== '') {
        $cleanFixed = preg_replace('/\D/', '', explode('-', $fixedVal)[0]);
        if (strlen($cleanFixed) === 3 && in_array($cleanFixed, $allPanas, true)) {
            return $cleanFixed;
        }
    }

    // Handle random pana
    if ($condType === 'random') {
        return $allPanas[array_rand($allPanas)];
    }

    // Fetch all bids for this market & date
    $stmtB = $db->prepare("SELECT game_type, single_digit, single_pana, double_pana, triple_pana, amount FROM `bid4` WHERE UPPER(market)=UPPER(?) AND DATE(bid_time)=?");
    $bids = [];
    $totalBetAmount = 0.0;
    if ($stmtB) {
        $stmtB->bind_param('ss', $market, $date);
        $stmtB->execute();
        $bids = $stmtB->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtB->close();
    }

    foreach ($bids as $b) {
        $totalBetAmount += (float)$b['amount'];
    }

    // If no bids placed, return a random single pana
    if (empty($bids) || $totalBetAmount <= 0) {
        return $allPanas[array_rand($allPanas)];
    }

    // Calculate payout for each pana
    $panaPayouts = [];
    foreach ($allPanas as $p) {
        $panaPayouts[$p] = starline4_calculate_payout_for_pana($bids, $p);
    }

    // 2. Low Payout: pick candidate with lowest payout to users (maximum profit for admin)
    if ($condType === 'low_payout') {
        $minPayout = min($panaPayouts);
        $bestCandidates = [];
        foreach ($panaPayouts as $p => $payout) {
            if ($payout <= $minPayout + 0.001) {
                $bestCandidates[] = $p;
            }
        }
        return $bestCandidates[array_rand($bestCandidates)];
    }

    // 3. Profit Percentage: keep payout <= (100 - profit_percentage)% of totalBetAmount
    if ($condType === 'profit_percentage') {
        $maxAllowedPayout = $totalBetAmount * ((100 - $profitPct) / 100.0);
        $candidates = [];
        foreach ($panaPayouts as $p => $payout) {
            if ($payout <= $maxAllowedPayout) {
                $candidates[$p] = $payout;
            }
        }

        if (!empty($candidates)) {
            // Sort by highest payout within allowed limit (fair distribution)
            arsort($candidates);
            $topPayout = reset($candidates);
            $bestCandidates = [];
            foreach ($candidates as $p => $payout) {
                if ($payout >= $topPayout - 0.001) {
                    $bestCandidates[] = $p;
                }
            }
            return $bestCandidates[array_rand($bestCandidates)];
        } else {
            // Fallback to lowest payout if no pana matches target profit
            $minPayout = min($panaPayouts);
            $bestCandidates = [];
            foreach ($panaPayouts as $p => $payout) {
                if ($payout <= $minPayout + 0.001) {
                    $bestCandidates[] = $p;
                }
            }
            return $bestCandidates[array_rand($bestCandidates)];
        }
    }

    return $allPanas[array_rand($allPanas)];
}

function starline4_send_result_notification(mysqli $db, string $market, string $resultStr, string $resultDate): void
{
    $fcmHelper = __DIR__ . '/includes/fcm_helper.php';
    if (!file_exists($fcmHelper)) return;
    require_once $fcmHelper;

    if (!function_exists('fcm_v1_send_topic') || !function_exists('google_access_token_from_sa_cached')) return;

    $saPath = __DIR__ . '/secure/service-account.json';
    if (!file_exists($saPath)) return;

    $token = google_access_token_from_sa_cached($saPath);
    if (!$token) return;

    $topic = 'all_subhgaming';
    $data = [
        'type'        => 'starline_result',
        'market'      => $market,
        'result'      => $resultStr,
        'result_date' => $resultDate,
        'title'       => "Starline Result: $market",
        'message'     => "$market Result: $resultStr",
        'uniq'        => "STARLINE4_{$market}_{$resultDate}"
    ];

    try {
        fcm_v1_send_topic(FCM_PROJECT_ID, $token, $data, $topic);
    } catch (Throwable $e) {}
}

function starline4_auto_generate_results(mysqli $db): array
{
    $today = date('Y-m-d');
    $todayDow = (int)date('N');
    $curTime = date('H:i:s');
    $declaredCount = 0;
    $declaredMarkets = [];

    $resM = $db->query("SELECT * FROM `markets4` WHERE is_published=1 ORDER BY close_time ASC, id ASC");
    if (!$resM) return ['ok' => false, 'count' => 0, 'declared' => []];

    while ($m = $resM->fetch_assoc()) {
        $mName = $m['market_name'];
        $closeT = date('H:i:s', strtotime($m['close_time'] ?? '00:00:00'));

        $isHoliday = !empty($m['today_closed'])
                  || ($todayDow === 6 && !empty($m['saturday_closed']))
                  || ($todayDow === 7 && !empty($m['sunday_closed']));

        if ($isHoliday) continue;

        // Auto declare when current time is past or equal to close time
        if ($curTime >= $closeT) {
            // Check if already declared for today
            $stmtC = $db->prepare("SELECT id, result_str FROM `market_result4` WHERE UPPER(market)=UPPER(?) AND result_date=? LIMIT 1");
            $hasResult = false;
            if ($stmtC) {
                $stmtC->bind_param('ss', $mName, $today);
                $stmtC->execute();
                $resRow = $stmtC->get_result()->fetch_assoc();
                $stmtC->close();
                if (!empty($resRow['result_str']) && $resRow['result_str'] !== '***-*') {
                    $hasResult = true;
                }
            }

            if (!$hasResult) {
                $bestPana = starline4_pick_best_result($db, $mName, $today);
                $dec = starline4_declare_result($db, $mName, $bestPana, $today);
                if (!empty($dec['ok'])) {
                    $digit = starline4_pana_digit($bestPana);
                    $resultStr = $bestPana . '-' . $digit;
                    starline4_send_result_notification($db, $mName, $resultStr, $today);
                    $declaredCount++;
                    $declaredMarkets[] = [
                        'market'  => $mName,
                        'result'  => $resultStr,
                        'winners' => $dec['winners'] ?? 0,
                        'paid'    => $dec['total_paid'] ?? 0.0
                    ];
                }
            }
        }
    }

    return ['ok' => true, 'count' => $declaredCount, 'declared' => $declaredMarkets];
}

function starline4_declare_result(mysqli $db, string $market, string $resultPana, string $resultDate = ''): array
{
    if ($resultDate === '') $resultDate = date('Y-m-d');
    $resultPana = preg_replace('/\D/', '', $resultPana);
    if (strlen($resultPana) !== 3)
        return ['ok' => false, 'message' => 'Invalid pana', 'winners' => 0, 'total_paid' => 0.0];

    $digit     = starline4_pana_digit($resultPana);
    $resultStr = $resultPana . '-' . $digit;
    $panaType  = starline4_pana_type($resultPana);

    $stmt = $db->prepare("INSERT INTO `market_result4` (`market`,`result_date`,`result_str`,`declared_at`) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE `result_str`=VALUES(`result_str`),`declared_at`=VALUES(`declared_at`)");
    if ($stmt) { $stmt->bind_param('sss', $market, $resultDate, $resultStr); $stmt->execute(); $stmt->close(); }

    $stmt = $db->prepare("SELECT id,user_id,game_type,single_digit,single_pana,double_pana,triple_pana,amount FROM `bid4` WHERE UPPER(market)=UPPER(?) AND DATE(bid_time)=? AND result_declared=0");
    if (!$stmt) return ['ok' => false, 'message' => 'DB error', 'winners' => 0, 'total_paid' => 0.0];
    $stmt->bind_param('ss', $market, $resultDate);
    $stmt->execute();
    $bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $winners = 0; $totalPaid = 0.0;

    foreach ($bids as $bid) {
        $won = false; $winAmount = 0.0;
        $gt  = strtoupper($bid['game_type']);
        if      ($gt === 'SINGLE_DIGIT' && (string)$bid['single_digit'] === $digit)                                   { $won = true; $winAmount = (float)$bid['amount'] * starline4_payout('SINGLE_DIGIT'); }
        elseif  ($gt === 'SINGLE_PANA'  && (string)$bid['single_pana']  === $resultPana && $panaType === 'single_pana') { $won = true; $winAmount = (float)$bid['amount'] * starline4_payout('SINGLE_PANA'); }
        elseif  ($gt === 'DOUBLE_PANA'  && (string)$bid['double_pana']  === $resultPana && $panaType === 'double_pana') { $won = true; $winAmount = (float)$bid['amount'] * starline4_payout('DOUBLE_PANA'); }
        elseif  ($gt === 'TRIPLE_PANA'  && (string)$bid['triple_pana']  === $resultPana && $panaType === 'triple_pana') { $won = true; $winAmount = (float)$bid['amount'] * starline4_payout('TRIPLE_PANA'); }

        if ($won && $winAmount > 0) {
            $ins = $db->prepare("INSERT INTO `wining` (`user_id`,`wining_balance`,`created_at`) VALUES (?,?,NOW())");
            if ($ins) { $ins->bind_param('sd', $bid['user_id'], $winAmount); $ins->execute(); $ins->close(); }
            $upd = $db->prepare("UPDATE `bid4` SET `win_amount`=?,`result_declared`=1 WHERE `id`=?");
            if ($upd) { $upd->bind_param('di', $winAmount, $bid['id']); $upd->execute(); $upd->close(); }
            $winners++; $totalPaid += $winAmount;
        } else {
            $upd = $db->prepare("UPDATE `bid4` SET `result_declared`=1 WHERE `id`=?");
            if ($upd) { $upd->bind_param('i', $bid['id']); $upd->execute(); $upd->close(); }
        }
    }

    return ['ok' => true, 'message' => "Result declared: $resultStr", 'winners' => $winners, 'total_paid' => $totalPaid];
}
