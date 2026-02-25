<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!$installed) {
    header('Location: install/install.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['status'] === 'disabled') {
    session_destroy();
    header('Location: login.php?error=disabled');
    exit;
}
?>