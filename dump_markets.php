<?php require "includes/db.php"; $db = db();
$markets = [];
$res = $db->query("SELECT market_name FROM markets ORDER BY market_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $markets[] = strtoupper(trim($row["market_name"]));
    }
}
echo json_encode($markets);
?>
