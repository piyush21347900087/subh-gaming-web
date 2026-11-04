<?php
$f = 'bio_data.php';
$c = file_get_contents($f);
$replacement = '?>
            <?php if ($userCity !== \'-\'): ?>
                <span class="user-status-badge" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                    <i class="fas fa-city"></i> City: <?= htmlspecialchars($userCity, ENT_QUOTES, \'UTF-8\') ?>
                </span>
            <?php endif; ?>
            <?php if ($userState !== \'-\'): ?>
                <span class="user-status-badge" style="background:#f3e5f5; color:#6a1b9a; border:1px solid #e1bee7; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                    <i class="fas fa-map"></i> State: <?= htmlspecialchars($userState, ENT_QUOTES, \'UTF-8\') ?>
                </span>
            <?php endif; ?>';
$c = preg_replace('/\$displayLoc = \[\];.*?<\?php endif; \?>/s', $replacement, $c);
file_put_contents($f, $c);
echo 'Done';
?>
