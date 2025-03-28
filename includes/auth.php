<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login($phone, $mpin, $pdo)
{
    $query = "SELECT * FROM users WHERE phone = ? AND is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    //     $mandalam_id = $_SESSION['user_level_id'];

    //     // Get mandalam and district details  
    //     $stmt = $pdo->prepare("  
    //   SELECT m.name as mandalam_name, d.name as district_name   
    //   FROM mandalams m   
    //   JOIN districts d ON m.district_id = d.id   
    //   WHERE m.id = ?  
    //   ");
    //     $stmt->execute([$mandalam_id]);
    //     $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!$user['mpin']) {
            return ['status' => false, 'message' => "This User has not set mpin <h6><a href='./admin/newMpin.php'>SET NEW MPIN</a></h6>"];
        } else if (password_verify($mpin, $user['mpin'])) {
        // } else if (true) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['level'] = '';

            // Set the correct dashboard path based on role  
            switch ($user['role']) {
                case 'state_admin':
                    $_SESSION['level'] = 'state';
                    $_SESSION['user_level_id'] = 1;
                    header('Location: dashboard/dashboard.php');
                    break;
                case 'district_admin':
                    $_SESSION['level'] = 'district';
                    $_SESSION['user_level_id'] = $user['district_id'];
                    header('Location: dashboard/dashboard.php');
                    break;
                case 'mandalam_admin':
                    $_SESSION['level'] = 'mandalam';
                    $_SESSION['user_level_id'] = $user['mandalam_id'];
                    header('Location: dashboard/dashboard.php');
                    break;
                case 'localbody_admin':
                    $_SESSION['level'] = 'localbody';
                    $_SESSION['user_level_id'] = $user['localbody_id'];
                    header('Location: dashboard/dashboard.php');
                    break;
                case 'unit_admin':
                    $_SESSION['level'] = 'unit';
                    $_SESSION['user_level_id'] = $user['unit_id'];
                    header('Location: dashboard/dashboard.php');
                    break;
                case 'collector':
                    return ['status' => false, 'message' => 'Login For Collectors Restricted!'];
                    break;
                default:
                    return ['status' => false, 'message' => 'This user is not Allowed! Contact Admin'];
            }
            exit();
        } else {
            return ['status' => false, 'message' => 'Invalid Credentials'];
        }
    } else {
        return ['status' => false, 'message' => 'User not found'];
    }
    return ['status' => false, 'message' => 'Invalid Credentials'];
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function logout()
{
    session_destroy();
    header('Location: index.php');
    exit();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ../index.php');  // Added ../ to fix path  
        exit();
    }
}

function check_user($pdo)
{
    $phone = $_POST['phone'];
    // Check if phone number exists in the database
    $query = "SELECT id FROM users WHERE phone = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$phone]);
    $result = $stmt->fetchColumn();

    if ($result) {
        // Phone number exists, send OTP via WhatsApp
        $otp = rand(100000, 999999);
        // Save OTP to the database or session
        $_SESSION['otp'] = $otp;
        $_SESSION['phone'] = $phone;

        // Code to send OTP via WhatsApp API
        // Example: sendWhatsAppOTP($phone, $otp);
        $success = "OTP has been sent to your phone number.";
        echo '<form action="verifyOtp.php" method="post">
            <div class="form-group">
            <label for="otp">OTP:</label>
            <input type="text" id="otp" name="otp" required>
            </div>
            <div class="form-group">
            <button type="submit" name="verify">Verify OTP</button>
            </div>
          </form>';
    } else {
        echo "<p>Phone number does not exist in our system.</p>";
    }

    $pdo = null;
}
