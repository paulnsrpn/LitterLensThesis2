<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login_reg.php');
}

$username_or_email = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username_or_email || !$password) {
    $_SESSION['login_errors'] = ['Please fill both fields.'];
    redirect('../login_reg.php');
}

// allow login by username or email
$stmt = $pdo->prepare("SELECT id, username, fullname, email, password_hash FROM users WHERE username = :ue OR email = :ue LIMIT 1");
$stmt->execute(['ue' => $username_or_email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_errors'] = ['Invalid credentials.'];
    redirect('../login_reg.php');
}

// success — create session
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['fullname'] = $user['fullname'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];

// redirect to protected area
redirect('../main.php');
