<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('请先登录');
}

$user = getCurrentUser();
$fileId = (int)($_GET['file_id'] ?? 0);

if ($fileId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('参数错误');
}

$db = getDB();
$result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0", [$fileId, $user['id']]);

if (!$result['success'] || empty($result['data'])) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

$file = $result['data'][0];

if ($file['file_type'] === 'folder') {
    header('HTTP/1.1 400 Bad Request');
    exit('无法下载文件夹');
}

$filePath = UPLOAD_DIR . $file['file_path'];

if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);

logOperation($user['id'], 'download', '下载文件: ' . $file['name']);
?>