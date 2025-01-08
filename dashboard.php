<?php
require_once './config/database.php';
require_once './includes/auth.php';
require_once './includes/functions.php';

requireLogin();
// if ($_SESSION['role'] != 'district_admin') {
//     header("Location: {$_SESSION['level']}.php");
//     exit();
// }

$adminHierarchy = [
    'state_admin' => [
        'manages' => ['district_admin', 'mandalam_admin', "localbody_admin", "unit_admin", "collector"],
        'table' => ['districts', "mandalams", "localbodies", "units"],
        'table_id_field' => ["district_id", "mandalam_id", "localbody_id", "unit_id"],
        'name_field' => 'name',
        'parent_field' => [null, 'district_id', "mandalam_id", "localbody_id", "unit_id"],
        'parent_field_table' => [null, 'districts', "mandalams", "localbodies", "units"],
        'heading' => 'State',
        'child_heading' => 'District'
    ],
    'district_admin' => [
        'manages' => ['mandalam_admin', "localbody_admin", "unit_admin", "collector"],
        'table' => ["mandalams", "localbodies", "units"],
        'table_id_field' => ["mandalam_id", "localbody_id", "unit_id"],
        'name_field' => 'name',
        'parent_field' => ['district_id', "mandalam_id", "localbody_id", "unit_id"],
        'parent_field_table' => ['districts', "mandalams", "localbodies", "units"],
        'heading' => 'District',
        'child_heading' => 'Mandalam'
    ],
    'mandalam_admin' => [
        'manages' => ["localbody_admin", "unit_admin", "collector"],
        'table' => ["localbodies", "units"],
        'table_id_field' => ["localbody_id", "unit_id"],
        'name_field' => 'name',
        'parent_field' => ["mandalam_id", "localbody_id", "unit_id"],
        'parent_field_table' => ["mandalams", "localbodies", "units"],
        'heading' => 'Mandalam',
        'child_heading' => 'Localbody'
    ],
    'localbody_admin' => [
        'manages' => ["unit_admin", "collector"],
        'table' => ['units'],
        'table_id_field' => ["unit_id"],
        'name_field' => 'name',
        'parent_field' => ["localbody_id", "unit_id"],
        'parent_field_table' => ["localbodies", "units"],
        'heading' => 'Localbody',
        'child_heading' => 'Unit'
    ],
    'unit_admin' => [
        'manages' => ["collector"],
        'table' => ["users"],
        'table_id_field' => ["collector_id"],
        'name_field' => 'name',
        'parent_field' => ["unit_id"],
        'parent_field_table' => ["units", "units"],
        'heading' => 'Unit',
        'child_heading' => 'Collector'
    ]
];

$parent_id = $_SESSION['user_level_id'];
$currentUserRole = $_SESSION['role'];
$currentLevel = $adminHierarchy[$currentUserRole];
$currentTable = $currentLevel['table'];
$childTable = $currentTable[0];

$currentHeading = $currentLevel['heading'];
$childHeading = $currentLevel['child_heading'];

$currentChildIdField = $currentLevel['table_id_field'][0];
$childName = strtolower($childHeading);
$parentTable = $currentLevel['parent_field_table'][0];
$parentField = $currentLevel['parent_field'][0];

$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;

