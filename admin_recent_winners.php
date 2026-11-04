<?php
require_once 'includes/db.php';
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');
$db = db();
$msg = '';

// Create table if not exists
$db->query("CREATE TABLE IF NOT EXISTS recent_winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20),
    market_name VARCHAR(100),
    win_amount DECIMAL(10, 2),
    game_type VARCHAR(50) DEFAULT '',
    session VARCHAR(50) DEFAULT '',
    is_fake TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM recent_winners WHERE id=$id");
    $msg = "Entry deleted.";
}

// Handle random generation
if (isset($_POST['generate_random'])) {
    $game_types = ['Haruf Andar', 'Haruf Bahar', 'Single Digit', 'Jodi', 'Single Pana'];
    
    $hindu_m = ['Rahul', 'Amit', 'Rohan', 'Vikas', 'Sunil', 'Karan', 'Deepak', 'Suresh', 'Ramesh', 'Ravi', 'Ajay', 'Vijay', 'Manoj', 'Anil', 'Rajesh', 'Sanjay', 'Santosh', 'Raj', 'Mukesh', 'Dinesh', 'Vinod', 'Ashok', 'Prakash', 'Mahesh', 'Sandeep', 'Nitin', 'Naveen', 'Arun', 'Pawan', 'Gaurav', 'Manish', 'Sachin', 'Sonu', 'Monu', 'Rakesh', 'Kamal', 'Jatin', 'Vishal', 'Tarun'];
    $muslim_m = ['Imran', 'Salman', 'Rizwan', 'Tariq', 'Aamir', 'Nadeem', 'Zeeshan', 'Farhan', 'Saqib', 'Bilal', 'Asif', 'Sohail', 'Sameer', 'Rehan', 'Faisal', 'Shoaib', 'Zakir', 'Irfan', 'Javed', 'Sahil', 'Wasim'];
    $sikh_m = ['Gurpreet', 'Manpreet', 'Harpreet', 'Jaspreet', 'Navdeep', 'Amandeep', 'Sukhdev', 'Balwinder', 'Paramjit', 'Kuldeep'];
    $christian_m = ['John', 'David', 'Joseph', 'Peter', 'Thomas', 'Michael', 'Kevin', 'Richard', 'Anthony', 'Victor'];

    $generate_combos = function($names) {
        $arr = [];
        foreach ($names as $n) {
            for ($c = 65; $c <= 90; $c++) {
                $arr[] = $n . ' ' . chr($c) . '.';
            }
        }
        shuffle($arr);
        return $arr;
    };

    $selected_names = array_merge(
        array_slice($generate_combos($hindu_m), 0, 270),
        array_slice($generate_combos($muslim_m), 0, 10),
        array_slice($generate_combos($sikh_m), 0, 10),
        array_slice($generate_combos($christian_m), 0, 10)
    );
    shuffle($selected_names);
    
    for ($i = 0; $i < 300; $i++) {
        $mob = $selected_names[$i];
        $mkt = ""; // Market removed
        // Generate random amount between 95 and 89,000
        $randType = mt_rand(1, 10);
        if ($randType <= 7) {
            $options = [95, 190, 200, 500, 950, 1000, 1500, 1900];
            $amt = $options[array_rand($options)];
        } else if ($randType <= 9) {
            $options = [2000, 2500, 3000, 4500, 5000, 7500, 9000, 10000, 12000, 15000];
            $amt = $options[array_rand($options)];
        } else {
            $options = [18000, 20000, 25000, 30000, 45000, 50000, 75000, 89000];
            $amt = $options[array_rand($options)];
        } 
        
        $db->prepare("INSERT INTO recent_winners (name, market_name, win_amount, game_type, session, is_fake) VALUES (?, ?, ?, '', '', 1)")
            ->execute([$mob, $mkt, $amt]);
    }
    $msg = "Generated 300 random winners.";
}

// Handle manual entry
if (isset($_POST['add_manual'])) {
    $mob = trim($_POST['name']);
    $mkt = ""; // Market removed
    $amt = (float)$_POST['win_amount'];
    
    if($mob && $amt > 0) {
        $db->prepare("INSERT INTO recent_winners (name, market_name, win_amount, game_type, session, is_fake) VALUES (?, ?, ?, '', '', 1)")
            ->execute([$mob, $mkt, $amt]);
        $msg = "Manual entry added.";
    }
}

// Fetch all winners
$res = $db->query("SELECT * FROM recent_winners ORDER BY id DESC");
$winners = $res->fetch_all(MYSQLI_ASSOC);

/* 
 Real-time Winners Logic:
 Since the table 'wining' exists, we could pull real ones if needed.
 However, for the UI design requested, usually admin prefers to control a mix of 'fake' ones and maybe some 'real' ones.
 For now, we managed the recent_winners table.
*/

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Winners Management | BABA JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f0e5; margin: 0; padding: 20px; color: #3e2723; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 25px; border-radius: 18px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border-left: 5px solid #ffb300; }
        h2 { border-bottom: 2px solid #ffb300; padding-bottom: 10px; color: #3e2723; }
        .msg { padding: 10px; background: #e8f5e9; color: #2e7d32; border-radius: 8px; margin-bottom: 15px; border: 1px solid #a5d6a7; }
        .form-row { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        input, select { padding: 10px; border-radius: 8px; border: 1px solid #eee8db; flex: 1; min-width: 120px; outline: none; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: opacity 0.2s; }
        .btn-blue { background: #5c6bc0; color: white; }
        .btn-green { background: #2e7d32; color: white; }
        .btn-red { background: #c62828; color: white; }
        .btn:hover { opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 14px; border-bottom: 1px solid #f5f0e5; }
        th { background: #fbf9f2; color: #795548; font-weight: 800; text-transform: uppercase; font-size: 11px; }
        .masked { font-family: monospace; font-weight: 700; }
        .amt { color: #2e7d32; font-weight: 800; }
        .back-btn { display: inline-block; margin-bottom: 20px; color: #ffb300; text-decoration: none; font-weight: 800; font-size: 14px; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h2><i class="fas fa-trophy"></i> Recent Winners Management</h2>
        
        <?php if($msg): ?>
            <div class="msg"><?= $msg ?></div>
            <?php if(isset($_POST['generate_random'])): ?>
            <script>
                setTimeout(function() {
                    window.location.href = 'admin_recent_winners.php';
                }, 3000);
            </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="margin-bottom: 25px;">
            <form method="POST" style="display:inline-block; margin-right:10px;">
                <button type="submit" name="generate_random" class="btn btn-blue"><i class="fas fa-magic"></i> Generate 300 Random Winners</button>
            </form>
            <p style="font-size: 0.9em; color: #666;">Use this to daily rotate winners with realistic names and amounts.</p>
        </div>
        
        <hr>
        
        <h3>Add Manual Entry</h3>
        <form method="POST" class="form-row">
            <input type="text" name="name" placeholder="Winner Name" required>
            <input type="number" name="win_amount" placeholder="Amount" required>
            <button type="submit" name="add_manual" class="btn btn-green"><i class="fas fa-plus"></i> Add</button>
        </form>
        
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Amount</th>
                    <th>Source</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($winners as $w): ?>
                <tr>
                    <td class="masked"><?= htmlspecialchars($w['name']) ?></td>
                    <td class="amt">₹<?= number_format($w['win_amount']) ?></td>
                    <td><span style="color: #666; font-size: 11px;"><?= $w['is_fake'] ? 'Random' : 'Real' ?></span></td>
                    <td><a href="?delete=<?= $w['id'] ?>" class="btn btn-red" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm('Silencer delete this?')"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
