<?php
// ================================================
// 🚪 SYSTEM LOGOUT — Session Termination
// ================================================
ob_start();
require_once 'system_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_regenerate_id(true);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

echo "<script>
  localStorage.removeItem('admin_id');
  localStorage.removeItem('admin_name');
  localStorage.removeItem('detectionSource');
  window.location.href = '/LitterLensThesis2/root/system_frontend/php/index_login.php';
</script>";
exit;
?>
