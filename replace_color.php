<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/open_market.php';
$content = file_get_contents($file);

$search2 = '            <style>
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
                </div>';

$replace2 = '            <style>
            .api-switch { position: relative; display: inline-block; width: 44px; height: 22px; margin: 0; }
            .api-switch input { opacity: 0; width: 0; height: 0; }
            .api-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 22px; }
            .api-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
            .api-switch input:checked + .api-slider { background-color: #007bff; }
            .api-switch input:focus + .api-slider { box-shadow: 0 0 1px #007bff; }
            .api-switch input:checked + .api-slider:before { transform: translateX(22px); }
            </style>
            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div class="status-indicator" style="font-size: 0.85rem; display: flex; align-items: center; gap: 8px; border-right: 1px solid #eee; padding-right: 10px;">
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === \'MANUAL\' ? \'#555\' : \'#999\' ?>;">MANUAL</span>
                    <label class="api-switch">
                        <input type="checkbox" id="apiModeToggle" <?= $resultMode === \'API\' ? \'checked\' : \'\' ?> onchange="toggleApiMode(this.checked ? \'API\' : \'MANUAL\')">
                        <span class="api-slider"></span>
                    </label>
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === \'API\' ? \'#007bff\' : \'#999\' ?>;">API (AUTO)</span>
                </div>';

$content_normalized = str_replace("\r\n", "\n", $content);
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);

$count2 = 0;
$content_normalized = str_replace($search2, $replace2, $content_normalized, $count2);

file_put_contents($file, $content_normalized);
echo "Replaced properly. count2=$count2";
