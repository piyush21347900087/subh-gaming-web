<?php
require_once 'process_starline4_wins.php';
$STARLINE4_GAME_TYPE = 'SINGLE_PANA';
$STARLINE4_GAME_LABEL = 'Single Pana';
$STARLINE4_GAME_COLUMN = 'single_pana';
$STARLINE4_BULK = true;
$digits = [1,2,3,4,5,6,7,8,9,0];
$panas = [];
for ($i=0;$i<count($digits);$i++) {
    for ($j=$i+1;$j<count($digits);$j++) {
        for ($k=$j+1;$k<count($digits);$k++) {
            $panas[] = $digits[$i].$digits[$j].$digits[$k];
        }
    }
}
$STARLINE4_NUMBERS = $panas;
require 'starline4_game_entry.php';
