<?php
include_once '../../config/database.php';

// $conn = new mysqli(host, username, password, dbname);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

if (isset($_GET['localbody_id'])) {
    $localbody_id = $_GET['localbody_id'];

    $query = "SELECT id,name FROM units WHERE localbody_id = ?";
    $stmt = $pdo->prepare($query);
    // $stmt->bind_param("i", $localbody_id);
    $stmt->execute([$localbody_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // $mandalams = array();
    // foreach ($result as $row) {
    //     $mandalams[] = $row;
    // }

    echo json_encode($result);
} else {
    echo json_encode(array("error" => "No localbody ID provided"));
}
