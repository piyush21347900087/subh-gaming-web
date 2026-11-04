<?php
$f = "buy.php";
$c = file_get_contents($f);
$c = preg_replace(
    '/(if \(\$st\) \{\s*\$st->bind_param\(\'sdsss\', \$userCode, \$amount, \$order_id, \$fileName\);\s*\})/',
    '$bonus = 0;
    $bSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
    if ($bSet && $rowSet = $bSet->fetch_assoc()) {
        if ((int)$rowSet[\'bonus_enabled\'] === 1) {
            $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
            if ($stmtRate) {
                $stmtRate->bind_param(\'d\', $amount);
                $stmtRate->execute();
                $stmtRate->bind_result($bonusAmt);
                if ($stmtRate->fetch()) {
                    $bonus = (float)$bonusAmt;
                }
                $stmtRate->close();
            }
        }
    }
    if ($hasUsdtCol) {
      $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot_usdt, bonus_amount) VALUES (?, ?, \'Pending\', \'CRYPTO\', \'" . date(\'Y-m-d H:i:s\') . "\', ?, ?, ?)";
    } else {
      $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot, bonus_amount) VALUES (?, ?, \'Pending\', \'CRYPTO\', \'" . date(\'Y-m-d H:i:s\') . "\', ?, ?, ?)";
    }
    $st = $db->prepare($sql);
    if ($st) {
      $st->bind_param(\'sdsssd\', $userCode, $amount, $order_id, $fileName, $bonus);',
    $c
);
file_put_contents($f, $c);
echo "buy.php fixed\n";
