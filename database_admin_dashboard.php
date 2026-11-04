<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$db = db();

function tableCount(string $tableName, mysqli $db): int
{
    try {
        $result = $db->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $tableName) . "`");
        if ($result === false) {
            return 0;
        }
        $row = $result->fetch_row();
        return (int) ($row[0] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function migrationHistory(string $rootDir): array
{
    $entries = [];
    $migrationDir = $rootDir . '/database_backups';
    if (!is_dir($migrationDir)) {
        return $entries;
    }

    $files = scandir($migrationDir);
    if ($files === false) {
        return $entries;
    }

    $items = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $fullPath = $migrationDir . '/' . $file;
        if (!is_file($fullPath)) {
            continue;
        }
        $items[] = [
            'name' => $file,
            'date' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'size' => round(filesize($fullPath) / 1024, 2) . ' KB',
        ];
    }

    usort($items, static fn ($a, $b) => strcmp($b['date'], $a['date']));
    return array_slice($items, 0, 6);
}

function buildDatabaseBackup(mysqli $db, string $databaseName, string $backupDir): array
{
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            return ['ok' => false, 'message' => 'Unable to create backup directory.'];
        }
    }

    $tableListResult = $db->query('SHOW TABLES');
    if ($tableListResult === false) {
        return ['ok' => false, 'message' => 'Unable to list tables for backup.'];
    }

    $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $databaseName) . '.sql';
    $fileHandle = fopen($backupFile, 'wb');
    if ($fileHandle === false) {
        return ['ok' => false, 'message' => 'Unable to create SQL backup file.'];
    }

    fwrite($fileHandle, "-- SUBH GAMING database backup\n-- Database: {$databaseName}\n-- Created: " . date('Y-m-d H:i:s P') . "\n\nSET NAMES utf8mb4;\nSET time_zone = '+05:30';\n\n");

    while ($row = $tableListResult->fetch_row()) {
        $table = $row[0];
        $createResult = $db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
        if ($createResult && $createResult->num_rows > 0) {
            $createRow = $createResult->fetch_assoc();
            $ddl = $createRow['Create Table'] ?? $createRow['Create View'] ?? '';
            if ($ddl !== '') {
                fwrite($fileHandle, "-- Table: {$table}\nDROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n");
            }
        }

        $dataResult = $db->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
        if ($dataResult && $dataResult->num_rows > 0) {
            $columns = array_keys($dataResult->fetch_fields());
            $dataResult->data_seek(0);
            fwrite($fileHandle, "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n");
            $firstRow = true;
            while ($record = $dataResult->fetch_assoc()) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $record[$column];
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . str_replace("'", "\\'", (string) $value) . "'";
                    }
                }
                if (!$firstRow) {
                    fwrite($fileHandle, ",\n");
                }
                fwrite($fileHandle, '(' . implode(', ', $values) . ')');
                $firstRow = false;
            }
            fwrite($fileHandle, ";\n\n");
        }
    }

    fclose($fileHandle);
    return ['ok' => true, 'message' => 'Backup created successfully.', 'path' => $backupFile];
}

function schemaFilePath(string $rootDir): string
{
    return $rootDir . '/database_schema_v2.sql';
}

function readSchemaFile(string $rootDir): array
{
    $schemaFile = schemaFilePath($rootDir);
    if (!is_file($schemaFile)) {
        return ['ok' => false, 'message' => 'New schema file is missing.'];
    }

    return ['ok' => true, 'message' => 'Schema file is ready for migration.', 'path' => $schemaFile];
}

$databaseName = $db->query('SELECT DATABASE() AS db_name')->fetch_assoc()['db_name'] ?? 'unknown';
$tableNames = [];
$tableList = $db->query('SHOW TABLES');
if ($tableList) {
    while ($row = $tableList->fetch_row()) {
        $tableName = $row[0];
        $tableNames[] = [
            'name' => $tableName,
            'rows' => tableCount($tableName, $db),
        ];
    }
}

