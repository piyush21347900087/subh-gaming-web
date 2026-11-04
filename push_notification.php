<?php
declare(strict_types=1);

require_once 'includes/fcm_helper.php';

// --- Config
if (!defined('GOOGLE_SA_JSON')) {
    define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
}
if (!defined('FCM_PROJECT_ID')) {
    define('FCM_PROJECT_ID', 'kismat555-b686e');
}
@date_default_timezone_set('Asia/Kolkata');

// --- Inputs
$market = trim($_POST['market_name'] ?? $_GET['market_name'] ?? '');
$timeInput = trim($_POST['market_time'] ?? $_GET['market_time'] ?? '');
$topic = trim($_POST['topic'] ?? $_GET['topic'] ?? 'all_subhgaming');
$dryRun = strtolower(trim($_POST['dry_run'] ?? $_GET['dry_run'] ?? '')) === 'true';

// --- Show HTML form if inputs missing
if ($market === '' || $timeInput === '') {
  header('Content-Type: text/html; charset=utf-8'); ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SUBH GAMING Notification</title>
    <style>
      body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f6f6; text-align: center; margin-top: 80px; color: #222 }
      form { display: inline-block; background: #fff; padding: 26px 34px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, .12) }
      h3 { color: #c62828; margin: 8px 0 14px }
      .label { display: block; text-align: left; font-weight: 600; margin-top: 8px }
      input { padding: 10px; margin: 6px 0; width: 260px; border: 1px solid #ccc; border-radius: 6px }
      button { background: #c62828; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; cursor: pointer; margin-top: 10px }
      button:hover { background: #a31212 }
    </style>
  <link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
  <body>
    <h3>📢 Send Market Notification (SUBH GAMING)</h3>
    <form method="POST">
      <label class="label">Market Name</label>
      <input name="market_name" placeholder="e.g. Kalyan" required>
      <label class="label">Market Time</label>
      <input name="market_time" placeholder="e.g. 05:30 PM" required>
      <input type="hidden" name="topic" value="all_subhgaming">
      <button type="submit">Send</button>
    </form>
  </body>
  </html>
  <?php exit;
}

header('Content-Type: application/json; charset=utf-8');

$accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
if (!$accessToken) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to obtain FCM access token']);
    exit;
}

$notifBody = ($t = strtotime($timeInput)) !== false ? date('h:i A', $t) : $timeInput;

$data = [
    'type' => 'market_result',
    'title' => (string)$market,
    'message' => "Time: " . (string)$notifBody,
    'market' => (string)$market,
    'market_color' => '#0066cc',
    'notification_color' => '#0066cc',
    'title_html' => '<font color="#0066cc">' . htmlspecialchars((string)$market, ENT_QUOTES, 'UTF-8') . '</font>',
    'time' => (string)$notifBody,
    'icon' => 'ic_notification',
    'image' => 'https://subhgaming.online/images/logo.png',
    'picture' => 'https://subhgaming.online/images/logo.png',
    'uniq' => 'MANUAL:' . time()
];

$res = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $data, $topic);

echo json_encode([
    'status' => ($res['http'] === 200 ? 'success' : 'error'),
    'http_code' => $res['http'],
    'topic' => $topic,
    'title_sent' => $market,
    'body_sent' => $notifBody,
    'response' => json_decode($res['body'], true)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);








