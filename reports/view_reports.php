<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$level = $_GET['level'] ?? '';
$id = $_GET['id'] ?? '';
$parent_id = $_GET['parent_id'] ?? '';
$collector_id = $_GET['colid'] ?? '';
$collector_filter = "";

$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$totalAmountFliter = $_GET['total_amount_filter'] ?? '';
$totalPaperFliter = $_GET['total_paper_filter'] ?? '';
$totalAppFliter = $_GET['total_app_filter'] ?? '';
$sortField = $_GET['sort_field'] ?? '';
$sortOrder = $_GET['sort_order'] ?? '';

$sortFieldParams = "";
if ($sortField) {
    $sortFieldParams .= "&sort_field=$sortField";
    if ($sortOrder) {
        $sortFieldParams .= "&sort_order=$sortOrder";
    }
} else {
    $sortField = $id && $level === "unit" ? "id" : 'total_collected';
    $sortOrder = 'DESC';
}

$filteredText = "";
$filteredDateText = "";

$dateFilterQuery1 = '';
$dateFilterQuery2 = '';
$FilterParams = '';
$FilterDateParams = '';
if ($fromDate) {
    $dateFilterQuery1 .= " AND d.created_at >= '$fromDate'";
    $dateFilterQuery2 .= " AND cr.collection_date >= '$fromDate'";
    $FilterDateParams .= "&from_date=$fromDate";
    $filteredDateText = ($toDate ? "" : "After ") . $fromDate;
}
if ($toDate) {
    $dateFilterQuery1 .= " AND d.created_at <= '$toDate'";
    $dateFilterQuery2 .= " AND cr.collection_date <= '$toDate'";
    $FilterDateParams .= "&to_date=$toDate";
    $filteredDateText .= ($fromDate ? " - " : "Before ") . $toDate;
}
$tableFilterQuery = 'HAVING 1=1';
if ($totalAmountFliter) {
    if (strpos($totalAmountFliter, '<') !== false) {
        $value = str_replace('<', '', $totalAmountFliter);
        $tableFilterQuery .= " AND total_collected <= $value";
    } elseif (strpos($totalAmountFliter, '>') !== false) {
        $value = str_replace('>', '', $totalAmountFliter);
        $tableFilterQuery .= " AND total_collected >= $value";
    } else {
        $tableFilterQuery .= " AND total_collected = $totalAmountFliter";
    }
    $FilterParams .= "&total_amount_filter=$totalAmountFliter";
    $filteredText = "Filters Applied";
}
if ($totalPaperFliter) {
    if (strpos($totalPaperFliter, '<') !== false) {
        $value = str_replace('<', '', $totalPaperFliter);
        $tableFilterQuery .= " AND total_collected_paper <= $value";
    } elseif (strpos($totalPaperFliter, '>') !== false) {
        $value = str_replace('>', '', $totalPaperFliter);
        $tableFilterQuery .= " AND total_collected_paper >= $value";
    } else {
        $tableFilterQuery .= " AND total_collected_paper = $totalPaperFliter";
    }
    $FilterParams .= "&total_paper_filter=$totalPaperFliter";
    $filteredText = "Filters Applied";
}
if ($totalAppFliter) {
    if (strpos($totalAppFliter, '<') !== false) {
        $value = str_replace('<', '', $totalAppFliter);
        $tableFilterQuery .= " AND total_collected_app <= $value";
    } elseif (strpos($totalAppFliter, '>') !== false) {
        $value = str_replace('>', '', $totalAppFliter);
        $tableFilterQuery .= " AND total_collected_app >= $value";
    } else {
        $tableFilterQuery .= " AND total_collected_app = $totalAppFliter";
    }
    $FilterParams .= "&total_app_filter=$totalAppFliter";
    $filteredText = "Filters Applied";
}

// // Filter options
// $filterOptions = [];
// if (isset($_GET['localbody']) && in_array('localbody_admin', $currentUserManages)) {
//     $filterOptions['localbody_id'] = $_GET['localbody'];
// } else if (isset($_GET['mandalam']) && in_array('mandalam_admin', $currentUserManages)) {
//     $filterOptions['mandalam_id'] = $_GET['mandalam'];
// } else if (isset($_GET['district']) && in_array('district_admin', $currentUserManages)) {
//     $filterOptions['district_id'] = $_GET['district'];
// }

// // Build filter query
// $filterQuery = '';
// $filterParams = '';
// foreach ($filterOptions as $field => $value) {
//     $fieldText =  str_replace('_id', '', $field);
//     $filterQuery .= " AND t.$field = :$field";
//     $filterParams .= "&$fieldText=$value";
//     $filteredText = "Filtered By $fieldText";
// }


if ($collector_id) {
    // $collector_filter = " AND d.collector_id = $collector_id ";
}

