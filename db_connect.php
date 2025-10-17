<?php
$env_path = __DIR__ . '/.env'; // same folder

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $host = $env['DB_HOST'] ?? 'localhost';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';
    $dbname = $env['DB_NAME'] ?? 'litterlens';
} else {
    die('.env file not found at ' . $env_path);
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!"; // <-- success message
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
