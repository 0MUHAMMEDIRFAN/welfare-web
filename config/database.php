<?php
// $host = 'https://welfarefunds.d4media.in';  
// $host = 'localhost';  
// $dbname = 'welfarefunds_partyfunds';  
// $username = 'welfarefunds_partyuser';  
// $password = 'welfare@123';

// $host = 'partyfunds.d4media.in:3306';  
// $host = 'server.d4media.in:3306';  
// $dbname = 'partyfundsd4medi_partyfunds';  
// $port = 3306;
// $username = 'partyfundsd4medi_prty_user';  
// $password = '-m%X4oVrE!kk';  

// $host = 'https://phpstack-1309512-5146856.cloudwaysapps.com/';  
// $dbname = 'partyfundsd4medi_partyfunds';  
// $username = 'partyfundsd4medi_prty_user';  
// $password = '-m%X4oVrE!kk';  
$host = '167.172.226.184:3306';  
$dbname = 'czpwckszqj';  
$username = 'czpwckszqj';  
$password = '3Un7QxR2be';  

try {  
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);  
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  
} catch(PDOException $e) {  
    die("Connection failed: " . $e->getMessage());  
}  
?>