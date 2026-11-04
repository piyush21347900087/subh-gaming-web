<?php
$content = file_get_contents("bio_data.php");
$find = <<<'EOD'
                $stmtAdd->execute();
                $stmtAdd->close();

                $newBal = $currentBal + $walletAmt;
EOD;

$rep = <<<'EOD'
                $stmtAdd->execute();
                $stmtAdd->close();

                $newBal = $currentBal + $walletAmt + $bonus;
EOD;

$content = str_replace(str_replace("\r\n", "\n", $find), str_replace("\r\n", "\n", $rep), str_replace("\r\n", "\n", $content));
file_put_contents("bio_data.php", $content);
echo "Updated bio_data.php newBal\n";
