<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin']))
    die("Access Denied");
$db = db();
// Auto-add wa_count & click column if missing
$checkCol = $db->query("SHOW COLUMNS FROM matka_data LIKE 'wa_count'");
if ($checkCol->num_rows == 0) {
    $db->query("ALTER TABLE matka_data ADD COLUMN wa_count INT DEFAULT 0");
}
$checkClick = $db->query("SHOW COLUMNS FROM matka_data LIKE 'click'");
if ($checkClick->num_rows == 0) {
    $db->query("ALTER TABLE matka_data ADD COLUMN click INT DEFAULT 0");
}
// AJAX: Powerful Multi-Keyword Full Database Search
if (isset($_GET['ajax_search'])) {
    $raw = trim($_GET['ajax_search']);
    $keywords = array_filter(array_map('trim', preg_split('/\s+/', $raw)));

    if (empty($keywords)) { echo json_encode([]); exit; }

    $conditions = [];
    foreach ($keywords as $kw) {
        $safe = $db->real_escape_string($kw);
        $conditions[] = "(name LIKE '%$safe%' OR mobile_no LIKE '%$safe%' OR source LIKE '%$safe%' OR remark LIKE '%$safe%' OR sr LIKE '%$safe%' OR id LIKE '%$safe%')";
    }
    $where = implode(' AND ', $conditions);

    $firstKw = $db->real_escape_string($keywords[0]);
    $sql = "SELECT *, CASE 
        WHEN name = '$firstKw' THEN 1
        WHEN name LIKE '$firstKw%' THEN 2
        WHEN name LIKE '%$firstKw%' THEN 3
        WHEN mobile_no LIKE '%$firstKw%' THEN 4
        ELSE 5 END AS _relevance
        FROM matka_data WHERE $where ORDER BY _relevance ASC, id DESC LIMIT 500";

    $res = $db->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) {
        unset($r['_relevance']);
        $out[] = $r;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}


