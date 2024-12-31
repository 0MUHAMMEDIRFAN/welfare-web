<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// If already logged in, redirect to appropriate dashboard  
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'state_admin':
            header('Location: dashboard/state.php');
            break;
        case 'mandalam_admin':
            header('Location: dashboard/mandalam.php');
            break;
        case 'localbody_admin':
            header('Location: dashboard/localbody.php');
            break;
        case 'unit_admin':
            header('Location: dashboard/unit.php');
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

    if (!login($phone, $mpin, $pdo)) {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Login - Party Fund Collection</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
            <div>
                <label>MPIN</label>
                <input type="password" name="mpin" required>
            </div>
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
    <style>
        .login-container {
            max-width: 400px;
            margin-inline: auto;
            margin-top: 40px;
            margin-bottom: 40px;
            /* margin: 100px auto; */
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .error {
            color: #dc3545;
            padding: 10px;
            margin-bottom: 20px;
            background: #ffe6e6;
            border-radius: 4px;
            text-align: center;
        }

        form div {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }

        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #45a049;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .banner-logo {
            max-height: 100px;
            object-fit: contain;
            margin: 2rem auto;
        }

        .company-logo {
            max-height: 60px;
            object-fit: contain;
        }

        .footer {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem 0;
            text-align: center;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
        }

        .copyright {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .login-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</body>

</html>