<?php
session_start();
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/mail.php';

header('Content-Type: application/json');

$enableEmailVerification = getSetting('enable_email_verification', false);

if (!$enableEmailVerification) {
    echo json_encode(['success' => false, 'message' => '邮箱验证功能未启用']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法错误']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => '邮箱地址不能为空']);
    exit;
}

if (!validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
    exit;
}

$db = getDB();

$result = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
if ($result['success'] && !empty($result['data'])) {
    echo json_encode(['success' => false, 'message' => '该邮箱已被注册']);
    exit;
}

$code = generateVerificationCode();

try {
    if (sendVerificationEmail($email, $code)) {
        if (saveVerificationCode($email, $code)) {
            echo json_encode(['success' => true, 'message' => '验证码发送成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '验证码保存失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '验证码发送失败']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>