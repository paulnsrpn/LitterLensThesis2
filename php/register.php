<?php
// register.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login_reg.php');
}

$username = trim($_POST['username'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$errors = [];

if (!$username || !$fullname || !$email || !$password || !$confirm) {
    $errors[] = "Please fill all fields.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email.";
}

if ($password !== $confirm) {
    $errors[] = "Passwords do not match.";
}

if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters.";
}

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['show_register'] = true; // ✅ show register panel on reload
    redirect('../login_reg.php');
}

// check if username or email exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
$stmt->execute(['username' => $username, 'email' => $email]);
if ($stmt->fetch()) {
    $_SESSION['register_errors'] = ['Username or email already taken.'];
    $_SESSION['show_register'] = true; // ✅ keep register panel open
    redirect('../login_reg.php');
}

// create user
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, fullname, email, password_hash) 
                       VALUES (:username, :fullname, :email, :password_hash)");
$stmt->execute([
    'username' => $username,
    'fullname' => $fullname,
    'email' => $email,
    'password_hash' => $password_hash
]);

// auto-login user
$userId = $pdo->lastInsertId();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;
$_SESSION['fullname'] = $fullname;

redirect('../main.php');
