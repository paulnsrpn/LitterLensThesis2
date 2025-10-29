<?php
// ================================================
// 🚪 SYSTEM LOGOUT — Session Termination
// ================================================

require_once 'system_config.php';

// ================================================
// 🧹 CLEAR ALL SESSION DATA
// ================================================
$_SESSION = [];

// ================================================
// 🍪 DESTROY SESSION COOKIE
// ================================================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ================================================
// 💣 DESTROY SESSION AND REGENERATE ID
// ================================================
session_destroy();
session_regenerate_id(true);

// ================================================
// 🔁 REDIRECT TO LOGIN PAGE
// ================================================
redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
?>
