<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication  
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
// Define hierarchy levels and their relationships  
$adminHierarchy = [
    'state_admin' => [
        'manages' => ['district_admin', 'mandalam_admin', "localbody_admin", "unit_admin"],
        'table' => ['districts', "mandalams", "localbodies", "units"],
        'name_field' => 'name',
        'parent_field' => [null, 'district_id', "mandalam_id", "localbody_id"],
        'parent_field_table' => [null, 'districts', "mandalams", "localbodies"]
    ],
    'district_admin' => [
        'manages' => ['mandalam_admin', "localbody_admin", "unit_admin"],
        'table' => ["mandalams", "localbodies", "units"],
        'name_field' => 'name',
        'parent_field' => ['district_id', "mandalam_id", "localbody_id"],
        'parent_field_table' => ['districts', "mandalams", "localbodies"]
    ],
    'mandalam_admin' => [
        'manages' => ["localbody_admin", "unit_admin"],
        'table' => ["localbodies", "units"],
        'name_field' => 'name',
        'parent_field' => ["mandalam_id", "localbody_id"],
        'parent_field_table' => ["mandalams", "localbodies"]
    ],
    'localbody_admin' => [
        'manages' => ["unit_admin"],
        'table' => ['units'],
        'name_field' => 'name',
        'parent_field' => ['localbody_id'],
        'parent_field_table' => ["localbodies"]
    ]
];

// Get current admin's level and what they can manage  
$currentUserRole = $_SESSION['role'];

if (!isset($adminHierarchy[$currentUserRole])) {
    header("Location: {$_SESSION['level']}.php");
    exit();
}

// Get the level being managed  
$managingRole = '';
if (isset($_GET['type']) && in_array($_GET['type'], $adminHierarchy[$currentUserRole]['manages'])) {
    $managingRole = $_GET['type'];
} else {
    header("Location: ?type=" . $adminHierarchy[$currentUserRole]['manages'][0]);
    exit();
}

$currentLevel = $adminHierarchy[$currentUserRole];
$canManage = $adminHierarchy[$currentUserRole]['manages'][array_search($managingRole, $currentLevel['manages'])];
$managementTable = $adminHierarchy[$currentUserRole]['table'][array_search($managingRole, $currentLevel['manages'])];
$parentField = $adminHierarchy[$currentUserRole]['parent_field'][array_search($managingRole, $currentLevel['manages'])];
$parentFieldTable = $adminHierarchy[$currentUserRole]['parent_field_table'][array_search($managingRole, $currentLevel['manages'])];
$mainField = $adminHierarchy[$currentUserRole]['parent_field'][0];
$mainFieldTable = $adminHierarchy[$currentUserRole]['parent_field_table'][0];

