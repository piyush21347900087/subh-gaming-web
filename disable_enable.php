<?php
/* ----------------------------------------------------------
   disable_enable.php – central timing + holiday helper (Pure Dynamic)
   • Market name + timings sab markets table se aayenge
   • custom_close_text sirf message ke liye — akela close nahi karega
----------------------------------------------------------*/
date_default_timezone_set('Asia/Kolkata');

/* --------- 1) Current IST time “HH:MM” --------- */
$timeNow = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))
    ->format('H:i');

/* --------- Default flags --------- */
$openDisabled = false;
$closeDisabled = false;
$pageDisabled = false;
$openHideStyle = '';
$closeHideStyle = '';
$pageLockScript = '';

$jodiDisabled = false;
$jodiHideStyle = '';
$jodiLockScript = '';

$isMarketClosedToday = false;
$marketCloseReason = '';

$currentOpenCut = null;
$currentCloseCut = null;
$currentPageCut = null;

/* small helper */
function cutTimeOrNull(?string $t): ?string
{
    if (!$t)
        return null;
    $t = trim($t);
    if ($t === '00:00:00' || $t === '00:00')
        return null;
    return substr($t, 0, 5);
}

/* --------- 2) Market resolve --------- */
$marketFromCaller = $market ?? null;
$marketGet = $_GET['market'] ?? '';
$marketNameRaw = trim($marketFromCaller ?: $marketGet);

$marketRow = null;

/* --------- 3) Fetch from DB --------- */
try {
    require_once __DIR__ . '/includes/db.php';
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @db();

    if (!$db->connect_error) {
        $db->set_charset('utf8mb4');

        /* 3a) If a market name was requested */
        if ($marketNameRaw !== '') {
            $sql = "SELECT market_name, open_time, close_time,
                           today_closed, saturday_closed, sunday_closed,
                           custom_close_text
                    FROM markets
                    WHERE UPPER(market_name)=UPPER(?) AND is_published=1
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('s', $marketNameRaw);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $marketRow = $row;
            }
            $stmt->close();
        }

        /* 3b) If not found → take first published */
        if ($marketRow === null) {
            $sql2 = "SELECT market_name, open_time, close_time,
                            today_closed, saturday_closed, sunday_closed,
                            custom_close_text
                     FROM markets
                     WHERE is_published=1
                     ORDER BY sort_order, market_name
                     LIMIT 1";
            $res2 = $db->query($sql2);
            if ($row2 = $res2->fetch_assoc()) {
                $marketRow = $row2;
            }
            $res2->free();
        }

        // $db->close();
    }

} catch (Throwable $e) {
    // ignore
}

/* --------- 4) Apply timings & rules --------- */
if ($marketRow !== null) {

    $market = strtoupper($marketRow['market_name']);
    $oCut = cutTimeOrNull($marketRow['open_time']);
    $cCut = cutTimeOrNull($marketRow['close_time']);

    /* If missing → keep market fully open */
    if (!$oCut && !$cCut) {
        $oCut = '00:00';
        $cCut = '23:59';
    } elseif (!$oCut) {
        $oCut = '00:00';
    } elseif (!$cCut) {
        $cCut = '23:59';
    }

    $pCut = $cCut;

    $currentOpenCut = $oCut;
    $currentCloseCut = $cCut;
    $currentPageCut = $pCut;

    /* Holiday logic — FIXED (custom_close_text akela close nahi karega) */
    $dayName = date('D');

    if (!empty($marketRow['today_closed'])) {
        $isMarketClosedToday = true;
        $marketCloseReason = $marketRow['custom_close_text'] ?: 'Today Market Closed';

    } elseif ($dayName === 'Sat' && !empty($marketRow['saturday_closed'])) {
        $isMarketClosedToday = true;
        $marketCloseReason = $marketRow['custom_close_text'] ?: 'Saturday Market Closed';

    } elseif ($dayName === 'Sun' && !empty($marketRow['sunday_closed'])) {
        $isMarketClosedToday = true;
        $marketCloseReason = $marketRow['custom_close_text'] ?: 'Sunday Market Closed';
    }

    /* If holiday → lock everything */
    if ($isMarketClosedToday) {

        $openDisabled = true;
        $closeDisabled = true;
        $pageDisabled = true;
        $jodiDisabled = true;

        $openHideStyle = 'display:none';
        $closeHideStyle = 'display:none';
        $jodiHideStyle = 'display:none';

        $pageLockScript = 'disabled';
        $jodiLockScript = 'disabled';

    } else {

        /* Normal timing logic */
        if ($timeNow >= $oCut) {
            $openDisabled = true;
            $openHideStyle = 'display:none';

            $jodiDisabled = true;
            $jodiHideStyle = 'display:none';
            $jodiLockScript = 'disabled';
        }

        if ($timeNow >= $cCut) {
            $closeDisabled = true;
            $closeHideStyle = 'display:none';
        }

        if ($timeNow >= $pCut) {
            $pageDisabled = true;
            $pageLockScript = 'disabled';
        }
    }

} else {
    $market = 'MARKET';
}
