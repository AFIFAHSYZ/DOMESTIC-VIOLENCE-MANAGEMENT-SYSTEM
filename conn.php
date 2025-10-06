<?php
$host = 'localhost';
$dbname = 'DVMS';
$user = 'postgres';
$password = 'afifah';
ob_start();
try {
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //echo "Database connected successfully!";
} catch (PDOException $e) {
   die("Database connection failed: " . $e->getMessage());
}


?>