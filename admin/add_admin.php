<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure user is logged in  
requireLogin();

// Helper function to get singular form of table name  
function getSingularForm($tableName)
{
    $irregularPlurals = [
        'localbodies' => 'localbody',
    ];
    // Check if it's an irregular plural  
    if (isset($irregularPlurals[$tableName])) {
        return $irregularPlurals[$tableName];
    }
    return rtrim($tableName, 's');
}

// Define admin hierarchy  
$adminHierarchy = [
    'state_admin' => [
        'manages' => ['district_admin', 'mandalam_admin', "localbody_admin", "unit_admin", "collector"],
        'table' => ['districts', "mandalams", "localbodies", "units", "units"],
        'name_field' => 'name',
        'parent_field' => [null, 'district_id', "mandalam_id", "localbody_id", "localbody_id"],
    ],
    'district_admin' => [
        'manages' => ['mandalam_admin', "localbody_admin", "unit_admin", "collector"],
        'table' => ["mandalams", "localbodies", "units", "units"],
        'name_field' => 'name',
        'parent_field' => ['district_id', "mandalam_id", "localbody_id", "localbody_id"],
    ],
    'mandalam_admin' => [
        'manages' => ["localbody_admin", "unit_admin", "collector"],
        'table' => ["localbodies", "units", "units"],
        'name_field' => 'name',
        'parent_field' => ["mandalam_id", "localbody_id", "localbody_id"],
    ],
    'localbody_admin' => [
        'manages' => ["unit_admin", "collector"],
        'table' => ['units', 'units'],
        'name_field' => 'name',
        'parent_field' => ["localbody_id", "localbody_id"],
    ]
];

$currentUserRole = $_SESSION['role'];
if (!isset($adminHierarchy[$currentUserRole])) {
    die("Unauthorized access");
}

// Get the type of admin being managed  
$managingRole = '';
if (isset($_GET['type']) && in_array($_GET['type'], $adminHierarchy[$currentUserRole]['manages'])) {
    $managingRole = $_GET['type'];
} else {
    header("Location: ?type=" . $adminHierarchy[$currentUserRole]['manages'][0]);
    exit();
}

// Check if we're editing  
$isEditing = isset($_GET['edit']) && isset($_GET['place_id']);
$adminData = null;
$placeData = null;

// Get current level configuration  
$currentLevel = $adminHierarchy[$currentUserRole];
$singularTableName = getSingularForm($currentLevel['table'][array_search($managingRole, $currentLevel['manages'])]);

// Validate place_id  

if (isset($_GET['place_id'])) {
    try {
        $query = "SELECT * FROM {$currentLevel['table'][array_search($managingRole,$currentLevel['manages'])]} WHERE id = ?";
        $params = [$_GET['place_id']];

        if ($currentLevel['parent_field']) {
            $query .= " AND {$currentLevel['parent_field'][array_search($managingRole,$currentLevel['manages'])]} = ?";
            $params[] = $_SESSION['user_level_id'];
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $placeData = $stmt->fetch(PDO::FETCH_ASSOC);

        // echo "<script>console.log('PHP Variable: " . json_encode($placeData) . "');</script>";
        // echo "<script>console.log('PHP Variable: " . addslashes($) . "');</script>";

        if (!$placeData) {
            die("Invalid place selected");
        }

        // If editing, get admin data  
        if ($isEditing) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE {$singularTableName}_id = ? AND role = ? ");
            $stmt->execute([$_GET['place_id'], $managingRole]);
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData) {
                die("Admin not found");
            }
        }
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
} else if ($isEditing) {
    die("Place ID is required");
}