$adminHierarchy = [
    'state_admin' => [
        'manages' => ['district_admin', 'mandalam_admin', "localbody_admin", "unit_admin"],
        'table' => ["districts", "mandalams", "localbodies", "units"],
        'name_field' => 'name',
        'parent_field' => [null, 'district_id', "mandalam_id", "localbody_id"],
        'parent_field_table' => [null, "districts", "mandalams", "localbodies"]
    ],
    'district_admin' => [
        'manages' => ['mandalam_admin', "localbody_admin", "unit_admin"],
        'table' => ["mandalams", "localbodies", "units"],
        'name_field' => 'name',
        'parent_field' => ['district_id', "mandalam_id", "localbody_id"],
        'parent_field_table' => ["districts", "mandalams", "localbodies"]
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
    ],
    'unit_admin' => [
        'manages' => ["unit_admin"],
        'table' => ['units'],
        'name_field' => 'name',
        'parent_field' => ['unit_id'],
        'parent_field_table' => ["units"]
    ]
];


// Define admin hierarchy and their manageable levels  
$levelHierarchy = [
    'district' => [
        'manages' => ['district', 'mandalam', "localbody", "unit"],
        'child' => ['mandalam', "localbody", "unit", "collector"],
        'child_tables' => ["mandalams", "localbodies", "units", "units"],
        'name_field' => 'name',
        'parent' => 'state',
        'parent_field' => ['district_id', "mandalam_id", "localbody_id", "unit_id"],
        'parent_field_table' => ["districts", "mandalams", "localbodies", "units"],
        'parent_table' => [],
        'parent_table_field' => []

    ],
    'mandalam' => [
        'manages' => ['mandalam', "localbody", "unit"],
        'child' => ["localbody", "unit", "collector"],
        'child_tables' => ["localbodies", "units", "units"],
        'name_field' => 'name',
        'parent' => 'district',
        'parent_field' => ["mandalam_id", "localbody_id", "unit_id"],
        'parent_field_table' => ["mandalams", "localbodies", "units"],
        'parent_table' => ["districts"],
        'parent_table_field' => ["district"]
    ],
    'localbody' => [
        'manages' => ["localbody", "unit"],
        'child' => ["unit", "collector"],
        'child_tables' => ['units', 'units'],
        'name_field' => 'name',
        'parent' => 'mandalam',
        'parent_field' => ["localbody_id", "unit_id"],
        'parent_field_table' => ["localbodies", "units"],
        'parent_table' => ["districts", "mandalams"],
        'parent_table_field' => ["district", "mandalam"]
    ],
    'unit' => [
        'manages' => ["unit"],
        'child' => ["collector"],
        'child_tables' => ['units'],
        'name_field' => 'name',
        'parent' => 'localbody',
        'parent_field' => ["unit_id"],
        'parent_field_table' => ["units"],
        'parent_table' => ["districts", "mandalams", "localbodies"],
        'parent_table_field' => ["district", "mandalam", "localbody"]
    ],
    'collector' => [
        'manages' => ["user"],
        'child' => ["users"],
        'child_tables' => ['users'],
        'name_field' => 'name',
        'parent' => 'unit',
        'parent_field' => ["unit_id"],
        'parent_field_table' => [],
        'parent_table' => ["districts", "mandalams", "localbodies", "units"],
        'parent_table_field' => ["district", "mandalam", "localbody", "unit"]
    ]
];

$currentUserRole = $_SESSION['role'];
$currentUserLevel = $_SESSION['level'];
$currentUserLevelId = $_SESSION['user_level_id'];
$currentUserLevelPerm = $adminHierarchy[$currentUserRole];
$currentUserManages = $currentUserLevelPerm['manages'];
$currentUserTables = $currentUserLevelPerm['table'];
$currentUserParentTables = $currentUserLevelPerm['parent_field_table'];
$currentUserParents = $currentUserLevelPerm['parent_field'];
$currentUserParent = $currentUserParents[0];
$currentUserTables = $currentUserLevelPerm['table'];

if (!$level) {
    $level = str_replace('_admin', '', $currentUserManages[0]);
    $locationStr = "view_reports.php?level=$level";
    if ($currentUserRole == "unit_admin") {
        $locationStr .= "&id=$currentUserLevelId";
    }
    header("Location: $locationStr");
    exit();
} else {
    if (!in_array($level . "_admin", $currentUserManages)) {
        die("Invalid Params");
    }
    if (!$id) {
        $userlevel = $levelHierarchy[$level]['parent'];
        if ($currentUserRole == "unit_admin") {
            // error_log($currentUserRole.PHP_EOL,3,"./log.log");
            header("Location: view_reports.php?level=unit&id=$currentUserLevelId");
            exit();
        }
    } else {
        $userlevel = $level;
    }
}

$managingRole = $level . "_admin";

