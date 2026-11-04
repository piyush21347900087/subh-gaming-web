<?php
require_once 'includes/db.php';
$db = db();
$sql = "CREATE TABLE IF NOT EXISTS matka_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sr VARCHAR(50),
    name VARCHAR(100),
    mobile_no VARCHAR(20),
    remark1 TEXT,
    remark2 TEXT,
    remark3 TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if ($db->query($sql)) {
    echo "Table 'matka_data' created successfully.\n";
} else {
    echo "Error: " . $db->error . "\n";
}
