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
$currentLevelChild = $currentLevelPerm['child'][0];
$currentLevelChildId = $currentLevelPerm['child'][0] . '_id';