try {
    // Get location details  
    if ($parentTable) {
        $stmt = $pdo->prepare("SELECT name FROM {$parentTable} WHERE id = ?");
        $stmt->execute([$parent_id]);
        $district = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $dynamicQuery = "";
    $currentAlias = "dn";
    $i = count($currentTable) - 1;
    while ($i > 0) {
        $table = $currentTable[$i];
        $alias = chr(100 + $i); // Generate alias like 'd', 'e', 'f', etc.
        $dynamicQuery .= " JOIN {$table} {$alias} ON {$currentAlias}.{$currentLevel['table_id_field'][$i]} = {$alias}.id";
        $currentAlias = $alias;
        $i--;
    }
    // Log the dynamic query for debugging
    echo "<script>console.log('" . addslashes($dynamicQuery) . "');</script>";

    // Get child org summary  
    $query = "SELECT SQL_CALC_FOUND_ROWS
    d.id,  
    d.name,  
    d." . ($currentHeading === "Unit" ? "phone" : "target_amount") . ",  
    COALESCE((  
        SELECT SUM(dn.amount)
        FROM donations dn
        {$dynamicQuery}
        WHERE {$currentAlias}.{$currentChildIdField} = d.id
        AND dn.deleted_at IS NULL
    ), 0) as collected_amount,
    COALESCE((  
        SELECT COUNT(DISTINCT dn.id)
        FROM donations dn
        {$dynamicQuery}
        WHERE {$currentAlias}.{$currentChildIdField} = d.id
        AND dn.deleted_at IS NULL
    ), 0) as donation_count
    FROM {$childTable} d";

    if ($parentField) {
        $query .= " WHERE d.{$parentField} = :parent_id";
    }
    $query .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($parentField) {
        $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total items count for pagination
    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    // Calculate totals  
    $totalTarget = 0;
    $totalCollected = 0;
    $totalDonations = 0;
    foreach ($summary as $row) {
        if ($currentHeading != "Unit") {
            $totalTarget += $row['target_amount'];
        }
        $totalCollected += $row['collected_amount'];
        $totalDonations += $row['donation_count'];
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <title><?php echo $currentHeading; ?> Admin Dashboard</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo $currentHeading; ?> Admin Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="./admin/resetMpin.php">Reset Mpin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard">
        <div class="header">
            <p class="d-flex flex-wrap gap-1">Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                <?php if ($parentTable) {
                    echo '(' . htmlspecialchars($district['name']) . ' ' . $currentHeading . ')';
                } ?>
            </p>
            <p class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="./admin/manage_structure.php?level=mandalam" class="btn btn-manage">Manage Organizations</a>
                <a href="./admin/manage_admins.php?level=mandalam" class="btn btn-manage">Manage Admins</a>
            </p>
        </div>

        <div class="summary-cards">
            <?php if ($currentHeading != "Unit"): ?>
                <div class="card">
                    <h3>Total Target</h3>
                    <p>₹<?php echo number_format($totalTarget, 2); ?></p>
                </div>
            <?php endif; ?>
            <div class="card">
                <h3>Total Collected</h3>
                <p>₹<?php echo number_format($totalCollected, 2); ?></p>
            </div>
            <div class="card">
                <h3>Achievement</h3>
                <p><?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>%</p>
            </div>
            <div class="card">
                <h3>Persons Donated</h3>
                <p><?php echo number_format($totalDonations); ?></p>
            </div>
        </div>

        <div class="content table-responsive">
            <h2><?php echo $childHeading; ?> Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo $childHeading; ?></th>
                        <?php if ($currentHeading != "Unit"): ?>
                            <th>Target Amount</th>
                        <?php else: ?>
                            <th>Phone</th>
                        <?php endif; ?>
                        <th>Collected Amount</th>
                        <?php if ($currentHeading != "Unit"): ?>
                            <th>Achievement</th>
                        <?php endif; ?>
                        <th class="d-none d-lg-table-cell">Persons Donated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)): ?>
                        <tr>
                            <td colspan="12" class="text-center">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summary as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <?php if ($currentHeading != "Unit"): ?>
                                    <td>
                                        ₹<?php echo number_format($row['target_amount'], 2); ?>
                                        <button class="btn btn-sm btn-warning edit-target"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-target="<?php echo $row['target_amount']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <?php endif; ?>
                                <td>₹<?php echo number_format($row['collected_amount'], 2); ?></td>
                                <?php if ($currentHeading != "Unit"): ?>
                                    <td>
                                        <?php
                                        $percentage = $row['target_amount'] > 0
                                            ? ($row['collected_amount'] / $row['target_amount']) * 100
                                            : 0;
                                        echo number_format($percentage, 2) . '%';
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <td class="d-none d-lg-table-cell"><?php echo number_format($row['donation_count']); ?></td>
                                <td>
                                    <a href="./dashboard/view_details.php?level=<?php echo $childName; ?>&id=<?php echo $row['id']; ?>"
                                        class="btn btn-view">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-info-row caption">
                            <td colspan="12" class="">
                                <div class="text-center d-flex justify-content-between align-items-center gap-2 small">
                                    <p class="align-middle h-100 m-0">Total : <?php echo count($summary); ?> / <?php echo $totalItems; ?></p>
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary btn-sm <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            ← Prev
                                        </a>
                                        <select class="form-select d-inline w-auto form-select-sm" onchange="location = this.value;">
                                            <?php for ($i = 1; $i <= ceil($totalItems / $limit); $i++): ?>
                                                <option value="?page=<?php echo $i; ?>" <?php echo $i == $page ? 'selected' : ''; ?>>
                                                    Page <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <a href="?page=<?php echo min(ceil($totalItems / $limit), $page + 1); ?>" class="btn btn-secondary btn-sm <?php echo $page == ceil($totalItems / $limit) ? 'disabled' : ''; ?>">
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

    <style>
        .dashboard {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .breadcrumb {
            color: #666;
            margin-bottom: 20px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;

            @media (max-width: 992px) {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));

                @media (max-width: 668px) {
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                }
            }
        }

        .card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .card p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-view {
            background: #007bff;
            color: white;
        }

        .btn-manage {
            background: rgb(250, 193, 7);
            color: black;
        }

        .btn.edit-target {
            display: inline-block;
            padding: 5px 5px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 11px;
        }

        .gap {
            margin: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
    <!-- Target Amount Edit Modal -->
    <div class="modal fade" id="editTargetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Target Amount</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTargetForm">
                        <input type="hidden" id="entity_id">
                        <div class="mb-3">
                            <label for="target_amount" class="form-label">Target Amount</label>
                            <input type="number" class="form-control" id="target_amount" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveTargetBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Bootstrap JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Edit target amount button click  
            $('.edit-target').click(function() {
                const entityId = $(this).data('id');
                const currentTarget = $(this).data('target');

                $('#entity_id').val(entityId);
                $('#target_amount').val(currentTarget);
                $('#editTargetModal').modal('show');
            });

            // Save target amount  
            $('#saveTargetBtn').click(function() {
                const entityId = $('#entity_id').val();
                const targetAmount = $('#target_amount').val();

                $.ajax({
                    url: 'ajax/ajax_update_target.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        entity_id: entityId,
                        target_amount: targetAmount
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error updating target amount: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('Error updating target amount. Please check console for details.');
                    }
                });
            });
        });
    </script>
</body>

</html>