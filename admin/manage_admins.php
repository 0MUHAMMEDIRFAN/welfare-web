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
// Define admin hierarchy and their manageable levels  
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

if (!isset($currentLevel)) {
    die("Unauthorized access");
}

// Get the level being managed  
$managingRole = '';
if (isset($_GET['type']) && in_array($_GET['type'], $currentLevel['manages'])) {
    $managingRole = $_GET['type'];
} else {
    header("Location: ?type=" . $currentLevel['manages'][0]);
    exit();
}

$currentTable = $currentLevel['table'][array_search($managingRole, $currentLevel['manages'])];
// $parentField = $currentLevel['parent_field'][array_search($managingRole, $currentLevel['manages'])];
$mainField = $currentLevel['parent_field'][0];
$singularTableName = getSingularForm($currentTable);


// if ($currentUserRole === 'localbody_admin') {
//     // $managingRole = isset($_GET['type']) && $_GET['type'] === 'collector' ? 'collector' : 'unit_admin';
// } else {
//     $managingRole = $currentLevel['manages'];
// }

// Handle MPIN update  
if (isset($_POST['update_mpin'])) {
    try {
        if (!preg_match("/^\d{4,6}$/", $_POST['new_mpin'])) {
            throw new Exception("MPIN must be 4-6 digits");
        }
        $stmt = $pdo->prepare("UPDATE users SET mpin = ?, updated_at = NOW() WHERE id = ? AND role = ?");
        $stmt->execute([
            password_hash($_POST['new_mpin'], PASSWORD_DEFAULT),
            $_POST['admin_id'],
            $managingRole
        ]);
        $success_message = "MPIN updated successfully";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle status toggle  
if (isset($_POST['toggle_status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ? AND role = ?");
        $stmt->execute([
            $_POST['status'] == '1' ? 0 : 1,
            $_POST['admin_id'],
            $managingRole
        ]);
        $success_message = "Status updated successfully";
    } catch (Exception $e) {
        $error_message = "Failed to update status";
    }
}

// Get places with their admins  
try {
    // $query = "SELECT p.*,   
    //                  u.id as admin_id,   
    //                  u.name as admin_name,   
    //                  u.phone as admin_phone,   
    //                  u.is_active,  
    //                  u.created_at as admin_created_at  
    //           FROM {$currentTable} p
    //           LEFT JOIN users u ON u.{$singularTableName}_id = p.id   
    //                            AND u.role = ? ";

    $query = "SELECT u.id as admin_id,   
                     u.name as admin_name,   
                     u.phone as admin_phone,   
                     u.is_active,  
                     u.created_at as admin_created_at,  
                     u.role,
                     u.district_id,
                     u.mandalam_id,
                     u.localbody_id,
                     u.unit_id,
                     u.{$singularTableName}_id as id,
                     p.name as name
                FROM users u 
                LEFT JOIN {$currentTable} p ON u.{$singularTableName}_id = p.id
                WHERE u.role = ?";
    // --   FROM {$currentTable} p
    //   LEFT JOIN {$currentTable} p ON u.{$singularTableName}_id = p.id AND u.role = ? ";

    if ($mainField) {
        $query .= " AND p.{$mainField} = ?";
    }

    // $query .= " ORDER BY p.{$currentLevel['name_field']}";

    $params = [$managingRole];
    if ($mainField) {
        $params[] = $_SESSION['user_level_id'];
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<script>console.log(" . json_encode($places) . ")</script>";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage <?php echo ucfirst(str_replace('_', ' ', $managingRole)); ?>s</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table td {
            vertical-align: middle;
        }

        .action-buttons {
            white-space: nowrap;
        }
    </style>
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
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (count($currentLevel['manages']) > 1): ?>
            <div class="mb-3">
                <?php foreach ($currentLevel['manages'] as $role): ?>
                    <a href="?type=<?php echo $role; ?>" class="btn <?php echo $managingRole === $role ? "btn-primary" : "btn-secondary" ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $role)); ?>s
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <?php echo ucfirst(str_replace('_', ' ', $managingRole)); ?>s Management
                </h5>
                <button class="btn btn-primary btn-sm" onclick="window.location.href='add_admin.php?type=<?php echo $managingRole; ?>'">
                    Add <?php echo ucfirst(str_replace('_', ' ', $managingRole)); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Admin Name</th>
                                <th><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?></th>
                                <th>Phone</th>
                                <th class="text-center" style="width: 70px;">Status</th>
                                <th class="text-end" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($places)): ?>
                                <tr>
                                    <td colspan="12" class="text-center">No records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($places as $place): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($place['admin_id']); ?></td>
                                        <td><?php echo $place['admin_name'] ? htmlspecialchars($place['admin_name']) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($place['name']); ?></td>
                                        <td><?php echo $place['admin_phone'] ? htmlspecialchars($place['admin_phone']) : '-'; ?></td>
                                        <td class="text-center" style="width: 40px;">
                                            <?php if ($place['admin_id']): ?>
                                                <span class="badge <?php echo $place['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $place['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" style="width: 130px;">
                                            <div class="action-buttons">
                                                <?php if ($place['admin_id']): ?>
                                                    <button onclick="showMpinModal(<?php echo $place['admin_id']; ?>)"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="admin_id" value="<?php echo $place['admin_id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $place['is_active']; ?>">
                                                        <button type="submit" name="toggle_status"
                                                            class="btn btn-<?php echo $place['is_active'] ? 'danger' : 'success'; ?> btn-sm"
                                                            onclick="return confirm('Are you sure you want to <?php echo $place['is_active'] ? 'deactivate' : 'activate'; ?> this admin?')">
                                                            <i class="fas fa-<?php echo $place['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <a href="add_admin.php?type=<?php echo $managingRole; ?>&place_id=<?php echo $place['id']; ?>&edit=1&admin_id=<?php echo $place['admin_id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="add_admin.php?type=<?php echo $managingRole; ?>&place_id=<?php echo $place['id']; ?>"
                                                        class="btn btn-success btn-sm">
                                                        <i class="fas fa-plus"></i> Add Admin
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MPIN Update Modal -->
    <div class="modal fade" id="mpinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update MPIN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="mpinForm">
                    <div class="modal-body">
                        <input type="hidden" name="admin_id" id="modal_admin_id">
                        <div class="mb-3">
                            <label class="form-label">New MPIN</label>
                            <input type="password" name="new_mpin" class="form-control"
                                required minlength="4" maxlength="6" pattern="\d{4,6}">
                            <div class="form-text">MPIN must be 4-6 digits</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_mpin" class="btn btn-primary">Update MPIN</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showMpinModal(adminId) {
            document.getElementById('modal_admin_id').value = adminId;
            var mpinModal = new bootstrap.Modal(document.getElementById('mpinModal'));
            mpinModal.show();
        }
    </script>
</body>

</html>