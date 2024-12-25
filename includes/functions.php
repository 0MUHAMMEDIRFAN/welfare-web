<?php
function getUserLevel($role) {  
    switch($role) {  
        case 'state_admin':    // Changed from 'state' to 'state_admin'  
            return 'state';  
        case 'mandalam_admin': // Assuming these are the correct role names  
            return 'mandalam';  
        case 'localbody_admin':  
            return 'localbody';  
        case 'unit_admin':  
            return 'unit';  
        default:  
            return null;  
    }  
}  

function getCollectionSummary($pdo, $level, $id) {  
    $summary = [];  
    $query ="";
    switch($level) {  
        case 'state':  
            $query = "SELECT d.id, d.name, d.target_amount,  
                    (SELECT SUM(amount) FROM donations dn   
                     JOIN units u ON dn.unit_id = u.id  
                     JOIN localbodies l ON u.localbody_id = l.id  
                     JOIN mandalams m ON l.mandalam_id = m.id  
                     WHERE m.district_id = d.id) as collected_amount  
                    FROM districts d";  
            break;  
            
        case 'mandalam':  
            $query = "SELECT m.id, m.name, m.target_amount,  
                    (SELECT SUM(amount) FROM donations dn   
                     JOIN units u ON dn.unit_id = u.id  
                     JOIN localbodies l ON u.localbody_id = l.id  
                     WHERE l.mandalam_id = m.id) as collected_amount  
                    FROM mandalams m WHERE m.district_id = ?";  
            break;  
            
        case 'localbody':  
            $query = "SELECT l.id, l.name, l.target_amount,  
                    (SELECT SUM(amount) FROM donations dn   
                     JOIN units u ON dn.unit_id = u.id  
                     WHERE u.localbody_id = l.id) as collected_amount  
                    FROM localbodies l WHERE l.mandalam_id = ?";  
            break;  
            
        case 'unit':  
            $query = "SELECT u.id, u.name, u.target_amount,  
                    (SELECT SUM(amount) FROM donations dn   
                     WHERE dn.unit_id = u.id) as collected_amount  
                    FROM units u WHERE u.localbody_id = ?";  
            break;  
    }  
    
    $stmt = $pdo->prepare($query);  
    if($level != 'state') {  
        $stmt->execute([$id]);  
    } else {  
        $stmt->execute();  
    }  
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);  
}  

function getDonationsList($pdo, $unit_id) {  
    $query = "SELECT * FROM donations WHERE unit_id = ? ORDER BY created_at DESC";  
    $stmt = $pdo->prepare($query);  
    $stmt->execute([$unit_id]);  
    return $stmt->fetchAll(PDO::FETCH_ASSOC);  
}  

function getAdmins($pdo, $level, $parent_id) {  
    $query = "SELECT id, name, phone, role FROM users WHERE {$level}_id = ?";  
    $stmt = $pdo->prepare($query);  
    $stmt->execute([$parent_id]);  
    return $stmt->fetchAll(PDO::FETCH_ASSOC);  
}  
?>