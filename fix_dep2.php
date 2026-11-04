<?php
$content = file_get_contents("deposit.php");

$find1 = <<<'EOD'
                $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id) VALUES (?, ?, 'Pending', 'UPI Intent', '" . date('Y-m-d H:i:s') . "', ?)";
                $st = $db->prepare($sql);
                if ($st) {
                    $st->bind_param('sds', $userCode, $amount, $order_id);
EOD;

$rep1 = <<<'EOD'
                $bAmt = get_bonus_amount($db, $amount);
                $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, bonus_amount) VALUES (?, ?, 'Pending', 'UPI Intent', '" . date('Y-m-d H:i:s') . "', ?, ?)";
                $st = $db->prepare($sql);
                if ($st) {
                    $st->bind_param('sdsd', $userCode, $amount, $order_id, $bAmt);
EOD;

$find2 = <<<'EOD'
        if (strtoupper($method) === 'CRYPTO' && $hasUsdt) {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot_usdt) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?)";
        } else {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?)";
        }
        
        $st = $db->prepare($sql);
        if ($st) {
            $st->bind_param('sdsss', $userCode, $amount, $method, $order_id, $fileName);
EOD;

$rep2 = <<<'EOD'
        $bAmt = get_bonus_amount($db, $amount);
        if (strtoupper($method) === 'CRYPTO' && $hasUsdt) {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot_usdt, bonus_amount) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)";
        } else {
             $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot, bonus_amount) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)";
        }
        
        $st = $db->prepare($sql);
        if ($st) {
            $st->bind_param('sdsssd', $userCode, $amount, $method, $order_id, $fileName, $bAmt);
EOD;

$content = str_replace(str_replace("\r\n", "\n", $find1), str_replace("\r\n", "\n", $rep1), str_replace("\r\n", "\n", $content));
$content = str_replace(str_replace("\r\n", "\n", $find2), str_replace("\r\n", "\n", $rep2), $content);

file_put_contents("deposit.php", $content);
echo "Updated deposit.php fixed\n";
