<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Location: deposit.php", true, 301);
exit;
