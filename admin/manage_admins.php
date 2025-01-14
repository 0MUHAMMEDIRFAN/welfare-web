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
$currentManages = $currentLevel['manages'];
$mainParentFields = $currentLevel['parent_field'];

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

$canManage = $currentManages[array_search($managingRole, $currentManages)];
$mainTables = $currentLevel['table'];
$currentTable = $mainTables[array_search($managingRole, $currentLevel['manages'])];
// $parentField = $currentLevel['parent_field'][array_search($managingRole, $currentLevel['manages'])];
$mainField = $currentLevel['parent_field'][0];
$singularTableName = getSingularForm($currentTable);


$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;


// if ($currentUserRole === 'localbody_admin') {
// // $managingRole = isset($_GET['type']) && $_GET['type'] === 'collector' ? 'collector' : 'unit_admin';
// } else {
// $managingRole = $currentLevel['manages'];
// }

// Handle MPIN update
// if (isset($_POST['update_mpin'])) {
// try {
// if (!preg_match("/^\d{4,6}$/", $_POST['new_mpin'])) {
// throw new Exception("MPIN must be 4-6 digits");
// }
// $stmt = $pdo->prepare("UPDATE users SET mpin = ?, updated_at = NOW() WHERE id = ? AND role = ?");
// $stmt->execute([
// password_hash($_POST['new_mpin'], PASSWORD_DEFAULT),
// $_POST['admin_id'],
// $managingRole
// ]);
// $success_message = "MPIN updated successfully";
// } catch (Exception $e) {
// $error_message = $e->getMessage();
// }
// }

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

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Filter options
$filterOptions = [];
if (isset($_GET['unit']) && in_array('unit_admin', $currentLevel['manages'])) {
    $filterOptions['unit_id'] = $_GET['unit'];
} else if (isset($_GET['localbody']) && in_array('localbody_admin', $currentLevel['manages'])) {
    $filterOptions['localbody_id'] = $_GET['localbody'];
} else if (isset($_GET['mandalam']) && in_array('mandalam_admin', $currentLevel['manages'])) {
    $filterOptions['mandalam_id'] = $_GET['mandalam'];
} else if (isset($_GET['district']) && in_array('district_admin', $currentLevel['manages'])) {
    $filterOptions['district_id'] = $_GET['district'];
}

// Build filter query
$filterQuery = '';
$filterParams = '';
$filteredText = "";
foreach ($filterOptions as $field => $value) {
    $fieldText =  str_replace('_id', '', $field);
    $filterQuery .= " AND u.$field = :$field";
    $filterParams .= "&$fieldText=$value";
    $filteredText = "Filtered By $fieldText";
}

