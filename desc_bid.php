<?php
require_once 'includes/db.php';
$conn = db();
$res = $conn->query("DESCRIBE bid");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
