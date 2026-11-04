<?php
$content = file_get_contents("bio_data.php");

$find = <<<'EOD'
                $sqlIns = "INSERT INTO deposit (user_id, amount, method, status";
                if ($hasOrder) $sqlIns .= ", order_id";
                elseif ($hasTxn) $sqlIns .= ", txn_id";
                $sqlIns .= ", date_time) VALUES (?, ?, ?, 'Done'";
                if ($hasOrder || $hasTxn) {
                    $sqlIns .= ", ?";
                }
                $sqlIns .= ", ?)";

                $st = $db->prepare($sqlIns);
                if ($hasOrder || $hasTxn) {
                    $st->bind_param('sdsss', $uId, $amt, $method, $txn, $dt);
                } else {
                    $st->bind_param('sdss', $uId, $amt, $method, $dt);
                }
EOD;

$rep = <<<'EOD'
                $bonus = 0;
                $bSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
                if ($bSet && $rowSet = $bSet->fetch_assoc()) {
                    if ((int)$rowSet['bonus_enabled'] === 1) {
                        $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
                        if ($stmtRate) {
                            $stmtRate->bind_param('d', $amt);
                            $stmtRate->execute();
                            $stmtRate->bind_result($bonusAmt);
                            if ($stmtRate->fetch()) {
                                $bonus = (float)$bonusAmt;
                            }
                            $stmtRate->close();
                        }
                    }
                }

                $sqlIns = "INSERT INTO deposit (user_id, amount, method, status";
                if ($hasOrder) $sqlIns .= ", order_id";
                elseif ($hasTxn) $sqlIns .= ", txn_id";
                $sqlIns .= ", date_time, bonus_amount) VALUES (?, ?, ?, 'Done'";
                if ($hasOrder || $hasTxn) {
                    $sqlIns .= ", ?";
                }
                $sqlIns .= ", ?, ?)";

                $st = $db->prepare($sqlIns);
                if ($hasOrder || $hasTxn) {
                    $st->bind_param('sdsssd', $uId, $amt, $method, $txn, $dt, $bonus);
                } else {
                    $st->bind_param('sdssd', $uId, $amt, $method, $dt, $bonus);
                }
EOD;

$content = str_replace($find, $rep, $content);
file_put_contents("bio_data.php", $content);
echo "Updated bio_data.php\n";
