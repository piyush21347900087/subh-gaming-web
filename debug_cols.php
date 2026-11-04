<?php
require_once 'includes/db.php';
$conn = db();
$res = $conn->query("DESC bid");
while($row = $res->fetch_assoc()) {
    echo "[" . $row['Field'] . "]\n";
}
