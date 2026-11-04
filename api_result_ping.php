<?php
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try{
  $db = db();

  $today = date('Y-m-d');
  $stmt = $db->prepare("SELECT IFNULL(MAX(declared_at),'') AS last_declared_at FROM market_result WHERE result_date = ?");
  $stmt->bind_param('s', $today);
  $stmt->execute();
  $stmt->bind_result($last);
  $stmt->fetch();
  $stmt->close();
  $db->close();

  echo json_encode([
    'ok' => true,
    'last_declared_at' => (string)$last
  ]);
}catch(Throwable $e){
  echo json_encode(['ok'=>false]);
}
