<?php
$file = 'd:\SUBH GAMING\SUBH GAMING Web Code\withdrawal_history.php';
$content = file_get_contents($file);

// 1. UPI Intent -> Auto UPI
$content = str_replace("'UPI_INTENT' => 'UPI Intent'", "'UPI_INTENT' => 'Auto UPI'", $content);
$content = str_replace("'upi intent' => 'UPI Intent'", "'upi intent' => 'Auto UPI'", $content);
$content = str_replace("'upi_intent' => 'UPI Intent'", "'upi_intent' => 'Auto UPI'", $content);
$content = str_replace("'intent' => 'UPI Intent'", "'intent' => 'Auto UPI'", $content);

// 2. Remove Marquee
$content = preg_replace('/<!-- Marquee -->\s*<div class="marquee-wrap">\s*<div class="marquee-content">.*?<\/div>\s*<\/div>/s', '', $content);

// 3. Remove Top UI blocks (from <style> before hindi-row up to just before info-text)
$content = preg_replace('/<style>\s*@keyframes pulseRupee.*?<\/style>\s*(<div class="info-text" id="infoText">)/s', '$1', $content);

// 4. Remove Live feed pill and container from below the table
$content = preg_replace('/<!-- Animated Live Feed Area \(Pill\) -->\s*<div class="active-players-pill">.*?<\/div>\s*<div class="live-feed-container" id="liveFeedContainerWithdraw"><\/div>/s', '', $content);

// 5. Update Table Columns for Date and Method, and Status
$searchTd = "<td><?= date('d M H:i', strtotime((string) \$d['date_time'])) ?></td>\n                      <td><?= htmlspecialchars(displayMethod(\$d['method'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>\n                      <td class=\"<?= \$stClass ?>\"><?= \$stText ?></td>";
$replaceTd = "<td><?= date('d M h:i A', strtotime((string) \$d['date_time'])) ?></td>\n                      <td style=\"font-size: 11px;\"><?= htmlspecialchars(displayMethod(\$d['method'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>\n                      <td class=\"<?= \$stClass ?>\" style=\"font-size: 11px;\"><?= \$stText ?></td>";
$content = str_replace($searchTd, $replaceTd, $content);

// 6. Pagination from 5 to 10
$content = preg_replace('/if \(currentSlots \+ cost > 5\) {/', 'if (currentSlots + cost > 10) {', $content);
$content = preg_replace('/const per = 5;/', 'const per = 10;', $content);

// 7. Remove initLiveFeed
$content = preg_replace('/\/\* Animated Live Feed JS \*\/\s*function initLiveFeed\(\) \{.*?\n      \}\s*initLiveFeed\(\);/s', '', $content);

file_put_contents($file, $content);
echo "Changes applied successfully.\n";
