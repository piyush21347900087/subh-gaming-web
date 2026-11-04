<?php
// Returns latest bank_kyc and upi_kyc for a user_id as JSON
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';
$db = db();
$uid = $_GET['user_id'] ?? '';
$out = ['bank' => null, 'upi' => null];

if ($uid) {
  // Bank KYC: fetch latest
  $q = $db->prepare("SELECT bank_name,account_name,account_number,ifsc,branch,status FROM bank_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
  $q->bind_param('s', $uid);
  $q->execute();
  $q->bind_result($b1, $b2, $b3, $b4, $b5, $b6);
  if ($q->fetch())
    $out['bank'] = ['bank_name' => $b1, 'account_name' => $b2, 'account_number' => $b3, 'ifsc' => $b4, 'branch' => $b5, 'status' => $b6];
  $q->close();

  // UPI KYC: fetch latest
  $q = $db->prepare("SELECT upi_id,qr_image,status FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
  $q->bind_param('s', $uid);
  $q->execute();
  $q->bind_result($u1, $u2, $u3);
  if ($q->fetch())
    $out['upi'] = ['upi_id' => $u1, 'qr_image' => $u2, 'status' => $u3];
  $q->close();
}

echo json_encode($out);
$db->close();

