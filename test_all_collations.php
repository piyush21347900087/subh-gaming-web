<?php require "includes/db.php"; $db = db(); 
$tables = ["users", "deposit", "withdrawal", "wining", "bid", "gali_bids", "bid3", "bid4", "wining3", "wining4"];
foreach($tables as $t) {
    $res = $db->query("SHOW FULL COLUMNS FROM $t LIKE 'user_id'");
    if($res) {
        $row = $res->fetch_assoc();
        echo "$t.user_id: " . $row["Collation"] . "\n";
    }
}
?>
