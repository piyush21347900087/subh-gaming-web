<?php
$f = "deposit_intent_save.php";
$c = file_get_contents($f);
$c = preg_replace(
    '/INSERT INTO deposit\s*\(\s*user_id,\s*amount,\s*screenshot,\s*method,\s*status,\s*date_time\s*\)\s*VALUES\s*\(\?,\?,\?,\?,\?,\?\)/m',
    'INSERT INTO deposit
            (user_id, amount, screenshot, method, status, date_time, bonus_amount)
          VALUES (?,?,?,?,?,?,?)',
    $c
);
$c = preg_replace(
    '/\$ins->bind_param\(\s*\'sdssss\',\s*\$code,\s*\$amount,\s*\$fileName,\s*\$method,\s*\$status,\s*\$date\s*\);/m',
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
      $ins->bind_param(\'sdssssd\', $code, $amount, $fileName, $method, $status, $date, $bonus);',
    $c
);
file_put_contents($f, $c);
echo "deposit_intent_save fixed\n";
