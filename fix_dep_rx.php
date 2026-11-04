<?php
$f = "deposit.php";
$c = file_get_contents($f);
$c = preg_replace(
    '/(if \(\$st\) \{\s*\$st->bind_param\(\'sdsss\', \$userCode, \$amount, \$method, \$order_id, \$fileName\);\s*\})/',
    '$bAmt = get_bonus_amount($db, $amount);
        if (strtoupper($method) === \'CRYPTO\' && $hasUsdt) {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot_usdt, bonus_amount) VALUES (?, ?, \'Pending\', ?, \'" . date(\'Y-m-d H:i:s\') . "\', ?, ?, ?)";
        } else {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot, bonus_amount) VALUES (?, ?, \'Pending\', ?, \'" . date(\'Y-m-d H:i:s\') . "\', ?, ?, ?)";
        }
        
        $st = $db->prepare($sql);
        if ($st) {
            $st->bind_param(\'sdsssd\', $userCode, $amount, $method, $order_id, $fileName, $bAmt);',
    $c
);
file_put_contents($f, $c);
echo "deposit.php manual/crypto fixed\n";
