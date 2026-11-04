<?php
$content = file_get_contents("upi_intent_callback.php");

$find = <<<'EOD'
    $stmt = $db->prepare("
        INSERT INTO deposit (user_id, amount, method, status, date_time, utr_number, remark)
        VALUES (?, ?, ?, ?, '" . date('Y-m-d H:i:s') . "', ?, ?)
    ");
    $stmt->bind_param('sdssss', $userId, $amount, $method, $statusDb, $txnId, $note);
EOD;

$rep = <<<'EOD'
    $bonus = 0;
    $bSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
    if ($bSet && $rowSet = $bSet->fetch_assoc()) {
        if ((int)$rowSet['bonus_enabled'] === 1) {
            $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
            if ($stmtRate) {
                $stmtRate->bind_param('d', $amount);
                $stmtRate->execute();
                $stmtRate->bind_result($bonusAmt);
                if ($stmtRate->fetch()) {
                    $bonus = (float)$bonusAmt;
                }
                $stmtRate->close();
            }
        }
    }

    $stmt = $db->prepare("
        INSERT INTO deposit (user_id, amount, method, status, date_time, utr_number, remark, bonus_amount)
        VALUES (?, ?, ?, ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)
    ");
    $stmt->bind_param('sdssssd', $userId, $amount, $method, $statusDb, $txnId, $note, $bonus);
EOD;

$content = str_replace(str_replace("\r\n", "\n", $find), str_replace("\r\n", "\n", $rep), str_replace("\r\n", "\n", $content));
file_put_contents("upi_intent_callback.php", $content);
echo "Updated upi_intent_callback.php fixed\n";