// Get items to manage based on admin level  
try {
    if ($parentField) {
        if (!$mainField) {
            $query = "SELECT t.*, p.name as parent_name FROM {$managementTable} t 
                  LEFT JOIN {$parentFieldTable} p ON t.$parentField = p.id ORDER BY t.id";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        } else {
            $query = "SELECT t.*, p.name as parent_name FROM {$managementTable} t 
              LEFT JOIN {$parentFieldTable} p ON t.$parentField = p.id 
              WHERE t.$mainField = :parent_id ORDER BY t.id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':parent_id' => $_SESSION['user_level_id']]);
        }
    } else {
        $query = "SELECT * FROM {$managementTable} ORDER BY id";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
echo "<script>console.log(" . json_encode($items) . ");</script>";
echo "<script>console.log(" . json_encode($_SESSION) . ");</script>";


?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>s</title>
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
            <a class="navbar-brand" href="#">Organization Structure Management</a>
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
        <?php if (count($adminHierarchy[$currentUserRole]['manages']) > 1): ?>
            <div class="mb-3">
                <?php foreach ($adminHierarchy[$currentUserRole]['manages'] as $role): ?>
                    <a href="?type=<?php echo $role; ?>" class="btn <?php echo $managingRole === $role ? "btn-primary" : "btn-secondary" ?>">
                        <?php echo ucfirst(str_replace('_admin', '', $role)); ?>s
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <h2>Manage <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>s</h2>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>s</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    Add <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <?php if ($parentFieldTable): ?>
                                    <th><?php echo ucfirst(getSingularForm($parentFieldTable)); ?></th>
                                <?php endif; ?>
                                <?php if ($canManage === 'localbody_admin'): ?>
                                    <th>Type</th>
                                <?php endif; ?>
                                <th>Target Amount</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="12" class="text-center">No records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr data-item="<?php echo $item['id']; ?>">
                                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <?php if ($parentFieldTable): ?>
                                            <td><?php echo htmlspecialchars($item['parent_name']); ?></td>
                                        <?php endif; ?>
                                        <?php if ($canManage === 'localbody_admin'): ?>
                                            <td><?php echo htmlspecialchars($item['type']); ?></td>
                                        <?php endif; ?>
                                        <td class="target-amount">₹<?php echo number_format($item['target_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($item['updated_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-item"
                                                data-id="<?php echo $item['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-target="<?php echo $item['target_amount']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-item"
                                                data-id="<?php echo $item['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addLoadingSpinner" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label for="item_name" class="form-label"><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?> Name</label>
                            <input type="text" class="form-control" id="item_name" required>
                        </div>
                        <?php if ($canManage === 'localbody_admin'): ?>
                            <div class="mb-3">
                                <label for="item_type" class="form-label">Type</label>
                                <select class="form-select" id="item_type" required>
                                    <option value="" hidden>Select Type</option>
                                    <option value="PANCHAYATH">Panchayath</option>
                                    <option value="MUNCIPALITY">Muncipality</option>
                                    <option value="CORPORATION">Corporation</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="item_target" class="form-label">Target Amount</label>
                            <input type="number" class="form-control" id="item_target" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="saveItemBtn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editLoadingSpinner" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <form id="editItemForm">
                        <input type="hidden" id="edit_item_id">
                        <div class="mb-3">
                            <label for="edit_item_name" class="form-label"><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?> Name</label>
                            <input type="text" class="form-control" id="edit_item_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_item_target" class="form-label">Target Amount</label>
                            <input type="number" class="form-control" id="edit_item_target" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="updateItemBtn">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            const currentLevel = '<?php echo $canManage; ?>';
            const managementTable = '<?php echo $managementTable; ?>';

            function showLoading(formId, spinnerId) {
                $(`#${spinnerId}`).removeClass('d-none');
                $(`#${formId}`).addClass('d-none');
            }

            function hideLoading(formId, spinnerId) {
                $(`#${spinnerId}`).addClass('d-none');
                $(`#${formId}`).removeClass('d-none');
            }

            // Add Item  
            $('#addItemForm').submit(function() {
                const name = $('#item_name').val();
                const target = $('#item_target').val();
                const $btn = $("#saveItemBtn");

                // Get type if it's a local body  
                const type = currentLevel === 'localbody_admin' ? $('#item_type').val() : null;

                if (!name || !target || (currentLevel === 'localbody_admin' && !type)) {
                    alert('Please fill all fields');
                    return;
                }

                showLoading('addItemForm', 'addLoadingSpinner');
                $btn.prop('disabled', true);

                const data = {
                    action: 'add',
                    name: name,
                    target_amount: target,
                    level: currentLevel,
                    table: managementTable
                };
                // Add type if it's a local body  
                if (currentLevel === 'localbody_admin') {
                    data.type = type;
                }
                <?php if ($parentField): ?>
                    data.parent_id = <?php echo $_SESSION['user_level_id']; ?>;
                    data.parent_field = '<?php echo $parentField; ?>';
                <?php endif; ?>

                $.ajax({
                    url: 'ajax/ajax_manage_structure.php',
                    method: 'POST',
                    dataType: 'json',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            location.href = '?type=<?php echo $managingRole; ?>';
                        } else {
                            alert('Error: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                        alert('Error adding item. Please check console for details.');
                    },
                    complete: function() {
                        hideLoading('addItemForm', 'addLoadingSpinner');
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Edit Item  
            $('.edit-item').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const target = $(this).data('target');

                $('#edit_item_id').val(id);
                $('#edit_item_name').val(name);
                $('#edit_item_target').val(target);
                $('#editItemModal').modal('show');
            });

            // Update Item  
            $('#editItemForm').submit(function() {
                const id = $('#edit_item_id').val();
                const name = $('#edit_item_name').val();
                const target = $('#edit_item_target').val();
                const $btn = $('#updateItemBtn');

                const data = {
                    action: 'update',
                    id: id,
                    name: name,
                    target_amount: target,
                    level: currentLevel,
                    table: managementTable
                }
                <?php if ($parentField): ?>
                    data.parent_id = <?php echo $_SESSION['user_level_id']; ?>;
                    data.parent_field = '<?php echo $parentField; ?>';
                <?php endif; ?>

                if (!id || !name || !target) {
                    alert('Please fill all fields');
                    return;
                }

                showLoading('editItemForm', 'editLoadingSpinner');
                $btn.prop('disabled', true);

                $.ajax({
                    url: 'ajax/ajax_manage_structure.php',
                    method: 'POST',
                    dataType: 'json',
                    data,
                    success: function(response) {
                        if (response.success) {
                            location.href = '?type=<?php echo $managingRole; ?>';
                        } else {
                            alert('Error: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                        alert('Error updating item. Please check console for details.');
                    },
                    complete: function() {
                        hideLoading('editItemForm', 'editLoadingSpinner');
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Delete Item  
            $('.delete-item').click(function() {
                if (confirm('Are you sure you want to delete this item?')) {
                    const id = $(this).data('id');
                    const $btn = $(this);

                    const data = {
                        action: 'delete',
                        id: id,
                        level: currentLevel,
                        table: managementTable
                    }

                    data.managing_role = "<?php echo $managingRole; ?>"


                    // <?php if ($parentField): ?>
                    //     data.parent_id = <?php echo $_SESSION['user_level_id']; ?>;
                    //     data.parent_field = '<?php echo $parentField; ?>';
                    // <?php endif; ?>

                    $btn.prop('disabled', true);

                    $.ajax({
                        url: 'ajax/ajax_manage_structure.php',
                        method: 'POST',
                        dataType: 'json',
                        data,
                        success: function(response) {
                            if (response.success) {
                                location.href = '?type=<?php echo $managingRole; ?>';
                            } else {
                                alert('Error: ' + (response.message || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            alert('Error deleting item. Please check console for details.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>