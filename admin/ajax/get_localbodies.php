<?php
include_once '../../config/database.php';

// $conn = new mysqli(host, username, password, dbname);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

if (isset($_GET['mandalam_id'])) {
    $mandalam_id = $_GET['mandalam_id'];

    $query = "SELECT id,name FROM localbodies WHERE mandalam_id = ?";
    $stmt = $pdo->prepare($query);
    // $stmt->bind_param("i", $mandalam_id);
    $stmt->execute([$mandalam_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // $mandalams = array();
    // foreach ($result as $row) {
    //     $mandalams[] = $row;
    // }

    echo json_encode($result);
} else {
    echo json_encode(array("error" => "No mandalam ID provided"));
}
