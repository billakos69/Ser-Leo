<?php

// Στοιχεία DB
$host = "localhost";
$user = "root";
$pass = "";
$db   = "movies_db";

// mysqli connection (αν το χρειάζεσαι)
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Σφάλμα DB: ' . htmlspecialchars($e->getMessage()));
}

// MySQLi σύνδεση
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die("Σφάλμα σύνδεσης στη βάση δεδομένων: " . $mysqli->connect_error);
}
?>
