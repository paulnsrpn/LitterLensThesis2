<?php
require_once 'system_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_register.php');
}

// === Get form input ===
$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$role     = 'user'; // default role

$errors = [];

// === Validation ===
if (!$fullname || !$email || !$password || !$confirm) {
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

// === Debug log ===
$debug_log = __DIR__ . '/debugfiles/debug_register.txt';
file_put_contents($debug_log, "==== Registration Attempt ====\n", FILE_APPEND);
file_put_contents($debug_log, "Time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($debug_log, "Full Name: $fullname | Email: $email\n", FILE_APPEND);

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['show_register'] = true;
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_register.php');
}

// === Manual Email Check ===
// Fetch all admins from Supabase
$allAdmins = getRecords('admin');

$emailExists = false;
foreach ($allAdmins as $admin) {
    if (strcasecmp($admin['email'], $email) === 0) { // case-insensitive
        $emailExists = true;
        break;
    }
}

file_put_contents($debug_log, "Email check result: " . ($emailExists ? "EXISTS" : "NOT EXISTS") . "\n", FILE_APPEND);

if ($emailExists) {
    $_SESSION['register_errors'] = ["Email already exists."];
    file_put_contents($debug_log, "Registration blocked due to duplicate email.\n", FILE_APPEND);
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_register.php');
}

// === Hash password ===
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// === Insert new admin into Supabase ===
$new_admin = [
    'admin_name' => $fullname,
    'email'      => $email,
    'password'   => $password_hash,
    'role'       => $role
];

$result = insertRecord('admin', $new_admin);

// Debug log
file_put_contents($debug_log, "Supabase insert result:\n" . print_r($result, true) . "\n", FILE_APPEND);

$admin = $result[0] ?? null;

if (!$admin) {
    $_SESSION['register_errors'] = ["Failed to create account. Please try again."];
    file_put_contents(__DIR__.'/debugfiles/debug_register_hash.txt', $password_hash.PHP_EOL, FILE_APPEND);
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_register.php');
}

// === Auto-login ===
session_regenerate_id(true);
$_SESSION['admin_id']   = $admin['admin_id'] ?? null;
$_SESSION['admin_name'] = $admin['admin_name'] ?? '';
$_SESSION['email']      = $admin['email'] ?? '';
$_SESSION['role']       = $admin['role'] ?? '';

file_put_contents($debug_log, "Registration successful. Auto-login done.\n\n", FILE_APPEND);

// === Redirect to admin dashboard ===
redirect('/LITTERLENSTHESIS2/root/system_frontend/php/admin.php');
