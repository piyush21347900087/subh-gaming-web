<?php
$file = 'user_profile.php';
$content = file_get_contents($file);

// 1. Backend processing logic for UPI
$upi_backend = <<<'EOD'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_upi_modal') {
    header('Content-Type: application/json');
    $upi_id = trim($_POST['upi_id'] ?? '');
    $phonepe = trim($_POST['phonepe'] ?? '');
    $gpay = trim($_POST['gpay'] ?? '');
    $paytm = trim($_POST['paytm'] ?? '');
    $bhim = trim($_POST['bhim'] ?? '');

    // 1. Update general UPI ID
    $qCheck = $db->prepare("SELECT id FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $qCheck->bind_param('s', $uCode);
    $qCheck->execute();
    $qCheck->bind_result($eid);
    if ($qCheck->fetch()) {
        $qCheck->close();
        $uStmt = $db->prepare("UPDATE upi_kyc SET upi_id=?, status='Approved', date_time=NOW() WHERE id=?");
        $uStmt->bind_param('si', $upi_id, $eid);
        $uStmt->execute();
        $uStmt->close();
    } else {
        $qCheck->close();
        $uStmt = $db->prepare("INSERT INTO upi_kyc (user_id, upi_id, status, date_time) VALUES (?,?,'Approved',NOW())");
        $uStmt->bind_param('ss', $uCode, $upi_id);
        $uStmt->execute();
        $uStmt->close();
    }

    // 2. Update UPI numbers
    $apps = [
        'phonepe' => $phonepe,
        'gpay' => $gpay,
        'paytm' => $paytm,
        'bhim' => $bhim
    ];

    foreach ($apps as $type => $num) {
        $qCheck2 = $db->prepare("SELECT id FROM upi_numbers WHERE user_id=? AND upi_type=? LIMIT 1");
        $qCheck2->bind_param('ss', $uCode, $type);
        $qCheck2->execute();
        $qCheck2->bind_result($nid);
        if ($qCheck2->fetch()) {
            $qCheck2->close();
            $nStmt = $db->prepare("UPDATE upi_numbers SET upi_number=?, date_time=NOW() WHERE id=?");
            $nStmt->bind_param('si', $num, $nid);
            $nStmt->execute();
            $nStmt->close();
        } else {
            $qCheck2->close();
            $nStmt = $db->prepare("INSERT INTO upi_numbers (user_id, upi_number, upi_type, date_time) VALUES (?,?,?,NOW())");
            $nStmt->bind_param('sss', $uCode, $num, $type);
            $nStmt->execute();
            $nStmt->close();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'UPI Details Updated Successfully!']);
    exit;
}

$userUpiData = ['upi_id' => '', 'phonepe' => '', 'gpay' => '', 'paytm' => '', 'bhim' => ''];
$qUpi = $db->prepare("SELECT upi_id FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$qUpi->bind_param('s', $uCode);
$qUpi->execute();
$qUpi->bind_result($b_upi);
if ($qUpi->fetch()) {
    $userUpiData['upi_id'] = $b_upi ?? '';
}
$qUpi->close();

$qUpiNum = $db->prepare("SELECT upi_type, upi_number FROM upi_numbers WHERE user_id=?");
$qUpiNum->bind_param('s', $uCode);
$qUpiNum->execute();
$qUpiNum->bind_result($u_type, $u_num);
while ($qUpiNum->fetch()) {
    if (array_key_exists($u_type, $userUpiData)) {
        $userUpiData[$u_type] = $u_num ?? '';
    }
}
$qUpiNum->close();
EOD;

$content = str_replace(
    '$userBankData = [\'bank_name\' => \'\', \'account_name\' => \'\', \'account_number\' => \'\', \'ifsc\' => \'\', \'branch\' => \'\'];',
    $upi_backend . "\n\n\$userBankData = ['bank_name' => '', 'account_name' => '', 'account_number' => '', 'ifsc' => '', 'branch' => ''];",
    $content
);

// 2. Action Card UI
$upi_card = <<<'EOD'
        <a href="javascript:void(0)" onclick="openAddUpiModal()" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/payment_icon.svg" onerror="this.src='icons/add_bank.svg'" alt="UPI Details" style="width:24px; filter:invert(1);">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Add UPI Details") ?></div>
                    <div class="profile-action-sub"><?= __("UPI, PhonePe, Google Pay, Paytm, BHIM") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fa-solid fa-chevron-right"></i>
            </div>
        </a>
