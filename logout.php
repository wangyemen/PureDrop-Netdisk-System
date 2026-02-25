<?php
session_start();
require_once __DIR__ . '/core/functions.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    logOperation($_SESSION['user_id'], 'logout', '用户登出');
}

session_destroy();

setcookie('remember_token', '', time() - 3600, '/');

header('Location: login.php');
exit;
?>