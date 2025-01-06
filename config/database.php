<?php
function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception(".env file not found at: " . $filePath);
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }

        $keyValue = explode('=', $line, 2);

        if (count($keyValue) === 2) {
            $key = trim($keyValue[0]);
            $value = trim($keyValue[1]);

            // Remove quotes if present
            $value = trim($value, '"');

            // Store in environment variables
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env.local');


$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
