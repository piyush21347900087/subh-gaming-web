<?php
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

/* ===================== USER CODE & INFO ===================== */
function fetch_one_val($db, $sql, $types, $params)
{
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $val = null;
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? $val : null;
  $stmt->close();
  return $out;
}

$code = fetch_one_val($db, 'SELECT user_id FROM users WHERE id = ?', 'i', [$uid]) ?: '';
if (preg_match('/^\d+$/', $code)) {
  $code = "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
  $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

/* NOTICE FETCHING */
$notices = [];
$noticeFlagId = 0;
try {
  $flagFile = __DIR__ . '/notice_flag.txt';
  if (is_file($flagFile)) {
    $rawFlag = trim((string) @file_get_contents($flagFile));
    if (ctype_digit($rawFlag)) {
      $noticeFlagId = (int) $rawFlag;
    }
  }

  $check = $db->query("SHOW TABLES LIKE 'notices'");
  if ($check && $check->num_rows > 0) {
    $q = $db->query("SELECT * FROM notices ORDER BY id DESC");
    if ($q && $q->num_rows > 0) {
      while ($r = $q->fetch_assoc()) {
        $notices[] = $r;
      }
    }
  }
} catch (Throwable $e) {
}

if ($noticeFlagId === 0 && !empty($notices)) {
    $noticeFlagId = (int) $notices[0]['id'];
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title><?= __("All Notifications") ?> | SUBH GAMING</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --header-bg: #8b0000;
            --btn-red: #e53935;
            --text-dark: #333333;
            --text-gray: #666666;
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

        /* ── Header Standardized matching withdrawal_history.php ── */
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
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
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

        /* ── Main Container ── */
        .main-container {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
            padding: 20px 16px;
            min-height: calc(100vh - 65px);
        }

        /* ── Empty State Centered ── */
        .empty-state-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(80vh - 65px);
            text-align: center;
            padding: 20px;
        }

        .illustration-container {
            margin-bottom: 22px;
        }

        .empty-state-text {
            font-size: 17px;
            font-weight: 500;
            color: #94a3b8;
            letter-spacing: 0.1px;
        }

        /* ── Notice Card List (When notices exist) ── */
        .notice-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #eeeeee;
        }

        .notice-time {
            font-size: 12px;
            color: #888888;
            font-weight: 500;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notice-card-title {
            font-size: 17px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .notice-card-message {
            font-size: 14px;
            color: #444444;
            line-height: 1.6;
            background: #f9f9f9;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #e53935;
        }

        .notice-card-img {
            width: 100%;
            border-radius: 10px;
            margin-top: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            cursor: pointer;
        }

        .notice-card-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            background: #e53935;
            color: #ffffff;
            padding: 8px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        /* Lightbox */
        .lightbox-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.88);
            z-index: 5000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lightbox-modal.active { display: flex; }
        .lightbox-content { max-width: 95%; max-height: 85vh; border-radius: 10px; }
        .lightbox-close {
            position: absolute; top: 20px; right: 20px; color: #fff;
            font-size: 24px; background: transparent; border: none; cursor: pointer;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <!-- Header identical to withdrawal_history.php -->
    <header>
        <div class="header-left">
            <a class="back-btn" href="javascript:history.back()" title="Back">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="header-title-flash">
                <img src="icons/gold_coin.svg" alt="coin">
                <?= __("All Notifications") ?>
                <img src="icons/gold_coin.svg" alt="coin">
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <?php if (!empty($notices)): ?>
            <?php foreach ($notices as $notice): ?>
                <div class="notice-card">
                    <div class="notice-time">
                        <i class="fa-regular fa-clock"></i>
                        <?= date("d M Y, h:i A", strtotime($notice['created_at'])) ?>
                    </div>

                    <div class="notice-card-title">
                        <?= htmlspecialchars($notice['title']) ?>
                    </div>

                    <?php if (!empty($notice['message'])): ?>
                        <div class="notice-card-message">
                            <?= nl2br(htmlspecialchars($notice['message'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array(($notice['type'] ?? ''), ['image', 'text_image']) && !empty($notice['image'])): ?>
                        <img src="<?= htmlspecialchars($notice['image']) ?>" alt="Notice" class="notice-card-img" onclick="openLightbox('<?= htmlspecialchars($notice['image'], ENT_QUOTES) ?>')">
                    <?php endif; ?>

                    <?php
                    $linkUrl = trim($notice['link_url'] ?? '');
                    $linkLbl = trim($notice['link_label'] ?? '');
                    if ($linkLbl === '') $linkLbl = 'Open Link';
                    ?>

                    <?php if ($linkUrl !== ''): ?>
                        <a class="notice-card-link" href="<?= htmlspecialchars($linkUrl) ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-link"></i> <?= htmlspecialchars(__($linkLbl)) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Loading.svg Empty State -->
            <div class="empty-state-wrapper">
                <div class="illustration-container">
                    <img src="icons/loading.svg" alt="Loading" style="width: 50px; height: 50px; margin-bottom: 10px;">
                </div>
                <div class="empty-state-text">No Results Found!</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox Modal -->
    <div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox()">
        <button type="button" class="lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
        <img src="" alt="Preview" class="lightbox-content" id="lightboxImg" onclick="event.stopPropagation()">
    </div>

    <script>
        function openLightbox(src) {
            var modal = document.getElementById('lightboxModal');
            var img = document.getElementById('lightboxImg');
            if (modal && img) {
                img.src = src;
                modal.classList.add('active');
            }
        }
        function closeLightbox() {
            var modal = document.getElementById('lightboxModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Mark notice as seen
        <?php if ($noticeFlagId > 0): ?>
            try {
                localStorage.setItem('last_notice_seen', '<?= (int) $noticeFlagId ?>');
            } catch (e) { }
        <?php endif; ?>
    </script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>

</html>
