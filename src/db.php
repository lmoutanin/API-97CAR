<?php

$host = 'localhost';
$dbname = '97car';
$user = 'postgres';
$pass = 'UserCar12@';
$port = '5432';

try {
    // Utilisation de PostgreSQL au lieu de MySQL
    $pdo = new PDO("pgsql:host=$host; port=$port; dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connexion échouée : ' . $e->getMessage();
}