EOD;

$content = str_replace(
    '<a href="javascript:void(0)" onclick="openAddBankModal()" class="profile-action-card">',
    $upi_card . "\n        <a href=\"javascript:void(0)\" onclick=\"openAddBankModal()\" class=\"profile-action-card\">",
    $content
);

// 3. Modal HTML
$upi_modal = <<<'EOD'
    <!-- ADD UPI DETAILS MODAL -->
    <div class="bank-modal-overlay" id="upiModalOverlay" onclick="closeAddUpiModal(event)">
        <div class="bank-modal-card" onclick="event.stopPropagation()">
            <div class="bank-modal-header">
                <div class="bank-modal-title">
                    Add UPI Details <img src="icons/gold_coin.svg" alt="coin" style="width: 22px; height: 22px; vertical-align: middle; margin-top: -2px;">
                </div>
                <button type="button" class="bank-modal-close" onclick="closeAddUpiModal(event)" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="bank-modal-body">
                <div class="bank-sub-title">
                    <span>Fill UPI details below</span>
                </div>
                <form id="addUpiForm" onsubmit="submitAddUpiModal(event)">
                    <div class="bank-input-group">
                        <label class="bank-input-label">UPI ID</label>
                        <input type="text" id="um_upi_id" class="bank-input-field" placeholder="yourname@ybl" value="<?= htmlspecialchars($userUpiData['upi_id']) ?>">
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">PhonePe Number</label>
                        <input type="text" id="um_phonepe" class="bank-input-field" placeholder="9876543210" value="<?= htmlspecialchars($userUpiData['phonepe']) ?>">
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Google Pay Number</label>
                        <input type="text" id="um_gpay" class="bank-input-field" placeholder="9876543210" value="<?= htmlspecialchars($userUpiData['gpay']) ?>">
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Paytm Number</label>
                        <input type="text" id="um_paytm" class="bank-input-field" placeholder="9876543210" value="<?= htmlspecialchars($userUpiData['paytm']) ?>">
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">BHIM Number</label>
                        <input type="text" id="um_bhim" class="bank-input-field" placeholder="9876543210" value="<?= htmlspecialchars($userUpiData['bhim']) ?>">
                    </div>
                    <button type="submit" class="bank-submit-btn" id="um_submit_btn">Submit</button>
                </form>
            </div>
        </div>
    </div>
EOD;

$content = str_replace(
    '<!-- BANK SUCCESS POPUP MODAL -->',
    $upi_modal . "\n\n    <!-- BANK SUCCESS POPUP MODAL -->",
    $content
);

// 4. JavaScript
$upi_js = <<<'EOD'
    /* Add UPI Modal JS */
    function openAddUpiModal() {
        var modal = document.getElementById('upiModalOverlay');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeAddUpiModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var modal = document.getElementById('upiModalOverlay');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    window.openAddUpiModal = openAddUpiModal;
    window.closeAddUpiModal = closeAddUpiModal;

    function submitAddUpiModal(e) {
        if (e) e.preventDefault();
        var upi_id = document.getElementById('um_upi_id').value.trim();
        var phonepe = document.getElementById('um_phonepe').value.trim();
        var gpay = document.getElementById('um_gpay').value.trim();
        var paytm = document.getElementById('um_paytm').value.trim();
        var bhim = document.getElementById('um_bhim').value.trim();

        if (!upi_id && !phonepe && !gpay && !paytm && !bhim) {
            showToast('Please fill at least one UPI detail.');
            return false;
        }

        var submitBtn = document.getElementById('um_submit_btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
        }

        var formData = new FormData();
        formData.append('action', 'submit_upi_modal');
        formData.append('upi_id', upi_id);
        formData.append('phonepe', phonepe);
        formData.append('gpay', gpay);
        formData.append('paytm', paytm);
        formData.append('bhim', bhim);

        fetch('user_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            if (data.ok) {
                closeAddUpiModal();
                openBankSuccessModal(data.message || 'UPI Details Saved Successfully!');
            } else {
                showToast(data.error || 'Failed to save UPI details.');
            }
        })
        .catch(function(err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            showToast('An error occurred. Please try again.');
        });
        return false;
    }
EOD;

$content = str_replace(
    'function submitAddBankModal(e) {',
    $upi_js . "\n\n    function submitAddBankModal(e) {",
    $content
);

file_put_contents($file, $content);
echo "Added UPI modal and logic to $file\n";
