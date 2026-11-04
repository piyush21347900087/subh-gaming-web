<?php require "includes/db.php"; $db = db(); 
$uid = "MK01";
$hasBonusCol = true;

// APP FORMULA (ajax_wallet.php)
$resDep = $db->query("SELECT (IFNULL(SUM(amount),0) + IFNULL(SUM(bonus_amount),0)) AS dep_done FROM deposit WHERE status='Done' AND user_id='$uid'");
$depositDone = (float) $resDep->fetch_assoc()["dep_done"];
$resWdBal = $db->query("SELECT IFNULL(SUM(amount),0) AS wd_sum FROM withdrawal WHERE user_id='$uid' AND LOWER(status) IN ('pending','approved','done')");
$withdrawMinus = (float) $resWdBal->fetch_assoc()["wd_sum"];
$resWin = $db->query("SELECT IFNULL(SUM(wining_balance),0) as win_bal FROM wining WHERE user_id = '$uid'");
$winBalance = (float) $resWin->fetch_assoc()["win_bal"];
$appBal = $depositDone - $withdrawMinus + $winBalance;

// BIO_DATA FORMULA
$sumDep = $depositDone; // Same for simplicity
$resWdAll = $db->query("SELECT IFNULL(SUM(amount),0) AS s FROM withdrawal WHERE user_id = '$uid' AND LOWER(status) = 'done' AND method NOT LIKE '%bid%'");
$sumWithAll = (float) $resWdAll->fetch_assoc()["s"];
$resBid = $db->query("SELECT IFNULL(SUM(amount),0) AS b FROM bid WHERE user_id='$uid'");
$biddingAmount = (float) $resBid->fetch_assoc()["b"];
$bioBal = $sumDep - $sumWithAll - $biddingAmount + $winBalance;

echo "App Formula: $depositDone - $withdrawMinus + $winBalance = $appBal\n";
echo "Bio Formula: $sumDep - $sumWithAll - $biddingAmount + $winBalance = $bioBal\n";

?>
