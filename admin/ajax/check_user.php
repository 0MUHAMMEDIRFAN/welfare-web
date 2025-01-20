<?php
session_start();
include_once '../../config/database.php';

$phone = $_POST['phone'];
// Check if phone number exists in the database
$query = "SELECT id FROM users WHERE phone = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$phone]);
$result = $stmt->fetchColumn();

if ($result) {
    // Phone number exists, send OTP via WhatsApp
    $otp = rand(100000, 999999);
    $otp_expiry = time() + 600; // OTP expires in 10 minutes (600 seconds)

    // Save OTP and expiry time to the session
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = $otp_expiry;
    $_SESSION['phone'] = $phone;

    // // Code to send OTP via WhatsApp API
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://app.dxing.in/api/send/whatsapp");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => '18ed3b36a814c961ecf50b5ab3079f9bcd1704e7',
        'account' => '1728045549a5771bce93e200c36f7cd9dfd0e5deaa66ffe1ed4ae7c',
        'recipient' => $phone,
        'type' => 'text',
        'message' => 'Your OTP for resetting your MPIN with Welfare Party Kerala is: ' . $otp . '. It will expire within 10 minutes',
        'priority' => 1
    ]));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    // $response = '{"status":200,"otp":"' . $otp . '"}';

    if (curl_errno($ch)) {
        echo '{"status":404,"message":"Failed to send OTP via Whatsapp"}';
    } else {
        $response_data = json_decode($response, true);
        if (isset($response_data['status']) && $response_data['status'] == 200) {
            $response_data['message'] = 'OTP has been sent successfully via WhatsApp.';
        } else {
            $response_data['message'] = 'Failed to send OTP via WhatsApp.';
        }
        echo json_encode($response_data);
    }
    curl_close($ch);
} else {
    echo '{"status":404,"message":"Phone number does not exist in our system"}';
}

$pdo = null;
