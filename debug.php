<?php
session_start();
require_once __DIR__ . '/core/functions.php';

$db = getDB();

echo "<h2>数据库文件记录检查</h2>";

$result = $db->query("SELECT COUNT(*) as total FROM files");
if ($result['success']) {
    echo "<p><strong>总文件数:</strong> " . $result['data'][0]['total'] . "</p>";
}

$result = $db->query("SELECT COUNT(*) as total FROM files WHERE is_deleted = 0");
if ($result['success']) {
    echo "<p><strong>未删除文件数:</strong> " . $result['data'][0]['total'] . "</p>";
}

$result = $db->query("SELECT COUNT(*) as total FROM files WHERE is_deleted = 1");
if ($result['success']) {
    echo "<p><strong>已删除文件数:</strong> " . $result['data'][0]['total'] . "</p>";
}

echo "<h3>最近的文件记录</h3>";
$result = $db->query("SELECT id, user_id, name, file_type, file_size, is_deleted, created_at FROM files ORDER BY created_at DESC LIMIT 10");
if ($result['success']) {
    if (empty($result['data'])) {
        echo "<p>没有找到文件记录</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>用户</th><th>名称</th><th>类型</th><th>大小</th><th>删除状态</th><th>创建时间</th></tr>";
        foreach ($result['data'] as $file) {
            echo "<tr>";
            echo "<td>{$file['id']}</td>";
            echo "<td>{$file['user_id']}</td>";
            echo "<td>{$file['name']}</td>";
            echo "<td>{$file['file_type']}</td>";
            echo "<td>{$file['file_size']}</td>";
            echo "<td>{$file['is_deleted']}</td>";
            echo "<td>{$file['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<h3>用户统计</h3>";
$result = $db->query("SELECT id, username, email, storage_used, storage_total FROM users");
if ($result['success']) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>用户名</th><th>邮箱</th><th>已用存储</th><th>总存储</th></tr>";
    foreach ($result['data'] as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['storage_used']}</td>";
        echo "<td>{$user['storage_total']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>上传分片记录</h3>";
$result = $db->query("SELECT COUNT(*) as total FROM upload_chunks");
if ($result['success']) {
    echo "<p><strong>上传分片记录数:</strong> " . $result['data'][0]['total'] . "</p>";
}

$result = $db->query("SELECT * FROM upload_chunks ORDER BY created_at DESC LIMIT 5");
if ($result['success']) {
    if (empty($result['data'])) {
        echo "<p>没有找到上传分片记录</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>上传ID</th><th>文件名</th><th>文件大小</th><th>状态</th><th>创建时间</th></tr>";
        foreach ($result['data'] as $chunk) {
            echo "<tr>";
            echo "<td>{$chunk['upload_id']}</td>";
            echo "<td>{$chunk['file_name']}</td>";
            echo "<td>{$chunk['file_size']}</td>";
            echo "<td>{$chunk['status']}</td>";
            echo "<td>{$chunk['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<h3>目录结构检查</h3>";
$uploadDir = UPLOAD_DIR;
echo "<p><strong>上传目录:</strong> " . $uploadDir . "</p>";
echo "<p><strong>目录存在:</strong> " . (is_dir($uploadDir) ? "是" : "否") . "</p>";
echo "<p><strong>目录可写:</strong> " . (is_writable($uploadDir) ? "是" : "否") . "</p>";

$dirs = ['files', 'chunks', 'thumbnails'];
foreach ($dirs as $dir) {
    $dirPath = $uploadDir . $dir;
    echo "<p><strong>{$dir}目录:</strong> " . (is_dir($dirPath) ? "存在" : "不存在") . "</p>";
}
?>