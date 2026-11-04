<?php
require_once 'process_starline4_wins.php';
$STARLINE4_GAME_TYPE = 'DOUBLE_PANA';
$STARLINE4_GAME_LABEL = 'Double Pana';
$STARLINE4_GAME_COLUMN = 'double_pana';
$STARLINE4_BULK = true;
$digits = [1,2,3,4,5,6,7,8,9,0];
$panas = [];
for ($i=0;$i<count($digits);$i++) {
    for ($j=0;$j<count($digits);$j++) {
        if ($i !== $j) {
            $p = [$digits[$i], $digits[$i], $digits[$j]];
            $str = implode('', $p);
            if (!in_array($str, $panas, true)) $panas[] = $str;
        }
    }
}
$STARLINE4_NUMBERS = $panas;
require 'starline4_game_entry.php';
