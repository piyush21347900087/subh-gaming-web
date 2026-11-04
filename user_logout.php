<?php
require_once 'includes/db.php';
// user_logout.php
/* user_logout.php – Log the user out and redirect to login (CLEAR saved mobile/password on logout) */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * ✅ As per your latest requirement:
 * - Logout karte hi:
 *   1) live table status offline
 *   2) session destroy
 *   3) remember_mobile/remember_pass cookies CLEAR
 *   4) localStorage CLEAR (JS)
 *   5) Android saved prefs CLEAR (AndroidNotify.clearLoginPref if available)
 * - Then redirect to user_login.php?loggedout=1 (so login page will not auto-fill)
 */

// --- Live table me status offline ---
try {
  if (!empty($_SESSION['user']['id'])) {
    $id = (int) $_SESSION['user']['id'];

    $db = db();
    $db->query("SET time_zone = '+05:30'");

    $stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($LL_user_id);
    $stmt->fetch();
    $stmt->close();

    if (!empty($LL_user_id)) {
      $stmt2 = $db->prepare("UPDATE live SET status='offline', last_seen=NOW() WHERE user_id=?");
      $stmt2->bind_param('s', $LL_user_id);
      $stmt2->execute();
      $stmt2->close();
    }

    $db->close();
  }
} catch (Throwable $e) {
  // silent fail
}

/* Unset session */
$_SESSION = [];

/* Destroy the session cookie */
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"] ?? '/',
    $params["domain"] ?? '',
    $params["secure"] ?? false,
    $params["httponly"] ?? true
  );
}

/* ✅ Clear remember cookies */
$domain = '.subhgaming.online';
setcookie('remember_mobile', '', time() - 3600, '/', $domain);
setcookie('remember_pass', '', time() - 3600, '/', $domain);

/* ✅ Set logout marker cookie (login page will not autofill/autologin for few minutes) */
setcookie('LL_logged_out', '1', time() + 600, '/', $domain, false, true);

/* Destroy session */
session_destroy();

/* ✅ Clear localStorage + Android prefs via JS then redirect */
echo <<<HTML
<!doctype html>
<html><head>
    <link rel="icon" type="image/png" href="images/fevicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logging out...</title><link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<script>
  try{
    localStorage.removeItem('remember_mobile');
    localStorage.removeItem('remember_pass');
  }catch(e){}

  try{
    if(typeof AndroidNotify !== 'undefined' && AndroidNotify.clearLoginPref){
      AndroidNotify.clearLoginPref();
    }
  }catch(e){}

  window.location.href = "user_login.php?loggedout=1";
</script>
</body></html>
HTML;
exit;




