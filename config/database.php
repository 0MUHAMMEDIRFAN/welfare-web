<?php
// $host = 'https://welfarefunds.d4media.in';  
// $dbname = 'welfarefunds_partyfunds';  
// $username = 'welfarefunds_partyuser';  
// $password = 'welfare@123';
$host = 'localhost';  
$dbname = 'partyfunds';  
$username = 'root';  
$password = '';  

try {  
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);  
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  
} catch(PDOException $e) {  
    die("Connection failed: " . $e->getMessage());  
}  
?>