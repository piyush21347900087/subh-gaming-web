<?php
$file = "admin_dashboard.php";
$content = file_get_contents($file);

$search = <<<EOT
/* Wallet Balance (Done deposits - Done withdrawals for users) - ALWAYS ALL TIME */
\$walletBal = 0.0;
\$totBonus = 0.0;
try {
  \$sqlWalletBal = "SELECT 
                    (COALESCE((SELECT SUM(amount) + IFNULL(SUM(bonus_amount),0) FROM deposit WHERE status='Done'), 0) 
                     - COALESCE((SELECT SUM(amount) FROM withdrawal WHERE LOWER(status) NOT IN ('failed','rejected','cancel','cancelled')), 0) 
                     + COALESCE((SELECT SUM(wining_balance) FROM wining), 0))";
  \$walletBal = (float) \$db->query(\$sqlWalletBal)->fetch_row()[0];

  /* Total Distributed Bonus */
  \$sqlTotBonus = "SELECT COALESCE(SUM(bonus_amount), 0) FROM deposit WHERE status='Done'";
  \$totBonus = (float) \$db->query(\$sqlTotBonus)->fetch_row()[0];
} catch (Throwable \$e) {
  \$walletBal = 0.0;
  \$totBonus = 0.0;
}
EOT;

$replace = <<<EOT
/* Wallet Balance (Accurate calculation from user_total_balance_sql) - ALWAYS ALL TIME */
\$walletBal = 0.0;
\$totBonus = 0.0;

if (!function_exists('table_exists')) {
function table_exists(mysqli \$db, string \$table): bool
{
    try {
        \$t = \$db->real_escape_string(\$table);
        \$res = \$db->query("SHOW TABLES LIKE '{\$t}'");
        return (\$res && \$res->num_rows > 0);
    } catch (Throwable \$e) {
        return false;
    }
}
}
if (!function_exists('column_exists')) {
function column_exists(mysqli \$db, string \$table, string \$column): bool
{
    try {
        \$st = \$db->prepare("SHOW COLUMNS FROM `\$table` LIKE ?");
        if (!\$st) return false;
        \$st->bind_param('s', \$column);
        \$st->execute();
        \$res = \$st->get_result();
        \$ok = \$res && \$res->num_rows > 0;
        \$st->close();
        return \$ok;
    } catch (Throwable \$e) {
        return false;
    }
}
}
if (!function_exists('user_total_balance_sql')) {
function user_total_balance_sql(mysqli \$db, bool \$hasBonusCol): string
{
    \$bonus = \$hasBonusCol
        ? "(SELECT IFNULL(SUM(bonus_amount),0) FROM deposit WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND'))"
        : "0";

    \$mainWins = table_exists(\$db, 'wining')
        ? "(SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci))"
        : "0";

    \$extraWins = [];
    foreach (['wining3', 'wining4'] as \$table) {
        if (table_exists(\$db, \$table)) {
            \$safe = str_replace('`', '', \$table);
            \$extraWins[] = "(SELECT IFNULL(SUM(wining_balance),0) FROM `{\$safe}` WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status)='win')";
        }
    }
    \$extraWinSql = \$extraWins ? implode(' + ', \$extraWins) : "0";

    \$bids = [];
    if (table_exists(\$db, 'bid')) {
        \$refundFilter = table_exists(\$db, 'bid_refunds')
            ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))"
            : "";
        \$bids[] = "(SELECT IFNULL(SUM(amount),0) FROM bid b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {\$refundFilter})";
    }
    if (table_exists(\$db, 'gali_bids')) {
        \$refundFilter = table_exists(\$db, 'bid_refunds')
            ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))"
            : "";
        \$bids[] = "(SELECT IFNULL(SUM(amount),0) FROM gali_bids g WHERE (g.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR g.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {\$refundFilter})";
    }
    foreach (['bid3', 'bid4'] as \$table) {
        if (table_exists(\$db, \$table)) {
            \$safe = str_replace('`', '', \$table);
            \$cancelFilter = column_exists(\$db, \$table, 'is_cancelled') ? "AND COALESCE(b.is_cancelled,0)=0" : "";
            \$bids[] = "(SELECT IFNULL(SUM(amount),0) FROM `{\$safe}` b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {\$cancelFilter})";
        }
    }
    \$bidSql = \$bids ? implode(' + ', \$bids) : "0";

    return "GREATEST(0.00, (
        (SELECT IFNULL(SUM(amount),0) FROM deposit WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND'))
        + {\$bonus}
        + {\$mainWins}
        + {\$extraWinSql}
        - (SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) <> 'game_bid')))
        - ({\$bidSql})
    ))";
}
}

try {
  \$hasBonusCol = \$hasColumn(\$db, 'deposit', 'bonus_amount');
  \$balSql = user_total_balance_sql(\$db, \$hasBonusCol);
  
  \$sqlWalletBal = "SELECT SUM(\$balSql) FROM users u WHERE 1";
  \$walletBal = (float) \$db->query(\$sqlWalletBal)->fetch_row()[0];

  /* Total Distributed Bonus */
  if (\$hasBonusCol) {
      \$sqlTotBonus = "SELECT COALESCE(SUM(bonus_amount), 0) FROM deposit WHERE LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND')";
      \$totBonus = (float) \$db->query(\$sqlTotBonus)->fetch_row()[0];
  }
} catch (Throwable \$e) {
  \$walletBal = 0.0;
  \$totBonus = 0.0;
}
EOT;

// we normalize crlf just in case
$search = str_replace("\r\n", "\n", $search);
$content = str_replace("\r\n", "\n", $content);

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Success!";
} else {
    echo "Search string not found!\n";
}
?>
