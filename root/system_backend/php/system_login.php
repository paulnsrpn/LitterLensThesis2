<?php
require_once 'system_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$username_or_email = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username_or_email || !$password) {
    $_SESSION['login_errors'] = ['Please fill both fields.'];
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$ue = urlencode($username_or_email);
$filter = "or(admin_name.eq.$ue,email.eq.$ue)";

// Fetch admin
$admins = getRecords('admin', $filter);
$admin = $admins[0] ?? null;

// Verify password
if (!$admin || !password_verify($password, $admin['password'])) {
    $_SESSION['login_errors'] = ['Invalid username/email or password.'];
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

// Success — create session
session_regenerate_id(true);
$_SESSION['admin_id'] = $admin['admin_id'] ?? null; // use the actual column name
$_SESSION['admin_name'] = $admin['admin_name'] ?? '';
$_SESSION['email'] = $admin['email'] ?? '';
$_SESSION['role'] = $admin['role'] ?? '';

// Redirect to dashboard
redirect('/LITTERLENSTHESIS2/root/system_frontend/php/admin.php');
?>
