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

$encoded = rawurlencode($username_or_email);
$filter = "or(admin_name.ilike.$encoded,email.ilike.$encoded)";

// Debug filter and result
file_put_contents(__DIR__.'/debugfiles/debug_filter.txt', $filter.PHP_EOL);
$admins = getRecords('admin', $filter);
file_put_contents(__DIR__.'/debugfiles/debug_login.txt', print_r($admins, true), FILE_APPEND);

// ✅ Match the correct account
$admin = null;
foreach ($admins as $row) {
    if (
        strcasecmp(trim($row['admin_name']), $username_or_email) === 0 ||
        strcasecmp(trim($row['email']), $username_or_email) === 0
    ) {
        $admin = $row;
        break;
    }
}

if (!$admin || !password_verify($password, $admin['password'])) {
    $_SESSION['login_errors'] = ['Invalid username/email or password.'];
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

session_regenerate_id(true);
$_SESSION['admin_id'] = $admin['admin_id'] ?? null;
$_SESSION['admin_name'] = $admin['admin_name'] ?? '';
$_SESSION['email'] = $admin['email'] ?? '';
$_SESSION['role'] = $admin['role'] ?? '';

redirect('/LITTERLENSTHESIS2/root/system_frontend/php/admin.php');
?>
