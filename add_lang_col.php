<?php
require 'includes/db.php';
$db = db();

// Check if language column exists
$check = $db->query("SHOW COLUMNS FROM users LIKE 'language'");
if ($check->num_rows == 0) {
    // Add column
    $db->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'en' AFTER password");
    echo "Added language column.\n";
} else {
    echo "Language column already exists.\n";
}
