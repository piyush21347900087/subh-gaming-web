<?php
require_once 'includes/lang.php';
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

date_default_timezone_set('Asia/Kolkata');

if (empty($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
        $msg = __('All fields are required.');
        $msgType = 'error';
    } elseif ($newPass !== $confirmPass) {
        $msg = __('New Password and Confirm New Password do not match.');
        $msgType = 'error';
    } elseif (strlen($newPass) < 4) {
        $msg = __('Password must be at least 4 characters.');
        $msgType = 'error';
    } else {
        // Fetch current password from DB
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->bind_result($dbPassword);
        $stmt->fetch();
        $stmt->close();

        // Verify current password (supports plain text or hashed password)
        $isCurrentValid = false;
        if ($dbPassword === $currentPass) {
            $isCurrentValid = true;
        } elseif (function_exists('password_verify') && password_verify($currentPass, $dbPassword)) {
            $isCurrentValid = true;
        }

        if (!$isCurrentValid) {
            $msg = __('Current Password is incorrect.');
            $msgType = 'error';
        } else {
            // Update password in DB
            $upStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upStmt->bind_param("si", $newPass, $uid);
            if ($upStmt->execute()) {
                $msg = __('Password updated successfully!');
                $msgType = 'success';
            } else {
                $msg = __('Failed to update password. Please try again.');
                $msgType = 'error';
            }
            $upStmt->close();
        }
    }
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="theme-color" content="#000000">
    <title><?= __("Change Password") ?> | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --header-bg: #000000;
            --btn-red: #e53935;
            --back-red: #e53935;
            --text-dark: #ffffff;
            --input-bg: #f8f9fb;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Header Standardized ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
            border-bottom: 1px solid #1a1a1a;
            padding-top: calc(env(safe-area-inset-top) + 12px) !important;
            padding-bottom: 12px !important;
            padding-left: 14px !important;
            padding-right: 14px !important;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
            border: none;
            flex-shrink: 0;
            transition: opacity 0.15s ease;
        }

        .back-btn:active {
            opacity: 0.8;
        }

        .header-title-flash {
            color: #ffffff !important;
            font-size: 19px !important;
            font-weight: 800 !important;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }

        .header-title-flash img {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }

        /* ── Main Container & White Card Box ── */
        .content {
            max-width: 500px;
            margin: 0 auto;
            padding: 24px 18px 40px;
        }

        .card-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #e53935;
            border-top: 1px solid #eeeeee;
            border-right: 1px solid #eeeeee;
            border-bottom: 1px solid #eeeeee;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background-color: #f8f9fb;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            color: #111111;
            outline: none;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .form-input:focus {
            background-color: #ffffff;
            border-color: #e53935;
            box-shadow: 0 0 0 3px rgba(229, 57, 53, 0.12);
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background-color: #e53935;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 14px rgba(229, 57, 53, 0.3);
            transition: opacity 0.15s ease, transform 0.1s ease;
        }

        .submit-btn:active {
            transform: scale(0.98);
            opacity: 0.9;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <!-- Header -->
    <header>
        <a class="back-btn" href="javascript:history.back()" title="Back">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div class="header-title-flash">
            <img src="icons/gold_coin.svg" alt="coin">
            <?= __("Change Password") ?>
            <img src="icons/gold_coin.svg" alt="coin">
        </div>
    </header>

    <!-- Main Content -->
    <div class="content">
        <div class="card-box">
            <form method="POST" action="change_password.php" id="changePasswordForm">
                <div class="form-group">
                    <label class="form-label"><?= __("Current Password") ?></label>
                    <input type="password" name="current_password" class="form-input" placeholder="******" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __("New Password") ?></label>
                    <input type="password" name="new_password" class="form-input" placeholder="******" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __("Confirm New Password") ?></label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="******" required>
                </div>

                <button type="submit" class="submit-btn"><?= __("Submit") ?></button>
            </form>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
    <script>
        Swal.fire({
            icon: '<?= $msgType ?>',
            title: '<?= htmlspecialchars($msg, ENT_QUOTES) ?>',
            confirmButtonColor: '#e53935',
            confirmButtonText: 'OK'
        });
    </script>
    <?php endif; ?>

    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
