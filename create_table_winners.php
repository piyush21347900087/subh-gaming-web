<?php
require_once 'includes/db.php';
$db = db();

$sql = "CREATE TABLE IF NOT EXISTS recent_winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL,
    market_name VARCHAR(100) NOT NULL,
    win_amount DECIMAL(10, 2) NOT NULL,
    game_type VARCHAR(50) NOT NULL,
    session VARCHAR(50) DEFAULT NULL,
    is_fake TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql)) {
    echo "✅ SUCCESS: recent_winners table created successfully!\n";
} else {
    echo "❌ ERROR: " . $db->error . "\n";
}
unlink(__FILE__);
