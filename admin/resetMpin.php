<?php
// Include database connection
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentMpin = $_POST['current_mpin'];
    $newMpin = $_POST['new_mpin'];
    $verifyMpin = $_POST['verify_mpin'];
    $userId = $_SESSION['user_id']; // Assuming user ID is stored in session

    // Check if new MPIN and verify MPIN match
    if ($newMpin !== $verifyMpin) {
        $error = "New MPIN and Verify MPIN do not match.";
    } else {
        // Fetch current MPIN from database
        $query = "SELECT mpin FROM users WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $dbMpin = $stmt->fetchColumn();

        // Verify current MPIN
        if (!password_verify($currentMpin, $dbMpin)) {
            // if (false) {
            $error = "Current MPIN is incorrect.";
        } else {


            // Hash the new MPIN
            $hashedNewMpin = password_hash($newMpin, PASSWORD_DEFAULT);

            // Update MPIN in database
            $updateQuery = "UPDATE users SET mpin = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(1, $hashedNewMpin);
            $updateStmt->bindParam(2, $userId);
            if ($updateStmt->execute()) {
                $success = "MPIN has been successfully updated.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reset MPIN</title>
    <link rel="icon" href="../assets/images/party-logo.jpg" type="image/png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Reset MPIN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Reset MPIN</h3>
                    </div>
                    <?php if ($error): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_mpin" class="form-label">Current MPIN</label>
                                <input type="password" id="current_mpin" name="current_mpin" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_mpin" class="form-label">New MPIN</label>
                                <div class="input-container m-0">
                                    <input type="password" id="new_mpin" name="new_mpin" class="form-control" pattern="\d{4}|\d{6}" minlength="4" maxlength="6" required oninvalid="this.setCustomValidity('MPIN must be 4 or 6 digits.')" oninput="this.setCustomValidity('')">
                                    <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility(event)"></i>
                                </div>
                                <div class="form-text">MPIN must be 4 or 6 digits.</div>
                            </div>
                            <div class="mb-3">
                                <label for="verify_mpin" class="form-label">Verify MPIN</label>
                                <input type="text" id="verify_mpin" name="verify_mpin" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Reset MPIN</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
   
</body>
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
</script>

</html>