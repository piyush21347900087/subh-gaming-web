<?php
require 'includes/db.php';
$db = db();

// Populate missing IP addresses from ad_tracking to user_fcm_tokens
$res = $db->query("SELECT r_value, ip_address FROM ad_tracking WHERE ip_address IS NOT NULL AND ip_address != '' GROUP BY r_value, ip_address");
while ($r = $res->fetch_assoc()) {
    $uid = $db->real_escape_string($r['r_value']);
    $ip = $db->real_escape_string($r['ip_address']);
    $db->query("UPDATE user_fcm_tokens SET ip_address='$ip' WHERE user_id='$uid' AND (ip_address IS NULL OR ip_address='')");
}
echo "IP addresses populated from ad_tracking.\n";

// Fetch city/state for IPs
$res = $db->query("SELECT id, ip_address FROM user_fcm_tokens WHERE ip_address IS NOT NULL AND ip_address != '' AND (city IS NULL OR city = '' OR city = '-')");
while ($r = $res->fetch_assoc()) {
    $id = $r['id'];
    $ip = $r['ip_address'];
    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,city,regionName';
    $raw = @file_get_contents($url);
    if ($raw) {
        $j = json_decode($raw, true);
        if (isset($j['status']) && $j['status'] === 'success') {
            $c = $db->real_escape_string($j['city'] ?? '-');
            $s = $db->real_escape_string($j['regionName'] ?? '-');
            $db->query("UPDATE user_fcm_tokens SET city='$c', state='$s' WHERE id=$id");
            echo "Updated ID $id ($ip) to $c, $s\n";
        }
    }
    usleep(500000); // 0.5s delay to avoid rate limit (ip-api allows 45/min)
}
echo 'Done';
?>
