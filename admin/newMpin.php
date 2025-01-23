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
    <title>SET NEW MPIN</title>
    <link rel="icon" href="../assets/images/party-logo.jpg" type="image/png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../index.css">
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
                <button class="submit-button" id="submit-button" type="submit" name="submit">Submit</button>
            </div>
        </form>
        <div style="text-align: center; margin-top: 20px;">
            <a id="backLogin" href="../index.php" class="btn btn-light">← Login Page</a>
        </div>
    </div>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

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