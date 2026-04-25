<?php
$host = 'localhost';
$user = 'root';
$pass = ''; 
$dbname = 'absensi_bugar';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { }
?>