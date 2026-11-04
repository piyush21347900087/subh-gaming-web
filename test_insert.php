<?php require "includes/db.php"; $db=db(); 
$uidNorm = "DPD1DYH"; $msg = "Test message"; $imagePath = null;
$stmt = $db->prepare("INSERT INTO chat_messages(user_id,sender,message,image,is_edited,ts) VALUES(?, 'admin', ?, ?, 0, NOW())");
$stmt->bind_param("sss", $uidNorm, $msg, $imagePath);
if($stmt->execute()){ echo "INSERTED! ID: " . $stmt->insert_id; } else { echo "ERROR: " . $stmt->error; }
?>
