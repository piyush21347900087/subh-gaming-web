<?php
require_once 'includes/db.php';
// admin_notice.php – Single dynamic notice (create + edit) + LINK + NOTIFICATION
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

/* ================== FCM CONFIG & HELPERS ================== */
if (!defined('GOOGLE_SA_JSON')) {
    define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
}

require_once 'includes/fcm_helper.php';

/* ================== END FCM HELPERS ================== */

$successMsg = '';
$errorMsg = '';
$current = null;
$clearFormAfterSave = false;

$hasImageColumn = false;
$hasLinkUrlCol = false;
$hasLinkLblCol = false;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = db();
  $db->query("CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(32) DEFAULT 'text_image',
    image VARCHAR(255),
    link_url VARCHAR(255),
    link_label VARCHAR(255),
    show_message TINYINT(1) DEFAULT 1,
    show_link_url TINYINT(1) DEFAULT 1,
    show_link_label TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");

  try { $db->query("ALTER TABLE notices MODIFY COLUMN type VARCHAR(32) DEFAULT 'text_image'"); } catch (Throwable $e) {}

  // Add missing columns if not present
  $cols = ['show_message', 'show_link_url', 'show_link_label'];
  foreach ($cols as $c) {
    if (!$db->query("SHOW COLUMNS FROM notices LIKE '$c'")->num_rows) {
      $db->query("ALTER TABLE notices ADD COLUMN $c TINYINT(1) DEFAULT 1");
    }
  }

  $hasImageColumn = $db->query("SHOW COLUMNS FROM notices LIKE 'image'")->num_rows > 0;
  $hasLinkUrlCol = $db->query("SHOW COLUMNS FROM notices LIKE 'link_url'")->num_rows > 0;
  $hasLinkLblCol = $db->query("SHOW COLUMNS FROM notices LIKE 'link_label'")->num_rows > 0;

  // Latest notice
  $res = $db->query("SELECT * FROM notices ORDER BY id DESC LIMIT 1");
  if ($res && $res->num_rows > 0) {
    $current = $res->fetch_assoc();
  }

  // Save/Update
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifyOn = true;

    $title = trim($_POST['title'] ?? '');
    $msg = trim($_POST['message'] ?? '');
    $rawType = trim($_POST['type'] ?? 'text_image');
    $type = in_array($rawType, ['text', 'image', 'text_image']) ? $rawType : 'text_image';
    $oldImg = trim($_POST['existing_image'] ?? '');

    $linkUrl = trim($_POST['link_url'] ?? '');
    $linkLabel = trim($_POST['link_label'] ?? '');

    $showMsg = isset($_POST['show_message']) ? 1 : 0;
    $showLink = isset($_POST['show_link_url']) ? 1 : 0;
    $showLbl = isset($_POST['show_link_label']) ? 1 : 0;

    if ($title === '') {
      throw new Exception('Title required');
    }

    if ($linkUrl !== '') {
      if (!preg_match('#^https?://#i', $linkUrl)) {
        throw new Exception('Link URL me http:// ya https:// hona chahiye.');
      }
    }

    if ($type === 'image') {
      $msg = '';
    }

    // Image upload logic
    $newImagePath = $oldImg;

    if ($type === 'text') {
      // If type is text (Title + Message), clear image
      $newImagePath = '';
    } elseif (($type === 'image' || $type === 'text_image') && $hasImageColumn) {
      if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
          throw new Exception('Sirf JPG, PNG, WEBP images allowed hain.');
        }

        $uploadDir = __DIR__ . '/uploads/notices/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0775, true);
        }

        $fileName = 'notice_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $fullPath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $fullPath)) {
          throw new Exception('Image upload failed.');
        }
        $newImagePath = 'uploads/notices/' . $fileName;
      } else {
        $newImagePath = $oldImg;
      }
    }

    // Insert/Update DB Logic (Always update ID 1 for single notice)
    $stmt = $db->prepare("INSERT INTO notices (id, title, message, type, image, link_url, link_label, show_message, show_link_url, show_link_label, created_at) 
                          VALUES (1, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW()) 
                          ON DUPLICATE KEY UPDATE 
                          title=VALUES(title), message=VALUES(message), type=VALUES(type), image=VALUES(image), 
                          link_url=VALUES(link_url), link_label=VALUES(link_label), created_at=NOW()");
    $stmt->bind_param('ssssss', $title, $msg, $type, $newImagePath, $linkUrl, $linkLabel);
    $stmt->execute();
    $stmt->close();

    // =========================================================
    // ✅ SEND NOTIFICATION LOGIC (3 Types: Text, Image, Text+Image)
    // =========================================================
    if (!$notifyOn) {
      $successMsg = 'Notice saved (Notification not sent).';
    } else {
      $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
      if ($accessToken) {
        $fullImageUrl = '';
        if (!empty($newImagePath)) {
          $host = $_SERVER['HTTP_HOST'] ?? 'dp-boss.fun';
          $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (strpos($host, 'dp-boss.fun') !== false);
          $protocol = $isHttps ? "https" : "http";
          $pathPart = ltrim(str_replace('\\', '/', $newImagePath), '/');
          $fullImageUrl = $protocol . "://" . $host . "/" . $pathPart;
        }

        $sendMsg = ($type === 'image') ? '' : $msg;
        $sendImg = ($type === 'text') ? '' : $fullImageUrl;

        $payload = [
          'type' => 'admin_notice',
          'title' => (!empty($title) && stripos($title, 'TEST') === false) ? $title : 'SUBH GAMING Notice',
          'message' => $sendMsg,
          'body' => $sendMsg,
          'link_url' => $linkUrl,
          'link_label' => $linkLabel,
          'title_html' => '<font color="#b7ff00">' . htmlspecialchars((!empty($title) && stripos($title, 'TEST') === false) ? $title : 'SUBH GAMING Notice') . '</font>',
          'notification_color' => '#b7ff00',
          'icon' => 'logo',
          'image' => !empty($sendImg) ? $sendImg : '',
          'picture' => !empty($sendImg) ? $sendImg : '',
          'show_message' => "1",
          'show_link_url' => "1",
          'show_link_label' => "1"
        ];

        if (!empty($fullImageUrl)) {
          $payload['image'] = $fullImageUrl;
          $payload['picture'] = $fullImageUrl;
        }

        $res = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $payload, 'all_subhgaming');
        if ((int)$res['http'] === 200) {
          $successMsg = 'Notice saved & Notification sent to all users!';
        } else {
          $successMsg = 'Notice saved, but notification failed: ' . ($res['body'] ?? 'No response');
        }
      } else {
        $errorMsg = 'Notice saved, but failed to get FCM Access Token.';
      }
    }
    // =========================================================

    // Reload latest
    $res = $db->query("SELECT * FROM notices WHERE id=1 LIMIT 1");
    if ($res && $res->num_rows > 0)
      $current = $res->fetch_assoc();

    // Global flag
    $flagFile = __DIR__ . '/notice_flag.txt';
    @file_put_contents($flagFile, time());

    $_SESSION['admin_notice_success'] = $successMsg;
    $_SESSION['admin_notice_error'] = $errorMsg;
    $_SESSION['admin_notice_clear_form'] = 1;
    header('Location: admin_notice.php?sent=' . time());
    exit;
  }

} catch (Throwable $e) {
  $errorMsg = 'Error: ' . $e->getMessage();
}

