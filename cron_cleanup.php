<?php
/* ---------------------------------------------------------
   cron_cleanup.php
   ✅ Infinite Background loop for Auto-Rejecting Pending Intents
--------------------------------------------------------- */
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/db.php';
if (function_exists('db')) {
    $db = db();
} elseif (isset($db) && $db instanceof mysqli) {
    // already available
} else {
    require_once __DIR__ . '/config/db.php';
}

if (!($db instanceof mysqli)) {
    exit("DB connection failed\n");
}

while (true) {
    try {
        $expiryTime = date('Y-m-d H:i:s', time() - 60);
        $db->query("UPDATE deposit 
                    SET status='Failed' 
                    WHERE status='Pending' 
                    AND (
                         method LIKE '%Intent%' 
                         OR method LIKE '%IMB%' 
                         OR method LIKE '%Pay0%'
                         OR method LIKE '%Payo%'
                         OR method='UPI_INTENT'
                    )
                    AND date_time < '$expiryTime'");
    } catch (Throwable $e) {
        // silent retry
    }
    sleep(10);
}
