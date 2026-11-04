<?php
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_profile.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bid_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/how_to_play.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/game_rates.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/notice_board.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_result.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Add include if missing
    if (strpos($content, 'lang.php') === false && strpos($content, '<?php') !== false) {
        $content = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $content, 1);
    }
    
    // Translate common titles and texts
    $replacements = [
        '<div class="header-title-flash">My Profile</div>' => '<div class="header-title-flash"><?= __("My Profile") ?></div>',
        '<div class="header-title-flash">Wallet</div>' => '<div class="header-title-flash"><?= __("Wallet") ?></div>',
        '<div class="header-title-flash">Funds</div>' => '<div class="header-title-flash"><?= __("Funds") ?></div>',
        '<div class="header-title-flash">Bank Details</div>' => '<div class="header-title-flash"><?= __("Bank Details") ?></div>',
        '<div class="header-title-flash">Winning History</div>' => '<div class="header-title-flash"><?= __("Winning History") ?></div>',
        '<div class="header-title-flash">Bid History</div>' => '<div class="header-title-flash"><?= __("Bid History") ?></div>',
        '<div class="header-title-flash">Add Money</div>' => '<div class="header-title-flash"><?= __("Add Money") ?></div>',
        '<div class="header-title-flash">Withdraw Money</div>' => '<div class="header-title-flash"><?= __("Withdraw Money") ?></div>',
        '<div class="header-title-flash">How to Play</div>' => '<div class="header-title-flash"><?= __("How to Play") ?></div>',
        '<div class="header-title-flash">Game Rates</div>' => '<div class="header-title-flash"><?= __("Game Rates") ?></div>',
        '<div class="header-title-flash">Notice Board</div>' => '<div class="header-title-flash"><?= __("Notice Board") ?></div>',
        
        // Also simple labels
        '>Name<' => '><?= __("Name") ?><',
        '>Mobile Number<' => '><?= __("Mobile Number") ?><',
        '>Email<' => '><?= __("Email") ?><',
        '>Address<' => '><?= __("Address") ?><',
        '>City<' => '><?= __("City") ?><',
        '>Pin Code<' => '><?= __("Pin Code") ?><',
        '>Save Profile<' => '><?= __("Save Profile") ?><',
        
        '>Account Number<' => '><?= __("Account Number") ?><',
        '>Bank Name<' => '><?= __("Bank Name") ?><',
        '>IFSC Code<' => '><?= __("IFSC Code") ?><',
        '>Account Holder Name<' => '><?= __("Account Holder Name") ?><',
        '>Save Bank Details<' => '><?= __("Save Bank Details") ?><',
        
        '<th>Date</th>' => '<th><?= __("Date") ?></th>',
        '<th>Amount</th>' => '<th><?= __("Amount") ?></th>',
        '<th>Status</th>' => '<th><?= __("Status") ?></th>',
        '<th>Session</th>' => '<th><?= __("Session") ?></th>',
        '<th>Market</th>' => '<th><?= __("Market") ?></th>',
        '<th>Type</th>' => '<th><?= __("Type") ?></th>'
    ];
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}

echo "Inner pages translated.";
