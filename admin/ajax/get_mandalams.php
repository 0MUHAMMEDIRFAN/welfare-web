<?php
include_once '../../config/database.php';

// $conn = new mysqli(host, username, password, dbname);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

if (isset($_GET['district_id'])) {
    $district_id = $_GET['district_id'];

    $query = "SELECT id,name FROM mandalams WHERE district_id = ?";
    $stmt = $pdo->prepare($query);
    // $stmt->bind_param("i", $district_id);
    $stmt->execute([$district_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // $mandalams = array();
    // foreach ($result as $row) {
    //     $mandalams[] = $row;
    // }

    echo json_encode($result);
} else {
    echo json_encode(array("error" => "No district ID provided"));
}
