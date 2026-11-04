<?php
require_once 'includes/db.php';
$conn = db();
$res = $conn->query("DESCRIBE markets");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