$currentOrgLevelPerm = $levelHierarchy[$level];
$currentOrgLevelChilds = $currentOrgLevelPerm['child'];
$currentOrgLevelTables = $currentOrgLevelPerm['parent_field_table'];
$currentOrgLevelParentTables = $currentOrgLevelPerm['parent_table'];
$currentOrgLevelParentTableFields = $currentOrgLevelPerm['parent_table_field'];
$currentOrgLevelChildTables = $currentOrgLevelPerm['child_tables'];
$currentOrgLevelChildIds = $currentOrgLevelPerm['parent_field'] ?? '';


try {

    $currentOrgLevelIdField = $currentOrgLevelChildIds[0] == "unit_id" ? "id" : $currentOrgLevelChildIds[0];
    $currentOrgLevelChildIdField = $currentOrgLevelChildIds[1] ?? '';
    $currentOrgLevelChildTable = $currentOrgLevelChildTables[0];
    $currentOrgLevelTable = $currentOrgLevelTables[0];


    // Get the current level details  
    // $dynamicQuery = "";
    // $currentAlias = "a";
    // $i = 0;
    // while ($i < count($currentUserTables) - 1) {
    //     $table = $currentUserTables[$i];
    //     $alias = chr(100 + $i); // Generate alias like 'd', 'e', 'f', etc.
    //     $dynamicQuery .= " JOIN {$table} {$alias} ON {$currentAlias}.{$currentUserLevelPerm['parent_field'][$i + 1]} = {$alias}.id";
    //     $currentAlias = $alias;
    //     $i++;
    // }
    $dynamicOrgStructQuery = "";
    $currentAlias = "a";
    $i = 1;
    while ($i < count($currentOrgLevelTables)) {
        $table = $currentOrgLevelTables[$i];
        $alias = chr(100 + $i); // Generate alias like 'd', 'e', 'f', etc.
        $dynamicOrgStructQuery .= "(SELECT COUNT(DISTINCT $alias.id) FROM $table $alias" . ($id ? " WHERE $alias.$currentOrgLevelIdField = $id" : ($currentUserParent ? " WHERE $alias.$currentUserParent = $currentUserLevelId" : "")) . ") as total_$table, ";
        $currentAlias = $alias;
        $i++;
    }
    $dynamicOrgParentStructQuery = "";
    $dynamicOrgNameQuery = "";
    if ($id && count($currentOrgLevelParentTables)) {
        $currentAlias = "a";
        $i = count($currentOrgLevelParentTables) - 1;
        while ($i > -1) {
            $table = $currentOrgLevelParentTables[$i];
            $field = $currentOrgLevelParentTableFields[$i];
            $alias = chr(100 + $i); // Generate alias like 'd', 'e', 'f', etc.
            $dynamicOrgParentStructQuery .= " JOIN $table $alias ON $currentAlias.{$field}_id = $alias.id";
            $dynamicOrgNameQuery .= " $alias.name as {$field}_name,";
            $currentAlias = $alias;
            $i--;
        }
    }

    // error_log($dynamicOrgParentStructQuery . PHP_EOL, 3, "./log.log");
    $queryConditionStr = ($id ? " WHERE u.$currentOrgLevelIdField = a.id" : ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : ""));

    $levelQuery = "SELECT a.*, $dynamicOrgNameQuery
            $dynamicOrgStructQuery
            (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
             JOIN units u ON cr.unit_id = u.id $queryConditionStr $dateFilterQuery2) as total_collection_paper,
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
             JOIN units u ON d.unit_id = u.id AND d.payment_type = 'CASH' $queryConditionStr $dateFilterQuery1) as total_collection_offline,
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
             JOIN units u ON d.unit_id = u.id AND d.payment_type != 'CASH' $queryConditionStr $dateFilterQuery1) as total_collection_online,
            (SELECT COUNT(DISTINCT d.id) FROM donations d
             JOIN units u ON d.unit_id = u.id $queryConditionStr $dateFilterQuery1) as total_donors
    FROM $currentOrgLevelTable a 
    $dynamicOrgParentStructQuery 
    " . ($id ? " WHERE a.id = $id" : ($currentUserParent ? " WHERE a.$currentUserParent = $currentUserLevelId" : ""));

    // error_log($levelQuery . PHP_EOL, 3, "./log.log");

    $stmt = $pdo->prepare($levelQuery);
    $stmt->execute();
    $details = $stmt->fetch(PDO::FETCH_ASSOC);
    // error_log(json_encode($details), 3, './log.log');
    $totalCollectedMobile = 0;
    $totalDonors = 0;

    $totalCashCollected = $details['total_collection_offline'];
    $totalOnlineCollected = $details['total_collection_online'];
    $totalCollectedMobile = $totalCashCollected + $totalOnlineCollected;
    $totalCollectedPaper = $details['total_collection_paper'];
    $totalCollected = $totalCollectedMobile + $totalCollectedPaper;

    $totalDonors = $details['total_donors'];
    if (!$id) {
        if ($currentUserRole === "state_admin") {
            $totalTargetQuery = "SELECT SUM(target_amount) as total_target FROM districts";
        } else {
            $totalTargetQuery = "SELECT target_amount FROM $currentUserParentTables[0] WHERE id = $currentUserLevelId";
        }
        $stmt = $pdo->prepare($totalTargetQuery);
        $stmt->execute();
        $totalTarget = $stmt->fetchColumn();
    } else {
        $totalTarget = $details['target_amount'];
    }

    if (!$details) {
        die("Record not found");
    }

    // Get collection Table details  
    $collectionQryCndtn =  (($level == "localbody" && $id) || $level == "unit" ?  "WHERE d." : "JOIN units u ON d.unit_id = u.id WHERE u.") . ($id ? $currentOrgLevelChildIdField : ($currentOrgLevelIdField == "id" ? "unit_id" : $currentOrgLevelIdField)) . " = a.id ";
    $collectionQuery = ($level !== "unit" || !$id) ? "SELECT SQL_CALC_FOUND_ROWS 
            a.name as name ,a.id,a.target_amount,
            (SELECT COALESCE(SUM(d.amount),0) FROM donations d
            $collectionQryCndtn $dateFilterQuery1) as total_collected_app,
            (SELECT COUNT(DISTINCT d.id) FROM donations d 
             $collectionQryCndtn $dateFilterQuery1) as total_donations,
            (SELECT COALESCE(SUM(d.amount), 0) FROM collection_reports d 
             $collectionQryCndtn $dateFilterQuery1) as total_collected_paper,
            ((SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
              $collectionQryCndtn $dateFilterQuery1) + 
             (SELECT COALESCE(SUM(d.amount), 0) FROM collection_reports d 
              $collectionQryCndtn $dateFilterQuery1)) as total_collected,
              (SELECT MAX(d.created_at) FROM donations d 
              $collectionQryCndtn $dateFilterQuery1) as last_collected_date
    FROM " . ($id ? $currentOrgLevelChildTable : $currentOrgLevelTable) . " a
    WHERE 1=1 " . ($id ? " AND a.$currentOrgLevelIdField = $id" : ($currentUserParent ? " AND a.$currentUserParent = $currentUserLevelId" : "")) . " 
    GROUP BY a.id, a.name, a.target_amount
    $tableFilterQuery
    ORDER BY $sortField $sortOrder
    LIMIT $limit OFFSET $offset"
        : "SELECT SQL_CALC_FOUND_ROWS   
            d.id, d.unit_id, d.amount, d.payment_type, d.created_at, d.collector_id, 
            u.name as collector_name, d.receipt_number,d.name
            FROM donations d 
            LEFT JOIN users u ON d.collector_id = u.id
            WHERE 1=1 AND d.unit_id = $id $collector_filter $dateFilterQuery1
            UNION ALL
            SELECT cr.id, cr.unit_id, cr.amount, 'COUPON' as payment_type, cr.collection_date as created_at, cr.collector_id, 
            u.name as collector_name, 'NO-RECIEPT' as receipt_number , 'NO-NAME' as name 
            FROM collection_reports cr 
            LEFT JOIN users u ON cr.collector_id = u.id
            WHERE 1=1 AND cr.unit_id = $id $dateFilterQuery2 $collector_filter
            ORDER BY $sortField $sortOrder
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($collectionQuery);

    $stmt->execute();
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    // error_log(json_encode($collectionQuery) . PHP_EOL, 3, "./log.log");
    // error_log(json_encode($collections) . PHP_EOL, 3, "./log.log");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

