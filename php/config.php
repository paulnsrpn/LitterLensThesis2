<?php
// config.php
session_start();

// Database credentials
define('DB_HOST', '127.0.0.1');     // or localhost
define('DB_NAME', 'litterlens_db');
define('DB_USER', 'root');          // change for production
define('DB_PASS', '');              // change for production

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    // In development you can echo the message; in production log it instead
    die("Database connection failed: " . $e->getMessage());
}

// helper: redirect
function redirect($url) {
    header("Location: $url");
    exit;
}
