<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// If already logged in, redirect to appropriate dashboard  
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'state_admin':
            header('Location: dashboard/dashboard.php');
            break;
        case 'district_admin':
            header('Location: dashboard/dashboard.php');
            break;
        case 'mandalam_admin':
            header('Location: dashboard/dashboard.php');
            break;
        case 'localbody_admin':
            header('Location: dashboard/dashboard.php');
            break;
        case 'unit_admin':
            header('Location: dashboard/dashboard.php');
            break;
        default:
            // If role is unknown, logout  
            logout();
    }
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'] ?? '';
    $mpin = $_POST['mpin'] ?? '';
    $result = login($phone, $mpin, $pdo);
    echo '<script>console.log(' . json_encode($result['status']) . ')</script>';
    if (!$result['status']) {
        $error = $result['message'];
    }
}
?>


<!DOCTYPE html>
<html>

<head>
    <title>Login - Party Fund Collection</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="index.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
    <!-- Banner Logo at top -->
    <div class="text-center">
        <img src="assets/images/party-logo.jpg" alt="Banner Logo" class="banner-logo">
    </div>

    <div class="login-container">
        <h2>Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div>
                <label>Phone</label>
                <input type="text" name="phone" required>
            </div>
            <div class="mb-2 input-container">
                <label>MPIN</label>
                <div class="input-container m-0">
                    <input type="password" name="mpin" id="mpin" required>
                    <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility(event)"></i>
                </div>
            </div>
            <p class="text-end mb-2">
                <a href="./admin/newMpin.php" class="fw-bolder small text-primary">Forgot MPIN?</a>
            </p>
            <button type="submit">Login</button>
        </form>
    </div>
    <!-- Verify Payment Button -->
    <div class="text-center" style="margin: 20px;">
        <a href="verifyDonation.php" class="btn btn-primary">Verify Payment</a>
    </div>
    <!-- Footer with Company Logo -->
    <footer class="footer mt-auto">
        <div class="container">
            <img src="assets/images/d4logo.png" alt="Company Logo" class="company-logo">
            <div class="copyright">
                © <?php echo date('Y'); ?> D4media. All rights reserved.
            </div>
        </div>
    </footer>
</body>
<script>
    function togglePasswordVisibility(event) {
        var input = document.getElementById("mpin");
        if (input.type === "password") {
            input.type = "text";
            event.target.classList.remove("fa-eye");
            event.target.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            event.target.classList.remove("fa-eye-slash");
            event.target.classList.add("fa-eye");
        }
    }
</script>
<!-- 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('form').on('submit', function(e) {
            e.preventDefault();
            var phone = $('input[name="phone"]').val();

            $.ajax({
                url: 'check_user.php',
                type: 'POST',
                data: {
                    phone: phone
                },
                success: function(response) {
                    if (response.user_exists) {
                        if (response.mpin) {
                            $('form').append('<div><label>MPIN</label><input type="password" name="mpin" required></div>');
                        } else {
                            // Generate OTP and show OTP input
                            $.ajax({
                                url: 'send_otp.php',
                                type: 'POST',
                                data: {
                                    phone: phone
                                },
                                success: function(otpResponse) {
                                    $('form').append('<div><label>OTP</label><input type="text" name="otp" required></div>');
                                    $('form').append('<input type="hidden" name="otp_verification" value="1">');
                                    $('form').append('<button type="submit">Verify OTP</button>');
                                }
                            });
                        }
                    } else {
                        $('.error').text('User does not exist').show();
                    }
                }
            });
        });
    });
</script> -->

</html>