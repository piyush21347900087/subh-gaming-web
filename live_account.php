<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
<meta charset="UTF-8">
<title>Live Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ===== Base ===== */
* {
    box-sizing: border-box;
}

body { 
    font-family: 'Segoe UI', Arial, sans-serif; 
    background: linear-gradient(135deg, #f5f0e5, #efe2cf);
    margin: 0;
    padding: 16px 10px;
    color: #263238;
}

/* ===== Container / Card ===== */
.container {
    max-width: 820px;
    margin: 10px auto;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    padding: 18px 18px 22px 18px;
    border: 1px solid #f1e4d5;
}

/* ===== Top bar ===== */
.top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: nowrap;
}

/* Left: Back button */
.back-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #b71c1c;
    color: #fff;
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 13px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 3px 10px rgba(183,28,28,0.32);
    border: none;
}
.back-btn span.arrow {
    font-size: 14px;
}
.back-btn:hover { 
    background:#9b1414; 
}

/* Center: Page title */
.page-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
    color: #3e2723;
    letter-spacing: 0.03em;
}

/* Right: Status text */
.stat-wrap {
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 600;
    color: #37474f;
    white-space: nowrap;
}
.stat-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #fff3e0;
    border: 1px solid #ffe0b2;
}
.stat-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4caf50;
}
.stat-dot.idle {
    background:#ffb300;
}
.stat-sep {
    font-size: 11px;
    color:#999;
    margin: 0 4px;
}

/* ===== Section header ===== */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 10px 2px 4px 2px;
}

