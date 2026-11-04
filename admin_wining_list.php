<?php
require_once 'includes/db.php';
// admin_wining_list.php – Admin Wining List with Today / All Time Filter
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');

// Determine filter
$filter = $_GET['filter'] ?? 'today';
$whereClauses = ["status='Win'"];
if ($filter === 'today') {
    $whereClauses[] = "DATE(win_time)=CURDATE()";
}
$where = implode(' AND ', $whereClauses);

// DB connection & pagination
$db = db();
if ($db->connect_error) die('DB Error: '.$db->connect_error);

$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$total  = $db->query("SELECT COUNT(*) FROM wining WHERE $where")->fetch_row()[0];
$pages  = max(1, ceil($total/$limit));
$page   = min($page, $pages);
$offset = ($page-1)*$limit;
$serial = $offset + 1;

// Fetch winners
$sql = "
  SELECT id, user_id, market, session_type,
         single_digit, jodi_digit,
         single_pana, double_pana, triple_pana,
         amount, wining_balance,
         DATE_FORMAT(win_time,'%d-%b-%Y') AS win_date,
         DATE_FORMAT(win_time,'%I:%i %p') AS win_time
  FROM wining
  WHERE $where
  ORDER BY win_time DESC
  LIMIT $limit OFFSET $offset
";
$rows = $db->query($sql);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Wining List</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{--red:#e53935;--brown:#4e342e;--glass:rgba(255,255,255,.7);
           --radius:12px;--shadow:0 4px 18px rgba(0,0,0,.08);--bg:#f3f4f7;--blue:#0277bd}
    *{margin:0;padding:0;box-sizing:border-box;font-family:Poppins,Arial,sans-serif}
    body{background:var(--bg);padding:24px;min-height:100vh}
    a{text-decoration:none;color:inherit}

    .top-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
      background:var(--glass);padding:12px 20px;margin-bottom:20px;
      border-radius:var(--radius);backdrop-filter:blur(10px);box-shadow:var(--shadow)}
    .top-bar h1{font-size:20px;color:var(--brown)}
    .filters{display:flex;gap:8px;align-items:center}
    .filter-btn{padding:6px 12px;border:1px solid var(--red);border-radius:6px;
      background:#fff;color:var(--red);cursor:pointer;transition:.2s}
    .filter-btn.active{background:var(--red);color:#fff;border:none}
    #searchBox{padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:14px}

    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;min-width:1200px}
    th,td{padding:10px 8px;text-align:center;font-size:14px;cursor:pointer}
    th{background:var(--brown);color:#fff;position:relative}
    th.sorted-asc::after,th.sorted-desc::after{
      content:'';position:absolute;right:8px;top:50%;
      border:5px solid transparent}
    th.sorted-asc::after{border-bottom-color:#fff;margin-top:-2px}
    th.sorted-desc::after{border-top-color:#fff;margin-top:-8px}
    tbody tr:nth-child(even){background:#fafafa}

    .push-btn{padding:6px 12px;background:var(--blue);color:#fff;
      border:none;border-radius:6px;cursor:pointer;transition:.2s}
    .push-btn:disabled{opacity:.5;cursor:not-allowed}
    .push-btn:hover:not(:disabled){background:#025a9d}

    .pagination{display:flex;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap}
    .pagination a,.pagination span{padding:6px 12px;border-radius:6px;background:#e0d7ce;color:#333}
    .pagination .current{background:var(--brown);color:#fff;font-weight:600}
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

  <div class="top-bar">
    <h1>Wining List</h1>
    <div class="filters">
      <button id="btnToday" class="filter-btn <?= $filter==='today'?'active':'' ?>">Today</button>
      <button id="btnAll"   class="filter-btn <?= $filter==='all'?'active':'' ?>">All Time</button>
      <input type="text" id="searchBox" placeholder="Live search…">
    </div>
  </div>

  <div class="table-wrap">
    <table id="winTable">
      <thead>
        <tr>
          <th data-type="num">Sr</th>
          <th data-type="text">User ID</th>
          <th data-type="text">Market</th>
          <th data-type="text">Session</th>
          <th data-type="text">Single</th>
          <th data-type="text">Jodi</th>
          <th data-type="text">S.Pana</th>
          <th data-type="text">D.Pana</th>
          <th data-type="text">T.Pana</th>
          <th data-type="num">Amount</th>
          <th data-type="num">Winning</th>
          <th data-type="text">Date</th>
          <th data-type="text">Time</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if($rows && $rows->num_rows): while($w=$rows->fetch_assoc()): ?>
        <tr>
          <td><?= $serial++ ?></td>
          <td><?= htmlspecialchars($w['user_id']) ?></td>
          <td><?= htmlspecialchars($w['market']) ?></td>
          <td><?= htmlspecialchars(ucfirst($w['session_type'])) ?></td>
          <td><?= $w['single_digit'] ?></td>
          <td><?= $w['jodi_digit'] ?></td>
          <td><?= $w['single_pana'] ?></td>
          <td><?= $w['double_pana'] ?></td>
          <td><?= $w['triple_pana'] ?></td>
          <td><?= number_format($w['amount'],2) ?></td>
          <td><?= number_format($w['wining_balance'],2) ?></td>
          <td><?= $w['win_date'] ?></td>
          <td><?= $w['win_time'] ?></td>
          <td>
            <button class="push-btn"
                    data-user="<?= htmlspecialchars($w['user_id']) ?>">
              Push
            </button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="14">No winning data found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
  <div class="pagination">
    <?php if($page>1): ?>
      <a href="?filter=<?= $filter ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    <?php for($p=1;$p<=$pages;$p++): ?>
      <?php if($p==$page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?filter=<?= $filter ?>&page=<?= $p ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if($page<$pages): ?>
      <a href="?filter=<?= $filter ?>&page=<?= $page+1 ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<script>
// Filter buttons
document.getElementById('btnToday').onclick = function() {
  location.search = '?filter=today&page=1';
};
document.getElementById('btnAll').onclick = function() {
  location.search = '?filter=all&page=1';
};

// Live search
var searchBox = document.getElementById('searchBox');
var rows = [...document.querySelectorAll('#winTable tbody tr')];
searchBox.oninput = function(e) {
  var q = e.target.value.toLowerCase();
  rows.forEach(function(r) {
    r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
  });
};

// Column sorting
document.querySelectorAll('#winTable th[data-type]').forEach(function(th,i) {
  th.onclick = function() {
    var asc = !th.classList.contains('sorted-asc');
    document.querySelectorAll('th').forEach(h=>h.classList.remove('sorted-asc','sorted-desc'));
    th.classList.add(asc?'sorted-asc':'sorted-desc');
    var tbody = th.closest('table').querySelector('tbody');
    var typ = th.dataset.type;
    [...tbody.querySelectorAll('tr')]
      .sort(function(a,b) {
        var A = a.children[i].innerText.trim(), B = b.children[i].innerText.trim();
        if (typ==='num'){A=parseFloat(A)||0;B=parseFloat(B)||0;}
        return (A>B?1:A<B?-1:0)*(asc?1:-1);
      })
      .forEach(r=>tbody.appendChild(r));
  };
});

// AJAX push
document.querySelectorAll('.push-btn').forEach(function(btn) {
  btn.onclick = function() {
    var uid = btn.dataset.user;
    btn.disabled = true;
    fetch('user_wining.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`push_single_user=1&user_id=${encodeURIComponent(uid)}`
    })
    .then(_=>alert('Pushed '+uid))
    .catch(_=>alert('Error pushing '+uid))
    .finally(function() { return btn.disabled = false; });
  };
});
</script>

</body>
</html>
