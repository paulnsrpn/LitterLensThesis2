<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login_reg.php');
}

$admin_name = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
$role = 'user'; // default role

$errors = [];

// Basic validation
if (!$admin_name || !$email || !$password || !$confirm) {
    $errors[] = "Please fill all fields.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

if ($password !== $confirm) {
    $errors[] = "Passwords do not match.";
}

if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters.";
}

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['show_register'] = true;
    redirect('../login_reg.php');
}

// Check if username or email exists
$stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE admin_name = :admin_name OR email = :email LIMIT 1");
$stmt->execute(['admin_name' => $admin_name, 'email' => $email]);

if ($stmt->fetch()) {
    $_SESSION['register_errors'] = ['Username or email already exists.'];
    $_SESSION['show_register'] = true;
    redirect('../login_reg.php');
}

// Insert new admin
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO admin (admin_name, email, password, role)
                       VALUES (:admin_name, :email, :password, :role)");
$stmt->execute([
    'admin_name' => $admin_name,
    'email' => $email,
    'password' => $password_hash,
    'role' => $role
]);

// Auto-login
$admin_id = $pdo->lastInsertId();
session_regenerate_id(true);
$_SESSION['admin_id'] = $admin_id;
$_SESSION['admin_name'] = $admin_name;
$_SESSION['email'] = $email;
$_SESSION['role'] = $role;

redirect('../dashboard.php');
