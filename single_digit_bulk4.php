<?php
require_once 'process_starline4_wins.php';
$STARLINE4_GAME_TYPE = 'SINGLE_DIGIT';
$STARLINE4_GAME_LABEL = 'Single Digit';
$STARLINE4_GAME_COLUMN = 'single_digit';
$STARLINE4_BULK = true;
$STARLINE4_NUMBERS = array_map('strval', range(0, 9));
require 'starline4_game_entry.php';
