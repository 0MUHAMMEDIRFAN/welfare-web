<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if ($_SESSION['role'] != 'unit_admin') {
    header("Location: {$_SESSION['level']}.php");
    exit();
}

$unit_id = $_SESSION['user_level_id'];


$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;

try {
    // Get location details  
    $stmt = $pdo->prepare("  
        SELECT   
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

    $stmt->execute([$unit_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query for collectors summary  
    $query = "SELECT SQL_CALC_FOUND_ROWS
        u.id,  
        u.name,  
        u.phone,  
        COALESCE((  
            SELECT SUM(dn.amount)  
            FROM donations dn  
            WHERE dn.collector_id = u.id  
            AND dn.deleted_at IS NULL  
        ), 0) as collected_amount,  
        COALESCE((  
            SELECT COUNT(DISTINCT dn.id)  
            FROM donations dn  
            WHERE dn.collector_id = u.id  
            AND dn.deleted_at IS NULL  
        ), 0) as donation_count,  
        u.is_active  
    FROM users u  
    WHERE u.role = 'collector'   
    AND u.unit_id = :unit_id LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
    $stmt->execute();
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    // Calculate totals  
    $totalCollected = 0;
    $totalTarget = $location['unit_target_amount'];
    $totalDonations = 0;

    echo "<script>console.log(" . json_encode($summary) . ");</script>";
    echo "<script>console.log(" . json_encode($location) . ");</script>";
    echo "<script>console.log(" . json_encode($_SESSION) . ");</script>";


    foreach ($summary as $row) {
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
    <title>Unit Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Unit Admin Dashboard</a>
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
        <div class="header">
            <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                (<?php echo htmlspecialchars($location['unit_name']); ?> Unit,
                <?php echo htmlspecialchars($location['localbody_name']); ?>,<br>
                <?php echo htmlspecialchars($location['mandalam_name']); ?> Mandalam,
                <?php echo htmlspecialchars($location['district_name']); ?> District)</p>
            <p>
                <a href="../admin/manage_collectors.php?unit_id=<?php echo $unit_id; ?>" class="btn btn-manage">Manage Collectors</a>
            </p>

        </div>

        <div class="summary-cards">
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
        <div class="content">
            <h2>Collectors Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Collected Amount</th>
                        <th>Persons Donated</th>
                        <th>Status</th>
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
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td>₹<?php echo number_format($row['collected_amount'], 2); ?></td>
                                <td><?php echo number_format($row['donation_count']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['is_active'] == 1 ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_details.php?level=unit&id=<?php echo $unit_id; ?>&colid=<?php echo $row['id']; ?>"
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