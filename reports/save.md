 $collectionQuery = match ($userlevel) {
        'state' => "SELECT SQL_CALC_FOUND_ROWS   
            di.name as name, di.id,  
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.district_id = di.id $dateFilterQuery1) as total_collected_app,  
            (SELECT COUNT(DISTINCT d.id) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.district_id = di.id $dateFilterQuery1) as total_donations,
            (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
             JOIN units u ON cr.unit_id = u.id 
             WHERE u.district_id = di.id $dateFilterQuery2) as total_collected_paper,
            ((SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
              JOIN units u ON d.unit_id = u.id 
              WHERE u.district_id = di.id $dateFilterQuery1) + 
             (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
              JOIN units u ON cr.unit_id = u.id 
              WHERE u.district_id = di.id $dateFilterQuery2)) as total_collected,
            di.target_amount
            FROM districts di   
            WHERE 1=1
            GROUP BY di.id, di.name, di.target_amount  
            ORDER BY $sortField $sortOrder  
            LIMIT $limit OFFSET $offset",
        'district' => "SELECT SQL_CALC_FOUND_ROWS   
            m.name as name, m.id,  
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.mandalam_id = m.id $dateFilterQuery1) as total_collected_app,  
            (SELECT COUNT(DISTINCT d.id) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.mandalam_id = m.id $dateFilterQuery1) as total_donations,
            (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
             JOIN units u ON cr.unit_id = u.id 
             WHERE u.mandalam_id = m.id $dateFilterQuery2) as total_collected_paper,
            ((SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
              JOIN units u ON d.unit_id = u.id 
              WHERE u.mandalam_id = m.id $dateFilterQuery1) + 
             (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
              JOIN units u ON cr.unit_id = u.id 
              WHERE u.mandalam_id = m.id $dateFilterQuery2)) as total_collected,
            m.target_amount
            FROM mandalams m   
            WHERE 1=1 "
            . ($id ? " AND m.district_id = $id" : ($currentUserParent ? " AND m.$currentUserParent = $currentUserLevelId" : "")) . "  
            GROUP BY m.id, m.name, m.target_amount  
            ORDER BY $sortField $sortOrder  
            LIMIT $limit OFFSET $offset",
        'mandalam' => "SELECT SQL_CALC_FOUND_ROWS   
            l.name as name, l.id,  
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.localbody_id = l.id $dateFilterQuery1) as total_collected_app,  
            (SELECT COUNT(DISTINCT d.id) FROM donations d 
             JOIN units u ON d.unit_id = u.id 
             WHERE u.localbody_id = l.id $dateFilterQuery1) as total_donations,
            (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
             JOIN units u ON cr.unit_id = u.id 
             WHERE u.localbody_id = l.id $dateFilterQuery2) as total_collected_paper,
            ((SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
              JOIN units u ON d.unit_id = u.id 
              WHERE u.localbody_id = l.id $dateFilterQuery1) + 
             (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
              JOIN units u ON cr.unit_id = u.id 
              WHERE u.localbody_id = l.id $dateFilterQuery2)) as total_collected,
            l.target_amount
            FROM localbodies l  
            WHERE 1=1 "
            . ($id ? " AND l.mandalam_id = $id" : ($currentUserParent ? " AND l.$currentUserParent = $currentUserLevelId" : "")) . "  
            GROUP BY l.id, l.name, l.target_amount  
            ORDER BY $sortField $sortOrder  
            LIMIT $limit OFFSET $offset",
        'localbody' => "SELECT SQL_CALC_FOUND_ROWS   
            u.name as name, u.id,  
            (SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
             WHERE d.unit_id = u.id $dateFilterQuery1) as total_collected_app,  
            (SELECT COUNT(DISTINCT d.id) FROM donations d 
             WHERE d.unit_id = u.id $dateFilterQuery1) as total_donations,
            (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
             WHERE cr.unit_id = u.id $dateFilterQuery2) as total_collected_paper,
            ((SELECT COALESCE(SUM(d.amount), 0) FROM donations d 
              WHERE d.unit_id = u.id $dateFilterQuery1) + 
             (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr 
              WHERE cr.unit_id = u.id $dateFilterQuery2)) as total_collected,
            u.target_amount
            FROM units u  
            WHERE 1=1 "
            . ($id ? " AND u.localbody_id = $id" : ($currentUserParent ? " AND u.$currentUserParent = $currentUserLevelId" : "")) . "  
            GROUP BY u.id, u.name, u.target_amount  
            ORDER BY $sortField $sortOrder  
            LIMIT $limit OFFSET $offset",
        'unit' => "SELECT SQL_CALC_FOUND_ROWS   
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
            LIMIT $limit OFFSET $offset",
        default => throw new Exception("Invalid level")
    };
    


    if ($id) {
        $levelQuery = match ($level) {
            'district' => "SELECT di.*,   
                (SELECT COUNT(*) FROM mandalams WHERE district_id = $id) as total_mandalams,  
                (SELECT COUNT(DISTINCT l.id) FROM localbodies l WHERE l.district_id = $id) as total_localbodies,  
                (SELECT COUNT(DISTINCT u.id) FROM units u WHERE u.district_id = $id) as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id WHERE u.district_id = $id $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.district_id = $id AND d.payment_type = 'CASH' $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.district_id = $id AND d.payment_type != 'CASH' $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.district_id = $id $dateFilterQuery1) as total_donors
            FROM districts di WHERE di.id = $id",
            'mandalam' => "SELECT m.*, d.name as district_name,  
                (SELECT COUNT(*) FROM localbodies WHERE mandalam_id = $id) as total_localbodies,  
                (SELECT COUNT(DISTINCT u.id) FROM units u WHERE u.mandalam_id = $id) as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id WHERE u.mandalam_id = $id $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.mandalam_id = $id AND d.payment_type = 'CASH' $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.mandalam_id = $id AND d.payment_type != 'CASH' $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.mandalam_id = $id $dateFilterQuery1) as total_donors
            FROM mandalams m   
            JOIN districts d ON m.district_id = d.id WHERE m.id = $id",
            'localbody' => "SELECT l.*, m.name as mandalam_name, d.name as district_name,  
                (SELECT COUNT(*) FROM units WHERE localbody_id = $id) as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id WHERE u.localbody_id = $id $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.localbody_id = $id AND d.payment_type = 'CASH' $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.localbody_id = $id AND d.payment_type != 'CASH' $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id WHERE u.localbody_id = $id $dateFilterQuery1) as total_donors
            FROM localbodies l   
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id WHERE l.id = $id",
            'unit' => "SELECT u.*, l.name as localbody_name, m.name as mandalam_name, d.name as district_name,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                WHERE cr.unit_id = u.id $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                WHERE d.unit_id = u.id AND d.payment_type = 'CASH' $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                WHERE d.unit_id = u.id AND d.payment_type != 'CASH' $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                WHERE d.unit_id = u.id $dateFilterQuery1) as total_donors
            FROM units u   
            JOIN localbodies l ON u.localbody_id = l.id  
            JOIN mandalams m ON l.mandalam_id = m.id  
            JOIN districts d ON m.district_id = d.id WHERE u.id = $id",
            default => throw new Exception("Invalid level")
        };
    } else {
        $levelQuery = match ($level) {
            'district' => "SELECT COUNT(*) as total_districts,
                (SELECT COUNT(DISTINCT m.id) FROM mandalams m) as total_mandalams,
                (SELECT COUNT(DISTINCT l.id) FROM localbodies l) as total_localbodies,  
                (SELECT COUNT(DISTINCT u.id) FROM units u) as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type = 'CASH' $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type != 'CASH' $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id $dateFilterQuery1) as total_donors
            FROM districts WHERE 1=1",
            'mandalam' => "SELECT COUNT(*) as total_mandalams,  
                (SELECT COUNT(DISTINCT l.id) FROM localbodies l" . ($currentUserParent ? " WHERE l.$currentUserParent = $currentUserLevelId" : "") . ") as total_localbodies,
                (SELECT COUNT(DISTINCT u.id) FROM units u" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . ") as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id " . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type = 'CASH' " . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type != 'CASH' " . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id " . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_donors
            FROM mandalams WHERE 1=1" . ($currentUserParent ? " AND $currentUserParent = $currentUserLevelId" : ""),
            'localbody' => "SELECT COUNT(*) as total_localbodies,
                (SELECT COUNT(DISTINCT u.id) FROM units u" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . ") as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type = 'CASH'" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type != 'CASH'" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_donors
            FROM localbodies WHERE 1=1" . ($currentUserParent ? " AND $currentUserParent = $currentUserLevelId" : ""),
            'unit' => "SELECT COUNT(*) as total_units,
                (SELECT COALESCE(SUM(cr.amount), 0) FROM collection_reports cr
                JOIN units u ON cr.unit_id = u.id" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery2) as total_collection_paper,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type = 'CASH'" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_offline,
                (SELECT COALESCE(SUM(d.amount), 0) FROM donations d
                JOIN units u ON d.unit_id = u.id AND d.payment_type != 'CASH'" . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . " $dateFilterQuery1) as total_collection_online,
                (SELECT COUNT(DISTINCT d.id) FROM donations d
                JOIN units u ON d.unit_id = u.id " . ($currentUserParent ? " WHERE u.$currentUserParent = $currentUserLevelId" : "") . ") as total_donors
            FROM units WHERE 1=1" . ($currentUserParent ? " AND $currentUserParent = $currentUserLevelId" : ""),
            default => throw new Exception("Invalid level")
        };
    }