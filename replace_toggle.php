<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/open_market.php';
$content = file_get_contents($file);

$search2 = '            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
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

$replace2 = '            <style>
            .api-switch { position: relative; display: inline-block; width: 44px; height: 22px; margin: 0; }
            .api-switch input { opacity: 0; width: 0; height: 0; }
            .api-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 22px; }
            .api-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
            .api-switch input:checked + .api-slider { background-color: #2e7d32; }
            .api-switch input:focus + .api-slider { box-shadow: 0 0 1px #2e7d32; }
            .api-switch input:checked + .api-slider:before { transform: translateX(22px); }
            </style>
            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div class="status-indicator" style="font-size: 0.85rem; display: flex; align-items: center; gap: 8px; border-right: 1px solid #eee; padding-right: 10px;">
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === \'MANUAL\' ? \'#0066cc\' : \'#999\' ?>;">MANUAL</span>
                    <label class="api-switch">
                        <input type="checkbox" id="apiModeToggle" <?= $resultMode === \'API\' ? \'checked\' : \'\' ?> onchange="toggleApiMode(this.checked ? \'API\' : \'MANUAL\')">
                        <span class="api-slider"></span>
                    </label>
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === \'API\' ? \'#2e7d32\' : \'#999\' ?>;">API (AUTO)</span>
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
                    var cb = document.getElementById(\'apiModeToggle\');
                    cb.checked = !cb.checked;
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
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);

$count2 = 0;
$content_normalized = str_replace($search2, $replace2, $content_normalized, $count2);

file_put_contents($file, $content_normalized);
echo "Replaced properly. count2=$count2";
