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
$stmt = $pdo->prepare("SELECT admin_id, admin_name, email, password, role 
                       FROM admin 
                       WHERE admin_name = :ue OR email = :ue 
                       LIMIT 1");
$stmt->execute(['ue' => $username_or_email]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// verify password
if (!$admin || !password_verify($password, $admin['password'])) {
    $_SESSION['login_errors'] = ['Invalid username/email or password.'];
    redirect('../login_reg.php');
}

// success — create session
session_regenerate_id(true);
$_SESSION['admin_id'] = $admin['admin_id'];
$_SESSION['admin_name'] = $admin['admin_name'];
$_SESSION['email'] = $admin['email'];
$_SESSION['role'] = $admin['role'];

// redirect to main dashboard
redirect('../dashboard.php');
