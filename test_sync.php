<?php require "includes/db.php"; $db = db(); 
$syncId = 1;
$sqlSync = "
UPDATE users u
SET wallet_balance = GREATEST(0.00, (
    COALESCE((SELECT SUM(amount) FROM deposit WHERE (user_id=u.user_id OR user_id=CAST(u.id AS CHAR)) AND LOWER(status)='done' AND (method IS NULL OR UPPER(method) <> 'REFUND')), 0)
    - COALESCE((SELECT SUM(amount) FROM withdrawal WHERE (user_id=u.user_id OR user_id=CAST(u.id AS CHAR)) AND LOWER(status)='done' AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) <> 'game_bid'))), 0)
    + COALESCE((SELECT SUM(wining_balance) FROM wining WHERE (user_id=u.user_id OR user_id=CAST(u.id AS CHAR))), 0)
    - COALESCE((SELECT SUM(amount) FROM bid b WHERE (b.user_id=u.user_id OR b.user_id=CAST(u.id AS CHAR)) ), 0)
))
WHERE id = $syncId
";
$res = $db->query($sqlSync);
if(!$res) { echo "ERROR: " . $db->error; } else { echo "SYNC OK"; }
?>
