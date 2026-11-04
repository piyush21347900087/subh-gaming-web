<?php
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_login.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_register.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_forgot_password.php'
];

$replacements = [
    '<title>Login | SUBH GAMING</title>' => '<title><?= __("Login") ?> | SUBH GAMING</title>',
    '<title>Register | SUBH GAMING</title>' => '<title><?= __("Register") ?> | SUBH GAMING</title>',
    '<title>Forgot Password | SHREE JI MATKA</title>' => '<title><?= __("Forgot Password") ?> | SUBH GAMING</title>',
    
    '<label class="label">Mobile Number</label>' => '<label class="label"><?= __("Mobile Number") ?></label>',
    'placeholder="10 Digit Number"' => 'placeholder="<?= __("10 Digit Number") ?>"',
    '<label class="label">4 Digit Password</label>' => '<label class="label"><?= __("4 Digit Password") ?></label>',
    'placeholder="4-Digit Password"' => 'placeholder="<?= __("4-Digit Password") ?>"',
    '>Forgot?</a>' => '><?= __("Forgot?") ?></a>',
    '>Login Now</button>' => '><?= __("Login Now") ?></button>',
    'Don\'t have an account?' => '<?= __("Don\'t have an account?") ?>',
    '>Register Here</a>' => '><?= __("Register Here") ?></a>',
    
    'placeholder="Mobile Number"' => 'placeholder="<?= __("Mobile Number") ?>"',
    '>GET OTP</button>' => '><?= __("GET OTP") ?></button>',
    'placeholder="Full Name"' => 'placeholder="<?= __("Full Name") ?>"',
    '>REGISTER NOW</button>' => '><?= __("REGISTER NOW") ?></button>',
    'Already have an account?' => '<?= __("Already have an account?") ?>',
    '>Login here</a>' => '><?= __("Sign In") ?></a>', // If "Login here" isn't translated, use Sign In or just keep it
    'placeholder="Enter 4-Digit OTP"' => 'placeholder="<?= __("Enter 4-Digit OTP") ?>"',
    'placeholder="4-Digit OTP"' => 'placeholder="<?= __("4-Digit OTP") ?>"',
    '>VERIFY & CONTINUE</button>' => '><?= __("VERIFY & CONTINUE") ?></button>',
    
    '>LOGIN NOW</a>' => '><?= __("LOGIN NOW") ?></a>',
    '>VERIFY OTP</button>' => '><?= __("VERIFY OTP") ?></button>',
    'Remember password?' => '<?= __("Remember password?") ?>',
    '>Sign In</a>' => '><?= __("Sign In") ?></a>'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    if (strpos($content, 'includes/lang.php') === false) {
        $content = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $content, 1);
    }
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "Auth files updated.";
