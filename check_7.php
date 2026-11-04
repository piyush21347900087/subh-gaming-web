<?php require "includes/db.php"; 
echo "Total Users: " . db()->query("SELECT COUNT(*) FROM users")->fetch_row()[0] . "\n";
echo "Live Users: " . db()->query("SELECT COUNT(*) FROM live WHERE status=\"online\"")->fetch_row()[0] . "\n";
echo "Deactive Users: " . db()->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetch_row()[0] . "\n";
echo "Pending Dep: " . db()->query("SELECT COUNT(*) FROM deposit WHERE status=\"Pending\"")->fetch_row()[0] . "\n";
echo "Pending WD: " . db()->query("SELECT COUNT(*) FROM withdrawal WHERE status=\"Pending\"")->fetch_row()[0] . "\n";
echo "Pending KYC: " . db()->query("SELECT COUNT(*) FROM bank_kyc WHERE status=\"Pending\"")->fetch_row()[0] . "\n";
?>
