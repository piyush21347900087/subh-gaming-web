<?php
$f = file_get_contents("index.php");

$pixelCode = <<<'EOF'
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1683896666505809');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=1683896666505809&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
EOF;

$f = str_replace("</head>", $pixelCode, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
