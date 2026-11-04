<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'db_connect.php'; // your DB connection

$res = $db->query("SELECT id, market, result_str, declared_at 
                   FROM market_result 
                   ORDER BY declared_at DESC LIMIT 1")->fetch_assoc();

echo json_encode([
  'id'      => $res['id'],
  'market'  => $res['market'],
  'result'  => $res['result_str'],
  'time'    => $res['declared_at']
]);
