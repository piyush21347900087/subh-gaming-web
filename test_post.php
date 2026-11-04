<?php 
session_start();
$_SESSION["admin"] = 1;
$_POST = ["action" => "send", "uid" => "DPD1DYH", "message" => "Hello from API!"];
require "admin_live_chat.php";
?>
