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
    