if (isset($_POST["export"])) {
    if (empty($collections)) {
        die("No data available to export.");
    }

    // Set headers for CSV file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=collections.csv');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, array_keys($collections[0]));

    // Write rows
    foreach ($collections as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports View</title>
    <link rel="icon" href="../assets/images/party-logo.jpg" type="image/png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../dashboard/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo ucfirst($currentUserLevel); ?> Admin</a>
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


    <?php if (!$id): ?>
        <div class="container p-0 header d-flex gap-1 mt-4 align-items-center">
            <?php if (count($currentUserManages) > 1): ?>
                <div class="mb-3 d-flex flex-wrap gap-1 header-tabs">
                    <?php foreach ($currentUserManages as $role): ?>
                        <a href="?level=<?php echo str_replace('_admin', '', $role); ?>" class="btn <?php echo $managingRole ===  $role ? "btn-primary" : "" ?>">
                            <?php echo ucfirst(str_replace('_admin', '', $role)); ?>s
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <h2><?php echo ucfirst(str_replace('_admin', '', $managingRole)); ?>s</h2>
            <?php endif; ?>
            <p class="">
                <a href="../dashboard/dashboard.php" class="btn btn-light">← <span class="d-none d-sm-inline">Back</span></a>
            </p>
        </div>
    <?php endif; ?>

    <div class="dashboard">
        <div class="header">
            <h2>
                <?php if ($id): ?>
                    <?php echo htmlspecialchars($details['name']); ?> <?php echo ucfirst($level); ?>
                <?php else: ?>
                    All <?php echo ucfirst($level); ?>s
                <?php endif; ?>
            </h2>
            <div class="mb-3 d-flex flex-wrap justify-content-end gap-1">
                <?php if ($filteredDateText): ?>
                    <a href="?level=<?php echo $level . $sortFieldParams . $FilterParams; ?>" class="btn btn-info"><?php echo $filteredDateText; ?> <i class="fa-solid fa-circle-xmark"></i></a>
                <?php endif; ?>
                <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#filterDateModal"><i class="fa-solid fa-list"></i> Select Date</button>
                <?php if ($id): ?>
                    <p class="mb-0">
                        <?php if ($parent_id): ?>
                            <a href="<?php echo $currentOrgLevelPerm['parent'] ? './view_reports.php?level=' . $currentOrgLevelPerm['parent'] . '&id=' . $parent_id : './' . $currentUserLevel . '.php'; ?>" class="btn btn-light">← <span class="d-none d-sm-inline">Back</span></a>
                        <?php else: ?>
                            <a href="javascript:history.back()" class="btn btn-light">← <span class="d-none d-sm-inline">Back</span></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="details-section">
            <?php if ($level != 'district' && $id): ?>
                <p class="breadcrumb">
                    <a href="./view_reports.php?level=<?php echo $level ?>" class="btn btn-outline-dark p-0 px-1"><i class="fa-solid fa-house"></i></a>&nbsp;
                    → <?php echo htmlspecialchars($details['district_name']); ?>
                    <?php if (isset($details['mandalam_name'])): ?>
                        → <?php echo htmlspecialchars($details['mandalam_name']); ?>
                    <?php endif; ?>
                    <?php if (isset($details['localbody_name'])): ?>
                        → <?php echo htmlspecialchars($details['localbody_name']); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Target Amount</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalTarget, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Collected</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalCollected, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Percentage
                                    </div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>%</div>
                                        </div>
                                        <div class="col">
                                            <div class="progress progress-sm mr-2">
                                                <div class="progress-bar bg-success" role="progressbar"
                                                    style="width: <?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>%" aria-valuenow="<?php echo $totalTarget > 0 ? number_format(($totalCollected / $totalTarget) * 100, 2) : 0; ?>" aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Orgs Data</div>
                                    <?php if (isset($details['total_mandalams'])): ?>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">Total Mandalams: <span class="float-right"><?php echo $details['total_mandalams']; ?></span></div>
                                    <?php endif; ?>
                                    <?php if (isset($details['total_localbodies'])): ?>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">Total Local Bodies: <span class="float-right"><?php echo $details['total_localbodies']; ?></span></div>
                                    <?php endif; ?>
                                    <?php if (isset($details['total_units'])): ?>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">Total Units: <span class="float-right"><?php echo $details['total_units']; ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>



            <div class="row px-2">
                <?php if (!empty($collections) && ($level !== "unit" || !$id)): ?>
                    <div class="card shadow mb-4 col-md-6 p-0">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Collection</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($collections, 0, 6) as $row): ?>
                                <?php
                                $percentage = $row['target_amount'] > 0 ? (($row['total_collected_app'] + $row['total_collected_paper']) / $row['target_amount']) * 100 : 0;
                                $progressBarClasses = ['danger', 'success', 'info', 'warning', 'dark', 'primary', 'secondary'];
                                $randomClass = $progressBarClasses[array_rand($progressBarClasses)]; ?>
                                <h4 class="small font-weight-bold"><?php echo  htmlspecialchars($row['name']); ?> <span class="float-right"><?php echo number_format($percentage) . '%'; ?>
                                    </span></h4>
                                <div class="progress mb-4">
                                    <div class="progress-bar bg-<?php echo $randomClass; ?>" role="progressbar" style="width: <?php echo number_format($percentage); ?>%"
                                        aria-valuenow="<?php echo number_format($percentage, 2); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>


                <div class="<?php echo (empty($collections) || ($level == "unit" && $id)) ? 'col-md-12' : 'col-md-6' ?> ">
                    <div class="row col-">
                        <h5 class="text-center mb-3">Collected Through Application</h5>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Online</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalOnlineCollected, 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fa-brands fa-google-pay fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Offline</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalCashCollected, 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Collected App</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalCollectedMobile, 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-mobile fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                                Donors</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalDonors); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row col-">
                        <h5 class="text-center mb-3">Collected Through Coupons</h5>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Collected</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalCollectedPaper, 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-ticket fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12 col-sm-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                                Donors</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">-</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-between">
                <h3>Collection Reports</h3>
                <div class="mb-3 d-flex flex-wrap justify-content-end gap-1">
                    <?php if ($filteredText): ?>
                        <a href="?level=<?php echo $level . $sortFieldParams . $FilterDateParams; ?>" class="btn btn-info"><?php echo $filteredText; ?> <i class="fa-solid fa-circle-xmark"></i></a>
                    <?php endif; ?>
                    <form method="post">
                        <button type="submit" name="export" class="btn btn-success"><i class="fa-solid fa-share"></i> Export</button>
                    </form>
                    <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="fa-solid fa-list"></i> Filter Table</button>

                </div>
            </div>
            <div class="content table-responsive">
                <table>
                    <thead>
                        <tr>
                            <?php if ($level == 'unit' && $id): ?>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Donor Name</th>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Collector</th>
                            <?php else: ?>
                                <th>
                                    <a href="<?php echo "?level=$level&id=$id&sort_field=id&sort_order=" . ($sortField === 'id' && $sortOrder === 'DESC' ? 'ASC' : 'DESC') . $FilterParams; ?>" class="text-decoration-none text-dark">
                                        ID
                                        <?php echo $sortField === 'id' ? ($sortOrder === 'DESC' ? ' <i class="fa-solid fa-arrow-up-wide-short"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short"></i>') : ''; ?>
                                    </a>
                                </th>
                                <th>Name</th>
                                <th>Target</th>
                                <th>
                                    <a href="<?php echo "?level=$level&id=$id&sort_field=total_collected_app&sort_order=" . ($sortField === 'total_collected_app' && $sortOrder === 'DESC' ? 'ASC' : 'DESC') . $FilterParams; ?>" class="text-decoration-none text-dark">
                                        App Collection
                                        <?php echo $sortField === 'total_collected_app' ? ($sortOrder === 'DESC' ? ' <i class="fa-solid fa-arrow-up-wide-short"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short"></i>') : ''; ?>
                                </th>
                                <th>
                                    <a href="<?php echo "?level=$level&id=$id&sort_field=total_collected_paper&sort_order=" . ($sortField === 'total_collected_paper' && $sortOrder === 'DESC' ? 'ASC' : 'DESC') . $FilterParams; ?>" class="text-decoration-none text-dark">
                                        Paper Collection
                                        <?php echo $sortField === 'total_collected_paper' ? ($sortOrder === 'DESC' ? ' <i class="fa-solid fa-arrow-up-wide-short"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short"></i>') : ''; ?>
                                </th>
                                <th>
                                    <a href="<?php echo "?level=$level&id=$id&sort_field=total_collected&sort_order=" . ($sortField === 'total_collected' && $sortOrder === 'DESC' ? 'ASC' : 'DESC') . $FilterParams; ?>" class="text-decoration-none text-dark">
                                        Total Collection
                                        <?php echo $sortField === 'total_collected' ? ($sortOrder === 'DESC' ? ' <i class="fa-solid fa-arrow-up-wide-short"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short"></i>') : ''; ?>
                                </th>
                                <th>Percentage</th>
                                <th>Last Collected</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collections)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($collections as $row): ?>
                                <tr class="<?php echo $level == "unit" && $id ? "" :  "table-clickable-row" ?>" onclick='handleTableRowClick(<?php echo json_encode($row); ?>)'>
                                    <?php if ($level == 'unit' && $id): ?>
                                        <td><?php echo htmlspecialchars($row['receipt_number']); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>₹<?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['payment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['collector_name']); ?></td>
                                    <?php else: ?>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>₹<?php echo number_format($row['target_amount']); ?></td>
                                        <td>₹<?php echo number_format($row['total_collected_app']); ?></td>
                                        <td>₹<?php echo number_format($row['total_collected_paper']); ?></td>
                                        <td>₹<?php echo number_format($row['total_collected_paper'] + $row['total_collected_app']); ?> (<?php echo $row['total_donations']; ?>)</td>
                                        <td>
                                            <?php $percentage = $row['target_amount'] > 0
                                                ? (($row['total_collected_app'] + $row['total_collected_paper']) / $row['target_amount']) * 100
                                                : 0;
                                            echo number_format($percentage) . '%';
                                            ?>
                                        </td>
                                        <td><?php echo ($row['last_collected_date'] ?? "-----"); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($collections)): ?>
                        <tfoot>
                            <tr class="table-info-row caption">
                                <td colspan="12" class="">
                                    <div class="text-center d-flex justify-content-between align-items-center gap-2 small">
                                        <p class="align-middle h-100 m-0">Total : <?php echo count($collections); ?> / <?php echo $totalItems; ?></p>
                                        <div class="d-flex justify-content-center align-items-center gap-2">
                                            <a href="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo max(1, $page - 1); ?><?php echo $parent_id ? '&parent_id=' . $parent_id : ''; ?><?php echo $FilterParams . $sortFieldParams; ?>" class="btn btn-light btn-sm <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                                ← Prev
                                            </a>
                                            <select class="form-select d-inline w-auto form-select-sm" onchange="location = this.value;">
                                                <?php for ($i = 1; $i <= ceil($totalItems / $limit); $i++): ?>
                                                    <option value="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo $i; ?><?php echo $parent_id ? '&parent_id=' . $parent_id : ''; ?><?php echo $FilterParams . $sortFieldParams; ?>" <?php echo $i == $page ? 'selected' : ''; ?>>
                                                        Page <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <a href="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo min(ceil($totalItems / $limit), $page + 1); ?><?php echo $parent_id ? '&parent_id=' . $parent_id : ''; ?><?php echo $FilterParams . $sortFieldParams; ?>" class="btn btn-light btn-sm <?php echo $page == ceil($totalItems / $limit) ? 'disabled' : ''; ?>">
                                                Next →
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
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
                        <label for="total_amount_condition" class="form-label">Total Amount</label>
                        <div class="d-flex gap-2 flex-sm-nowrap flex-wrap">
                            <div class="mb-3 w-100 input-group">
                                <select name="total_amount_condition" id="total_amount_condition" class="input-group-prepend" style="max-width: 100px;" value="<">
                                    <option value="<">Below</option>
                                    <option value=">">Above</option>
                                    <option value="">Equals</option>
                                </select>
                                <input type="number" name="total_amount_filter" id="total_amount_filter" placeholder="Amount" class="form-control" value="">
                            </div>
                        </div>
                        <label for="total_paper_condition" class="form-label">Paper Collection</label>
                        <div class="d-flex gap-2 flex-sm-nowrap flex-wrap">
                            <div class="mb-3 w-100 input-group">
                                <select name="total_paper_condition" id="total_paper_condition" class="input-group-prepend" style="max-width: 100px;" value="<">
                                    <option value="<">Below</option>
                                    <option value=">">Above</option>
                                    <option value="">Equals</option>
                                </select>
                                <input type="number" name="total_paper_filter" id="total_paper_filter" placeholder="Amount" class="form-control" value="">
                            </div>
                        </div>
                        <label for="total_app_condition" class="form-label">App Collection</label>
                        <div class="d-flex gap-2 flex-sm-nowrap flex-wrap">
                            <div class="mb-3 w-100 input-group">
                                <select name="total_app_condition" id="total_app_condition" class="input-group-prepend" style="max-width: 100px;" value="<">
                                    <option value="<">Below</option>
                                    <option value=">">Above</option>
                                    <option value="">Equals</option>
                                </select>
                                <input type="number" name="total_app_filter" id="total_app_filter" placeholder="Amount" class="form-control" value="">
                            </div>
                        </div>

                        <?php
                        // $i = 0;
                        // while ($i < array_search($managingRole, $currentUserManages) && $currentUserManages[$i] !== 'collector') {
                        //     $mainName = ucfirst(str_replace('_admin', '', $currentUserManages[$i]));
                        //     echo '<div class="mb-3">
                        //     <label class="form-label">' . $mainName . '</label>
                        //         <select name="' . strtolower($mainName) . '" id="filter_' . $mainName . '" class="form-control">
                        //             <option value="" hidden>Select ' . $mainName . '</option><option value="" disabled>Select Previous First</option>';
                        //     if ($i == 0) {
                        //         $mainTable = $currentUserTables[$i];
                        //         $mainParentField = $currentUserParents[$i];
                        //         $query = "SELECT id, name FROM {$mainTable} WHERE 1";
                        //         if ($mainParentField) {
                        //             $query .= " AND {$mainParentField} = ?";
                        //             $stmt = $pdo->prepare($query);
                        //             $stmt->execute([$_SESSION['user_level_id']]);
                        //         } else {
                        //             $stmt = $pdo->query($query);
                        //         }
                        //         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        //             $selected = '';
                        //             echo "<option value=\"{$row['id']}\" $selected>{$row['name']}</option>";
                        //         }
                        //     }
                        //     echo '</select>
                        //         <div class="invalid-feedback">' . $mainName . ' is required</div>
                        //     </div>';
                        //     $i++;
                        // }
                        ?>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="saveFilterBtn">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Date Modal -->
    <div class="modal fade" id="filterDateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="filterDateLoadingSpinner" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <form id="filterDateForm">
                        <div class="d-flex gap-2 flex-sm-nowrap flex-wrap">
                            <div class="mb-3 w-100">
                                <label for="from_date" class="form-label">From</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($fromDate); ?>">
                            </div>
                            <div class="mb-3 w-100">
                                <label for="to_date" class="form-label">To</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($toDate); ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="saveFilterDateBtn">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const level = "<?php echo $level; ?>";
    const parentId = "<?php echo $id; ?>";
    const redirectLevel = parentId ? "<?php echo $currentOrgLevelChilds[0]; ?>" : level

    function handleTableRowClick(row) {
        const childLevelId = row["id"];
        // console.log(row)
        if (level === 'unit' && parentId) {
            // Add any specific logic for 'unit' level if needed
        } else {
            location.href = `view_reports.php?level=${redirectLevel}&id=${childLevelId}`;
        }
    }

    $(document).ready(function() {
        function showLoading(formId, spinnerId) {
            $(`#${spinnerId}`).removeClass('d-none');
            $(`#${formId}`).addClass('d-none');
        }

        function hideLoading(formId, spinnerId) {
            $(`#${spinnerId}`).addClass('d-none');
            $(`#${formId}`).removeClass('d-none');
        }

        // Filter Table
        $('#filterForm').submit(function(event) {
            event.preventDefault();
            // const name = $('#item_name').val();
            // const target = $('#item_target').val();
            const $btn = $("#saveFilterBtn");

            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            const formData = new FormData(this);

            const totalAmountCondition = formData.get('total_amount_condition');
            const totalAmountFilter = formData.get('total_amount_filter');
            const totalPaperCondition = formData.get('total_paper_condition');
            const totalPaperFilter = formData.get('total_paper_filter');
            const totalAppCondition = formData.get('total_app_condition');
            const totalAppFilter = formData.get('total_app_filter');

            if (totalAmountFilter) {
                params.set("total_amount_filter", `${totalAmountCondition}${totalAmountFilter}`);
            }
            if (totalPaperFilter) {
                params.set("total_paper_filter", `${totalPaperCondition}${totalPaperFilter}`);
            }
            if (totalAppFilter) {
                params.set("total_app_filter", `${totalAppCondition}${totalAppFilter}`);
            }

            window.location.href = `${url.pathname}?${params.toString()}`;
            // console.log(`${url.pathname}?${params.toString()}`);

            showLoading('filterForm', 'filterLoadingSpinner');
            $btn.prop('disabled', true);


        });

        // Filter Date Table
        $('#filterDateForm').submit(function(event) {
            event.preventDefault();
            // const name = $('#item_name').val();
            // const target = $('#item_target').val();
            const $btn = $("#saveFilterDateBtn");

            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            const formData = new FormData(this);

            formData.get('from_date') ? params.set('from_date', formData.get('from_date')) : "";
            formData.get('to_date') ? params.set('to_date', formData.get('to_date')) : "";


            window.location.href = `${url.pathname}?${params.toString()}`;
            // console.log(`${url.pathname}?${params.toString()}`);

            showLoading('filterDateForm', 'filterDateLoadingSpinner');
            $btn.prop('disabled', true);


        });

        // $('#filter_District').change(async function() {
        //     var districtId = $(this).val();
        //     if (districtId) {
        //         $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Loading Mandalams...</option>');
        //         loadMandalams(districtId).then((response) => {
        //             let mandalams = JSON.parse(response);
        //             let options = '<option value="" hidden>Select Mandalam</option>';
        //             if (mandalams.length) {
        //                 mandalams.forEach(function(mandalam) {
        //                     options += `<option value="${mandalam.id}">${mandalam.name}</option>`;
        //                 });
        //             } else {
        //                 options += `<option value="" disabled>No Mandalams Under Selected District</option>`
        //             }
        //             $('#filter_Mandalam').html(options);
        //         }).catch((error) => {
        //             $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option><option value="" disabled>Error Loading Mandalam</option>');
        //             $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
        //         });
        //     } else {
        //         $('#filter_Mandalam').html('<option value="" hidden>Select Mandalam</option>');
        //         $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
        //     }
        // });

        // $('#filter_Mandalam').change(function() {
        //     if ($(this).val()) {
        //         $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Loading Localbodies...</option>');
        //         loadLocalbodies($(this).val()).then((response) => {
        //             let localbodies = JSON.parse(response);
        //             let options = '<option value="" hidden>Select Localbody</option>';
        //             if (localbodies.length) {
        //                 localbodies.forEach(function(localbody) {
        //                     options += `<option value="${localbody.id}">${localbody.name}</option>`;
        //                 });
        //             } else {
        //                 options += `<option value="" disabled>No Localbodies Under Selected Mandalam</option>`
        //             }
        //             $('#filter_Localbody').html(options);
        //         }).catch((error) => {
        //             $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option><option value="" disabled>Error Loading Localbodies</option>');
        //         });
        //     } else {
        //         $('#filter_Localbody').html('<option value="" hidden>Select Localbody</option>');
        //     }
        // });


        // function loadMandalams(districtId) {
        //     return new Promise((resolve, reject) => {
        //         $.ajax({
        //             url: 'ajax/get_mandalams.php',
        //             method: 'GET',
        //             data: {
        //                 district_id: districtId
        //             },
        //             success: function(response) {
        //                 resolve(response);
        //             },
        //             error: function(xhr, status, error) {
        //                 reject(error);
        //             }
        //         });
        //     });
        // }

        // function loadLocalbodies(mandalamId) {
        //     return new Promise((resolve, reject) => {
        //         $.ajax({
        //             url: 'ajax/get_localbodies.php',
        //             method: 'GET',
        //             data: {
        //                 mandalam_id: mandalamId
        //             },
        //             success: function(response) {
        //                 resolve(response);
        //             },
        //             error: function(xhr, status, error) {
        //                 reject(error);
        //             }
        //         });
        //     });
        // }
    });
</script>

</html>