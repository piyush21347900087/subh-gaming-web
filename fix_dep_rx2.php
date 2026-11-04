<?php
$f = "deposit.php";
$c = file_get_contents($f);
$c = preg_replace(
    '/(\$sql = "INSERT INTO deposit \(\$uCol, amount, status, method, date_time, order_id\) VALUES \(\?, \?, \'Pending\', \'UPI Intent\', \'".*\', \?\)";\s*\$st = \$db->prepare\(\$sql\);\s*if \(\$st\) \{\s*\$st->bind_param\(\'sds\', \$userCode, \$amount, \$order_id\);)/',
    '$bAmt = get_bonus_amount($db, $amount);
                $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, bonus_amount) VALUES (?, ?, \'Pending\', \'UPI Intent\', \'" . date(\'Y-m-d H:i:s\') . "\', ?, ?)";
                $st = $db->prepare($sql);
                if ($st) {
                    $st->bind_param(\'sdsd\', $userCode, $amount, $order_id, $bAmt);',
    $c
);
file_put_contents($f, $c);
echo "deposit.php UPI Intent fixed\n";
