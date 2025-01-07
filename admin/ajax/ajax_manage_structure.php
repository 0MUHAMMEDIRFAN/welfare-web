<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Set JSON header  
header('Content-Type: application/json');

// Check authentication  
requireLogin();

// Define allowed operations for each level  
$allowedOperations = [
    'state_admin' => ['districts', "mandalams", "localbodies", 'units'],
    'district_admin' => ['mandalams', 'localbodies', 'units'],
    'mandalam_admin' => ['localbodies', 'units'],
    'localbody_admin' => ['units']
];

if (!isset($allowedOperations[$_SESSION['role']])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $level = $_POST['level'] ?? '';
    $table = $_POST['table'] ?? '';

    // Validate table access  
    if (!in_array($table, $allowedOperations[$_SESSION['role']])) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'Unauthorized table access']));
    }

    try {
        switch ($action) {
            case 'add':
                $name = $_POST['name'] ?? '';
                $target = $_POST['target_amount'] ?? 0;
                $parent_id = $_POST['parent_id'] ?? null;
                $parent_field = $_POST['parent_field'] ?? null;
                $type = $_POST['type'] ?? null;

                // Build query based on whether there's a parent field and type  
                if ($table === 'localbodies') {
                    if (!in_array($type, ['PANCHAYAT', 'MUNCIPALITY', 'CORPORATION'])) {
                        exit(json_encode(['success' => false, 'message' => 'Invalid local body type']));
                    }

                    $query = "INSERT INTO $table (name, target_amount, $parent_field, type)   
                  VALUES (:name, :target, :parent_id, :type)";
                    $params = [
                        ':name' => $name,
                        ':target' => $target,
                        ':parent_id' => $parent_id,
                        ':type' => $type
                    ];
                } elseif ($parent_field) {
                    $query = "INSERT INTO $table (name, target_amount, $parent_field)   
                  VALUES (:name, :target, :parent_id)";
                    $params = [
                        ':name' => $name,
                        ':target' => $target,
                        ':parent_id' => $parent_id
                    ];
                } else {
                    $query = "INSERT INTO $table (name, target_amount)   
                  VALUES (:name, :target)";
                    $params = [
                        ':name' => $name,
                        ':target' => $target
                    ];
                }
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);

                if ($result) {
                    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add item']);
                }
                break;

            case 'update':
                $id = $_POST['id'] ?? '';
                $name = $_POST['name'] ?? '';
                $target = $_POST['target_amount'] ?? 0;
                $parent_id = $_POST['parent_id'] ?? null;
                $parent_field = $_POST['parent_field'] ?? null;

                // Verify ownership if not state admin  
                // if ($_SESSION['role'] !== 'state_admin') {
                //     $parent_field = null;
                //     switch ($_SESSION['role']) {
                //         case 'district_admin':
                //             $parent_field = 'district_id';
                //             break;
                //         case 'mandalam_admin':
                //             $parent_field = 'mandalam_id';
                //             break;
                //         case 'localbody_admin':
                //             $parent_field = 'localbody_id';
                //             break;
                //     }

                //     if ($parent_field) {
                //         $verify_query = "SELECT id FROM $table WHERE id = :id AND $parent_field = :parent_id";
                //         $verify_stmt = $pdo->prepare($verify_query);
                //         $verify_stmt->execute([
                //             ':id' => $id,
                //             ':parent_id' => $_SESSION['user_level_id']
                //         ]);

                //         if (!$verify_stmt->fetch()) {
                //             http_response_code(403);
                //             exit(json_encode(['success' => false, 'message' => 'Unauthorized access to this item']));
                //         }
                //     }
                // }
                if ($parent_field) {
                    $query = "UPDATE $table SET name = :name, target_amount = :target, $parent_field = :parent_id WHERE id = :id";
                    $params = [
                        ':name' => $name,
                        ':target' => $target,
                        ':parent_id' => $parent_id,
                        ':id' => $id
                    ];
                } else {
                    $query = "UPDATE $table SET name = :name, target_amount = :target WHERE id = :id";
                    $params = [
                        ':name' => $name,
                        ':target' => $target,
                        ':id' => $id
                    ];
                }


                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update item']);
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? '';

                // Verify ownership and check for child records  
                // $parent_id = $_POST['parent_id'] ?? null;
                $parent_field = null;
                $managingRole = $_POST['managing_role'] ?? null;
                $child_table = null;

                // if ($_SESSION['role'] !== 'state_admin') {

                // if ($parent_field) {
                //     // Verify ownership  
                //     $verify_query = "SELECT id FROM $table WHERE id = :id AND $parent_field = :parent_id";
                //     $verify_stmt = $pdo->prepare($verify_query);
                //     $verify_stmt->execute([
                //         ':id' => $id,
                //         ':parent_id' => $_SESSION['user_level_id']
                //     ]);

                //     if (!$verify_stmt->fetch()) {
                //         http_response_code(403);
                //         exit(json_encode(['success' => false, 'message' => 'Unauthorized access to this item']));
                //     }
                // }

                // }

                // Check for child records if applicable  
                switch ($managingRole) {
                    case 'district_admin':
                        $parent_field = 'district_id';
                        $child_table = 'mandalams';
                        break;
                    case 'mandalam_admin':
                        $parent_field = 'mandalam_id';
                        $child_table = 'localbodies';
                        break;
                    case 'localbody_admin':
                        $parent_field = 'localbody_id';
                        $child_table = 'units';
                        break;
                    default:
                        break;
                }

                if ($child_table && $parent_field) {
                    $check_query = "SELECT COUNT(*) FROM $child_table WHERE $parent_field = :id";
                    $check_stmt = $pdo->prepare($check_query);
                    $check_stmt->execute([':id' => $id]);
                    if ($check_stmt->fetchColumn() > 0) {
                        exit(json_encode([
                            'success' => false,
                            'message' => 'Cannot delete: This item has dependent records'
                        ]));
                    }
                }

                $query = "DELETE FROM $table WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([':id' => $id]);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
                }
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
