<?php  
require_once '../includes/auth.php';  
require_once '../config/database.php';  
requireLogin();  

$level = $_GET['level'] ?? '';  
$id = $_GET['id'] ?? '';  
$start_date = $_GET['start_date'] ?? '';  
$end_date = $_GET['end_date'] ?? '';  

if (!$level || !$id || !$start_date || !$end_date) {  
    http_response_code(400);  
    echo json_encode(['error' => 'Missing required parameters']);  
    exit;  
}  

$query = "SELECT   
    DATE(d.created_at) as date,  
    COALESCE(SUM(d.amount), 0) as amount  
FROM donations d  
JOIN units u ON d.unit_id = u.id  
WHERE d.deleted_at IS NULL  
    AND d.created_at BETWEEN :start_date AND :end_date ";  

switch($level) {  
    case 'districts':  
        $query .= "AND u.district_id = :id";  
        break;  
    case 'mandalams':  
        $query .= "AND u.mandalam_id = :id";  
        break;  
    case 'localbodies':  
        $query .= "AND u.localbody_id = :id";  
        break;  
    case 'units':  
        $query .= "AND d.unit_id = :id";  
        break;  
    default:  
        http_response_code(400);  
        echo json_encode(['error' => 'Invalid level']);  
        exit;  
}  

$query .= " GROUP BY DATE(d.created_at)  
           ORDER BY date";  

try {  
    $stmt = $pdo->prepare($query);  
    $stmt->execute([  
        ':start_date' => $start_date,  
        ':end_date' => $end_date,  
        ':id' => $id  
    ]);  
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    echo json_encode($data);  
} catch (PDOException $e) {  
    http_response_code(500);  
    echo json_encode(['error' => 'Database error']);  
}  