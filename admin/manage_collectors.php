<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication and authorization  
requireLogin();
if ($_SESSION['role'] != 'unit_admin') {
    header("Location: ../index.php");
    exit();
}

$unit_id = $_SESSION['user_level_id'];;
$success_message = '';
$error_message = '';


$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalItems = 0;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';


// Get unit details  
try {
    $stmt = $pdo->prepare("  
        SELECT   
            u.name as unit_name,  
            l.name as localbody_name,  
            m.name as mandalam_name,  
            d.name as district_name  
        FROM units u  
        JOIN localbodies l ON u.localbody_id = l.id  
        JOIN mandalams m ON l.mandalam_id = m.id  
        JOIN districts d ON m.district_id = d.id  
        WHERE u.id = ?  
    ");
    $stmt->execute([$unit_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// Handle form submissions  
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    // Validate input  
                    $name = trim($_POST['name']);
                    $phone = trim($_POST['phone']);
                    // $mpin = trim($_POST['mpin']);  

                    if (
                        empty($name)
                        || empty($phone)
                        // || empty($mpin)
                    ) {
                        throw new Exception("All fields are required");
                    }

                    if (!preg_match("/^[0-9]{10}$/", $phone)) {
                        throw new Exception("Invalid phone number format");
                    }

                    // if (!preg_match("/^[0-9]{4,6}$/", $mpin)) {
                    //     throw new Exception("MPIN must be 4-6 digits");
                    // }

                    // Check if phone number already exists  
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                    $stmt->execute([$phone]);
                    if ($stmt->fetch()) {
                        throw new Exception("Phone number already registered");
                    }

                    // Add new collector  
                    $stmt = $pdo->prepare("  
                        INSERT INTO users (name, phone, role, unit_id, is_active)   
                        VALUES (?, ?, 'collector', ?, 1)  
                    ");
                    $stmt->execute([
                        $name,
                        $phone,
                        // password_hash($mpin, PASSWORD_DEFAULT),
                        $unit_id
                    ]);
                    $success_message = "Collector added successfully";
                    break;

                case 'update':
                    $collector_id = $_POST['collector_id'];
                    $name = trim($_POST['name']);
                    $phone = trim($_POST['phone']);
                    // $mpin = trim($_POST['mpin']);

                    if (empty($name) || empty($phone)) {
                        throw new Exception("Name and phone are required");
                    }

                    if (!preg_match("/^[0-9]{10}$/", $phone)) {
                        throw new Exception("Invalid phone number format");
                    }

                    // Check if phone number exists for other users  
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
                    $stmt->execute([$phone, $collector_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Phone number already registered to another user");
                    }

                    // Update collector  
                    // if (!empty($mpin)) {
                    //     if (!preg_match("/^[0-9]{4,6}$/", $mpin)) {
                    //         throw new Exception("MPIN must be 4-6 digits");
                    //     }
                    //     $stmt = $pdo->prepare("  
                    //         UPDATE users   
                    //         SET name = ?, phone = ?, mpin = ?   
                    //         WHERE id = ? AND unit_id = ?  
                    //     ");
                    //     $stmt->execute([$name, $phone, password_hash($mpin, PASSWORD_DEFAULT), $collector_id, $unit_id]);
                    // } else {
                    $stmt = $pdo->prepare("  
                            UPDATE users   
                            SET name = ?, phone = ?   
                            WHERE id = ? AND unit_id = ?  
                        ");
                    $stmt->execute([$name, $phone, $collector_id, $unit_id]);
                    // }
                    $success_message = "Collector updated successfully";
                    break;

                case 'toggle_status':
                    $collector_id = $_POST['collector_id'];
                    $new_status = $_POST['status'] == '1' ? 0 : 1;

                    $stmt = $pdo->prepare("  
                        UPDATE users   
                        SET is_active = ?   
                        WHERE id = ? AND unit_id = ?  
                    ");
                    $stmt->execute([$new_status, $collector_id, $unit_id]);
                    $success_message = "Collector status updated successfully";
                    break;
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get collectors list  
try {
    // Handle search functionality
    if ($search) {
        $searchQuery = " AND (name LIKE :search OR phone LIKE :search)";
        $searchParam = '%' . $search . '%';
        $searchParams = "&search=$search";
    } else {
        $searchQuery = '';
        $searchParam = '';
        $searchParams = '';
    }

    $query = "SELECT SQL_CALC_FOUND_ROWS id, name, phone, is_active   
        FROM users   
        WHERE role = 'collector'   
        AND unit_id = :unit_id   
        $searchQuery
        ORDER BY name  
        LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    $stmt->execute();
    $collectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total items for pagination
    $totalItems = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Collectors</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="./admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Manage Collectors</a>
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
            <div class="details-section">
                <h4><?php echo htmlspecialchars($location['unit_name']); ?> Unit</h4>
                <p class="text-muted">
                    <?php echo htmlspecialchars($location['district_name']); ?> District
                    → <?php echo htmlspecialchars($location['mandalam_name']); ?> Mandalam
                    → <?php echo htmlspecialchars($location['localbody_name']); ?> Localbody
                </p>
            </div>
            <p>
                <a href="../dashboard/dashboard.php" class="btn btn-secondary">← Back</a>
            </p>
        </div>


        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add New Collector Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Collector</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <!-- <div class="col-md-3 mb-3">  
                            <label for="mpin" class="form-label">MPIN</label>  
                            <input type="password" class="form-control" id="mpin" name="mpin"   
                                   pattern="[0-9]{4,6}" maxlength="6" required>  
                        </div>   -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Add Collector</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Collectors List -->
        <div class="mb-4">
            <div class="card mb-1">
                <div class="card-header border-bottom-0">
                    <h5 class="mb-0">Collectors List</h5>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collectors as $collector): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($collector['name']); ?></td>
                                <td><?php echo htmlspecialchars($collector['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo $collector['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $collector['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $collector['id']; ?>">
                                        Edit
                                    </button>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="collector_id" value="<?php echo $collector['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $collector['is_active']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $collector['is_active'] == 1 ? 'btn-danger' : 'btn-success'; ?>">
                                            <?php echo $collector['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal for each collector -->
                            <div class="modal fade" id="editModal<?php echo $collector['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Collector</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="collector_id" value="<?php echo $collector['id']; ?>">

                                                <div class="mb-3">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" class="form-control" name="name"
                                                        value="<?php echo htmlspecialchars($collector['name']); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Phone</label>
                                                    <input type="text" class="form-control" name="phone"
                                                        value="<?php echo htmlspecialchars($collector['phone']); ?>"
                                                        pattern="[0-9]{10}" maxlength="10" required>
                                                </div>

                                                <!-- <div class="mb-3">  
                                                    <label class="form-label">New MPIN (leave blank to keep current)</label>  
                                                    <input type="password" class="form-control" name="mpin"   
                                                           pattern="[0-9]{4,6}" maxlength="6">  
                                                </div>   -->
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($collectors)): ?>
                        <tfoot>
                            <tr class="table-info-row caption">
                                <td colspan="12" class="">
                                    <div class="text-center d-flex justify-content-between align-items-center gap-2 small">
                                        <p class="align-middle h-100 m-0">Total : <?php echo count($collectors); ?> / <?php echo $totalItems; ?></p>
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
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>