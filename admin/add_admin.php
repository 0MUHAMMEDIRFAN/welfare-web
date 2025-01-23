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
$currentLevel = $adminHierarchy[$currentUserRole];
$currentManages = $currentLevel['manages'];
$mainTables = $currentLevel['table'];
$mainParentFields = $currentLevel['parent_field'];
if (!isset($currentLevel)) {
    die("Unauthorized access");
}

// Get the type of admin being managed  
$managingRole = '';
if (isset($_GET['type']) && in_array($_GET['type'], $currentManages)) {
    $managingRole = $_GET['type'];
} else {
    header("Location: ?type=" . $currentManages[0]);
    exit();
}

$parentField = $mainParentFields[array_search($managingRole, $currentManages)];
$currentTable = $mainTables[array_search($managingRole, $currentManages)];
$singularTableName = getSingularForm($currentTable);

// Check if we're editing  
$isEditing = isset($_GET['edit']) && isset($_GET['place_id']) && isset($_GET['admin_id']);
$adminData = null;
$placeData = null;

// Get current level configuration  
// Validate place_id  

if (isset($_GET['place_id'])) {
    try {
        $query = "SELECT * FROM {$currentTable} WHERE id = ?";
        $params = [$_GET['place_id']];

        // if ($parentField) {
        //     $query .= " AND {$parentField} = ?";
        //     $params[] = $_SESSION['user_level_id'];
        // }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $placeData = $stmt->fetch(PDO::FETCH_ASSOC);

        // echo "<script>console.log('PHP Variable: '" . json_encode($placeData) . ");</script>";
        // echo "<script>console.log('PHP Variable: " . addslashes($) . "');</script>";

        if (!$placeData) {
            die("Invalid place selected");
        }

        // If editing, get admin data  
        if ($isEditing) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? ");
            $stmt->execute([$_GET['admin_id']]);
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            // echo "<script>console.log(" . json_encode($adminData) . ");</script>";

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
            throw new Exception("Name, phone and parent_field are required");
        }

        if (!preg_match("/^\d{10}$/", $_POST['phone'])) {
            throw new Exception("Phone number must be 10 digits");
        }

        // if (!$isEditing && empty($_POST['mpin'])) {
        //     throw new Exception("MPIN is required for new admin");
        // }

        // if (isset($_POST['mpin']) && !empty($_POST['mpin']) && !preg_match("/^\d{4,6}$/", $_POST['mpin'])) {
        //     throw new Exception("MPIN must be 4-6 digits");
        // }

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

            // if (!empty($_POST['mpin'])) {
            //     $updateFields[] = "mpin = ?";
            //     $params[] = password_hash($_POST['mpin'], PASSWORD_DEFAULT);
            // }

            $params[] = $adminData['id'];

            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $stmt->execute($params);
        } else {
            // Add new admin  
            $stmt = $pdo->prepare("INSERT INTO users (name, phone, role, {$singularTableName}_id, created_at, updated_at)   
                                 VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                // password_hash($_POST['mpin'], PASSWORD_DEFAULT),
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
    <link rel="icon" href="../assets/images/party-logo.jpg" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">

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
                        <a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a>
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
                                <input type="text" name="phone" class="form-control" required minlength="10" maxlength="10" pattern="\d{10}"
                                    value="<?php echo $isEditing ? htmlspecialchars($adminData['phone']) : ''; ?>">
                                <div class="invalid-feedback">Valid 10-digit phone number is required</div>
                            </div>

                            <?php
                            $i = 0;
                            while ($i <= array_search($managingRole, $currentManages) && $currentManages[$i] !== 'collector') {
                                $mainName = ucfirst(str_replace('_admin', '', $currentManages[$i]));
                                echo '<div class="mb-3">
                                <label class="form-label">' . $mainName . '</label>
                                <select name="' . ($i == ($managingRole == 'collector' ? array_search($managingRole, $currentManages) - 1 : array_search($managingRole, $currentManages)) ? "place_id" : "") . '" id="item_' . $mainName . '" class="form-control" required>
                                    <option value="" hidden>Select ' . $mainName . '</option><option value="" disabled>Select Previous First</option>';
                                if ($isEditing) {
                                    $mainTable = $mainTables[$i];
                                    $mainParentField = $mainParentFields[$i];
                                    // echo '<script>console.log('.addslashes($mainTable).')</script>';
                                    $query = "SELECT id, name FROM {$mainTable} WHERE 1";
                                    if ($mainParentField) {
                                        $query .= " AND {$mainParentField} = ?";
                                        $stmt = $pdo->prepare($query);
                                        $stmt->execute([$adminData[$mainParentField]]);
                                    } else {
                                        $stmt = $pdo->query($query);
                                    }
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = $row['id'] == $adminData[str_replace('_admin', '', $currentManages[$i]) . "_id"] ? 'selected' : '';
                                        echo "<option value=\"{$row['id']}\" $selected>{$row['name']}</option>";
                                    }
                                } else if ($i == 0) {
                                    $mainTable = $mainTables[$i];
                                    $mainParentField = $mainParentFields[$i];
                                    // echo '<script>console.log('.addslashes($mainTable).')</script>';
                                    $query = "SELECT id, name FROM {$mainTable} WHERE 1";
                                    if ($mainParentField) {
                                        $query .= " AND {$mainParentField} = ?";
                                        $stmt = $pdo->prepare($query);
                                        $stmt->execute([$_SESSION['user_level_id']]);
                                    } else {
                                        $stmt = $pdo->query($query);
                                    }
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = $row['id'] == $placeData['id'] ? 'selected' : '';
                                        echo "<option value=\"{$row['id']}\" $selected>{$row['name']}</option>";
                                    }
                                }
                                echo '</select>
                                <div class="invalid-feedback">' . $mainName . ' is required</div>
                            </div>';
                                $i++;
                            }
                            ?>
                            <!-- 
                            <div class="mb-3">
                                <label class="form-label"><?php echo $isEditing ? 'New MPIN (leave blank to keep current)' : 'MPIN'; ?></label>
                                <input type="password" name="mpin" class="form-control"
                                    <?php echo $isEditing ? '' : 'required'; ?>
                                    pattern="\d{4,6}" minlength="4" maxlength="6">
                                <div class="form-text">MPIN must be 4-6 digits</div>
                                <div class="invalid-feedback">Valid MPIN is required (4-6 digits)</div>
                            </div> -->

                            <div class="d-flex justify-content-between">
                                <a href="manage_admins.php?type=<?php echo $managingRole; ?>"
                                    class="btn btn-light">
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
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
            // After selecting orgs
            $('#item_District').change(function() {
                var districtId = $(this).val();
                $('#item_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Loading Districts...</option>');
                $('#item_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Select Mandalam First</option>');
                $('#item_Unit').html('<option value="" hidden>Select Unit</option><option value="" disabled>Select Localbody First</option>');
                if (districtId) {
                    $.ajax({
                        url: 'ajax/get_mandalams.php',
                        method: 'GET',
                        data: {
                            district_id: districtId
                        },
                        success: function(response) {
                            let mandalams = JSON.parse(response);
                            let options = '<option value="" hidden>Select Mandalam</option>';
                            if (mandalams.length) {
                                mandalams.forEach(function(mandalam) {
                                    options += `<option value="${mandalam.id}">${mandalam.name}</option>`;
                                });
                            } else {
                                options += `<option value="" disabled>No Mandalams Under Selected district</option>`
                            }
                            $('#item_Mandalam').html(options);
                        },
                        error: function(error) {
                            $('#item_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Error Loading Districts/option>');
                        }
                    });
                } else {
                    $('#item_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Select a valid District</option>');
                }
            });

            $('#item_Mandalam').change(function() {
                var mandalamId = $(this).val();
                $('#item_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Loading Mandalams...</option>');
                $('#item_Unit').html('<option value="" hidden>Select Unit</option><option value="" disabled>Select Localbody First</option>');
                if (mandalamId) {
                    $.ajax({
                        url: 'ajax/get_localbodies.php',
                        method: 'GET',
                        data: {
                            mandalam_id: mandalamId
                        },
                        success: function(response) {
                            let localbodies = JSON.parse(response);
                            let options = '<option value="" hidden>Select Localbody</option>';
                            if (localbodies.length) {
                                localbodies.forEach(function(localbody) {
                                    options += `<option value="${localbody.id}">${localbody.name}</option>`;
                                });
                            } else {
                                options += `<option value="" disabled>No Localbodies Under Selected Mandalam</option>`
                            }
                            $('#item_Localbody').html(options);
                        },
                        error: function(error) {
                            $('#item_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Error Loading Mandalams</option>');
                        }
                    });
                } else {
                    $('#item_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Select a valid Mandalam</option>');
                }
            });

            $('#item_Localbody').change(function() {
                var localbodyId = $(this).val();
                $('#item_Unit').html('<option value="" hidden>Select Unit</option><option value="" disabled>Loading Localbodies...</option>');
                if (localbodyId) {
                    $.ajax({
                        url: 'ajax/get_units.php',
                        method: 'GET',
                        data: {
                            localbody_id: localbodyId
                        },
                        success: function(response) {
                            let units = JSON.parse(response);
                            let options = '<option value="" hidden>Select Unit</option>';
                            if (units.length) {
                                units.forEach(function(unit) {
                                    options += `<option value="${unit.id}">${unit.name}</option>`;
                                });
                            } else {
                                options += `<option value="" disabled>No Units Under Selected Localbody</option>`
                            }
                            $('#item_Unit').html(options);
                        },
                        error: function(error) {
                            $('#item_Unit').html('<option value="" hidden>Select Unit</option><option value="" disabled>Error Loading Localbodies</option>');
                        }
                    });
                } else {
                    $('#item_Unit').html('<option value="" hidden>Select Unit</option><option value="" disabled>Select a valid Localbody</option>');
                }
            });
        })
    </script>
</body>

</html>