.section-title {
    font-size: 14px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 999px;
    background: linear-gradient(90deg,#1b5e20,#43a047);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(27,94,32,0.35);
}

.section-title.idle {
    background: linear-gradient(90deg,#f9a825,#ffb300);
    box-shadow: 0 2px 8px rgba(251,192,45,0.45);
}

.section-title.offline {
    background: linear-gradient(90deg,#37474f,#607d8b);
    box-shadow: 0 2px 8px rgba(55,71,79,0.35);
}

.section-sub {
    font-size: 12px;
    color: #757575;
}

/* ===== Table wrapper ===== */
.table-wrapper {
    margin-top: 4px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #f0f0f0;
    box-shadow: 0 4px 14px rgba(0,0,0,0.04);
    background: #ffffff;
}

/* ===== Table ===== */
.table-modern {
    border-collapse: collapse;
    width: 100%;
}

.table-modern thead {
    background: #faf6f2;
}
.table-modern th, 
.table-modern td {
    padding: 9px 8px;
    font-size: 13px;
    text-align: center;
}
.table-modern th {
    font-weight: 700;
    color: #4e342e;
    border-bottom: 1px solid #e0d3c5;
}
.table-modern td {
    border-bottom: 1px solid #f4ede6;
    color: #424242;
}

.table-modern tbody tr:nth-child(even) td {
    background: #fffdf9;
}

/* Name cell with dot */
.name-cell {
    text-align: left;
    display: flex;
    align-items: center;
    gap: 6px;
}
.status-dot { 
    width: 11px; 
    height: 11px; 
    display:inline-block; 
    border-radius:50%; 
}
.dot-online { 
    background:#43a047; 
    box-shadow: 0 0 6px rgba(67,160,71,0.7);
}
.dot-idle {
    background:#ffb300;
    box-shadow: 0 0 6px rgba(255,193,7,0.7);
}
.dot-offline {
    background:#b0bec5;
    box-shadow: none;
}

/* Last seen */
.last-seen {
    font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 12px;
    color: #5d4037;
}

/* Empty message */
.empty-row td {
    padding: 14px 6px;
    color:#b71c1c;
    font-weight: 500;
}

/* ===== Pagination (for active live only) ===== */
.pagination {
    padding: 6px 8px 8px;
    display:flex; 
    justify-content:center; 
    gap:6px;
    background: #fff;
}

.page-btn {
    background:#f3f0eb;
    border: 1px solid #e0d7cd;
    color:#4e342e;
    font-size:12px;
    border-radius:999px;
    padding:4px 10px;
    cursor:pointer;
    font-weight:600;
    min-width: 30px;
}
.page-btn.active {
    background:#b71c1c;
    border-color:#b71c1c;
    color:#fff;
}
.page-btn:hover:not(.disabled) {
    background:#d84315;
    border-color:#d84315;
    color:#fff;
}
.page-btn.disabled {
    background: #f5f5f5;
    border-color: #e0e0e0;
    color: #9e9e9e;
    cursor: not-allowed;
    opacity: 0.6;
}

/* ===== Error Box ===== */
.error-box {
    padding: 16px 10px;
    text-align: center;
    color:#b71c1c;
    font-size: 13px;
}

/* ===== Responsive ===== */
@media(max-width: 720px){
    body {
        padding: 10px 6px;
    }
    .container{
        padding: 14px 12px 16px 12px;
        border-radius: 14px;
    }
    .top-bar {
        flex-wrap: wrap;
        row-gap: 8px;
    }
    .page-title{
        font-size:16px;
        order: 2;
        flex-basis: 100%;
    }
    .back-btn{
        font-size:12px;
        padding:6px 12px;
    }
    .stat-wrap{
        font-size:12px;
    }
    .table-modern th,
    .table-modern td{
        font-size:12px;
        padding:7px 5px;
    }
}
</style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<div class="container">

    <!-- Top Bar -->
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-btn">
            <span class="arrow">&#8592;</span>
            <span>Dashboard</span>
        </a>

        <div class="page-title">Live Users</div>

        <div class="stat-wrap">
            <span class="stat-label">
                <span class="stat-dot"></span>
                <span id="statLive">Live: 0</span>
                <span class="stat-sep">|</span>
                <span class="stat-dot idle"></span>
                <span id="statOnline">Login: 0</span>
                <span class="stat-sep">|</span>
                <span style="color:#0277bd; font-weight:700;">Today Visit:</span>
                <span id="statVisitToday" style="font-weight:700;">0</span>
                <span class="stat-sep">|</span>
                <span style="color:#e65100; font-weight:700;">Play Today:</span>
                <span id="statPlayToday" style="font-weight:700;">0</span>
            </span>
        </div>
    </div>

    <!-- Section 1: Live (Active in last 20 sec) -->
    <div class="section-header">
        <div class="section-title">Live (Active in 20 sec)</div>
        <div class="section-sub">Users active in last 20 seconds</div>
    </div>
    <div id="activeTableBox" class="table-wrapper"></div>

    <!-- Section 2: Login but not active in 20 sec -->
    <div class="section-header">
        <div class="section-title idle">Login but not active</div>
        <div class="section-sub">Online users, but no activity in last 20 seconds</div>
    </div>
    <div id="idleTableBox" class="table-wrapper"></div>

    <!-- Section 3: Offline users -->
    <div class="section-header">
        <div class="section-title offline">Offline</div>
        <div class="section-sub">Last seen time of recently offline users</div>
    </div>
    <div id="offlineTableBox" class="table-wrapper"></div>

</div>

<script>
var onlinePage = 1,
    rowsPerPage = 10;

function fetchTables() {
  fetch('ajax_live_users.php?online_page=' + onlinePage + '&rows=' + rowsPerPage)
    .then(r => r.json())
    .then(function(d) {
      if (!d || d.success === false) {
        var err = '<div class="error-box">Error loading data.</div>';
        document.getElementById('activeTableBox').innerHTML  = err;
        document.getElementById('idleTableBox').innerHTML    = err;
        document.getElementById('offlineTableBox').innerHTML = err;
        return;
      }

      var onlineList = d.online || [];
      var active = onlineList.filter(r => r.recent == 1);
      var idle   = onlineList.filter(r => !r.recent);

      // TOP BADGE: Live = active, Login = total online
      document.getElementById('statLive').textContent   = "Live: "   + active.length;
      document.getElementById('statOnline').textContent = "Login: "  + d.online_count;
      document.getElementById('statVisitToday').textContent = d.visit_today || 0;
      document.getElementById('statPlayToday').textContent  = d.play_today || 0;

      /* --------- ACTIVE TABLE ---------- */
      var actHtml = `
      <table class="table-modern">
        <thead>
          <tr>
            <th style="width:52px;">#</th>
            <th style="width:110px;">User ID</th>
            <th>Name</th>
            <th style="width:160px;">Last Seen</th>
          </tr>
        </thead>
        <tbody>`;

      if (!active.length) {
        actHtml += `<tr class="empty-row">
                      <td colspan="4">No users active in last 20 seconds</td>
                    </tr>`;
      } else {
        active.forEach(function(r, i) {
          actHtml += `
          <tr>
            <td>${d.online_start + i + 1}</td>
            <td>${escapeHTML(r.user_id)}</td>
            <td>
              <div class="name-cell">
                <span class="status-dot dot-online"></span>
                <span>${escapeHTML(r.name)}</span>
              </div>
            </td>
            <td><span class="last-seen">${escapeHTML(r.last_seen)}</span></td>
          </tr>`;
        });
      }
      actHtml += `</tbody></table>`;
      actHtml += makePagination(d.online_pages, onlinePage);
      document.getElementById('activeTableBox').innerHTML = actHtml;

      /* --------- IDLE TABLE ---------- */
      var idleHtml = `
      <table class="table-modern">
        <thead>
          <tr>
            <th style="width:52px;">#</th>
            <th style="width:110px;">User ID</th>
            <th>Name</th>
            <th style="width:160px;">Last Seen</th>
          </tr>
        </thead>
        <tbody>`;

      if (!idle.length) {
        idleHtml += `<tr class="empty-row">
                      <td colspan="4">No logged-in users idle for more than 20 seconds</td>
                    </tr>`;
      } else {
        idle.forEach(function(r, i) {
          idleHtml += `
          <tr>
            <td>${i + 1}</td>
            <td>${escapeHTML(r.user_id)}</td>
            <td>
              <div class="name-cell">
                <span class="status-dot dot-idle"></span>
                <span>${escapeHTML(r.name)}</span>
              </div>
            </td>
            <td><span class="last-seen">${escapeHTML(r.last_seen)}</span></td>
          </tr>`;
        });
      }
      idleHtml += `</tbody></table>`;
      document.getElementById('idleTableBox').innerHTML = idleHtml;

      /* --------- OFFLINE TABLE ---------- */
      var offHtml = `
      <table class="table-modern">
        <thead>
          <tr>
            <th style="width:52px;">#</th>
            <th style="width:110px;">User ID</th>
            <th>Name</th>
            <th style="width:160px;">Last Seen (Offline)</th>
          </tr>
        </thead>
        <tbody>`;

      var offline = d.offline || [];
      if (!offline.length) {
        offHtml += `<tr class="empty-row">
                      <td colspan="4">No recent offline users</td>
                    </tr>`;
      } else {
        offline.forEach(function(r, i) {
          offHtml += `
          <tr>
            <td>${i + 1}</td>
            <td>${escapeHTML(r.user_id)}</td>
            <td>
              <div class="name-cell">
                <span class="status-dot dot-offline"></span>
                <span>${escapeHTML(r.name)}</span>
              </div>
            </td>
            <td><span class="last-seen">${escapeHTML(r.last_seen)}</span></td>
          </tr>`;
        });
      }
      offHtml += `</tbody></table>`;
      document.getElementById('offlineTableBox').innerHTML = offHtml;
    })
    .catch(function() {
      var err = '<div class="error-box">Error loading data.</div>';
      document.getElementById('activeTableBox').innerHTML  = err;
      document.getElementById('idleTableBox').innerHTML    = err;
      document.getElementById('offlineTableBox').innerHTML = err;
    });
}

function escapeHTML(txt){
  return String(txt).replace(/[&<>"']/g, m => (
    {"&":"&amp;","<":"&lt;","</":"&lt;/",">":"&gt;",'"':"&quot;","'":"&#39;"}[m] || m
  ));
}

function makePagination(pages, current){
  if(!pages || pages <= 1) return "";
  var html = `<div class="pagination">`;
  
  // Previous button
  if (current > 1) {
    html += `<button class="page-btn" onclick="onlinePage=${current - 1};fetchTables();" title="Previous Page">&laquo;</button>`;
  } else {
    html += `<button class="page-btn disabled" disabled>&laquo;</button>`;
  }
  
  // Sliding window of 5 pages
  var start = Math.max(1, current - 2);
  var end = Math.min(pages, start + 4);
  if (end - start < 4) {
    start = Math.max(1, end - 4);
  }
  
  for(var i = start; i <= end; i++){
    html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="onlinePage=${i};fetchTables();">${i}</button>`;
  }
  
  // Next button
  if (current < pages) {
    html += `<button class="page-btn" onclick="onlinePage=${current + 1};fetchTables();" title="Next Page">&raquo;</button>`;
  } else {
    html += `<button class="page-btn disabled" disabled>&raquo;</button>`;
  }
  
  html += `</div>`;
  return html;
}

fetchTables();
setInterval(fetchTables, 5000);
</script>

</body>
</html>
