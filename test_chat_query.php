<?php require "includes/db.php"; $db = db();
$uidNorm = "MK01";
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$chk = $db->prepare("SELECT 1 FROM users WHERE TRIM(BOTH ' ' FROM UPPER(user_id)) COLLATE utf8mb4_unicode_ci = ? LIMIT 1");
$chk->bind_param("s", $uidNorm);
$chk->execute();
$has = $chk->get_result()->fetch_row();
var_dump($has);
if($db->error) echo $db->error;
?>
