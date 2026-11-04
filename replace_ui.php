<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/open_market.php';
$content = file_get_contents($file);

$search1 = '$syncData = sync_from_master($db, $masterApiUrl);
if ($syncData) {
    $apiStatus = isset($syncData[\'api_active\']) ? ($syncData[\'api_active\'] ? "ACTIVE" : "DISABLED") : "OFFLINE";
    $remainingDays = $syncData[\'remaining_days\'] ?? 0;
}';

$replace1 = '/* ================== GLOBAL API MODE TOGGLE ================== */
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'action\']) && $_POST[\'action\'] === \'toggle_api_mode\') {
    $newMode = $_POST[\'mode\'] === \'MANUAL\' ? \'MANUAL\' : \'API\';
    $db->query("UPDATE rk_config SET value=\'$newMode\' WHERE key=\'result_mode\'");
    if ($db->affected_rows === 0) {
        $db->query("INSERT IGNORE INTO rk_config (`key`, `value`) VALUES (\'result_mode\', \'$newMode\')");
    }
    echo json_encode([\'ok\' => true, \'mode\' => $newMode]);
    exit;
}

$resMode = $db->query("SELECT value FROM rk_config WHERE key=\'result_mode\' LIMIT 1");
$resultMode = \'API\';
if ($resMode && $rm = $resMode->fetch_assoc()) {
    $resultMode = strtoupper($rm[\'value\']);
}

$syncData = null;
if ($resultMode === \'API\') {
    $syncData = sync_from_master($db, $masterApiUrl);
}

if ($syncData) {
    $apiStatus = isset($syncData[\'api_active\']) ? ($syncData[\'api_active\'] ? "ACTIVE" : "DISABLED") : "OFFLINE";
    $remainingDays = $syncData[\'remaining_days\'] ?? 0;
} else {
    $apiStatus = "MANUAL MODE";
    $remainingDays = 0;
}';

$search2 = '            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div class="status-indicator" style="font-size: 0.85rem; font-weight: bold; display: flex; align-items: center; gap: 6px; border-right: 1px solid #eee; padding-right: 10px;">
                    <span style="color: #666;">API:</span> 
                    <span style="color: <?= ($apiStatus === \'ACTIVE\') ? \'#2e7d32\' : \'#c62828\' ?>;"><i class="fa fa-circle"></i> <?= $apiStatus ?></span>
                </div>
                <div class="api-timer-display" style="font-size: 0.85rem; color: #770000; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-clock"></i> Expiring in: <span style="color: #c62828;"><?= ($remainingDays > 0) ? ($remainingDays . " Days") : "0 Days" ?></span>
                </div>
            </div>';

$replace2 = '            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div class="status-indicator" style="font-size: 0.85rem; font-weight: bold; display: flex; align-items: center; gap: 6px; border-right: 1px solid #eee; padding-right: 10px;">
                    <span style="color: #666;">Result Mode:</span> 
                    <select id="apiModeSelect" onchange="toggleApiMode(this.value)" style="border:1px solid #ccc; border-radius:4px; padding:2px 5px; font-weight:bold; outline:none; font-size:12px; cursor:pointer;">
                        <option value="API" <?= $resultMode === \'API\' ? \'selected\' : \'\' ?>>🌐 API (Auto)</option>
                        <option value="MANUAL" <?= $resultMode === \'MANUAL\' ? \'selected\' : \'\' ?>>✍ MANUAL</option>
                    </select>
                </div>
                <?php if ($resultMode === \'API\'): ?>
                    <div class="api-timer-display" style="font-size: 0.85rem; color: #770000; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                        <span style="color: <?= ($apiStatus === \'ACTIVE\') ? \'#2e7d32\' : \'#c62828\' ?>;"><i class="fa fa-circle"></i> <?= $apiStatus ?></span> | 
                        <i class="fa fa-clock"></i> Expiring in: <span style="color: #c62828;"><?= ($remainingDays > 0) ? ($remainingDays . " Days") : "0 Days" ?></span>
                    </div>
                <?php else: ?>
                    <div style="font-size: 0.85rem; color: #0066cc; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-hand-paper"></i> Manual Entry Active
                    </div>
                <?php endif; ?>
            </div>
            <script>
            function toggleApiMode(mode) {
                if(!confirm("Are you sure you want to change Result Mode to " + mode + "?")) {
                    window.location.reload();
                    return;
                }
                fetch(\'open_market.php\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/x-www-form-urlencoded\' },
                    body: \'action=toggle_api_mode&mode=\' + mode
                })
                .then(r => r.json())
                .then(function(res) {
                    if (res.ok) window.location.reload();
                    else alert(\'Failed to update mode\');
                }).catch(function(e) {
                    alert(\'Network error\');
                    window.location.reload();
                });
            }
            </script>';

// Normalize line endings to \n before replacing
$content_normalized = str_replace("\r\n", "\n", $content);
$search1 = str_replace("\r\n", "\n", $search1);
$replace1 = str_replace("\r\n", "\n", $replace1);
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);

$count1 = 0;
$count2 = 0;
$content_normalized = str_replace($search1, $replace1, $content_normalized, $count1);
$content_normalized = str_replace($search2, $replace2, $content_normalized, $count2);

// Revert line endings to original file's ending if needed, but modern editors and PHP handle \n fine on Windows.
file_put_contents($file, $content_normalized);
echo "Replaced properly. count1=$count1, count2=$count2";
