<?php
require_once __DIR__ . '/core/functions.php';

$db = getDB();

echo "=== 数据库文件记录检查 ===\n\n";

$result = $db->query("SELECT COUNT(*) as total FROM files");
if ($result['success']) {
    echo "总文件数: " . $result['data'][0]['total'] . "\n";
}

$result = $db->query("SELECT COUNT(*) as total FROM files WHERE is_deleted = 0");
if ($result['success']) {
    echo "未删除文件数: " . $result['data'][0]['total'] . "\n";
}

$result = $db->query("SELECT COUNT(*) as total FROM files WHERE is_deleted = 1");
if ($result['success']) {
    echo "已删除文件数: " . $result['data'][0]['total'] . "\n";
}

echo "\n=== 最近的文件记录 ===\n";
$result = $db->query("SELECT id, user_id, name, file_type, file_size, is_deleted, created_at FROM files ORDER BY created_at DESC LIMIT 10");
if ($result['success']) {
    if (empty($result['data'])) {
        echo "没有找到文件记录\n";
    } else {
        foreach ($result['data'] as $file) {
            echo "ID: {$file['id']}, 用户: {$file['user_id']}, 名称: {$file['name']}, 类型: {$file['file_type']}, 大小: {$file['file_size']}, 删除状态: {$file['is_deleted']}, 创建时间: {$file['created_at']}\n";
        }
    }
}

echo "\n=== 用户统计 ===\n";
$result = $db->query("SELECT COUNT(*) as total FROM users");
if ($result['success']) {
    echo "总用户数: " . $result['data'][0]['total'] . "\n";
}

$result = $db->query("SELECT id, username, email, storage_used, storage_total FROM users");
if ($result['success']) {
    foreach ($result['data'] as $user) {
        echo "ID: {$user['id']}, 用户名: {$user['username']}, 邮箱: {$user['email']}, 已用存储: {$user['storage_used']}, 总存储: {$user['storage_total']}\n";
    }
}

echo "\n=== 上传分片记录 ===\n";
$result = $db->query("SELECT COUNT(*) as total FROM upload_chunks");
if ($result['success']) {
    echo "上传分片记录数: " . $result['data'][0]['total'] . "\n";
}

$result = $db->query("SELECT * FROM upload_chunks ORDER BY created_at DESC LIMIT 5");
if ($result['success']) {
    if (empty($result['data'])) {
        echo "没有找到上传分片记录\n";
    } else {
        foreach ($result['data'] as $chunk) {
            echo "上传ID: {$chunk['upload_id']}, 文件名: {$chunk['file_name']}, 文件大小: {$chunk['file_size']}, 状态: {$chunk['status']}, 创建时间: {$chunk['created_at']}\n";
        }
    }
}
?>