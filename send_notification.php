<?php
// ---------------- SUBH GAMING Push ----------------
require_once 'includes/db.php';
$fcmFile = __DIR__ . '/includes/fcm_helper.php';
if (file_exists($fcmFile)) {
    require_once $fcmFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? 'SUBH GAMING';
    $message = $_POST['message'] ?? 'New update!';

    $payload = [
        "type" => "admin_notice",
        "title" => $title,
        "message" => $message,
        "icon" => "logo",
        "image" => "https://subhgaming.online/images/logo.png",
        "picture" => "https://subhgaming.online/images/logo.png"
    ];

    if (function_exists('fcm_v1_send_topic') && function_exists('google_access_token_from_sa_cached')) {
        $accessToken = google_access_token_from_sa_cached(__DIR__ . '/secure/service-account.json');
        if ($accessToken) {
            $response = fcm_v1_send_topic('kismat555-b686e', $accessToken, $payload, 'all_subhgaming');
            echo "<pre>FCM v1 Response:\n" . print_r($response, true) . "</pre>";
        } else {
            echo "<pre>FCM v1 Error: Access token failed</pre>";
        }
    } else {
        echo "<pre>FCM v1 Error: Helper functions missing</pre>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="utf-8">
    <title>Send Push • SUBH GAMING</title>
    <style>
        body {
            font-family: Arial;
            background: #f5f0e6;
            padding: 40px
        }

        .wrap {
            max-width: 460px;
            margin: auto;
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 6px 20px #0002
        }

        h2 {
            margin: 0 0 18px;
            text-align: center;
            color: #B00000
        }

        label {
            font-weight: 700;
            display: block;
            margin-top: 16px
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            font-size: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-top: 6px
        }

        button {
            margin-top: 22px;
            width: 100%;
            padding: 12px;
            background: #d60000;
            color: #fff;
            border: none;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer
        }

        button:hover {
            background: #a50000
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <div class="wrap">
        <h2>Send Push Notification</h2>
        <form method="post">
            <label>Title</label>
            <input name="title" required placeholder="e.g. Market Update">
            <label>Message</label>
            <textarea name="message" rows="4" required placeholder="e.g. Ruby market closed at 9 PM"></textarea>
            <button type="submit">Send to ALL</button>
        </form>
    </div>
</body>

</html>





