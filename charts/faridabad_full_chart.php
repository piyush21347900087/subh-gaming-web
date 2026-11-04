<?php
require_once '../includes/db.php';
/* Gali Market Chart Page */
/* RK_GALI_CHART_V7 */
session_start();

$db = db();
if ($db->connect_error) die('DB error');
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

$marketName = 'FARIDABAD';

/* Monthly Filter */
$selectedMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');
[$selYear, $selMonth] = array_map('intval', explode('-', $selectedMonth));
$firstDay = sprintf('%04d-%02d-01', $selYear, $selMonth);
$lastDay  = date('Y-m-t', strtotime($firstDay));
$monthLabel = date('F Y', strtotime($firstDay));

$daysInMonth = (int)date('t', strtotime($firstDay));

/* Fetch Results */
$allResults = [];
$stmt = $db->prepare("SELECT result_date, jodi FROM gali_result WHERE market = ? AND result_date BETWEEN ? AND ?");
$stmt->bind_param('sss', $marketName, $firstDay, $lastDay);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $allResults[$row['result_date']] = $row['jodi'];
}
$stmt->close();
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($marketName) ?> Chart</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary-pink: #ff005c;
      --primary-bg: #f8f9fb;
      --card-bg: #ffffff;
      --text-main: #333333;
      --text-dim: #666666;
      --border-radius: 16px;
      --shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background-color: var(--primary-bg); color: var(--text-main); display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding-bottom: 30px; }
    
    .header-bar {
      width: 100%; max-width: 480px; padding: 12px 15px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      background-color: var(--primary-pink);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .back-btn { color: #ffffff; font-size: 20px; text-decoration: none; width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
    .back-btn:hover { background: rgba(255, 255, 255, 0.1); }
    .app-logo { height: 16px; width: auto; filter: brightness(0) invert(1); }

    .container { width: 100%; max-width: 480px; padding: 15px; flex: 1; }
    
    .page-title { 
      font-size: 15px; font-weight: 800; color: #ffffff; 
      background: linear-gradient(135deg, var(--primary-pink), #ff4b82);
      padding: 12px; border-radius: 12px; margin-bottom: 15px; 
      text-transform: uppercase; box-shadow: var(--shadow);
      text-align: center;
    }

    .details-box {
      background: var(--card-bg); margin-bottom: 15px; box-shadow: var(--shadow);
      padding: 15px; border-radius: var(--border-radius); border: 1px solid rgba(0,0,0,0.03); text-align: center;
    }
    .section-header {
      font-size: 13px; font-weight: 700; color: var(--text-dim); margin-bottom: 10px;
      text-transform: uppercase; letter-spacing: 0.5px;
    }

    input[type="month"] { 
      padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 20px; 
      font-size: 14px; width: 100%; max-width: 200px; outline: none; text-align: center;
      font-weight: 600; color: var(--text-main); background: #f8f9fb;
      transition: border-color 0.2s;
    }
    input[type="month"]:focus { border-color: var(--primary-pink); }

    .table-container {
      background: var(--card-bg); box-shadow: var(--shadow);
      border-radius: var(--border-radius); border: 1px solid rgba(0,0,0,0.03);
      overflow: hidden; margin-top: 15px; width: 100%;
    }

    table { width: 100%; border-collapse: collapse; }
    thead th { 
      background: var(--primary-pink); color: #fff; font-size: 13px; font-weight: 700; padding: 12px 10px; 
      text-align: center; text-transform: uppercase;
    }
    tbody td { 
      padding: 10px; text-align: center; font-size: 14px; font-weight: 700;
      color: var(--text-main); border-bottom: 1px solid #f0f2f5;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:nth-child(even) { background: #fafbfc; }
    
    .date-cell { background: #f8f9fb; color: var(--text-dim); width: 60px; font-weight: 600; }
    .sat-sun { background: rgba(255, 0, 92, 0.02) !important; }
    .sat-sun .date-cell { color: var(--primary-pink); }
    .sat-sun td { color: var(--primary-pink); }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<header class="header-bar">
  <a href="../gali_desawar.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
  <img src="../images/logo.png" alt="Logo" class="app-logo">
  <div style="width:24px"></div>
</header>

<div class="container">
  <div class="page-title"><?php echo htmlspecialchars($monthLabel); ?> – <?php echo htmlspecialchars($marketName); ?></div>

  <div class="details-box">
    <div class="section-header">Select Month</div>
    <form method="get">
      <input type="month" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>" max="<?php echo date('Y-m'); ?>" onchange="this.form.submit()">
    </form>
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th class="date-cell">Date</th>
          <th>Result</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
            $dateStr = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
            $dayOfWeek = date('N', strtotime($dateStr));
            $isWeekend = ($dayOfWeek >= 6);
            $val = $allResults[$dateStr] ?? '--';
        ?>
          <tr class="<?php echo $isWeekend ? 'sat-sun' : ''; ?>">
            <td class="date-cell"><?php echo sprintf('%02d', $d); ?></td>
            <td><?php echo htmlspecialchars($val); ?></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>