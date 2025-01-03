<?php
require_once '../../config/database.php';

// verifyOtp.php

// Get the OTP and user ID from the request
$otp = isset($_POST['otp']) ? $_POST['otp'] : '';
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';
$newMpin = isset($_POST['newMpin']) ? password_hash($_POST['newMpin'], PASSWORD_DEFAULT) : '';

if (empty($otp) || empty($phone) || empty($newMpin)) {
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit;
}

// Check if the OTP in the session matches the provided OTP
session_start();
if (isset($_SESSION['otp'])  && isset($_SESSION['phone'])) {
    if ($_SESSION['otp'] == $otp  && $_SESSION['phone'] == $phone) {
        // Check if the OTP has expired
        if (isset($_SESSION['otp_expiry']) && time() <= $_SESSION['otp_expiry']) {
            // OTP is valid and not expired, update the MPIN
            $stmt = $pdo->prepare("UPDATE users SET mpin = ? WHERE phone = ?");
            if ($stmt->execute([$newMpin, $phone])) {
                // Remove OTP from session after successful verification
                unset($_SESSION['otp']);
                unset($_SESSION['otp_expiry']);
                unset($_SESSION['phone']);
                session_destroy();
                echo json_encode(['status' => true, 'message' => 'OTP verified and MPIN updated successfully']);
            } else {
                echo json_encode(['status' => false, 'message' => 'Failed to update MPIN']);
            }
        } else {
            // OTP has expired
            echo json_encode(['status' => false, 'message' => 'OTP has expired']);
        }
    } else {
        // OTP is invalid
        echo json_encode(['status' => false, 'message' => 'Invalid OTP']);
    }
} else {
    // OTP is invalid
    echo json_encode(['status' => false, 'message' => 'OTP has Expired']);
}
