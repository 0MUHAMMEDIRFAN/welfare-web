<?php
require_once 'config/database.php';

// verifyDonation.php
$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get receipt number and phone number from request
    $receipt_number = trim($_POST['receipt_number']);
    $phone_number = trim($_POST['phone_number']);

    // Validate inputs
    if (empty($receipt_number) || empty($phone_number)) {
        echo "Receipt Number and Phone Number are required.";
    } else {
        try {
            // Prepare and bind
            $stmt = $pdo->prepare("SELECT * FROM donations WHERE receipt_number = ? AND mobile_number = ?");

            // Execute the statement
            $stmt->execute([$receipt_number, $phone_number]);

            // Get the result
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($result)) {
                // Output data of each row
                foreach ($result as $row) {
                    $success .= "Donation verified: " . htmlspecialchars($row["name"]) . " donated " . htmlspecialchars($row["amount"]) . " on " . htmlspecialchars($row["created_at"]) . "<br>";
                }
            } else {
                $error = "No donation found with the provided receipt number and phone number.";
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }

        // Close connections
        $stmt = null;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Verify Donation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="index.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
    <div class="login-container">

        <h2>Verify Donation</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post" action="verifyDonation.php">
            <div>
                <label for="receipt_number">Receipt Number</label>
                <input type="text" id="receipt_number" name="receipt_number" required><br><br>
            </div>
            <div>
                <label for="phone_number">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number" required><br><br>
            </div>
            <button class="" type="submit">Verify</button>
        </form>
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="btn btn-secondary">← Login Page</a>
        </div>
    </div>

</body>

</html>
