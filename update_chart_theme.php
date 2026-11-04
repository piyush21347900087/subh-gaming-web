<?php
$dir = __DIR__ . '/charts';
$files = glob($dir . '/*_full_chart.php');
$files[] = __DIR__ . '/create_new_market.php'; // Also update the template

$newStyle = <<<CSS
    <style>
        :root {
            --primary: #000081; /* Blue */
            --accent: #ff005c; /* Pink */
            --bg: #f8f9fa;
            --text: #1a1a24;
            --border: rgba(0, 0, 0, 0.08);
            --surface: #ffffff;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); padding: 20px 10px; overflow-x: hidden; }
        
        .header { text-align: center; margin-bottom: 25px; padding: 10px; }
        .header h1 { font-size: 28px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); text-shadow: 0 2px 4px rgba(0,0,129,0.1); }
        .header span { background: var(--accent); color: #fff; padding: 4px 12px; border-radius: 8px; font-size: 16px; box-shadow: 0 4px 10px rgba(255, 0, 92, 0.2); }
        
        .container { max-width: 1100px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.06); }
        
        /* Table Wrap enables smooth horizontal scrolling on mobile */
        .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 600px; }
        
        th { background: var(--primary); color: #ffffff; font-size: 12px; font-weight: 800; padding: 14px 4px; border: 1px solid rgba(255,255,255,0.15); text-transform: uppercase; letter-spacing: 0.5px; }
        td { border: 1px solid var(--border); text-align: center; padding: 10px 2px; vertical-align: middle; background: #fff; transition: background 0.2s; }
        tr:hover td { background: #fafbfc; }
        
        .date-cell { font-size: 11px; font-weight: 800; color: var(--primary); background: #f4f6f9; width: 16%; line-height: 1.4; border-right: 2px solid var(--border); }
        .pana { font-size: 10px; color: #6c757d; display: block; font-weight: 600; line-height: 1.2; margin: 2px 0; }
        .jodi { font-size: 16px; font-weight: 900; color: var(--text); display: block; margin: 2px 0; line-height: 1.1; }
        .sat .jodi, .sun .jodi { color: var(--accent); }
        
        .footer { text-align: center; margin-top: 40px; padding: 20px; font-size: 12px; font-weight: 700; color: #adb5bd; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Full Responsive Tweaks */
        @media (max-width: 768px) {
            body { padding: 12px 6px; }
            .header h1 { font-size: 22px; }
            .header span { font-size: 14px; }
            .container { border-radius: 12px; }
            table { min-width: 500px; } /* Ensures elements don't get squashed */
            th { font-size: 10px; padding: 10px 2px; }
            td { padding: 8px 1px; }
            .date-cell { font-size: 10px; }
            .jodi { font-size: 14px; }
            .pana { font-size: 9px; }
        }
  <?php if(isset(\$_GET['type']) && \$_GET['type'] === 'jodi') echo '.pana { display: none !important; }'; ?>
    </style>
CSS;

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // Replace everything between <style> and </style>
    $newContent = preg_replace('/<style>.*?<\/style>/s', $newStyle, $content);
    file_put_contents($file, $newContent);
    echo "Updated: " . basename($file) . "\n";
}
echo "Theme update completed.\n";
?>
