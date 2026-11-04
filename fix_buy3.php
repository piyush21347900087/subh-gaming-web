<?php
$c = file_get_contents("buy.php");
if (strpos($c, "function get_bonus_amount") === false) {
    $f = "function get_bonus_amount(mysqli \$db, float \$amount): float {
        try {
            \$enabled = (int)(\$db->query(\"SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1\")->fetch_row()[0] ?? 1);
            if (\$enabled !== 1) return 0.0;
            \$stmt = \$db->prepare(\"SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1\");
            \$stmt->bind_param('d', \$amount);
            \$stmt->execute();
            \$stmt->bind_result(\$bonus);
            \$out = \$stmt->fetch() ? (float)\$bonus : 0.0;
            \$stmt->close();
            return \$out;
        } catch (Throwable \$e) { return 0.0; }
    }
";
    $c = str_replace("<?php", "<?php\n" . $f, $c);
    file_put_contents("buy.php", $c);
    echo "Added get_bonus_amount to buy.php\n";
}