// AJAX: Main Search Auto-Suggest
if (isset($_GET['search_suggest'])) {
    $q = $db->real_escape_string(trim($_GET['search_suggest']));
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $res = $db->query("SELECT id, name, mobile_no, source FROM matka_data 
        WHERE name LIKE '%$q%' OR mobile_no LIKE '%$q%' OR source LIKE '%$q%'
        ORDER BY 
            CASE WHEN name LIKE '$q%' THEN 1 WHEN mobile_no LIKE '$q%' THEN 2 ELSE 3 END, id DESC
        LIMIT 8");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: Mobile Suggestion
if (isset($_GET['suggest_mobile'])) {
    $q = $db->real_escape_string($_GET['suggest_mobile']);
    $res = $db->query("SELECT DISTINCT name, mobile_no, source, remark FROM matka_data WHERE mobile_no LIKE '%$q%' OR name LIKE '%$q%' LIMIT 5");
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    echo json_encode($out);
    exit;
}
$msg = "";

// Helper to check for duplicate mobile
function isDuplicate($db, $mobile)
{
    if (!$mobile)
        return false;
    $res = $db->prepare("SELECT id FROM matka_data WHERE mobile_no = ? LIMIT 1");
    $res->bind_param("s", $mobile);
    $res->execute();
    $res->store_result();
    $count = $res->num_rows;
    $res->close();
    return $count > 0;
}

// 1. ADD / UPDATE
if (isset($_POST['save_record'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $mobile = $_POST['mobile_no'] ?? '';
    $main_source = $_POST['source_main'] ?? '';
    $manual_source = $_POST['source_manual'] ?? '';
    $source = ($main_source == 'manual') ? $manual_source : $main_source;
    $remark = $_POST['remark'] ?? '';
    if ($id) {
        $st = $db->prepare("UPDATE matka_data SET name=?, mobile_no=?, source=?, remark=? WHERE id=?");
        $st->bind_param("ssssi", $name, $mobile, $source, $remark, $id);
        if ($st->execute())
            $msg = "Updated!";
        $st->close();
    } else {
        if (isDuplicate($db, $mobile)) {
            $msg = "Error: This mobile number ($mobile) already exists!";
        } else {
            $resSr = $db->query("SELECT IFNULL(MAX(CAST(id AS UNSIGNED)), 0)+1 AS n FROM matka_data");
            $nsr = $resSr->fetch_assoc()['n'];
            $st = $db->prepare("INSERT INTO matka_data (sr, name, mobile_no, source, remark) VALUES (?,?,?,?,?)");
            $st->bind_param("sssss", $nsr, $name, $mobile, $source, $remark);
            if ($st->execute())
                $msg = "Saved!";
            $st->close();
        }
    }
}

// 1.5 BULK ADD FROM PREVIEW (Now always allows, but user can manage later)
if (isset($_POST['bulk_add_preview'])) {
    $count = 0;
    if (isset($_POST['is_checked'])) {
        foreach ($_POST['is_checked'] as $idx => $val) {
            $name = $_POST['scan_name'][$idx] ?? '';
            $mobile = $_POST['scan_mobile'][$idx] ?? '';
            $source = $_POST['scan_source'][$idx] ?? '';
            $remark = $_POST['scan_remark'][$idx] ?? '';
            if (empty($mobile))
                continue;
            $resSr = $db->query("SELECT IFNULL(MAX(CAST(id AS UNSIGNED)), 0)+1 AS n FROM matka_data");
            $nsr = $resSr->fetch_assoc()['n'];
            $st = $db->prepare("INSERT INTO matka_data (sr, name, mobile_no, source, remark) VALUES (?,?,?,?,?)");
            $st->bind_param("sssss", $nsr, $name, $mobile, $source, $remark);
            if ($st->execute())
                $count++;
            $st->close();
        }
    }
    $msg = "Added $count records!";
}

// 1.6 DELETE / BULK DELETE
if (isset($_POST['bulk_delete']) && isset($_POST['ids'])) {
    $db->query("DELETE FROM matka_data WHERE id IN (" . implode(',', array_map('intval', $_POST['ids'])) . ")");
    $msg = count($_POST['ids']) . " deleted!";
}
if (isset($_GET['delete'])) {
    $db->query("DELETE FROM matka_data WHERE id=" . (int) $_GET['delete']);
    $msg = "Deleted!";
}

// AJAX: Get Duplicate Data
if (isset($_GET['get_duplicates'])) {
    $query = "SELECT * FROM matka_data WHERE mobile_no IN (SELECT mobile_no FROM matka_data GROUP BY mobile_no HAVING COUNT(*) > 1) ORDER BY mobile_no, id DESC";
    $dupes = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    echo json_encode($dupes);
    exit;
}

// AJAX: Get Invalid Data (Blank or has letters)
if (isset($_GET['get_invalid'])) {
    $query = "SELECT * FROM matka_data WHERE mobile_no = '' OR mobile_no IS NULL OR mobile_no REGEXP '[a-zA-Z]' ORDER BY id DESC";
    $invalid = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    echo json_encode($invalid);
    exit;
}

// AJAX: Fix All Duplicates (Keep latest, delete old)
if (isset($_GET['fix_all_duplicates'])) {
    $db->query("DELETE t1 FROM matka_data t1 INNER JOIN matka_data t2 WHERE t1.id < t2.id AND t1.mobile_no = t2.mobile_no");
    echo json_encode(['status' => 'success', 'msg' => 'All duplicates cleaned!']);
    exit;
}

// AJAX: Track WA message sent (Increments both wa_count and click)
if (isset($_GET['track_wa'])) {
    $id = (int) $_GET['track_wa'];
    $db->query("UPDATE matka_data SET wa_count = wa_count + 1, click = click + 1 WHERE id = $id");
    echo json_encode(['status' => 'success']);
    exit;
}

// 2. DOWNLOAD HANDLER (Excel, PDF, HTML, TXT)
if (isset($_GET['export'])) {
    $f = $_GET['export'];
    $res = $db->query("SELECT sr, name, mobile_no, source, remark FROM matka_data ORDER BY id DESC");

    if ($f === 'excel' || $f === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=matka_data.csv');
        $o = fopen('php://output', 'w');
        fprintf($o, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($o, ['SR', 'Name', 'Mobile', 'Source', 'Remark']);
        while ($r = $res->fetch_assoc()) {
            $r['mobile_no'] = '="' . $r['mobile_no'] . '"';
            fputcsv($o, $r);
        }
        fclose($o);
        exit;
    } elseif ($f === 'txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=matka_data.txt');
        while ($r = $res->fetch_assoc()) {
            echo "{$r['sr']}\t{$r['name']}\t{$r['mobile_no']}\t{$r['source']}\t{$r['remark']}\r\n";
        }
        exit;
    } elseif ($f === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=matka_export.html');
        echo "<html><head>
    <link rel="icon" type="image/png" href="images/fevicon.png"><style>table{width:100%;border-collapse:collapse;font-family:sans-serif;}th,td{border:1px solid #ddd;padding:12px;text-align:left;}th{background:#f4f4f4;}</style><link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head><body>";
        echo "<h2>Matka Data Downloaded on " . date('d M Y') . "</h2>";
        echo "<table><tr><th>SR</th><th>Name</th><th>Mobile</th><th>Source</th><th>Remark</th></tr>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr><td>{$r['sr']}</td><td>{$r['name']}</td><td>{$r['mobile_no']}</td><td>{$r['source']}</td><td>{$r['remark']}</td></tr>";
        }
        echo "</table></body></html>";
        exit;
    } elseif ($f === 'pdf') {
        echo "<html><body onload='window.print();window.history.back();'><style>table{width:100%;border-collapse:collapse;font-family:sans-serif;}th,td{border:1px solid #000;padding:8px;font-size:12px;}th{background:#eee;}</style>";
        echo "<h2>Matka Data Report</h2><table border='1'><tr><th>SR</th><th>Name</th><th>Mobile</th><th>Source</th><th>Remark</th></tr>";
        while ($r = $res->fetch_assoc())
            echo "<tr><td>{$r['sr']}</td><td>{$r['name']}</td><td>{$r['mobile_no']}</td><td>{$r['source']}</td><td>{$r['remark']}</td></tr>";
        echo "</table></body></html>";
        exit;
    }
    exit;
}

// 3. IMPORT (CSV, TXT, HTML)
if (isset($_FILES['imp_file'])) {
    $ext = strtolower(pathinfo($_FILES['imp_file']['name'], PATHINFO_EXTENSION));
    $tmp = $_FILES['imp_file']['tmp_name'];
    $data_rows = [];
    if ($ext === 'csv' || $ext === 'xlsx') {
        if (($h = fopen($tmp, "r")) !== FALSE) {
            fgetcsv($h);
            while (($d = fgetcsv($h, 0, ",")) !== FALSE) {
                if (empty(trim(implode('', $d))))
                    continue;
                if (empty(trim($d[2] ?? '')))
                    continue;
                $data_rows[] = ['n' => $d[1] ?? '', 'm' => $d[2] ?? '', 's' => $d[3] ?? 'Import', 'r' => $d[4] ?? 'Bulk'];
            }
            fclose($h);
        }
    } elseif (in_array($ext, ['txt', 'html', 'htm'])) {
        $content = file_get_contents($tmp);
        // Strip tags for HTML/HTM to focus on viewable text
        if ($ext !== 'txt') {
            $content = strip_tags($content);
        }
        
        // Match sequences of 10 or more digits to handle 91/0 prefixes
        if (preg_match_all('/\d{10,}/', $content, $matches)) {
            $raw_nums = array_unique($matches[0]);
            foreach ($raw_nums as $val) {
                // Take the last 10 digits (Standard Indian Mobile)
                $m = substr($val, -10);
                $data_rows[] = [
                    'n' => 'Contact', 
                    'm' => $m, 
                    's' => strtoupper($ext) . ' Force Detect', 
                    'r' => 'Auto Extracted'
                ];
            }
        }
    }
    $PREVIEW_DATA = json_encode($data_rows);
}

// 4. COUNTER & PAGINATION
$q = trim($_GET['q'] ?? '');
$where = "";
if ($q !== '') {
    $keywords = array_filter(array_map('trim', preg_split('/\s+/', $q)));
    $conditions = [];
    foreach ($keywords as $kw) {
        $safe = $db->real_escape_string($kw);
        $conditions[] = "(name LIKE '%$safe%' OR mobile_no LIKE '%$safe%' OR source LIKE '%$safe%' OR remark LIKE '%$safe%' OR sr LIKE '%$safe%' OR id LIKE '%$safe%')";
    }
    if (!empty($conditions)) {
        $where = "WHERE " . implode(' AND ', $conditions);
    }
}
$totalEntries = $db->query("SELECT COUNT(*) as c FROM matka_data")->fetch_assoc()['c'];
$allowedPerPage = [20, 100, 200];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$totalFoundRow = $db->query("SELECT COUNT(*) as c FROM matka_data $where");
$totalFound = $totalFoundRow ? $totalFoundRow->fetch_assoc()['c'] : 0;
$pages = max(1, (int)ceil($totalFound / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$rowsRes = $db->query("SELECT * FROM matka_data $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$rows = $rowsRes ? $rowsRes->fetch_all(MYSQLI_ASSOC) : [];
$editRow = null;
if (isset($_GET['edit']))
    $editRow = $db->query("SELECT * FROM matka_data WHERE id=" . (int) $_GET['edit'])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>Matka Data Master</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        .box {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            max-width: 1200px;
            margin: auto;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            color: #fff;
            background: #1a73e8;
            transition: 0.2s;
            text-decoration: none !important;
        }

        .btn-green {
            background: #34a853;
        }

        .btn-purple {
            background: #673ab7;
        }

        .btn-danger {
            background: #d93025;
        }

        .btn-warning {
            background: #f97316;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: #fff;
            min-width: 180px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            z-index: 100;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #eee;
            margin-top: 5px;
        }

        .dropdown-content a,
        .dropdown-content label {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 13px;
            cursor: pointer;
        }

        .dropdown-content a:hover,
        .dropdown-content label:hover {
            background: #f8f9fa;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .counter-badge {
            background: #fee2e2;
            color: #b91c1c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
            border: 1.5px solid #fca5a5;
        }

        .top-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
            background: #fffcf5;
            padding: 20px;
            border-radius: 12px;
            border: 1.5px solid #ffeeba;
            position: relative;
            align-items: center;
        }

        .top-form input,
        .top-form select {
            padding: 12px 15px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            outline: none;
            flex: 1 1 150px;
            min-width: 140px;
            font-size: 14px;
            height: 48px;
            box-sizing: border-box;
        }

        .top-form select {
            background: #fff;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
            padding-right: 30px;
        }

        .btn {
            height: 48px;
            padding: 0 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            color: #fff;
            background: #1a73e8;
            transition: 0.2s;
            text-decoration: none !important;
            flex-shrink: 0;
        }

        .suggest-dropdown {
            position: absolute;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 250px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            top: 70px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .suggest-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .suggest-item:hover {
            background: #f9f9f9;
        }

        .suggest-item strong {
            display: block;
            color: #1a73e8;
        }

        .suggest-item span {
            color: #666;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            text-align: left;
            padding: 14px 10px;
            border-bottom: 1px solid #f1f1f1;
        }

        th {
            background: #f9fbfd;
            color: #7c8db5;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 800;
        }

        #ocrPanel {
            display: none;
            background: #f9f5ff;
            border: 2.5px dashed #673ab7;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }

        /* REPEAT DATA MODAL */
        #dupeModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
        }

        .modal-box {
            background: #fff;
            margin: 2% auto;
            padding: 25px;
            width: 95%;
            max-width: 1000px;
            border-radius: 16px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .dupe-group {
            border: 1.5px solid #eee;
            border-radius: 12px;
            margin-bottom: 15px;
            background: #fcfcfc;
        }

        .dupe-header {
            background: #fee2e2;
            padding: 10px 15px;
            border-radius: 10px 10px 0 0;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dupe-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>

    <div id="loadingBar"></div>

    <div class="box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <a href="admin_dashboard.php"
                    style="text-decoration:none; color:#1a73e8; font-weight:700; font-size:14px; margin-bottom:5px; display:block;"><i
                        class="fas fa-chevron-left"></i> Dashboard</a>
                <h2 style="margin:0; font-size:26px;">Data Management <span class="counter-badge">Total:
                        <?= $totalEntries ?></span></h2>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="btn btn-warning" onclick="openDupeModal()"><i class="fas fa-copy"></i> Repeat Data</button>
                <button class="btn" style="background:#dc2626;" onclick="openInvalidModal()"><i class="fas fa-exclamation-circle"></i> Invalid Numbers</button>
                <div class="dropdown">
                    <button class="btn btn-green"><i class="fas fa-download"></i> Download <i
                            class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <a href="?export=excel">Excel / CSV</a>
                        <a href="?export=pdf">PDF Report</a>
                        <a href="?export=html">HTML Table</a>
                        <a href="?export=txt">Notepad (TXT)</a>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-purple"><i class="fas fa-upload"></i> Import <i
                            class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <label>Excel / CSV<input type="file" onchange="impFile(this)" accept=".csv"
                                style="display:none;"></label>
                        <label>Notepad (TXT)<input type="file" onchange="impFile(this)" accept=".txt"
                                style="display:none;"></label>
                        <label>Web Page (HTML)<input type="file" onchange="impFile(this)" accept=".html,.htm"
                                style="display:none;"></label>
                        <label>PDF (Document)<input type="file" onchange="pdfFile(this)" accept=".pdf"
                                style="display:none;"></label>
                        <a onclick="openCam()"><i class="fas fa-camera"></i> Image Scan (OCR)</a>
                    </div>
                </div>
                <form id="impForm" method="post" enctype="multipart/form-data" style="display:none;"><input type="file"
                        name="imp_file" id="impInput"></form>
            </div>
        </div>

            <?php if ($msg):
                $isErr = strpos($msg, 'Error') !== false; ?>
                <div style="padding:15px; background:<?= $isErr ? '#fee2e2' : '#dcfce7' ?>; color:<?= $isErr ? '#b91c1c' : '#166534' ?>; border-radius:10px; margin-bottom:20px; font-weight:700; border:1px solid <?= $isErr ? '#fca5a5' : '#bbf7d0' ?>;"
                    id="statusMsg">
                    <i class="fas <?= $isErr ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i> <?= $msg ?>
                </div>
                <script>
                    setTimeout(function() {
                        var sm = document.getElementById('statusMsg');
                        if (sm) sm.style.display = 'none';
                        <?php if (!$isErr) { ?>
                            window.location.href = "admin_matka_data.php";
                        <?php } ?>
                    }, 3000);
                </script>
            <?php endif; ?>

        <div id="ocrPanel">
            <form method="post">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <h3 style="margin:0;"><i class="fas fa-search"></i> Import Preview</h3>
                        <button type="submit" name="bulk_add_preview" class="btn" style="background:#673ab7; height:38px;"><i class="fas fa-save"></i> Commit Selected</button>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn" onclick="copyMobs()" style="background:#555; height:38px;"><i class="fas fa-copy"></i> Copy Numbers</button>
                        <button type="button" class="btn" onclick="document.getElementById('ocrPanel').style.display='none'" style="background:#666; height:38px;">Discard</button>
                    </div>
                </div>
                <div id="ocrStatus" style="display:none; font-weight:700; margin:10px 0; color:#1a73e8;"><i class="fas fa-spinner fa-spin"></i> Processing...</div>
                
                <div style="background:#f0f7ff; padding:15px; border-radius:12px; border:1.5px solid #1a73e8; margin:20px 0; display:flex; gap:15px; align-items:center; box-shadow: 0 4px 15px rgba(26, 115, 232, 0.08);">
                    <div style="background:#1a73e8; color:#fff; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;">
                         <i class="fas fa-magic"></i>
                    </div>
                    <div style="font-weight:800; color:#1e293b; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap;">Batch Update All:</div>
                    <div style="flex:1; position:relative;">
                        <i class="fas fa-tag" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px;"></i>
                        <input type="text" id="globalSource" placeholder="Update Source for All Rows..." style="width:100%; padding:10px 10px 10px 32px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none; transition:0.2s;" oninput="syncGlobal('source', this.value)" onfocus="this.style.borderColor='#1a73e8'">
                    </div>
                    <div style="flex:1; position:relative;">
                        <i class="fas fa-comment-dots" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px;"></i>
                        <input type="text" id="globalRemark" placeholder="Update Remark for All Rows..." style="width:100%; padding:10px 10px 10px 32px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none; transition:0.2s;" oninput="syncGlobal('remark', this.value)" onfocus="this.style.borderColor='#1a73e8'">
                    </div>
                </div>

                <div style="max-height:400px; overflow-y:auto; margin-top:15px; border:1px solid #eee; border-radius:8px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.02);">
                    <table>
                        <thead style="position:sticky; top:0; z-index:10; background:#f9fbff;">
                            <tr style="border-bottom:2px solid #e2e8f0;">
                                <th width="30"><input type="checkbox" checked onclick="toggleAllPre(this)"></th>
                                <th>Name</th>
                                <th width="150">Mobile</th>
                                <th width="200">Source</th>
                                <th width="200">Remark</th>
                            </tr>
                        </thead>
                        <tbody id="ocrBody"></tbody>
                    </table>
                </div>
                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" name="bulk_add_preview" class="btn" style="background:#673ab7;">Commit Selected</button>
                    <button type="button" class="btn" onclick="document.getElementById('ocrPanel').style.display='none'" style="background:#666;">Discard</button>
                </div>
            </form>
        </div>

        <form method="post" action="admin_matka_data.php" class="top-form" onsubmit="return validateContactForm()">
            <input type="hidden" name="id" id="formId" value="<?= $editRow['id'] ?? '' ?>">
            <input type="text" name="name" id="formName" placeholder="Name"
                value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">

            <div style="flex:1; position:relative; min-width:200px;">
                <input type="tel" name="mobile_no" id="formMobile" placeholder="Mobile (10 Digits)" required autocomplete="off"
                    maxlength="10" pattern="\d{10}" oninput="suggestContacts(this); this.value = this.value.replace(/\D/g, '')" 
                    value="<?= htmlspecialchars($editRow['mobile_no'] ?? '') ?>">
                <div id="mobileSuggest" class="suggest-dropdown"></div>
            </div>

            <?php
            $currentSource = $editRow['source'] ?? '';
            $standardSources = ['APP DEV BY ADS', 'PLAYER BY ADS', 'SUBH GAMING', 'LALA 111'];
            $isManual = ($currentSource != '' && !in_array($currentSource, $standardSources));
            ?>
            <select name="source_main" id="formSourceSelect" onchange="toggleManualSource(this)">
                <option value="">Select Source</option>
                <option value="APP DEV BY ADS" <?= $currentSource == 'APP DEV BY ADS' ? 'selected' : '' ?>>APP DEV BY ADS</option>
                <option value="PLAYER BY ADS" <?= $currentSource == 'PLAYER BY ADS' ? 'selected' : '' ?>>PLAYER BY ADS</option>
                <option value="SUBH GAMING" <?= $currentSource == 'SUBH GAMING' ? 'selected' : '' ?>>SUBH GAMING</option>
                <option value="LALA 111" <?= $currentSource == 'LALA 111' ? 'selected' : '' ?>>LALA 111</option>
                <option value="manual" <?= $isManual ? 'selected' : '' ?>>MANUAL ADD</option>
            </select>
            <input type="text" name="source_manual" id="formSourceManual" placeholder="Type Source..."
                style="display: <?= $isManual ? 'block' : 'none' ?>;"
                value="<?= $isManual ? htmlspecialchars($currentSource) : '' ?>">

            <input type="text" name="remark" id="formRemark" placeholder="Remark"
                value="<?= htmlspecialchars($editRow['remark'] ?? '') ?>">
            <button type="submit" name="save_record" class="btn"><?= $editRow ? 'Update' : 'Add New' ?></button>
        </form>

        <div
            style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; background:#f9fbff; padding:12px 15px; border-radius:12px; border:1px solid #eef2f6;">
            <div style="position:relative; width:100%; max-width:340px;">
                <form id="searchForm" method="get" action="" style="margin:0;">
                    <i class="fas fa-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px; z-index:2;"></i>
                    <input type="text" id="liveSearch" name="q"
                        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Search Name, Mobile, Source, etc..." autocomplete="off"
                        style="width:100%; padding:9px 38px; border-radius:10px; border:1.5px solid #cbd5e1; outline:none; font-size:13.5px; transition:0.3s; height:40px;"
                        onfocus="this.style.borderColor='#7c3aed'">
                </form>
            </div>

            <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                <label style="font-size:13px; font-weight:700; color:#64748b; white-space:nowrap;"><i class="fas fa-list-ol" style="margin-right:4px;"></i>Show rows:</label>
                <select id="perPageSelect" onchange="changePerPage(this.value)"
                    style="padding:8px 30px 8px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; color:#1e293b; background:#fff; cursor:pointer; height:40px; outline:none; appearance:none; background-image:url(&quot;data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e&quot;); background-repeat:no-repeat; background-position:right 8px center; background-size:1em;">
                    <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>20 / page</option>
                    <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100 / page</option>
                    <option value="200" <?= $perPage == 200 ? 'selected' : '' ?>>200 / page</option>
                </select>
            </div>

            <button type="submit" name="bulk_delete" id="delBtn" form="bulkForm" class="btn btn-danger"
                style="display:none; height:42px; border-radius:8px;" onclick="return confirm('Delete?');"><i
                    class="fas fa-trash"></i> Delete Selected</button>
        </div>

        <form id="bulkForm" method="post">
            <div style="overflow-x:auto;">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" onclick="selAllRows(this)"></th>
                            <th>SR</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Whatsapp</th>
                            <th>Source</th>
                            <th>Remark</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#64748b; font-size:14px;">No records found.</td></tr>
                        <?php else: ?>
                        <?php
                        $cnt = $totalFound - $offset;
                        foreach ($rows as $r):
                            ?>
                            <tr id="main-row-<?= $r['id'] ?>">
                                <td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" class="rc" onclick="chRows()">
                                </td>
                                <td><?= $cnt-- ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <?php
                                $mDisp = trim($r['mobile_no']);
                                if (strpos($mDisp, '+91') === 0) {
                                    $mDisp = trim(substr($mDisp, 3));
                                } elseif (strpos($mDisp, '91') === 0 && strlen(preg_replace('/\D/', '', $mDisp)) == 12) {
                                    $mDisp = trim(substr($mDisp, 2));
                                }
                                ?>
                                <td style="color:#1a73e8; font-weight:700;">+91 <?= htmlspecialchars($mDisp) ?></td>
                                <td>
                                    <?php
                                    $waNum = preg_replace('/\D/', '', $r['mobile_no']);
                                    if (strlen($waNum) == 10)
                                        $waNum = "91" . $waNum;
                                    ?>
                                    <a href="https://wa.me/<?= $waNum ?>" target="_blank" onclick="trackWA(<?= $r['id'] ?>)"
                                        style="color:#25d366; text-decoration:none; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                                        <i class="fab fa-whatsapp" style="font-size:16px;"></i> Whatsapp
                                        <span title="WhatsApp Clicks"
                                            style="font-size:11px; background:#e1fadc; padding:2px 6px; border-radius:10px; color:#128c7e; margin-left:5px;">clicks:
                                            <?= (int) ($r['click'] ?? 0) ?></span>
                                    </a>
                                </td>
                                <td><span
                                        style="background:#f1f5f9; padding:4px 10px; border-radius:15px; font-size:11px; font-weight:800; color:#475569; border:1px solid #cbd5e1;"><?= htmlspecialchars($r['source'] ?: 'Direct') ?></span>
                                </td>
                                <td><?= htmlspecialchars($r['remark']) ?></td>
                                <td><a href="?edit=<?= $r['id'] ?>" style="color:#1a73e8; margin-right:15px;"><i
                                            class="fas fa-edit"></i></a> <a href="?delete=<?= $r['id'] ?>"
                                        style="color:#ef4444;" onclick="return confirm('Delete?');"><i
                                            class="fas fa-trash-alt"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <div id="paginationBox"
            style="margin-top:25px; display:flex; justify-content:center; align-items:center; gap:15px; flex-wrap:wrap;">
            <div style="display:flex; gap:10px;">
                <?php if ($page > 1): ?>
                    <a href="?p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&per_page=<?= $perPage ?>" class="btn"
                        style="background:#1a73e8; box-shadow:0 3px 10px rgba(26,115,232,0.2);"><i
                            class="fas fa-arrow-left"></i> Previous</a>
                <?php else: ?>
                    <span class="btn"
                        style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed; border:1px solid #e2e8f0; pointer-events:none;"><i
                            class="fas fa-arrow-left"></i> Previous</span>
                <?php endif; ?>
            </div>

            <div
                style="display:flex; align-items:center; gap:10px; background:#fff; padding:8px 18px; border-radius:12px; border:1.5px solid #e2e8f0; box-shadow:0 2px 10px rgba(0,0,0,0.03);">
                <span style="font-weight:700; color:#64748b; font-size:13px;">Page</span>
                <input type="number" id="jumpPage" value="<?= $page ?>" min="1" max="<?= $pages ?>"
                    style="width:55px; padding:6px; border:1.5px solid #cbd5e1; border-radius:8px; text-align:center; font-weight:800; color:#1e293b; outline:none;"
                    onfocus="this.style.borderColor='#1a73e8'">
                <span style="font-weight:700; color:#64748b; font-size:13px;">of <?= $pages ?></span>
                <button onclick="goToPageNum()" class="btn"
                    style="padding:7px 15px; background:#475569; font-size:12px; height:auto; border-radius:8px;">Go</button>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($page < $pages): ?>
                    <a href="?p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&per_page=<?= $perPage ?>" class="btn"
                        style="background:#1a73e8; box-shadow:0 3px 10px rgba(26,115,232,0.2);">Next <i
                            class="fas fa-arrow-right"></i></a>
                <?php else: ?>
                    <span class="btn"
                        style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed; border:1px solid #e2e8f0; pointer-events:none;">Next
                        <i class="fas fa-arrow-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <script>
        function goToPageNum() {
            var p = document.getElementById('jumpPage').value;
            var pp = <?= $perPage ?>;
            window.location.href = "?p=" + p + "&q=<?= urlencode($q) ?>&per_page=" + pp;
        }
        function changePerPage(val) {
            window.location.href = "?p=1&q=<?= urlencode($q) ?>&per_page=" + val;
        }
        </script>
    </div>

    <!-- DUPLICATE MANAGER MODAL -->
    <div id="dupeModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(5px);">
        <div class="modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #eee; padding-bottom:15px;">
                <h2 style="margin:0;"><i class="fas fa-clone"></i> Duplicate Manager <span id="dupeBadge" class="counter-badge" style="display:none;"></span></h2>
                <div style="display:flex; gap:10px;">
                    <button class="btn" style="background:#d93025;" onclick="fixAllDupes()"><i class="fas fa-bolt"></i> Fix All Repeats</button>
                    <button class="btn" style="background:#666;" onclick="document.getElementById('dupeModal').style.display='none'">Close</button>
                </div>
            </div>
            <div id="dupeContent"></div>
        </div>
    </div>

    <!-- INVALID DATA MANAGER MODAL -->
    <div id="invalidModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(5px);">
        <div class="modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #eee; padding-bottom:15px;">
                <h2 style="margin:0;"><i class="fas fa-exclamation-triangle"></i> Invalid Data Cleaner <span id="invalidBadge" class="counter-badge" style="display:none;"></span></h2>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-danger" id="bulkDelInvBtn" style="display:none; background:#d93025;" onclick="bulkDeleteInvalid()"><i class="fas fa-trash"></i> Delete Selected</button>
                    <button class="btn" style="background:#666;" onclick="document.getElementById('invalidModal').style.display='none'">Close</button>
                </div>
            </div>
            <div id="invalidContent"></div>
        </div>
    </div>

    <div id="cropModal"
        style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85);">
        <div style="background:#fff; margin:4% auto; padding:20px; width:90%; max-width:850px; border-radius:16px;">
            <h3>Clip Image</h3>
            <div id="cropContainer"><img id="preImg" src="" style="width:100%;"></div>
            <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;"><button class="btn"
                    onclick="document.getElementById('cropModal').style.display='none'">Cancel</button><button
                    class="btn btn-purple" id="cropBtn">Process</button></div>
        </div>
    </div>

    <script>
        var cropper = null;
        var picker = document.createElement('input'); picker.type = 'file'; picker.accept = 'image/*';

        function trackWA(id) {
            fetch('admin_matka_data.php?track_wa=' + id);
        }

        // ── AUTO-SUGGEST & DYNAMIC SEARCH ──────────────────────────────────────────
        var searchBox = document.getElementById('liveSearch');
        var searchForm = document.getElementById('searchForm');
        var loadingBar = document.getElementById('loadingBar');
        
        var searchTimer = null;
        var lastQuery = searchBox ? searchBox.value.trim() : '';

        function showLoading() {
            if(loadingBar) {
                loadingBar.style.opacity = '1';
                loadingBar.style.width = '60%';
            }
        }
        function hideLoading() {
            if(loadingBar) {
                loadingBar.style.width = '100%';
                setTimeout(function() {
                    loadingBar.style.opacity = '0';
                    loadingBar.style.width = '0';
                }, 200);
            }
        }

        function buildParams(page = 1) {
            var q = searchBox ? searchBox.value.trim() : '';
            var params = new URLSearchParams();
            if (q !== '') params.set('q', q);
            params.set('p', page);
            return params;
        }

        function fetchResults(page = 1) {
            var params = buildParams(page);
            var url = 'admin_matka_data.php?' + params.toString();

            showLoading();
            var tbodyNow = document.querySelector("#dataTable tbody");
            if (tbodyNow) tbodyNow.style.opacity = '0.4';

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    var newTbody = doc.querySelector('#dataTable tbody');
                    if (newTbody && tbodyNow) {
                        tbodyNow.innerHTML = newTbody.innerHTML;
                        tbodyNow.style.opacity = '1';
                    }

                    var newPag = doc.querySelector('#paginationBox');
                    var curPag = document.querySelector('#paginationBox');
                    if (newPag && curPag) {
                        curPag.innerHTML = newPag.innerHTML;
                    }

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', url);
                    }

                    hideLoading();
                })
                .catch(function(err) {
                    console.error(err);
                    if (tbodyNow) tbodyNow.style.opacity = '1';
                    hideLoading();
                });
        }

        if (searchBox) {
            searchBox.addEventListener('input', function () {
                var q = searchBox.value.trim();
                if (q === lastQuery) return;
                lastQuery = q;

                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() { return fetchResults(1); }, 350);
            });
        }

        if (searchForm) {
            searchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                fetchResults(1);
            });
        }
        
        document.addEventListener('click', function (e) {
            var link = e.target.closest('#paginationBox a');
            if (!link) return;
            e.preventDefault();
            var url = new URL(link.href, window.location.href);
            var page = parseInt(url.searchParams.get('p') || '1', 10);
            fetchResults(page);
        });




        // DUPLICATE DATA HANDLER
        async function openDupeModal() {
            document.getElementById('dupeModal').style.display = 'block';
            document.getElementById('dupeContent').innerHTML = 'Searching database...';
            var dupeBadge = document.getElementById('dupeBadge');
            dupeBadge.style.display = 'none';

            var res = await fetch('admin_matka_data.php?get_duplicates=1');
            var data = await res.json();

            if (data.length === 0) {
                document.getElementById('dupeContent').innerHTML = '<h3 style="text-align:center;">Saara data clean hai (No repeats)!</h3>';
                return;
            }

            // Grouping by Mobile No
            var groups = data.reduce(function(acc, obj) {
                var key = obj.mobile_no;
                if (!acc[key]) acc[key] = [];
                acc[key].push(obj);
                return acc;
            }, {});

            var groupCount = Object.keys(groups).length;
            dupeBadge.style.display = 'inline-block';
            dupeBadge.innerText = `${data.length} Total Repeats (Across ${groupCount} Numbers)`;

            var html = "";
            for (var mobile in groups) {
                html += `<div class="dupe-group">
            <div class="dupe-header">
                <span>Mobile: ${mobile} 
                    <a href="https://wa.me/91${mobile.replace(/\D/g, '')}" target="_blank" style="color:#25d366; margin-left:10px; font-size:16px;"><i class="fab fa-whatsapp"></i></a>
                </span> 
                <span style="font-size:11px; opacity:0.8;">(${groups[mobile].length} Entries Found)</span>
            </div>`;
                groups[mobile].forEach(function(item) {
                    html += `<div class="dupe-item" id="row-${item.id}">
                <div>
                    <strong style="color:#1a73e8;">${item.name || 'N/A'}</strong> | 
                    <span style="font-size:12px; color:#555;">Source: ${item.source} | Remark: ${item.remark}</span>
                </div>
                <button class="btn btn-danger" style="padding:6px 12px; font-size:11px;" onclick="deleteEntry(${item.id})"><i class="fas fa-trash"></i> Remove This</button>
            </div>`;
                });
                html += `</div>`;
            }
            document.getElementById('dupeContent').innerHTML = html;
        }

        async function fixAllDupes() {
            if (!confirm('Are you sure? This will keep only the LATEST record for each number and delete all old duplicates.')) return;
            document.getElementById('dupeContent').innerHTML = '<h3><i class="fas fa-spinner fa-spin"></i> Fixing all repeats... Please wait.</h3>';
            var res = await fetch('admin_matka_data.php?fix_all_duplicates=1');
            var data = await res.json();
            if (data.status === 'success') {
                alert(data.msg);
                window.location.reload();
            }
        }

        async function openInvalidModal() {
            document.getElementById('invalidModal').style.display = 'block';
            document.getElementById('invalidContent').innerHTML = 'Searching invalid records...';
            var badge = document.getElementById('invalidBadge');
            badge.style.display = 'none';

            var res = await fetch('admin_matka_data.php?get_invalid=1');
            var data = await res.json();

            if (data.length === 0) {
                document.getElementById('invalidContent').innerHTML = '<h3 style="text-align:center;">Saara data sahi hai (No blank/invalid numbers)!</h3>';
                return;
            }

            badge.style.display = 'inline-block';
            badge.innerText = `${data.length} Invalid Records Found`;

            var html = `<table style="width:100%; font-size:13px;">
                <thead><tr><th width="30"><input type="checkbox" onclick="toggleAllInvalid(this)"></th><th>ID</th><th>Name</th><th>Mobile</th><th>Reason</th><th>Action</th></tr></thead>
                <tbody>`;
            data.forEach(function(item) {
                var reason = !item.mobile_no ? 'Blank / Khali' : 'Characters / Symbols found';
                html += `<tr id="inv-row-${item.id}">
                    <td><input type="checkbox" class="inv-rc" value="${item.id}" onclick="chInvRows()"></td>
                    <td>${item.id}</td>
                    <td><strong>${item.name || 'N/A'}</strong></td>
                    <td style="color:#d32f2f; font-weight:700;">${item.mobile_no || '[BLANK]'}</td>
                    <td><span style="color:#666;">${reason}</span></td>
                    <td><button class="btn btn-danger" style="padding:6px 12px; font-size:11px;" onclick="deleteEntry(${item.id}, 'inv-row-')"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            });
            html += `</tbody></table>`;
            document.getElementById('invalidContent').innerHTML = html;
            chInvRows();
        }

        function toggleAllInvalid(s) {
            document.querySelectorAll('.inv-rc').forEach(c => c.checked = s.checked);
            chInvRows();
        }

        function chInvRows() {
            var count = document.querySelectorAll('.inv-rc:checked').length;
            var btn = document.getElementById('bulkDelInvBtn');
            if (btn) {
                btn.style.display = count > 0 ? 'inline-flex' : 'none';
                btn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${count})`;
            }
        }

        async function bulkDeleteInvalid() {
            var ids = Array.from(document.querySelectorAll('.inv-rc:checked')).map(c => c.value);
            if (ids.length === 0) return;
            if (!confirm(`Pakka delete? (${ids.length} entries)`)) return;

            // Simple loop for deletion as we already have a direct bulk delete PHP handler partly but let's use the ids loop for simplicity or add a quick PHP case.
            // Wait, we have bulk_delete on line 146. Let's use it.
            // Or better, just stick to individual calls if we don't want to mess with the main bulk form.
            
            // Let's use the main bulk form mechanism by posting to it.
            var formData = new FormData();
            formData.append('bulk_delete', '1');
            ids.forEach(id => formData.append('ids[]', id));

            await fetch('admin_matka_data.php', {
                method: 'POST',
                body: formData
            });

            alert(`${ids.length} records deleted!`);
            openInvalidModal(); // Refresh modal
        }

        // --- NEW FUNCTIONS ---

        function toggleManualSource(sel) {
            document.getElementById('formSourceManual').style.display = (sel.value === 'manual' ? 'block' : 'none');
        }

        var suggestTimeout;
        function suggestContacts(input) {
            var query = input.value.trim();
            var box = document.getElementById('mobileSuggest');
            if (query.length < 3) {
                box.style.display = 'none';
                return;
            }

            clearTimeout(suggestTimeout);
            suggestTimeout = setTimeout(async function() {
                var res = await fetch('admin_matka_data.php?suggest_mobile=' + encodeURIComponent(query));
                var data = await res.json();

                if (data.length > 0) {
                    box.innerHTML = '';
                    data.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'suggest-item';
                        div.innerHTML = `<strong>${item.name || 'No Name'}</strong><span>${item.mobile_no} (${item.source || 'Direct'})</span>`;
                        div.onclick = function() { return selectContact(item); };
                        box.appendChild(div);
                    });
                    box.style.display = 'block';
                } else {
                    box.style.display = 'none';
                }
            }, 300);
        }

        function selectContact(item) {
            document.getElementById('formName').value = item.name || '';
            document.getElementById('formMobile').value = item.mobile_no || '';
            document.getElementById('formRemark').value = item.remark || '';
            
            var sourceSelect = document.getElementById('formSourceSelect');
            var manualInput = document.getElementById('formSourceManual');
            var standardSources = ['APP DEV BY ADS', 'PLAYER BY ADS', 'SUBH GAMING', 'LALA 111'];
            
            if (standardSources.includes(item.source)) {
                sourceSelect.value = item.source;
                manualInput.style.display = 'none';
                manualInput.value = '';
            } else if (item.source) {
                sourceSelect.value = 'manual';
                manualInput.style.display = 'block';
                manualInput.value = item.source;
            }

            document.getElementById('mobileSuggest').style.display = 'none';
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#mobileSuggest') && e.target.id !== 'formMobile') {
                document.getElementById('mobileSuggest').style.display = 'none';
            }
        });

        function validateContactForm() {
            var mobile = document.getElementById('formMobile').value.trim();
            if (mobile.length !== 10) {
                alert("Please enter exactly 10 digits for the mobile number!");
                return false;
            }
            return true;
        }

        async function deleteEntry(id, prefix = 'row-') {
            if (!confirm('Pakka delete?')) return;
            await fetch('admin_matka_data.php?delete=' + id);
            var row = document.getElementById(prefix + id);
            if (row) { row.style.background = '#fee2e2'; setTimeout(function() { return row.remove(); }, 400); }
            var mainRow = document.getElementById('main-row-' + id);
            if (mainRow) { mainRow.style.background = '#fee2e2'; setTimeout(function() { return mainRow.remove(); }, 400); }
        }

        // REST OF THE SCRIPTS
        function openCam() { picker.click(); }
        picker.onchange = function(e) {
            var r = new FileReader(); r.onload = function(ev) {
                document.getElementById('preImg').src = ev.target.result;
                document.getElementById('cropModal').style.display = 'block';
                if (cropper) cropper.destroy();
                cropper = new Cropper(document.getElementById('preImg'), { viewMode: 1 });
            }; r.readAsDataURL(e.target.files[0]);
        };
        document.getElementById('cropBtn').onclick = async function () {
            var c = cropper.getCroppedCanvas(); document.getElementById('cropModal').style.display = 'none';
            showPreviewPanel("Scanning Image...");
            var res = await Tesseract.recognize(c.toDataURL(), 'eng+hin');
            parseAndPreview(res.data.text, "OCR Scan");
        };
        function impFile(i) { var f = document.getElementById('impInput'); f.files = i.files; document.getElementById('impForm').submit(); }
        async function pdfFile(i) {
            if (!i.files[0]) return;
            showPreviewPanel("Extracting PDF...");
            var r = new FileReader(); r.onload = async function () {
                var t = new Uint8Array(this.result); var pdf = await pdfjsLib.getDocument(t).promise;
                var pT = ""; for (var i = 1; i <= pdf.numPages; i++) {
                    var page = await pdf.getPage(i); var content = await page.getTextContent();
                    pT += content.items.map(it => it.str).join(" ") + "\n";
                } parseAndPreview(pT, "PDF Import");
            }; r.readAsArrayBuffer(i.files[0]);
        }
        function showPreviewPanel(m) {
            document.getElementById('ocrPanel').style.display = 'block'; document.getElementById('ocrBody').innerHTML = '';
            var st = document.getElementById('ocrStatus'); st.style.display = 'block'; st.innerText = m;
        }
        function parseAndPreview(t, s = "Import") {
            document.getElementById('ocrStatus').style.display = 'none';
            var lines = t.split('\n'); lines.forEach(function(l) {
                var lT = l.trim(); if (!lT || lT.length < 3) return;
                var mM = lT.match(/(?:\+?\d{1,3}[-.\s]?)?(\d{10})/);
                var m = mM ? mM[1] : ""; var n = lT.replace(m, '').replace(/[|\[\]{}()]/g, '').trim();
                if (n && /^[0-9\W]+$/.test(n)) n = "";
                if (m) addRow(n || "Scanned Name", m, s, "Bulk");
            });
        }
        function addRow(n, m, s, r) {
            var tr = document.createElement('tr');
            // If global fields have values, use them instead of passed values
            var gs = document.getElementById('globalSource').value.trim();
            var gr = document.getElementById('globalRemark').value.trim();
            if(gs) s = gs;
            if(gr) r = gr;

            tr.innerHTML = `<td><input type="checkbox" name="is_checked[]" checked></td>
        <td><input type="text" name="scan_name[]" value="${n}"></td>
        <td><input type="text" name="scan_mobile[]" class="sc-mob" value="${m}"></td>
        <td><input type="text" name="scan_source[]" class="sc-source" value="${s}"></td>
        <td><input type="text" name="scan_remark[]" class="sc-remark" value="${r}"></td>`;
            document.getElementById('ocrBody').appendChild(tr);
        }

        function syncGlobal(type, val) {
            if (type === 'source') {
                document.querySelectorAll('.sc-source').forEach(inp => inp.value = val);
            } else {
                document.querySelectorAll('.sc-remark').forEach(inp => inp.value = val);
            }
        }
        function copyMobs() { var ms = Array.from(document.querySelectorAll('.sc-mob')).map(i => i.value).filter(v => v); navigator.clipboard.writeText(ms.join(", ")); alert("Copied!"); }
        function toggleAllPre(s) { document.querySelectorAll('#ocrBody input[type="checkbox"]').forEach(c => c.checked = s.checked); }
        function selAllRows(s) { document.querySelectorAll('.rc').forEach(c => c.checked = s.checked); chRows(); }
        function chRows() {
            var checkedCount = document.querySelectorAll('.rc:checked').length;
            var delBtn = document.getElementById('delBtn');
            if (delBtn) {
                delBtn.style.display = checkedCount > 0 ? 'inline-flex' : 'none';
                delBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${checkedCount})`;
            }
        }

        <?php if (isset($PREVIEW_DATA)): ?>
            window.onload = function () {
                showPreviewPanel("Syncing Import Data...");
                var d = <?= $PREVIEW_DATA ?>; d.forEach(x => addRow(x.n, x.m, x.s || "Import", x.r || "Bulk"));
                document.getElementById('ocrStatus').style.display = 'none';
            };
        <?php endif; ?>
    </script>
</body>

</html>



