<?php
$f = file_get_contents("index.php");

$cssFind = ".footer {";
$cssReplace = <<<'EOF'
        .steps-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: #dddddd;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .step-item i {
            color: #ff4d4d;
            font-size: 22px;
        }
        .step-arrow {
            color: #333333;
            font-size: 14px;
        }

        .footer {
EOF;
$f = str_replace($cssFind, $cssReplace, $f);

$htmlFind = <<<'EOF'
        <!-- Main Action Buttons -->
        <a href="index.php?apk=1" class="btn-large btn-download">
            <i class="fa-solid fa-download"></i> Download App
        </a>

        <?php if (!empty($whatsappNo)): ?>
        <a href="https://wa.me/<?= preg_replace('/\\D/', '', $whatsappNo) ?>" target="_blank" rel="noopener" class="btn-large btn-whatsapp">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp
        </a>
        <?php endif; ?>
EOF;

$htmlReplace = <<<'EOF'
        <!-- 3 Steps -->
        <div class="steps-row">
            <div class="step-item"><i class="fa-solid fa-cloud-arrow-down"></i><span>Download</span></div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-item"><i class="fa-solid fa-mobile-screen"></i><span>Install</span></div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-item"><i class="fa-solid fa-gamepad"></i><span>Play</span></div>
        </div>

        <!-- Main Action Buttons -->
        <a href="index.php?apk=1" class="btn-large btn-download">
            <i class="fa-solid fa-download"></i> Download App
        </a>
EOF;

$f = str_replace($htmlFind, $htmlReplace, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
