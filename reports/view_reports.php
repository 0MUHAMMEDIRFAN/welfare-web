<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();


$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;

$level = $_GET['level'] ?? '';
$id = $_GET['id'] ?? '';
$collector_id = $_GET['colid'] ?? '';
$collector_filter = "";
if ($collector_id) {
    $collector_filter = " AND d.collector_id = $collector_id ";
}

if (!$level || !$id) {
    die("Invalid parameters");
}

// Define admin hierarchy and their manageable levels  
$levelHierarchy = [
    'district' => [
        'child' => ['mandalam', "localbody", "unit", "collector"],
        'child_tables' => ["mandalams", "localbodies", "units", "units"],
        'name_field' => 'name',
        'parent' => null,
        'parent_field' => ['district_id', "mandalam_id", "localbody_id", "localbody_id"],
    ],
    'mandalam' => [
        'child' => ["localbody", "unit", "collector"],
        'child_tables' => ["localbodies", "units", "units"],
        'name_field' => 'name',
        'parent' => 'district',
        'parent_field' => ["mandalam_id", "localbody_id", "localbody_id"],
    ],
    'localbody' => [
        'child' => ["unit", "collector"],
        'child_tables' => ['units', 'units'],
        'name_field' => 'name',
        'parent' => 'mandalam',
        'parent_field' => ["localbody_id", "localbody_id"],
    ],
    'unit' => [
        'child' => ["collector"],
        'child_tables' => ['units'],
        'name_field' => 'name',
        'parent' => 'localbody',
        'parent_field' => ["localbody_id"],
    ]
];

$currentLevelPerm = $levelHierarchy[$level];

