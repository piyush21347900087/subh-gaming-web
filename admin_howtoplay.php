<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

$db = db();
if ($db->connect_error) { die('DB error: ' . $db->connect_error); }
$db->set_charset('utf8mb4');

// Create table dynamically
$db->query("CREATE TABLE IF NOT EXISTS how_to_play_buttons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    link TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$successMsg = '';
$errorMsg = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = (int) $_POST['edit_id'];
        $title = trim($_POST['title'] ?? '');
        $link = trim($_POST['link'] ?? '');
        
        if ($title === '' || $link === '') {
            throw new Exception('Dono fields zaroori hain.');
        }
        
        $st = $db->prepare("UPDATE how_to_play_buttons SET title=?, link=? WHERE id=?");
        $st->bind_param('ssi', $title, $link, $id);
        if ($st->execute()) {
            $successMsg = 'Button details update ho gayi!';
        } else {
            throw new Exception('Database error.');
        }
        $st->close();
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

// Handle Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $title = trim($_POST['title'] ?? '');
        $link = trim($_POST['link'] ?? '');
        
        if ($title === '' || $link === '') {
            throw new Exception('Dono fields zaroori hain.');
        }
        
        // Check Max Limit
        $count = (int) ($db->query("SELECT COUNT(*) FROM how_to_play_buttons")->fetch_row()[0] ?? 0);
        if ($count >= 12) {
            throw new Exception('Aap maximum 12 buttons hi add kar sakte hain.');
        }
        
        $st = $db->prepare("INSERT INTO how_to_play_buttons (title, link) VALUES (?, ?)");
        $st->bind_param('ss', $title, $link);
        if ($st->execute()) {
            $successMsg = 'Button successfully add ho gaya!';
        } else {
            throw new Exception('Database error.');
        }
        $st->close();
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

// Handle Deletion
if (isset($_GET['delete'])) {
    $did = (int) $_GET['delete'];
    $db->query("DELETE FROM how_to_play_buttons WHERE id = $did");
    $successMsg = 'Button delete kar diya gaya.';
}

// Fetch data for edit
$editItem = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $q = $db->prepare("SELECT * FROM how_to_play_buttons WHERE id = ?");
    $q->bind_param('i', $eid);
    $q->execute();
    $editItem = $q->get_result()->fetch_assoc();
    $q->close();
}

$buttons = [];
$res = $db->query("SELECT * FROM how_to_play_buttons ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $buttons[] = $row;
    }
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title>Manage How To Play | Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --red: #c62828;
      --bg: #f5f0e5;
      --card: #fff7ea;
      --border: #e0c9aa;
      --text: #3e2723;
      --muted: #888;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0; font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f0e5, #e8d9c9); color: var(--text);
    }
    .topbar {
      background: var(--red); height: 56px; display: flex; align-items: center; justify-content: center;
      color: #fff; position: sticky; top: 0; z-index: 10; box-shadow: 0 3px 10px #0003;
    }
    .topbar .back {
      position: absolute; left: 0; top: 0; width: 52px; height: 100%;
      display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 22px;
    }
    .topbar-title { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
    .wrap { max-width: 700px; margin: 18px auto 28px; padding: 0 12px 30px; }
    .card {
      background: var(--card); border-radius: 18px; box-shadow: 0 4px 22px #0002;
      padding: 18px 16px 20px; border: 1px solid var(--border); margin-bottom: 20px;
    }
    .card-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .card-sub { font-size: 12px; color: var(--muted); margin-bottom: 14px; }
    
    .alert { border-radius: 10px; padding: 9px 11px; margin-bottom: 10px; font-size: 13px; }
    .alert.success { background: #e6f7e9; color: #1b5e20; border: 1px solid #a5d6a7; }
    .alert.error { background: #fdecea; color: #b71c1c; border: 1px solid #ef9a9a; }
    
    .form-row { margin-bottom: 12px; }
    .label { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
    input[type="text"] {
      width: 100%; padding: 9px 10px; border-radius: 10px; border: 1px solid #d0bda5;
      font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; background: #fff;
    }
    .btn-save {
      display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px;
      border-radius: 999px; border: none; background: var(--red); color: #fff;
      font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 8px;
    }
    .btn-save i { margin-right: 6px; }
    .btn-save:active { transform: scale(.98); }
    
    .btn-delete, .btn-edit {
      color: #fff; border: none; padding: 5px 9px;
      border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;
      text-decoration: none; display: inline-block;
    }
    .btn-delete { background: var(--red); margin-left: 4px; }
    .btn-edit { background: #1976d2; }
    
    /* Table Styles */
    .data-table {
      width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;
    }
    .data-table th, .data-table td {
      padding: 10px; border-bottom: 1px solid var(--border); text-align: left;
    }
    .data-table th { background: #e8d9c9; font-weight: 700; }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

  <div class="topbar">
    <a href="admin_dashboard.php" class="back"><i class="fas fa-arrow-left"></i></a>
    <div class="topbar-title">HOW TO PLAY BUTTONS</div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="card-title"><?= $editItem ? 'Update Button' : 'Create New Button' ?></div>
      <div class="card-sub">Apne buttons ko manage karein.</div>

      <?php if ($successMsg): ?>
        <div class="alert success"><?= htmlspecialchars($successMsg) ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
        <div class="alert error"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>">
        <?php if ($editItem): ?>
            <input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
          <div class="label">Button Title</div>
          <input type="text" name="title" value="<?= htmlspecialchars($editItem['title'] ?? '') ?>" placeholder="e.g. Watch Game Rules Video" required>
        </div>
        <div class="form-row">
          <div class="label">Link / URL</div>
          <input type="text" name="link" value="<?= htmlspecialchars($editItem['link'] ?? '') ?>" placeholder="https://..." required>
        </div>
        <button type="submit" class="btn-save">
            <i class="<?= $editItem ? 'fas fa-save' : 'fas fa-plus' ?>"></i> 
            <?= $editItem ? 'Save Updates' : 'Create Button' ?>
        </button>
        <?php if ($editItem): ?>
            <a href="admin_howtoplay.php" class="btn-save" style="background:#757575; text-decoration:none;"><i class="fas fa-times"></i> Cancel</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="card-title">Existing Buttons (<?= count($buttons) ?>/12)</div>
      
      <?php if (empty($buttons)): ?>
        <div style="font-size:13px; color:var(--muted); text-align:center; padding: 20px;">Koi button created nahi hai.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Link</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($buttons as $b): ?>
              <tr>
                <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                <td><a href="<?= htmlspecialchars($b['link']) ?>" target="_blank" style="color:#c62828;"><?= htmlspecialchars($b['link']) ?></a></td>
                <td style="white-space: nowrap;">
                  <a href="admin_howtoplay.php?edit=<?= $b['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
                  <a href="admin_howtoplay.php?delete=<?= $b['id'] ?>" class="btn-delete" onclick="return confirm('Delete karein?');"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      
    </div>
  </div>

</body>
</html>
