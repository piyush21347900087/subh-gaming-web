<?php
$content = file_get_contents("bio_data.php");

$find = <<<'EOD'
                $sqlIns = "INSERT INTO deposit (user_id, amount, method, status";
                if ($hasOrder) $sqlIns .= ", order_id";
                elseif ($hasTxn) $sqlIns .= ", txn_id";
                $sqlIns .= ", date_time) VALUES (?, ?, ?, 'Done'";
                if ($hasOrder || $hasTxn) $sqlIns .= ", ?";
                $sqlIns .= ", '" . date('Y-m-d H:i:s') . "')";

                $stmtAdd = $db->prepare($sqlIns);
                $oId = 'AD' . time() . mt_rand(100, 999);
                if ($hasOrder || $hasTxn) {
                    $stmtAdd->bind_param('sdss', $rawUserId, $walletAmt, $methodDbDeposit, $oId);
                } else {
                    $stmtAdd->bind_param('sds', $rawUserId, $walletAmt, $methodDbDeposit);
                }
                $stmtAdd->execute();
                $stmtAdd->close();
EOD;

$rep = <<<'EOD'
                $bonus = 0;
                $bSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
                if ($bSet && $rowSet = $bSet->fetch_assoc()) {
                    if ((int)$rowSet['bonus_enabled'] === 1) {
                        $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
                        if ($stmtRate) {
                            $stmtRate->bind_param('d', $walletAmt);
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
                if ($hasOrder || $hasTxn) $sqlIns .= ", ?";
                $sqlIns .= ", '" . date('Y-m-d H:i:s') . "', ?)";

                $stmtAdd = $db->prepare($sqlIns);
                $oId = 'AD' . time() . mt_rand(100, 999);
                if ($hasOrder || $hasTxn) {
                    $stmtAdd->bind_param('sdssd', $rawUserId, $walletAmt, $methodDbDeposit, $oId, $bonus);
                } else {
                    $stmtAdd->bind_param('sdsd', $rawUserId, $walletAmt, $methodDbDeposit, $bonus);
                }
                $stmtAdd->execute();
                $stmtAdd->close();
EOD;

$content = str_replace($find, $rep, $content);
file_put_contents("bio_data.php", $content);
echo "Updated bio_data.php properly\n";
