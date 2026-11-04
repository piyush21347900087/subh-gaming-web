<?php
require_once 'includes/db.php';
/* chat_unread_count.php – returns integer count of unread chat threads */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) { http_response_code(401); exit; }

$db = db();/*
 *  For every user thread we compare the latest message‐id (MAX(cm.id))
 *  with what the admin has already marked as seen (chat_seen.last_seen).
 *  Any thread where last_id > seen counts as “unread”.
 */
$sql = "
  SELECT COUNT(*) AS cnt
  FROM (
      SELECT cm.user_id,
             MAX(cm.id)             AS last_id,
             IFNULL(cs.last_seen,0) AS seen
      FROM chat_messages cm
      LEFT JOIN chat_seen cs ON cs.user_id = cm.user_id
      GROUP BY cm.user_id
      HAVING last_id > seen
  ) AS t
";
$cnt = $db->query($sql)->fetch_assoc()['cnt'] ?? 0;
echo $cnt;          // pure integer so it’s easy to read via Ajax
