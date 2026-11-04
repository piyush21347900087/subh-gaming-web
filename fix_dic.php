<?php
$content = file_get_contents("deposit_intent_create.php");

$find = <<<'EOD'
    $sql = "INSERT INTO deposit (".implode(",", $cols).") VALUES (".implode(",", $vals).")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$params);
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
    
    $cols[] = "bonus_amount";
    $vals[] = "?";
    $types .= "d";
    $params[] = $bonus;

    $sql = "INSERT INTO deposit (".implode(",", $cols).") VALUES (".implode(",", $vals).")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$params);
EOD;

$content = str_replace($find, $rep, $content);
file_put_contents("deposit_intent_create.php", $content);
echo "Updated deposit_intent_create.php\n";
