<?php
require_once 'includes/lang.php';
// Debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');


require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sms_helper.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json');

if (!function_exists('send_sms_with_error')) {
    function send_sms_with_error(string $mobile, string $message): array
    {
        if (!function_exists('send_sms')) {
            return ['ok' => false, 'message' => 'SMS helper function missing.'];
        }

        $result = send_sms($mobile, $message);
        if (is_array($result)) {
            return [
                'ok' => (bool) ($result['ok'] ?? $result['success'] ?? false),
                'message' => (string) ($result['message'] ?? $result['msg'] ?? ''),
            ];
        }

        if ($result === true) {
            return ['ok' => true, 'message' => 'OTP sent successfully.'];
        }

        if (is_string($result) && $result !== '') {
            $decoded = json_decode($result, true);
            if (is_array($decoded)) {
                $hasError = false;
                $hasSuccess = false;
                $msg = '';

                foreach ($decoded as $item) {
                    if (is_array($item)) {
                        if (isset($item['msgid'])) {
                            $hasSuccess = true;
                        }
                        if (isset($item['responseCode'])) {
                            $codeStr = (string)$item['responseCode'];
                            if (stripos($codeStr, 'success') !== false || stripos($codeStr, 'submitted') !== false) {
                                $hasSuccess = true;
                            } else {
                                $hasError = true;
                                $msg = $codeStr;
                            }
                        }
                    }
                }

                if ($hasSuccess) {
                    return ['ok' => true, 'message' => 'OTP sent successfully.'];
                }
                if ($hasError) {
                    return ['ok' => false, 'message' => $msg ?: 'Failed to send OTP.'];
                }
            }

            if (stripos($result, 'Message SuccessFully Submitted') !== false || 
                stripos($result, 'msgid') !== false || 
                stripos($result, 'success') !== false) {
                return ['ok' => true, 'message' => 'OTP sent successfully.'];
            }

            if (stripos($result, 'error') !== false || 
                stripos($result, 'invalid') !== false || 
                stripos($result, 'fail') !== false || 
                stripos($result, 'insufficient') !== false) {
                return ['ok' => false, 'message' => $result];
            }

            return ['ok' => true, 'message' => 'OTP sent successfully.'];
        }

        return ['ok' => false, 'message' => 'Failed to send OTP. Please try again later.'];
    }
}

$action = $_POST['action'] ?? '';

try {
    $mysqli = db();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => __('DB Connection Error')]);
    exit;
}

if ($action === 'check_referral') {
    $code = trim($_POST['code'] ?? '');
    if (empty($code)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }
    
    $stmt = $mysqli->prepare("SELECT name, user_id FROM users WHERE user_id = ? OR mobile_no = ? OR referral = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('sss', $code, $code, $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            echo json_encode([
                'status' => 'valid',
                'name' => (string)$row['name'],
                'user_id' => (string)$row['user_id']
            ]);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    echo json_encode(['status' => 'invalid', 'message' => 'Invalid']);
    exit;
}

$mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');

if (!$mobile || !preg_match('/^\d{10}$/', $mobile)) {
    echo json_encode(['status' => 'error', 'message' => __('Invalid mobile number.')]);
    exit;
}

// ── Check if OTP system is ON or OFF ─────────────────────────────────────────
function is_otp_enabled(mysqli $db): bool {
    try {
        $r = $db->query("SELECT status FROM otp_settings WHERE id=1 LIMIT 1");
        if ($r && $row = $r->fetch_row()) {
            return strtoupper(trim($row[0])) === 'ON';
        }
    } catch (Throwable $e) {}
    return true; // default: ON (safe fallback)
}
$otpSystemOn = is_otp_enabled($mysqli);

// OTP message template
function get_otp_message($otp) {
    // AquaSMS DLT Template: Your OTP code for verification is : {#var#} CRTFUL
    return "Your OTP code for verification is : " . $otp . " CRTFUL";
}

function log_otp($mysqli, $mobile, $otp, $type) {
    try {
        $stmt = $mysqli->prepare("INSERT INTO otp_records (mobile, otp, type, status) VALUES (?, ?, ?, 'Sent')");
        $stmt->bind_param('sss', $mobile, $otp, $type);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Silent fail if table not ready
    }
}