// Get places with their admins
try {
    // Handle search functionality
    if ($search) {
        $searchQuery = " AND (u.name LIKE :search OR p.name LIKE :search)";
        $searchParam = '%' . $search . '%';
        $searchParams = "&search=$search";
    } else {
        $searchQuery = '';
        $searchParam = '';
        $searchParams = '';
    }

    // $sortField = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    // $sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

    $query = "SELECT SQL_CALC_FOUND_ROWS u.id as admin_id,
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
        WHERE u.role = :role";

    if ($mainField) {
        $query .= " AND p.{$mainField} = :user_level_id";
    }

    if (!empty($search)) {
        $query .= $searchQuery;
    }

    $query .= $filterQuery;

    // $query .= " ORDER BY {$sortField} {$sortOrder}";
    $query .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':role', $managingRole, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if ($mainField) {
        $stmt->bindParam(':user_level_id', $_SESSION['user_level_id'], PDO::PARAM_INT);
    }
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    foreach ($filterOptions as $field => $value) {
        $stmt->bindParam(":$field", $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
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

    <meta name="viewport" content="width=device-width, initial-scale=1">

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
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="header">
            <?php if (count($currentLevel['manages']) > 1): ?>
                <div class="d-flex gap-1 flex-wrap">
                    <?php foreach ($currentLevel['manages'] as $role): ?>
                        <a href="?type=<?php echo $role; ?>" class="btn <?php echo $managingRole === $role ? "btn-primary" : "btn-secondary" ?>">
                            <span class="d-none d-lg-inline"><?php echo ucfirst(str_replace('_', ' ', $role)); ?>s</span>
                            <span class="d-lg-none"><?php echo ucfirst(str_replace('_admin', '', $role)); ?>s</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="m-0">
                <a href="../dashboard/dashboard.php" class=" btn btn-secondary">← Back</a>
            </p>
        </div>

        <div class="mb-3 d-flex flex-wrap justify-content-end gap-1">
            <?php if ($filteredText): ?>
                <a href="?type=<?php echo $managingRole; ?>" class="btn btn-info"><?php echo $filteredText; ?> <i class="fa-solid fa-circle-xmark"></i></a>
            <?php endif; ?>
            <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="fa-solid fa-list"></i> Filter Table</button>
            <form method="GET" class="d-flex flex-wrap gap-1 justify-content-between align-items-center">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($managingRole); ?>">
                <div class="input-group w-auto m-0">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                <!-- <div class="input-group">
                    <label class="input-group-text" for="sort">Sort by:</label>
                    <select class="form-select" name="sort" id="sort" onchange="this.form.submit()">
                        <option value="name" <?php echo $sortField === 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="admin_created_at" <?php echo $sortField === 'admin_created_at' ? 'selected' : ''; ?>>Created At</option>
                    </select>
                    <select class="form-select" name="order" onchange="this.form.submit()">
                        <option value="asc" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div> -->
            </form>
        </div>

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
                                <th>User Name</th>
                                <th><?php echo ucfirst($singularTableName); ?></th>
                                <th>Phone</th>
                                <th>createdAt</th>
                                <th class="text-center" style="width: 70px;">Status</th>
                                <th class="text-end" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="12" class="text-center">No records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $place): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($place['admin_id']); ?></td>
                                        <td><?php echo $place['admin_name'] ? htmlspecialchars($place['admin_name']) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($place['name']); ?></td>
                                        <td><?php echo $place['admin_phone'] ? htmlspecialchars($place['admin_phone']) : '-'; ?></td>
                                        <td><?php echo $place['admin_phone'] ? htmlspecialchars($place['admin_created_at']) : '-'; ?></td>
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
                                                    <!-- <button onclick="showMpinModal(<?php echo $place['admin_id']; ?>)"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-key"></i>
                                                    </button> -->
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
                                <tr class="table-info-row caption">
                                    <td colspan="12" class="">
                                        <div class="text-center d-flex justify-content-between align-items-center gap-2 small">
                                            <p class="align-middle h-100 m-0">Total : <?php echo count($items); ?> / <?php echo $totalItems; ?></p>
                                            <div class="d-flex justify-content-center align-items-center gap-2">
                                                <a href="?type=<?php echo $managingRole; ?>&page=<?php echo max(1, $page - 1); ?><?php echo htmlspecialchars($searchParams); ?><?php echo htmlspecialchars($filterParams); ?>" class="btn btn-secondary btn-sm <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                                    ← Prev
                                                </a>
                                                <select class="form-select d-inline w-auto form-select-sm" onchange="location = this.value;">
                                                    <?php for ($i = 1; $i <= ceil($totalItems / $limit); $i++): ?>
                                                        <option value="?type=<?php echo $managingRole; ?>&page=<?php echo $i; ?><?php echo htmlspecialchars($searchParams); ?><?php echo htmlspecialchars($filterParams); ?>" <?php echo $i == $page ? 'selected' : ''; ?>>
                                                            Page <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <a href="?type=<?php echo $managingRole; ?>&page=<?php echo min(ceil($totalItems / $limit), $page + 1); ?><?php echo htmlspecialchars($searchParams); ?><?php echo htmlspecialchars($filterParams); ?>" class="btn btn-secondary btn-sm <?php echo $page == ceil($totalItems / $limit) ? 'disabled' : ''; ?>">
                                                    Next →
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter <?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>s</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="filterLoadingSpinner" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <form id="filterForm">
                        <?php
                        $i = 0;
                        while ($i <= array_search($managingRole, $currentManages) && $currentManages[$i] !== 'collector') {
                            $mainName = ucfirst(str_replace('_admin', '', $currentManages[$i]));
                            echo '<div class="mb-3">
                            <label class="form-label">' . $mainName . '</label>
                                <select name="' . strtolower($mainName) . '" id="filter_' . $mainName . '" class="form-control" ' . ($i == 0 ? "required" : "") . '>
                                    <option value="" hidden>Select ' . $mainName . '</option><option value="" disabled>Select Previous First</option>';
                            if ($i == 0) {
                                $mainTable = $mainTables[$i];
                                $mainParentField = $mainParentFields[$i];
                                $query = "SELECT id, name FROM {$mainTable} WHERE 1";
                                if ($mainParentField) {
                                    $query .= " AND {$mainParentField} = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$_SESSION['user_level_id']]);
                                } else {
                                    $stmt = $pdo->query($query);
                                }
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = '';
                                    echo "<option value=\"{$row['id']}\" $selected>{$row['name']}</option>";
                                }
                            }
                            echo '</select>
                                <div class="invalid-feedback">' . $mainName . ' is required</div>
                            </div>';
                            $i++;
                        }
                        ?>
                        <!-- <?php if ($canManage === 'localbody_admin'): ?>
                            <div class="mb-3">
                                <label for="item_type" class="form-label">Type</label>
                                <select class="form-select" id="item_type" required>
                                    <option value="" hidden>Select Type</option>
                                    <option value="PANCHAYAT">PANCHAYAT</option>
                                    <option value="MUNICIPALITY">MUNICIPALITY</option>
                                    <option value="CORPORATION">CORPORATION</option>
                                </select>
                            </div>
                        <?php endif; ?> -->
                        <!-- <div class="mb-3">
                            <label for="item_target" class="form-label">Target Amount</label>
                            <input type="number" class="form-control" id="item_target" required>
                        </div> -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="saveFilterBtn">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MPIN Update Modal -->
    <!-- <div class="modal fade" id="mpinModal" tabindex="-1">
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
    </div> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // function showMpinModal(adminId) {
        //     document.getElementById('modal_admin_id').value = adminId;
        //     var mpinModal = new bootstrap.Modal(document.getElementById('mpinModal'));
        //     mpinModal.show();
        // }
        $(document).ready(function() {
            const currentLevel = '<?php echo $canManage; ?>';
            const MainLevel = '<?php echo $currentManages[0]; ?>'
            const currentUserLevel = '<?php echo $currentUserRole; ?>';
            const currentTable = '<?php echo $currentTable; ?>';
            // console.log(currentLevel, MainLevel, currentUserLevel, currentTable)

            function showLoading(formId, spinnerId) {
                $(`#${spinnerId}`).removeClass('d-none');
                $(`#${formId}`).addClass('d-none');
            }

            function hideLoading(formId, spinnerId) {
                $(`#${spinnerId}`).addClass('d-none');
                $(`#${formId}`).removeClass('d-none');
            }

            // After selecting orgs
            $('#filter_District').change(function() {
                var districtId = $(this).val();
                if (districtId) {
                    $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Loading Mandalams...</option>');
                    loadMandalams(districtId).then((response) => {
                        let mandalams = JSON.parse(response);
                        let options = '<option value="" hidden>Select Mandalam</option>';
                        if (mandalams.length) {
                            mandalams.forEach(function(mandalam) {
                                options += `<option value="${mandalam.id}">${mandalam.name}</option>`;
                            });
                        } else {
                            options += `<option value="" disabled>No Mandalams Under This District</option>`
                        }
                        $('#filter_Mandalam').html(options);
                    }).catch((error) => {
                        $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Error Loading Mandalam</option>');
                        $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
                    });
                } else {
                    $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option>');
                    $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
                }
            });

            $('#filter_Mandalam').change(function() {
                if ($(this).val()) {
                    $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Loading Localbodies...</option>');
                    loadLocalbodies($(this).val()).then((response) => {
                        let localbodies = JSON.parse(response);
                        let options = '<option value="" hidden>Select Localbody</option>';
                        if (localbodies.length) {
                            localbodies.forEach(function(localbody) {
                                options += `<option value="${localbody.id}">${localbody.name}</option>`;
                            });
                        } else {
                            options += `<option value="" disabled>No Localbodies Under This Mandalam</option>`
                        }
                        $('#filter_Localbody').html(options);
                    }).catch((error) => {
                        $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Error Loading Localbodies</option>');
                    });
                } else {
                    $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
                }
            });

            // Filter Table
            $('#filterForm').submit(function(event) {
                event.preventDefault();
                // const name = $('#item_name').val();
                // const target = $('#item_target').val();
                const $btn = $("#saveFilterBtn");

                // Get type if it's a local body  
                // const type = currentLevel === 'localbody_admin' ? $('#item_type').val() : null;

                // if (!name || !target || (currentLevel === 'localbody_admin' && !type)) {
                //     alert('Please fill all fields');
                //     return;
                // }
                const formData = $(this).serializeArray();
                const url = new URL(window.location.href);
                const params = new URLSearchParams(url.search);

                formData.forEach(field => {
                    if (field.value) {
                        params.set(field.name, field.value);
                    } else {
                        params.delete(field.name);
                    }
                });

                window.location.href = `${url.pathname}?${params.toString()}`;
                console.log(`${url.pathname}?${params.toString()}`);

                showLoading('filterForm', 'filterLoadingSpinner');
                $btn.prop('disabled', true);



            });

            function loadMandalams(districtId) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: 'ajax/get_mandalams.php',
                        method: 'GET',
                        data: {
                            district_id: districtId
                        },
                        success: function(response) {
                            resolve(response);
                        },
                        error: function(xhr, status, error) {
                            reject(error);
                        }
                    });
                });
            }

            function loadLocalbodies(mandalamId) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: 'ajax/get_localbodies.php',
                        method: 'GET',
                        data: {
                            mandalam_id: mandalamId
                        },
                        success: function(response) {
                            resolve(response);
                        },
                        error: function(xhr, status, error) {
                            reject(error);
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>