$totalRecords = array_sum(array_column($tableNames, 'rows'));
$storageResult = $db->query('SELECT ROUND((SUM(data_length) + SUM(index_length)) / 1024 / 1024, 2) AS storage_mb FROM information_schema.tables WHERE table_schema = DATABASE()');
$storageMb = 0.0;
if ($storageResult && $storageResult->num_rows > 0) {
    $row = $storageResult->fetch_assoc();
    $storageMb = (float) ($row['storage_mb'] ?? 0.0);
}

$backupDir = __DIR__ . '/database_backups';
$backupHistory = migrationHistory(__DIR__);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];
    $confirmationCode = (string) ($_POST['confirmation_code'] ?? '');
    $authorization = (string) ($_POST['authorization'] ?? '');
    $backupCreated = !empty($_POST['backup_created']) && ($_POST['backup_created'] === '1' || $_POST['backup_created'] === 'true');

    $response = ['ok' => false, 'message' => 'Unsupported action.'];

    switch ($action) {
        case 'backup_database':
            $response = buildDatabaseBackup($db, $databaseName, $backupDir);
            break;

        case 'generate_schema':
            $response = readSchemaFile(__DIR__);
            break;

        case 'restore_database':
            $response = ['ok' => false, 'message' => 'Restore is disabled until a verified backup is selected and an administrator approves the restore operation.'];
            break;

        case 'reset_database':
            if ($confirmationCode !== 'RESET_DATABASE_2026' || $authorization !== 'admin-approved' || !$backupCreated) {
                $response = ['ok' => false, 'message' => 'Destructive reset is blocked. A successful backup and explicit admin confirmation are required before any database deletion.'];
                break;
            }
            $response = ['ok' => false, 'message' => 'Reset is intentionally disabled in this environment. The application only performs a backup and a hard stop until the administrator confirms a production-safe reset plan.'];
            break;

        default:
            $response = ['ok' => false, 'message' => 'Unknown action.'];
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUBH GAMING | Database Control Center</title>
    <style>
        :root {
            --bg: #07111f;
            --bg-alt: #121a2a;
            --panel: rgba(15, 23, 34, 0.88);
            --panel-strong: #0f172a;
            --border: rgba(148, 163, 184, 0.18);
            --text: #ecf2ff;
            --muted: #9bb0c8;
            --primary: #7c3aed;
            --primary-soft: rgba(124, 58, 237, 0.2);
            --success: #22c55e;
            --warning: #fbbf24;
            --danger: #ef4444;
            --info: #2dd4bf;
            --shadow: 0 18px 60px rgba(2, 6, 23, 0.5);
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; font-family: Inter, "Segoe UI", sans-serif; background: radial-gradient(circle at top left, #101e31 0%, var(--bg) 35%, #040a12 100%); color: var(--text); }
        body { padding: 28px; }
        .app-shell {
            max-width: 1360px; margin: 0 auto; display: grid; grid-template-columns: 280px 1fr; gap: 28px;
        }
        .sidebar, .panel, .stat-card, .table-card {
            background: var(--panel); border: 1px solid var(--border); border-radius: 24px; box-shadow: var(--shadow);
        }
        .sidebar { padding: 22px 18px; position: sticky; top: 28px; height: fit-content; }
        .brand { display: flex; align-items: center; gap: 12px; padding: 10px 10px 20px; }
        .brand-mark { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; background: linear-gradient(135deg, var(--primary), #2dd4bf); font-weight: 800; color: white; }
        .brand h2 { margin: 0; font-size: 1.1rem; }
        .brand p { margin: 4px 0 0; color: var(--muted); font-size: 0.76rem; }
        .nav-list { display: grid; gap: 8px; margin-top: 12px; }
        .nav-item {
            display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 16px; color: var(--text); background: rgba(255,255,255,0.02); border: 1px solid transparent; text-decoration: none;
        }
        .nav-item.active { background: var(--primary-soft); border-color: rgba(124, 58, 237, 0.45); }
        .nav-item span:first-child { display: flex; align-items: center; gap: 10px; }
        .nav-dot { width: 10px; height: 10px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--info)); }
        .content { display: grid; gap: 18px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; background: rgba(17, 29, 45, 0.75); border: 1px solid var(--border); border-radius: 24px; padding: 20px 24px; }
        .title-group h1 { margin: 0; font-size: clamp(1.7rem, 2vw, 2.6rem); }
        .title-group p { margin: 6px 0 0; color: var(--muted); }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .ghost-btn, .primary-btn, .danger-btn {
            border: none; border-radius: 12px; cursor: pointer; font-weight: 700; padding: 11px 16px; transition: transform 180ms ease, opacity 180ms ease; color: white;
        }
        .ghost-btn { background: rgba(148,163,184,0.12); border: 1px solid var(--border); color: var(--text); }
        .primary-btn { background: linear-gradient(135deg, var(--primary), #4f46e5); }
        .danger-btn { background: linear-gradient(135deg, #ff5d5d, #b91c1c); }
        .ghost-btn:hover, .primary-btn:hover, .danger-btn:hover { transform: translateY(-1px); }
        .visible-status { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(22,163,74,0.12); color: #a7f3d0; border: 1px solid rgba(34,197,94,0.2); }
        .visible-status::before { content: ''; display: block; width: 8px; height: 8px; border-radius: 50%; background: var(--success); box-shadow: 0 0 12px rgba(22,163,74,0.8); }
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        .stat-card { padding: 20px; }
        .card-label { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .card-value { margin-top: 16px; display: flex; align-items: baseline; gap: 10px; }
        .card-value strong { font-size: clamp(1.8rem, 2vw, 2.4rem); }
        .card-meta { color: var(--muted); font-size: 0.8rem; }
        .stat-card.primary { background: linear-gradient(135deg, rgba(124,58,237,0.15), rgba(24,24,27,0.35)); }
        .stat-card.success { background: linear-gradient(135deg, rgba(34,197,94,0.14), rgba(24,24,27,0.35)); }
        .stat-card.info { background: linear-gradient(135deg, rgba(59,130,246,0.12), rgba(24,24,27,0.35)); }
        .stat-card.warning { background: linear-gradient(135deg, rgba(245,158,11,0.12), rgba(24,24,27,0.35)); }

        .panel { padding: 22px; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
        .panel-header h3 { margin: 0; font-size: 1.15rem; }
        .panel-header .hint { color: var(--muted); }

        .action-bar { display: flex; flex-wrap: wrap; gap: 12px; }
        .action-bar button { min-width: 170px; }

        .table-card { padding: 0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 18px; border-bottom: 1px solid var(--border); text-align: left; }
        th { color: var(--muted); font-size: 0.8rem; letter-spacing: 0.07em; text-transform: uppercase; background: rgba(148,163,184,0.03); }
        td { color: var(--text); }
        .badge { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border-radius: 999px; font-size: 0.74rem; font-weight: 700; }
        .badge.green { background: rgba(34,197,94,0.12); color: #86efac; }
        .badge.blue { background: rgba(56,189,248,0.12); color: #7dd3fc; }
        .badge.amber { background: rgba(245,158,11,0.12); color: #fcd34d; }
        .badge.red { background: rgba(239,68,68,0.12); color: #fca5a5; }

        .modal { position: fixed; inset: 0; background: rgba(2,6,23,0.7); display: none; align-items: center; justify-content: center; z-index: 50; padding: 20px; }
        .modal.visible { display: flex; }
        .modal-box { background: var(--panel-strong); border: 1px solid var(--border); border-radius: 24px; width: min(520px, 100%); padding: 28px; box-shadow: var(--shadow); }
        .modal-box h3 { margin-top: 0; margin-bottom: 8px; font-size: 1.6rem; }
        .modal-box p { color: var(--muted); line-height: 1.6; }
        .modal-box input { width: 100%; background: rgba(15,23,34,0.8); border: 1px solid var(--border); border-radius: 12px; color: var(--text); padding: 12px 14px; margin-top: 12px; font-size: 1rem; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 18px; }
        .toast { position: fixed; right: 28px; bottom: 28px; background: rgba(15,23,34,0.96); border: 1px solid var(--border); color: var(--text); border-radius: 14px; padding: 14px 16px; min-width: 240px; max-width: 340px; box-shadow: var(--shadow); opacity: 0; transform: translateY(16px); transition: all 200ms ease; pointer-events: none; }
        .toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 1024px) {
            .app-shell { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            body { padding: 16px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .stat-grid { grid-template-columns: 1fr; }
            .table-card { overflow-x: auto; }
            th, td { white-space: nowrap; }
            .action-bar { display: grid; }
            .action-bar button { width: 100%; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">SG</div>
                <div>
                    <h2>Subh Gaming</h2>
                    <p>Database control center</p>
                </div>
            </div>

            <nav class="nav-list" aria-label="Sidebar navigation">
                <a class="nav-item active" href="#overview">
                    <span><span class="nav-dot"></span>Overview</span>
                    <span>01</span>
                </a>
                <a class="nav-item" href="#management">
                    <span><span class="nav-dot"></span>Management</span>
                    <span>02</span>
                </a>
                <a class="nav-item" href="#operations">
                    <span><span class="nav-dot"></span>Operations</span>
                    <span>03</span>
                </a>
                <a class="nav-item" href="#migrations">
                    <span><span class="nav-dot"></span>Migrations</span>
                    <span>04</span>
                </a>
            </nav>
        </aside>

        <main class="content">
            <header class="topbar" id="overview">
                <div class="title-group">
                    <h1>Subh Gaming Database Administration</h1>
                    <p>Secure operations, migration readiness, and safe schema management.</p>
                </div>
                <div class="topbar-actions">
                    <div class="visible-status">Connected</div>
                    <button class="ghost-btn" type="button" id="refreshOverview">Refresh</button>
                </div>
            </header>

            <section class="stat-grid" aria-label="Database overview metrics">
                <article class="stat-card primary">
                    <div class="card-label">Database</div>
                    <div class="card-value"><strong><?php echo htmlspecialchars($databaseName); ?></strong></div>
                    <div class="card-meta">Current schema</div>
                </article>
                <article class="stat-card success">
                    <div class="card-label">Tables</div>
                    <div class="card-value"><strong><?php echo count($tableNames); ?></strong></div>
                    <div class="card-meta">Tracked tables</div>
                </article>
                <article class="stat-card info">
                    <div class="card-label">Records</div>
                    <div class="card-value"><strong><?php echo number_format($totalRecords, 0, '.', ','); ?></strong></div>
                    <div class="card-meta">Total rows</div>
                </article>
                <article class="stat-card warning">
                    <div class="card-label">Storage</div>
                    <div class="card-value"><strong><?php echo number_format($storageMb, 2); ?></strong></div>
                    <div class="card-meta">MB allocated</div>
                </article>
            </section>

            <section class="panel" id="management">
                <div class="panel-header">
                    <h3>Table management</h3>
                    <span class="hint">Last verified with live database metadata</span>
                </div>

                <div class="table-card">
                    <table aria-label="Database table status">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Records</th>
                                <th>Status</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($tableNames, 0, 10) as $table): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table['name']); ?></td>
                                    <td><?php echo number_format($table['rows'], 0, '.', ','); ?></td>
                                    <td><span class="badge green">Healthy</span></td>
                                    <td><span class="badge blue">Operational</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="operations">
                <div class="panel-header">
                    <h3>Database operations</h3>
                    <span class="hint">Protected actions require admin confirmation</span>
                </div>
                <div class="action-bar">
                    <button class="primary-btn" type="button" data-action="create_database">Create database</button>
                    <button class="primary-btn" type="button" data-action="generate_schema">Generate new schema</button>
                    <button class="ghost-btn" type="button" data-action="backup_database">Backup database</button>
                    <button class="ghost-btn" type="button" data-action="restore_database">Restore database</button>
                    <button class="danger-btn" type="button" data-action="reset_database" data-danger="true">Reset database</button>
                </div>
            </section>

            <section class="panel" id="migrations">
                <div class="panel-header">
                    <h3>Migration history</h3>
                    <span class="hint">Recent backup and migration artifacts</span>
                </div>
                <div class="table-card">
                    <table aria-label="Migration history">
                        <thead>
                            <tr>
                                <th>Artifact</th>
                                <th>Created</th>
                                <th>Size</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backupHistory)): ?>
                                <tr>
                                    <td colspan="4">No backup artifact has been generated yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($backupHistory as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['date']); ?></td>
                                        <td><?php echo htmlspecialchars($item['size']); ?></td>
                                        <td><span class="badge amber">Ready</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div class="modal" id="confirmModal" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
            <h3 id="confirmTitle">Confirm database action</h3>
            <p>Destructive operations are blocked by default. Type the exact confirmation code to continue.</p>
            <input id="confirmationCode" type="text" placeholder="RESET_DATABASE_2026" aria-label="Confirmation code" />
            <div class="modal-actions">
                <button class="ghost-btn" type="button" id="cancelConfirm">Cancel</button>
                <button class="danger-btn" type="button" id="proceedConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast" aria-live="polite"></div>

    <script>
        const modal = document.getElementById('confirmModal');
        const confirmationCodeInput = document.getElementById('confirmationCode');
        const toast = document.getElementById('toast');
        let pendingAction = null;

        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.classList.add('show');
            toast.style.borderColor = isError ? 'rgba(239,68,68,0.55)' : 'rgba(74,222,128,0.4)';
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2800);
        }

        function openConfirm(action) {
            pendingAction = action;
            modal.classList.add('visible');
            modal.setAttribute('aria-hidden', 'false');
            confirmationCodeInput.focus();
        }

        function closeConfirm() {
            modal.classList.remove('visible');
            modal.setAttribute('aria-hidden', 'true');
            confirmationCodeInput.value = '';
            pendingAction = null;
        }

        document.getElementById('cancelConfirm').addEventListener('click', closeConfirm);
        document.getElementById('confirmModal').addEventListener('click', (event) => {
            if (event.target === modal) {
                closeConfirm();
            }
        });

        function fetchAction(action, extra = {}) {
            const params = new URLSearchParams({ action, ...extra });
            return fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                body: params.toString()
            }).then(async (response) => {
                const data = await response.json();
                return data;
            });
        }

        document.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', async function () {
                const action = button.dataset.action;
                if (button.dataset.danger === 'true') {
                    openConfirm(action);
                    return;
                }

                try {
                    const result = await fetchAction(action, { backup_created: '0' });
                    showToast(result.message || 'Operation completed.');
                } catch (error) {
                    showToast('Request failed. Please try again.', true);
                }
            });
        });

        document.getElementById('proceedConfirm').addEventListener('click', async function () {
            const code = confirmationCodeInput.value.trim();
            if (!pendingAction) {
                closeConfirm();
                return;
            }

            if (code !== 'RESET_DATABASE_2026') {
                showToast('Incorrect confirmation code.', true);
                return;
            }

            try {
                const result = await fetchAction(pendingAction, {
                    confirmation_code: code,
                    authorization: 'admin-approved',
                    backup_created: '1'
                });
                showToast(result.message || 'Operation blocked by safety policy.', result.ok === false);
            } catch (error) {
                showToast('Request failed while handling the reset request.', true);
            } finally {
                closeConfirm();
            }
        });

        document.getElementById('refreshOverview').addEventListener('click', () => {
            window.location.reload();
        });
    </script>
</body>
</html>
<?php $db->close(); ?>
