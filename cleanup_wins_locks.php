<?php

function cleanup_old_wins_locks(mysqli $db): void
{
    if (!hasTable($db, 'wins_run_lock'))
        return;
    try {
        // Schema A cleanup: last_run_at column
        if (hasColumn($db, 'wins_run_lock', 'last_run_at')) {
            $db->query("DELETE FROM wins_run_lock WHERE last_run_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        }
        // Schema B cleanup: updated_at column
        if (hasColumn($db, 'wins_run_lock', 'updated_at')) {
            $db->query("DELETE FROM wins_run_lock WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        }
    } catch (Throwable $e) {
    }
}
cleanup_old_wins_locks($db);
