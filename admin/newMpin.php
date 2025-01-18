<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
// require_once '../includes/functions.php';
$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SET NEW MPIN</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
    <div class="login-container">
        <h2>SET NEW MPIN</h2>
        <div class="error d-none" id="error"><?php echo $error; ?></div>
        <div class="success d-none" id="success"><?php echo $success; ?></div>
        <form action="newMpin.php" method="post">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" required>
            </div>
            <div class="form-group submit-button">
                <button id="submit-button" type="submit" name="submit">Submit</button>
            </div>
        </form>
        <div style="text-align: center; margin-top: 20px;">
            <a id="backLogin" href="../index.php" class="btn btn-secondary">← Go Back to Login Page</a>

        </div>
    </div>
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

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-bottom: 20px;
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

        .custom_button {
            max-width: 340px;
            width: 100%;
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function togglePasswordVisibility(event) {
        var input = document.getElementById("new_mpin");
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

    $(document).ready(function() {
        isotpsent = false

        $('form').on('submit', function(event) {
            $('#submit-button').html('<span class="spinner-border spinner-border-sm"></span>')
            $('#error').addClass("d-none").html("")
            $('#success').addClass("d-none").html("")
            event.preventDefault();
            var phone = $('#phone').val();

            if (!isotpsent) {
                $.ajax({
                    url: "./ajax/check_user.php",
                    type: "POST",
                    data: {
                        phone: phone
                    },
                    success: function(response) {
                        response = JSON.parse(response)
                        if (response.status == 200) {
                            isotpsent = true;
                            $('form .submit-button').before(`<div class="form-group"><label for="otp">OTP</label><input type="text" id="otp" name="otp" required></div><div class="form-group"><label for="new_mpin">New MPIN</label><div class="form-group input-container m-0"><input type="password" id="new_mpin" name="new_mpin" pattern="\\d{4}|\\d{6}" minlength="4" maxlength="6" oninvalid="this.setCustomValidity('MPIN must be 4 or 6 digits.')" oninput="this.setCustomValidity('')" required><i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility(event)"></i></div><div class="form-text">MPIN must be 4 or 6 digits.</div></div>`);
                            $('#success').html(response.message).removeClass('d-none');
                        } else {
                            $('#error').removeClass('d-none').html(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#error').removeClass('d-none').html("Failed to Generate OTP");
                        // console.error("Failed to verify user: " + error);

                    },
                    complete: function() {
                        $('#submit-button').html('Submit')
                    }
                });
            } else {
                var otp = $('#otp').val();
                var newMpin = $('#new_mpin').val();
                $.ajax({
                    url: "./ajax/verifyOtp.php",
                    type: "POST",
                    data: {
                        otp: otp,
                        newMpin: newMpin,
                        phone: phone
                    },
                    success: function(response) {
                        response = JSON.parse(response)
                        if (response.status) {
                            $('#success').removeClass('d-none').html(response.message);
                            $('form').remove();
                            $('#backLogin').html("Login With New MPIN")
                        } else {
                            $('#error').removeClass('d-none').html(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#error').removeClass('d-none').html("Failed to verify OTP");
                        console.error("Failed to verify OTP: " + error);
                    },
                    complete: function() {
                        $('#submit-button').html('Submit')
                    }
                });
            }
        });
    });
</script>

</html>