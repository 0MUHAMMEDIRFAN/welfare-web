<?php
require_once '../../config/database.php';  
require_once '../../includes/auth.php';  

// Set JSON header  
header('Content-Type: application/json');  

// Check authentication  
requireLogin();  

// Define allowed operations for each level  
$allowedOperations = [  
    'state_admin' => [  
        'table' => 'districts',  
        'parent_field' => null  
    ],  
    'district_admin' => [  
        'table' => 'mandalams',  
        'parent_field' => 'district_id'  
    ],  
    'mandalam_admin' => [  
        'table' => 'localbodies',  
        'parent_field' => 'mandalam_id'  
    ],  
    'localbody_admin' => [  
        'table' => 'units',  
        'parent_field' => 'localbody_id'  
    ]  
];  

// Check if current role is allowed to update targets  
if (!isset($allowedOperations[$_SESSION['role']])) {  
    http_response_code(403);  
    exit(json_encode(['success' => false, 'message' => 'Unauthorized access']));  
}  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $entity_id = $_POST['entity_id'] ?? null;  
    $target_amount = $_POST['target_amount'] ?? null;  

    if (!$entity_id || !$target_amount) {  
        http_response_code(400);  
        exit(json_encode(['success' => false, 'message' => 'Missing required fields']));  
    }  

    try {  
        $currentLevel = $allowedOperations[$_SESSION['role']];  
        $table = $currentLevel['table'];  
        $parent_field = $currentLevel['parent_field'];  

        // For non-state admins, verify that the entity belongs to their jurisdiction  
        if ($parent_field) {  
            $verify_query = "SELECT id FROM $table WHERE id = :entity_id AND $parent_field = :parent_id";  
            $verify_stmt = $pdo->prepare($verify_query);  
            $verify_stmt->execute([  
                ':entity_id' => $entity_id,  
                ':parent_id' => $_SESSION['user_level_id']  
            ]);  

            if (!$verify_stmt->fetch()) {  
                http_response_code(403);  
                exit(json_encode(['success' => false, 'message' => 'Unauthorized access to this entity']));  
            }  
        }  

        // Update target amount  
        $query = "UPDATE $table SET target_amount = :target_amount WHERE id = :entity_id";  
        $stmt = $pdo->prepare($query);  
        $result = $stmt->execute([  
            ':target_amount' => $target_amount,  
            ':entity_id' => $entity_id  
        ]);  

        if ($result) {  
           // // Calculate and update parent's target amount  
            // if ($parent_field) {  
            //     $update_parent_query = "UPDATE " . getPreviousLevelTable($_SESSION['role']) . "  
            //                           SET target_amount = (  
            //                               SELECT SUM(target_amount)  
            //                               FROM $table  
            //                               WHERE $parent_field = :parent_id  
            //                           )  
            //                           WHERE id = :parent_id";  
            //     $update_parent_stmt = $pdo->prepare($update_parent_query);  
            //     $update_parent_stmt->execute([':parent_id' => $_SESSION['user_level_id']]);  
            // }  

            echo json_encode(['success' => true]);  
        } else {  
            echo json_encode(['success' => false, 'message' => 'Update failed']);  
        }  
    } catch(PDOException $e) {  
        http_response_code(500);  
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);  
    }  
}  

// // Helper function to get the parent level table name  
// function getPreviousLevelTable($currentRole) {  
//     $hierarchy = [  
//         'localbody_admin' => 'localbodies',  
//         'mandalam_admin' => 'mandalams',  
//         'district_admin' => 'districts'  
//     ];  
//     return $hierarchy[$currentRole] ?? null;  
// }  