function update_otp_status($mysqli, $mobile, $otp, $status) {
    try {
        $stmt = $mysqli->prepare("UPDATE otp_records SET status = ? WHERE mobile = ? AND otp = ? AND status = 'Sent' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $status, $mobile, $otp);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Silent fail
    }
}

try {
    if ($action === 'send_registration_otp') {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE mobile_no = ? LIMIT 1");
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => __('Mobile number already registered.')]);
            $stmt->close();
            exit;
        }
        $stmt->close();

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // ── OTP OFF: bypass SMS, auto-verify ──────────────────────────────
        if (!$otpSystemOn) {
            $_SESSION['reg_otp']    = 'BYPASS';
            $_SESSION['reg_mobile'] = $mobile;
            echo json_encode(['status' => 'success', 'otp_bypass' => true, 'message' => __('OTP sent successfully.')]);
            exit;
        }
        // ─────────────────────────────────────────────────────────────────

        $otp = generate_otp(4);
        $message = get_otp_message($otp);
        $sms_res = send_sms_with_error($mobile, $message);

        if (!$sms_res['ok']) {
            echo json_encode(['status' => 'error', 'message' => $sms_res['message'] ?: __('Failed to send OTP. Please try again later.')]);
            exit;
        }

        $_SESSION['reg_otp']    = $otp;
        $_SESSION['reg_mobile'] = $mobile;
        log_otp($mysqli, $mobile, $otp, 'Registration');
        echo json_encode(['status' => 'success', 'message' => __('OTP sent successfully.')]);
        exit;
    }

    if ($action === 'send_forgot_otp') {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE mobile_no = ? LIMIT 1");
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => __('Mobile number not registered.')]);
            $stmt->close();
            exit;
        }
        $stmt->close();

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // ── OTP OFF: bypass SMS, auto-verify ──────────────────────────────
        if (!$otpSystemOn) {
            $_SESSION['forgot_otp']    = 'BYPASS';
            $_SESSION['forgot_mobile'] = $mobile;
            echo json_encode(['status' => 'success', 'otp_bypass' => true, 'message' => __('OTP sent successfully.')]);
            exit;
        }
        // ─────────────────────────────────────────────────────────────────

        $otp = generate_otp(4);
        $message = get_otp_message($otp);
        $sms_res = send_sms_with_error($mobile, $message);

        if (!$sms_res['ok']) {
            echo json_encode(['status' => 'error', 'message' => $sms_res['message'] ?: __('Failed to send OTP. Please try again later.')]);
            exit;
        }
        $_SESSION['forgot_otp']    = $otp;
        $_SESSION['forgot_mobile'] = $mobile;
        log_otp($mysqli, $mobile, $otp, 'Forgot Password');
        echo json_encode(['status' => 'success', 'message' => __('OTP sent successfully.')]);
        exit;
    }

    if ($action === 'verify_registration_otp') {
        $otp_entered = trim($_POST['otp'] ?? '');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // ── OTP OFF: auto-verify without checking code ─────────────────────
        if (!$otpSystemOn) {
            $_SESSION['reg_otp'] = 'BYPASS';
            echo json_encode(['status' => 'success', 'message' => __('OTP verified.')]);
            exit;
        }
        // ──────────────────────────────────────────────────────────────────

        if (isset($_SESSION['reg_otp']) && (
            $_SESSION['reg_otp'] === 'BYPASS' ||
            ((string)$_SESSION['reg_otp'] === (string)$otp_entered && $_SESSION['reg_mobile'] === $mobile)
        )) {
            update_otp_status($mysqli, $mobile, $otp_entered, 'Verified');
            echo json_encode(['status' => 'success', 'message' => __('OTP verified.')]);
        } else {
            echo json_encode(['status' => 'error', 'message' => __('Invalid OTP.')]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => __('Invalid action.')]);
    exit;
} catch (Throwable $e) {
    // Optionally log the error for debugging
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
