<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

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
        'parent_field_table' => ["units"],
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
        if ($currentHeading == "District") {
            $stmt = $pdo->prepare("SELECT name as district_name FROM {$parentTable} WHERE id = ?");
        } else if ($currentHeading == "Mandalam") {
            $stmt = $pdo->prepare("SELECT
                m.name as mandalam_name, 
                d.name as district_name   
            FROM mandalams m   
            JOIN districts d ON m.district_id = d.id   
            WHERE m.id = ? ");
        } else if ($currentHeading == "Localbody") {
            $stmt = $pdo->prepare("SELECT 
                l.name as localbody_name,  
                m.name as mandalam_name,  
                d.name as district_name  
            FROM localbodies l  
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id  
            WHERE l.id = ? ");
        } else if ($currentHeading == "Unit") {
            $stmt = $pdo->prepare("SELECT   
                u.name as unit_name,  
                l.name as localbody_name,  
                m.name as mandalam_name,  
                d.name as district_name,
                u.target_amount as unit_target_amount
            FROM units u  
            JOIN localbodies l ON u.localbody_id = l.id  
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id  
            WHERE u.id = ?  
        ");
        }
        $stmt->execute([$parent_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
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
    // echo "<script>console.log('" . addslashes($dynamicQuery) . "');</script>";

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
    ), 0) as collected_amount_app,
    COALESCE((  
        SELECT SUM(dn.amount)
        FROM collection_reports dn
        {$dynamicQuery}
        WHERE {$currentAlias}.{$currentChildIdField} = d.id
    ), 0) as collected_amount_coupon,
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
        if ($parentField == "unit_id") {
            $query .= " AND d.role = 'collector'";
        }
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
    $totalTarget = $currentHeading == "Unit" ? $location['unit_target_amount'] : 0;
    $totalCollectedMobile = 0;
    $totalCollectedToday = 0;
    $totalDonations = 0;

    // Query to get total target amount
    if ($currentHeading != "Unit") {
        $totalTargetQuery = "SELECT SUM(target_amount) as total_target FROM {$childTable}";
        if ($parentField) {
            $totalTargetQuery .= " WHERE {$parentField} = :parent_id";
        }
        $stmt = $pdo->prepare($totalTargetQuery);
        if ($parentField) {
            $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $totalTarget = $stmt->fetchColumn();
    }

    // Query to get total collected amount from mobile and total donations via mobile
    $totalCollectedMobileQuery = "SELECT
        COALESCE(SUM(dn.amount), 0) as total_collected_mobile,
        COALESCE(SUM(CASE WHEN dn.payment_type = 'CASH' THEN dn.amount END), 0) as total_cash_collected,
        COALESCE(SUM(CASE WHEN dn.payment_type != 'CASH' THEN dn.amount END), 0) as total_online_collected,
        COALESCE(COUNT(DISTINCT dn.id), 0) as total_donations
            FROM donations dn
            JOIN units u ON dn.unit_id = u.id  
            -- {$dynamicQuery}
            WHERE dn.deleted_at IS NULL";
    if ($parentField) {
        if ($parentField == "unit_id") {
            $totalCollectedMobileQuery .= " AND u.id = :parent_id";
        } else {
            $totalCollectedMobileQuery .= " AND u.{$parentField} = :parent_id";
        }
        // $totalCollectedMobileQuery .= " AND {$currentAlias}.{$parentField} = :parent_id";
    }
    $stmt = $pdo->prepare($totalCollectedMobileQuery);
    if ($parentField) {
        $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCollectedMobile = $result['total_collected_mobile'];
    $totalCashCollected = $result['total_cash_collected'];
    $totalOnlineCollected = $result['total_online_collected'];
    $totalDonations = $result['total_donations'];

    // Query to get total collected amount from paper
    $totalCollectedPaperQuery = "SELECT
        COALESCE(SUM(cr.amount), 0) as total_collected_paper
            FROM collection_reports cr
            JOIN units u ON cr.unit_id = u.id";
    if ($parentField) {
        if ($parentField == "unit_id") {
            $totalCollectedPaperQuery .= " WHERE u.id = :parent_id";
        } else {
            $totalCollectedPaperQuery .= " WHERE u.{$parentField} = :parent_id";
        }
    }
    $stmt = $pdo->prepare($totalCollectedPaperQuery);
    if ($parentField) {
        $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $totalCollectedPaper = $stmt->fetchColumn();
    $totalCollected = $totalCollectedMobile + $totalCollectedPaper;

    // Query to get total collected amount today
    // $totalCollectedTodayQuery = "SELECT
    //     COALESCE(SUM(dn.amount), 0) as total_collected_today
    //         FROM donations dn
    //         JOIN units u ON dn.unit_id = u.id  
    //         -- {$dynamicQuery}
    //         WHERE dn.deleted_at IS NULL AND DATE(dn.created_at) = CURDATE()";
    // if ($parentField) {
    //     if ($parentField == "unit_id") {
    //         $totalCollectedTodayQuery .= " AND u.id = :parent_id";
    //     } else {
    //         $totalCollectedTodayQuery .= " AND u.{$parentField} = :parent_id";
    //     }
    //     // $totalCollectedTodayQuery .= " AND {$currentAlias}.{$parentField} = :parent_id";
    // }
    // $stmt = $pdo->prepare($totalCollectedTodayQuery);
    // if ($parentField) {
    //     $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
    // }
    // $stmt->execute();
    // $totalCollectedToday = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <title><?php echo $currentHeading; ?> Admin Dashboard</title>
    <link rel="stylesheet" href="./dashboard.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
                        <a class="nav-link" href="../admin/resetMpin.php">Reset Mpin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard">
        <div class="header mb-5">
            <div class="d-flex flex-column flex-wrap">
                <h5 class="d-flex flex-wrap gap-1 m-0">Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></h5>
                <p class="d-flex flex-wrap gap-1 breadcrumb m-0">
                    <?php
                    if ($currentHeading == "District") {
                        echo '' . htmlspecialchars($location['district_name']) . ' District';
                    } else if ($currentHeading == "Mandalam") {
                        echo '' . htmlspecialchars($location['district_name']) . ' District';
                        echo ' → ' . htmlspecialchars($location['mandalam_name']) . ' Mandalam';
                    } else if ($currentHeading == "Localbody") {
                        echo '' . htmlspecialchars($location['district_name']) . ' District';
                        echo ' → ' . htmlspecialchars($location['mandalam_name']) . ' Mandalam';
                        echo ' → ' . htmlspecialchars($location['localbody_name']) . ' Localbody';
                    } else if ($currentHeading == "Unit") {
                        echo '' . htmlspecialchars($location['district_name']) . ' District';
                        echo ' → ' . htmlspecialchars($location['mandalam_name']) . ' Mandalam';
                        echo ' → ' . htmlspecialchars($location['localbody_name']) . ' Localbody';
                        echo ' → ' . htmlspecialchars($location['unit_name']) . ' Unit';
                    }
                    ?>
                </p>
            </div>
            <p class="d-flex gap-2 flex-wrap justify-content-end m-0">
                <?php if ($currentHeading == "Unit") {
                    echo '<a href="../admin/manage_collectors.php" class="btn btn-manage">Manage Collectors</a>';
                } else {
                    echo '<a href="../admin/manage_structure.php?level=mandalam" class="btn btn-manage">Manage Organizations</a>
                          <a href="../admin/manage_admins.php?level=mandalam" class="btn btn-manage">Manage Admins</a>';
                }
                ?>
            </p>
        </div>

        <div class="summary-cards">
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Target</h3>
                <!-- <i class="fa-solid fa-minimize card-icon bg-"></i> -->
                <!-- </div> -->
                <p>₹<?php echo ($currentHeading == "Unit") ? $location["unit_target_amount"] : number_format($totalTarget, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Total Collection</h3>
                <!-- <i class="fa-solid fa-arrow-trend-up card-icon"></i> -->
                <!-- </div> -->
                <p>₹<?php echo number_format($totalCollected, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Percentage</h3>
                <!-- <i class="fa-solid fa-percent card-icon bg-"></i> -->
                <!-- </div> -->
                <p><?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>%</p>
                <!-- <h6 class="">(<?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>%)</h6> -->
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Donors</h3>
                <!-- <i class="fa-solid fa-users card-icon bg-"></i> -->
                <!-- </div> -->
                <p><?php echo number_format($totalDonations); ?></p>
            </div>
        </div>
        <h5 class="text-center mb-3">Collected Through Application</h5>
        <div class="summary-cards">
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Online</h3>
                <!-- <i class="fa-brands fa-paypal card-icon bg-"></i> -->
                <!-- </div> -->
                <p>₹<?php echo number_format($totalOnlineCollected, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Offline</h3>
                <!-- <i class="fa-solid fa-money-bill card-icon bg-"></i> -->
                <!-- </div> -->
                <p>₹<?php echo number_format($totalCashCollected, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Total</h3>
                <!-- <i class="fa-solid fa-arrow-trend-up card-icon bg-"></i> -->
                <!-- </div> -->
                <p>₹<?php echo number_format($totalCollectedMobile, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Donors</h3>
                <!-- <i class="fa-solid fa-users card-icon bg-"></i> -->
                <!-- </div> -->
                <p><?php echo number_format($totalDonations); ?></p>
            </div>
        </div>
        <h5 class="text-center mb-3">Collected Through Coupons</h5>
        <div class="summary-cards">
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Total</h3>
                <!-- <i class="fa-solid fa-arrow-trend-up card-icon bg-"></i> -->
                <!-- </div> -->
                <p>₹<?php echo number_format($totalCollectedPaper, 2); ?></p>
            </div>
            <div class="card custom-card">
                <!-- <div class="d-flex justify-content-between align-items-center"> -->
                <h3>Donors</h3>
                <!-- <i class="fa-solid fa-users card-icon bg-"></i> -->
                <!-- </div> -->
                <p>-</p>
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
                        <th>
                            App Collection
                            <?php if ($currentHeading != "Unit"): ?>
                            <?php endif; ?>
                        </th>
                        <th>
                            Paper Collection
                            <?php if ($currentHeading != "Unit"): ?>
                            <?php endif; ?>
                        </th>
                        <th>
                            Total Collection
                            <?php if ($currentHeading != "Unit"): ?>
                            <?php endif; ?>
                        </th>
                        <?php if ($currentHeading != "Unit"): ?>
                            <th>Percentage</th>
                        <?php endif; ?>

                        <!-- <th>Collected Today</th> -->
                        <th class="d-none d-lg-table-cell">Donors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)): ?>
                        <tr>
                            <td colspan="12" class="text-center">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summary as $row): ?>
                            <tr class="table-clickable-row" onclick="location.href='../reports/view_reports.php?level=<?php echo $currentHeading == "Unit" ? "unit" : $childName; ?>&id=<?php echo $currentHeading == "Unit" ? $parent_id . "&colid=" . $row['id'] : $row['id']; ?>'">
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
                                <td>₹<?php echo number_format($row['collected_amount_app'], 2); ?></td>
                                <td>₹<?php echo number_format($row['collected_amount_coupon'], 2); ?></td>
                                <td>₹<?php echo number_format($row['collected_amount_app'] + $row['collected_amount_coupon'], 2); ?></td>
                                <?php if ($currentHeading != "Unit"): ?>
                                    <td>
                                        <?php $percentage = $row['target_amount'] > 0
                                            ? (($row['collected_amount_app'] + $row['collected_amount_coupon']) / $row['target_amount']) * 100
                                            : 0;
                                        echo number_format($percentage, 2) . '%';
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <!-- <td><?php echo htmlspecialchars($row['today_collected']); ?></td> -->
                                <td class="d-none d-lg-table-cell"><?php echo number_format($row['donation_count']); ?></td>
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
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="saveTargetBtn">Save changes</button>
                        </div>
                    </form>
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
            $('.edit-target').click(function(event) {
                event.stopPropagation();
                const entityId = $(this).data('id');
                const currentTarget = $(this).data('target');

                $('#entity_id').val(entityId);
                $('#target_amount').val(currentTarget);
                $('#editTargetModal').modal('show');
            });

            // Save target amount  
            $('#editTargetForm').submit(function(event) {
                event.preventDefault()
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