// Handle form submission  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input  
        if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['place_id'])) {
            throw new Exception("Name and phone are required");
        }

        if (!preg_match("/^\d{10}$/", $_POST['phone'])) {
            throw new Exception("Phone number must be 10 digits");
        }

        if (!$isEditing && empty($_POST['mpin'])) {
            throw new Exception("MPIN is required for new admin");
        }

        if (isset($_POST['mpin']) && !empty($_POST['mpin']) && !preg_match("/^\d{4,6}$/", $_POST['mpin'])) {
            throw new Exception("MPIN must be 4-6 digits");
        }

        // Check if phone number already exists  
        $phoneCheckQuery = "SELECT id FROM users WHERE phone = ? AND role = ? ";
        $phoneCheckParams = [$_POST['phone'], $managingRole];

        if ($isEditing) {
            $phoneCheckQuery .= " AND id != ?";
            $phoneCheckParams[] = $adminData['id'];
        }

        $stmt = $pdo->prepare($phoneCheckQuery);
        $stmt->execute($phoneCheckParams);
        if ($stmt->fetch()) {
            throw new Exception("Phone number already registered");
        }

        $pdo->beginTransaction();

        if ($isEditing) {
            // Update existing admin  
            $updateFields = ["name = ?", "phone = ?", "{$singularTableName}_id = ?", "updated_at = NOW()"];
            $params = [$_POST['name'], $_POST['phone'], $_POST['place_id']];

            if (!empty($_POST['mpin'])) {
                $updateFields[] = "mpin = ?";
                $params[] = password_hash($_POST['mpin'], PASSWORD_DEFAULT);
            }

            $params[] = $adminData['id'];

            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $stmt->execute($params);
        } else {
            // Add new admin  
            $stmt = $pdo->prepare("INSERT INTO users (name, phone, mpin, role, {$singularTableName}_id, created_at, updated_at)   
                                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                password_hash($_POST['mpin'], PASSWORD_DEFAULT),
                $managingRole,
                $_POST['place_id']
            ]);
        }

        $pdo->commit();

        // Set success message in session  
        $_SESSION['success_message'] = ($isEditing ? 'Updated' : 'Added') . " admin successfully";

        // Construct the redirect URL  
        $redirect_url = "manage_admins.php?type=$managingRole";
        // if ($currentUserRole === 'localbody_admin') {
        //     $redirect_url .= "?type=" . urlencode($managingRole);
        // }

        // Make sure nothing has been output yet  
        if (!headers_sent()) {
            header("Location: " . $redirect_url);
            exit();
        } else {
            echo "<script>window.location.href='" . $redirect_url . "'</script>";
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title><?php echo $isEditing ? 'Edit' : 'Add'; ?> <?php echo ucfirst(str_replace('_', ' ', $managingRole)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/<?php echo $_SESSION['level']; ?>.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php echo $isEditing ? 'Edit' : 'Add'; ?>
                            <?php echo ucfirst(str_replace('_', ' ', $managingRole)); ?>
                            <!-- for <?php echo htmlspecialchars($placeData['name']); ?> -->
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?php echo $isEditing ? htmlspecialchars($adminData['name']) : ''; ?>">
                                <div class="invalid-feedback">Name is required</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" required pattern="\d{10}"
                                    value="<?php echo $isEditing ? htmlspecialchars($adminData['phone']) : ''; ?>">
                                <div class="invalid-feedback">Valid 10-digit phone number is required</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?></label>
                                <!-- <input type="" name="phone" class="form-control" required pattern="\d{10}" -->
                                <select name="place_id" class="form-control" required>
                                    <option value="">Select <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?></option>
                                    <?php
                                    $query = "SELECT id, name FROM {$currentLevel['table'][array_search($managingRole,$currentLevel['manages'])]} WHERE 1";
                                    if ($currentLevel['parent_field']) {
                                        $query .= " AND {$currentLevel['parent_field'][array_search($managingRole,$currentLevel['manages'])]} = ?";
                                        $stmt = $pdo->prepare($query);
                                        $stmt->execute([$_SESSION['user_level_id']]);
                                    } else {
                                        $stmt = $pdo->query($query);
                                    }
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = $row['id'] == $placeData['id'] ? 'selected' : '';
                                        echo "<option value=\"{$row['id']}\" $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback"><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?> is required</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo $isEditing ? 'New MPIN (leave blank to keep current)' : 'MPIN'; ?></label>
                                <input type="password" name="mpin" class="form-control"
                                    <?php echo $isEditing ? '' : 'required'; ?>
                                    pattern="\d{4,6}" minlength="4" maxlength="6">
                                <div class="form-text">MPIN must be 4-6 digits</div>
                                <div class="invalid-feedback">Valid MPIN is required (4-6 digits)</div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="manage_admins.php?type=<?php echo $managingRole; ?>"
                                    class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php echo $isEditing ? 'Update' : 'Add'; ?> Admin
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation  
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>

</html>