if (isset($_SESSION['admin_notice_success'])) {
  $successMsg = (string) $_SESSION['admin_notice_success'];
  unset($_SESSION['admin_notice_success']);
}
if (isset($_SESSION['admin_notice_error'])) {
  $errorMsg = (string) $_SESSION['admin_notice_error'];
  unset($_SESSION['admin_notice_error']);
}
if (!empty($_SESSION['admin_notice_clear_form'])) {
  $current = null;
  $clearFormAfterSave = true;
  unset($_SESSION['admin_notice_clear_form']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title>Admin Notice | DP BOSS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2E7D32;
      --bg: #f5f0e5;
      --card: #ffffff;
      --border: #e0c9aa;
      --text: #1f2937;
      --muted: #6b7280;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f0e5, #e8d9c9);
      color: var(--text);
    }

    .topbar {
      background: var(--primary);
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: 0 3px 10px #0003;
    }

    .topbar .back {
      position: absolute;
      left: 0;
      top: 0;
      width: 52px;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      text-decoration: none;
      font-size: 22px;
    }

    .topbar-title {
      font-size: 18px;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .wrap {
      max-width: 700px;
      margin: 18px auto 28px;
      padding: 0 12px 30px;
    }

    .card {
      background: var(--card);
      border-radius: 18px;
      box-shadow: 0 4px 22px #0002;
      padding: 18px 16px 20px;
      border: 1px solid var(--border);
    }

    .card-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .card-sub {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 14px;
    }

    .alert {
      border-radius: 10px;
      padding: 9px 11px;
      margin-bottom: 10px;
      font-size: 13px;
    }

    .alert.success {
      background: #e6f7e9;
      color: #1b5e20;
      border: 1px solid #a5d6a7;
    }

    .alert.error {
      background: #fdecea;
      color: #b71c1c;
      border: 1px solid #ef9a9a;
    }

    .form-row {
      margin-bottom: 12px;
    }

    .label {
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: 9px 10px;
      border-radius: 10px;
      border: 1px solid #d0bda5;
      font-family: 'Poppins', sans-serif;
      font-size: 14px;
      outline: none;
      background: #fff;
    }

    textarea {
      min-height: 90px;
      resize: vertical;
    }

    .type-row {
      display: flex;
      gap: 16px;
      margin: 4px 0 8px;
      font-size: 14px;
    }

    .type-row label {
      display: flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
    }

    input[type="file"] {
      font-size: 13px;
    }

    .btn-save {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 20px;
      border-radius: 999px;
      border: none;
      background: var(--primary);
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
    }

    .btn-save i {
      margin-right: 6px;
    }

    .btn-save:active {
      transform: scale(.98);
    }

    .preview {
      margin-top: 18px;
      padding: 14px 12px 16px;
      border-radius: 16px;
      background: #fff;
      border: 1px dashed #e0c9aa;
    }

    .preview-head {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .preview-title {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--primary);
    }

    .preview-text {
      font-size: 14px;
      line-height: 1.5;
    }

    .preview-img {
      width: 100%;
      max-width: 360px;
      border-radius: 12px;
      display: block;
      margin: 10px auto 0;
    }

    .preview-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 12px;
      padding: 10px 14px;
      border-radius: 999px;
      background: var(--primary);
      color: #fff;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
    }

    .preview-link i {
      margin-right: 8px;
    }

    .preview-img-container {
      position: relative;
      margin: 10px auto 0;
      max-width: 360px;
      display: none;
    }

    .footer {
      text-align: center;
      margin-top: 14px;
      font-size: 12px;
      color: var(--muted);
    }

    .footer strong {
      font-weight: 700;
      letter-spacing: 1px;
    }

    @media(max-width:480px) {
      .wrap {
        padding: 0 10px 24px;
      }

      .card {
        padding: 16px 12px 18px;
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="topbar">
    <a href="admin_dashboard.php" class="back"><i class="fas fa-arrow-left"></i></a>
    <div class="topbar-title">ADMIN NOTICE</div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="card-title">Manage Notice</div>
      <div class="card-sub">Save karein aur automatically Notification bhejein (3 Types).</div>

      <?php if ($successMsg): ?>
        <div class="alert success"><i class="fas fa-check-circle" style="margin-right:6px;"></i><?= htmlspecialchars($successMsg) ?> <div style="font-size:12px; margin-top:4px; opacity:0.85; font-weight:500;">(Page <span id="redirCount">3</span> sec me redirect ho raha hai...)</div></div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="alert error"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <?php
      $curTitle = '';
      $curMsg = '';
      $curType = 'text_image';
      $curImg = '';
      $curLink = '';
      $curLbl = '';
      ?>

      <form method="post" enctype="multipart/form-data" id="noticeForm" autocomplete="off">

        <div class="form-row">
          <div class="label">Title</div>
          <input type="text" name="title" id="noticeTitle" value="<?= htmlspecialchars($curTitle) ?>" placeholder="Notice Title" required autocomplete="off">
        </div>

        <div class="form-row">
          <div class="label">Type</div>
          <div class="type-row" style="flex-wrap: wrap; gap: 12px;">
            <label><input type="radio" name="type" value="text" <?= $curType === 'text' ? 'checked' : '' ?>>
              <span>Title + Message</span></label>
            <label><input type="radio" name="type" value="image" <?= $curType === 'image' ? 'checked' : '' ?>>
              <span>Title + Image</span></label>
            <label><input type="radio" name="type" value="text_image" <?= ($curType === 'text_image' || !in_array($curType, ['text', 'image'])) ? 'checked' : '' ?>>
              <span>Title + Message + Image</span></label>
          </div>
        </div>

        <div class="form-row" id="textBlock">
          <div class="label">Message</div>
          <textarea name="message" id="noticeMsg" placeholder="Notice Message"><?= htmlspecialchars($curMsg) ?></textarea>
        </div>

        <div class="form-row" id="imageBlock" style="display:none;">
          <div class="label">Upload Image</div>
          <input type="file" name="image" id="noticeFile" accept="image/*">
          <?php if ($curImg): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">
              Current File: <code><?= htmlspecialchars(basename($curImg)) ?></code>
            </div>
          <?php endif; ?>
          <div id="formImgPreviewContainer" style="margin-top:10px; display:<?= $curImg ? 'block' : 'none' ?>;">
            <img id="formImgPreview" src="<?= htmlspecialchars($curImg) ?>" style="max-width:100%; height:auto; border-radius:10px; border:1px solid var(--border);">
          </div>
        </div>

        <div class="form-row">
          <div class="label">Link URL (Optional)</div>
          <input type="text" name="link_url" value="<?= htmlspecialchars($curLink) ?>" placeholder="https://...">
        </div>

        <div class="form-row">
          <div class="label">Link Button Label</div>
          <input type="text" name="link_label" value="<?= htmlspecialchars($curLbl) ?>" placeholder="e.g. Open Now">
        </div>

        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($curImg) ?>">

        <button type="submit" class="btn-save"><i class="fas fa-paper-plane"></i> Save & Send Notification</button>
      </form>

      <div class="preview" id="previewBox">
        <div class="preview-head">Live Preview</div>
        <div class="preview-title" id="prevTitle"><?= htmlspecialchars($curTitle ?: 'Title'); ?></div>
        <div class="preview-text" id="prevText" style="<?= ($curType === 'text' || $curType === 'text_image') ? 'display:block;' : 'display:none;' ?>">
          <?= nl2br(htmlspecialchars($curMsg)) ?: 'Message'; ?>
        </div>
        
        <div class="preview-img-container" id="prevImgContainer" <?= ($curImg && ($curType === 'image' || $curType === 'text_image')) ? 'style="display:block;"' : '' ?>>
          <img src="<?= htmlspecialchars($curImg) ?>" class="preview-img" id="prevImg">
        </div>

        <a href="<?= htmlspecialchars($curLink ?: '#') ?>" target="_blank" class="preview-link" id="prevLink"
          style="<?= $curLink ? '' : 'display:none;' ?>">
          <i class="fas fa-link"></i> <span id="prevLinkLabel"><?= htmlspecialchars($curLbl ?: 'Open Link') ?></span>
        </a>
      </div>

    </div>
  </div>

  <script>
    function updateTypeBlocks() {
      var selectedRadio = document.querySelector('input[name="type"]:checked');
      var selectedType = selectedRadio ? selectedRadio.value : 'text_image';

      var showMsg = (selectedType === 'text' || selectedType === 'text_image');
      var showImg = (selectedType === 'image' || selectedType === 'text_image');

      document.getElementById('textBlock').style.display = showMsg ? 'block' : 'none';
      document.getElementById('imageBlock').style.display = showImg ? 'block' : 'none';
      
      document.getElementById('prevImgContainer').style.display = showImg ? 'block' : 'none';
      document.getElementById('prevText').style.display = showMsg ? 'block' : 'none';
      
      updateLinkPreview();
    }
    document.querySelectorAll('input[name="type"]').forEach(r => r.addEventListener('change', updateTypeBlocks));
    updateTypeBlocks();

    var titleInput = document.querySelector('input[name="title"]');
    var msgInput = document.querySelector('textarea[name="message"]');
    var prevTitle = document.getElementById('prevTitle');
    var prevText = document.getElementById('prevText');

    if (titleInput) titleInput.addEventListener('input', function () { prevTitle.textContent = this.value || 'Title'; });
    if (msgInput) msgInput.addEventListener('input', function () { prevText.textContent = this.value || 'Message'; });

    var fileInput = document.getElementById('noticeFile');
    var prevImg = document.getElementById('prevImg');
    var formImgPreview = document.getElementById('formImgPreview');
    var formImgPreviewContainer = document.getElementById('formImgPreviewContainer');

    if (fileInput) {
      fileInput.addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
          if (prevImg) prevImg.src = ev.target.result;
          if (formImgPreview) formImgPreview.src = ev.target.result;
          if (formImgPreviewContainer) formImgPreviewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }

    var linkUrlInput = document.querySelector('input[name="link_url"]');
    var linkLabelInput = document.querySelector('input[name="link_label"]');
    var prevLink = document.getElementById('prevLink');
    var prevLinkLabel = document.getElementById('prevLinkLabel');

    function updateLinkPreview() {
      var url = (linkUrlInput.value || '').trim();
      var lbl = (linkLabelInput.value || '').trim();
      var finalLbl = lbl || 'Open Link';

      if (url) {
        prevLink.style.display = 'inline-flex';
        prevLink.href = url;
        prevLinkLabel.textContent = finalLbl;
      } else {
        prevLink.style.display = 'none';
      }
    }
    if (linkUrlInput) linkUrlInput.addEventListener('input', updateLinkPreview);
    if (linkLabelInput) linkLabelInput.addEventListener('input', updateLinkPreview);
    updateLinkPreview();

    var shouldClearNoticeForm = <?= $clearFormAfterSave ? 'true' : 'false' ?>;
    if (shouldClearNoticeForm) {
      var noticeForm = document.getElementById('noticeForm');
      if (noticeForm) {
        noticeForm.reset();
      }
      if (titleInput) titleInput.value = '';
      if (msgInput) msgInput.value = '';
      if (linkUrlInput) linkUrlInput.value = '';
      if (linkLabelInput) linkLabelInput.value = '';
      if (fileInput) fileInput.value = '';
      if (formImgPreviewContainer) formImgPreviewContainer.style.display = 'none';
      if (prevTitle) prevTitle.textContent = 'Title';
      if (prevText) prevText.textContent = 'Message';
      updateTypeBlocks();
      updateLinkPreview();
    }

    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        window.location.reload();
      }
    });

    // ✅ Auto redirect after 3 seconds on submit / notice sent
    <?php if (isset($_GET['sent']) || !empty($successMsg)): ?>
    var count = 3;
    var countElem = document.getElementById('redirCount');
    var timer = setInterval(function () {
      count--;
      if (countElem) countElem.textContent = count;
      if (count <= 0) {
        clearInterval(timer);
        window.location.href = 'admin_notice.php';
      }
    }, 1000);
    <?php endif; ?>
  </script>

</body>

</html>