try {
    // Get the current level details  
    $levelQuery = match ($level) {
        'district' => "SELECT d.*,   
            (SELECT COUNT(*) FROM mandalams WHERE district_id = d.id) as total_mandalams,  
            (SELECT COUNT(DISTINCT l.id) FROM localbodies l   
             JOIN mandalams m ON l.mandalam_id = m.id   
             WHERE m.district_id = d.id) as total_localbodies,  
            (SELECT COUNT(DISTINCT u.id) FROM units u   
             JOIN localbodies l ON u.localbody_id = l.id   
             JOIN mandalams m ON l.mandalam_id = m.id   
             WHERE m.district_id = d.id) as total_units  
            FROM districts d WHERE d.id = ?",
        'mandalam' => "SELECT m.*, d.name as district_name,  
            (SELECT COUNT(*) FROM localbodies WHERE mandalam_id = m.id) as total_localbodies,  
            (SELECT COUNT(DISTINCT u.id) FROM units u   
             JOIN localbodies l ON u.localbody_id = l.id   
             WHERE l.mandalam_id = m.id) as total_units  
            FROM mandalams m   
            JOIN districts d ON m.district_id = d.id   
            WHERE m.id = ?",
        'localbody' => "SELECT l.*, m.name as mandalam_name, d.name as district_name,  
            (SELECT COUNT(*) FROM units WHERE localbody_id = l.id) as total_units  
            FROM localbodies l   
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id   
            WHERE l.id = ?",
        'unit' => "SELECT u.*, l.name as localbody_name, m.name as mandalam_name, d.name as district_name  
            FROM units u   
            JOIN localbodies l ON u.localbody_id = l.id  
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id   
            WHERE u.id = ?",
        default => throw new Exception("Invalid level")
    };

    $stmt = $pdo->prepare($levelQuery);
    $stmt->execute([$id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$details) {
        die("Record not found");
    }

    // Get collection details  



    $collectionQuery = match ($level) {
        'district' => "SELECT SQL_CALC_FOUND_ROWS   
            m.name as mandalam_name, m.id as mandalam_id,  
            COALESCE(SUM(d.amount), 0) as total_amount,  
            COUNT(DISTINCT d.id) as total_donations  
            FROM mandalams m   
            LEFT JOIN localbodies l ON l.mandalam_id = m.id  
            LEFT JOIN units u ON u.localbody_id = l.id  
            LEFT JOIN donations d ON d.unit_id = u.id  
            WHERE m.district_id = ?  
            GROUP BY m.id, m.name  
            ORDER BY m.name  
            LIMIT $limit OFFSET $offset",
        'mandalam' => "SELECT SQL_CALC_FOUND_ROWS   
            l.name as localbody_name, l.id as localbody_id,  
            COALESCE(SUM(d.amount), 0) as total_amount,  
            COUNT(DISTINCT d.id) as total_donations  
            FROM localbodies l  
            LEFT JOIN units u ON u.localbody_id = l.id  
            LEFT JOIN donations d ON d.unit_id = u.id  
            WHERE l.mandalam_id = ?  
            GROUP BY l.id, l.name  
            ORDER BY l.name  
            LIMIT $limit OFFSET $offset",
        'localbody' => "SELECT SQL_CALC_FOUND_ROWS   
            u.name as unit_name, u.id as unit_id,  
            COALESCE(SUM(d.amount), 0) as total_amount,  
            COUNT(DISTINCT d.id) as total_donations  
            FROM units u  
            LEFT JOIN donations d ON d.unit_id = u.id  
            WHERE u.localbody_id = ?  
            GROUP BY u.id, u.name  
            ORDER BY u.name  
            LIMIT $limit OFFSET $offset",
        'unit' => "SELECT SQL_CALC_FOUND_ROWS   
            d.*, u.name as collector_name  
            FROM donations d  
            LEFT JOIN users u ON d.collector_id = u.id  
            WHERE d.unit_id = ?  $collector_filter
            ORDER BY d.created_at DESC  
            LIMIT $limit OFFSET $offset",
        default => throw new Exception("Invalid level")
    };

    $stmt = $pdo->prepare($collectionQuery);
    $stmt->execute([$id]);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports View</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo ucfirst($_SESSION['level']); ?> Admin</a>
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
    <div class="dashboard">
        <div class="header">
            <h2><?php echo htmlspecialchars($details['name']); ?> <?php echo ucfirst($level); ?></h2>
            <p>
                <!-- <a href="<?php echo $currentLevelPerm['parent'] ? './view_reports.php?level=' . $currentLevelPerm['parent'] . '&id=' . $id : './' . $_SESSION['level'] . '.php'; ?>" class="btn btn-secondary">← Back</a> -->
                <a href="javascript:history.back()" class="btn btn-secondary">← Back</a>

            </p>
        </div>

        <div class="details-section">

            <?php if ($level != 'district'): ?>
                <p class="breadcrumb">
                    <?php echo htmlspecialchars($details['district_name']); ?>
                    <?php if (isset($details['mandalam_name'])): ?>
                        → <?php echo htmlspecialchars($details['mandalam_name']); ?>
                    <?php endif; ?>
                    <?php if (isset($details['localbody_name'])): ?>
                        → <?php echo htmlspecialchars($details['localbody_name']); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <div class="summary-cards">
                <div class="card">
                    <h3>Target Amount</h3>
                    <p>₹<?php echo number_format($details['target_amount'], 2); ?></p>
                </div>
                <?php if (isset($details['total_mandalams'])): ?>
                    <div class="card">
                        <h3>Total Mandalams</h3>
                        <p><?php echo $details['total_mandalams']; ?></p>
                    </div>
                <?php endif; ?>
                <?php if (isset($details['total_localbodies'])): ?>
                    <div class="card">
                        <h3>Total Local Bodies</h3>
                        <p><?php echo $details['total_localbodies']; ?></p>
                    </div>
                <?php endif; ?>
                <?php if (isset($details['total_units'])): ?>
                    <div class="card">
                        <h3>Total Units</h3>
                        <p><?php echo $details['total_units']; ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="content table-responsive">
                <h3>Collection Reports</h3>
                <table>
                    <thead>
                        <tr>
                            <?php if ($level == 'unit'): ?>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Donor Name</th>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Collector</th>
                            <?php else: ?>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Total Collections</th>
                                <th>Collected</th>
                                <th>Actions</th>
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
                                <tr>
                                    <?php if ($level == 'unit'): ?>
                                        <td><?php echo htmlspecialchars($row['receipt_number']); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>₹<?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['payment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['collector_name']); ?></td>
                                    <?php else: ?>
                                        <td>
                                            <?php
                                            echo htmlspecialchars($row[match ($level) {
                                                'district' => 'mandalam_id',
                                                'mandalam' => 'localbody_id',
                                                'localbody' => 'unit_id',
                                                default => 'id'
                                            }]);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo htmlspecialchars($row[match ($level) {
                                                'district' => 'mandalam_name',
                                                'mandalam' => 'localbody_name',
                                                'localbody' => 'unit_name',
                                                default => 'name'
                                            }]);
                                            ?>
                                        </td>
                                        <td><?php echo $row['total_donations']; ?></td>
                                        <td>₹<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td>
                                            <a href="view_reports.php?level=<?php
                                                                            echo match ($level) {
                                                                                'district' => 'mandalam',
                                                                                'mandalam' => 'localbody',
                                                                                'localbody' => 'unit'
                                                                            };
                                                                            ?>&id=<?php echo $row[match ($level) {
                                                                                        'district' => 'mandalam_id',
                                                                                        'mandalam' => 'localbody_id',
                                                                                        'localbody' => 'unit_id'
                                                                                    }]; ?>"
                                                class="btn btn-view"><i class="fa-regular fa-newspaper"></i> Report</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-info-row caption">
                                <td colspan="12" class="">
                                    <div class="text-center d-flex justify-content-between align-items-center gap-2 small">
                                        <p class="align-middle h-100 m-0">Total : <?php echo count($collections); ?> / <?php echo $totalItems; ?></p>
                                        <div class="d-flex justify-content-center align-items-center gap-2">
                                            <a href="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary btn-sm <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                                ← Prev
                                            </a>
                                            <select class="form-select d-inline w-auto form-select-sm" onchange="location = this.value;">
                                                <?php for ($i = 1; $i <= ceil($totalItems / $limit); $i++): ?>
                                                    <option value="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo $i; ?>" <?php echo $i == $page ? 'selected' : ''; ?>>
                                                        Page <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <a href="?level=<?php echo $level; ?>&id=<?php echo $id; ?>&page=<?php echo min(ceil($totalItems / $limit), $page + 1); ?>" class="btn btn-secondary btn-sm <?php echo $page == ceil($totalItems / $limit) ? 'disabled' : ''; ?>">
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
</body>

</html>