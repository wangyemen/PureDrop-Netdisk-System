<?php
session_start();
require_once __DIR__ . '/core/functions.php';

$fileId = (int)($_GET['file_id'] ?? 0);

if (empty($fileId)) {
    echo "<p>请提供文件ID</p>";
    exit;
}

if (!isLoggedIn()) {
    echo "<p>请先登录</p>";
    exit;
}

$user = getCurrentUser();
$db = getDB();

// 获取文件信息
$result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0", [$fileId, $user['id']]);

if ($result['success'] && !empty($result['data'])) {
    $file = $result['data'][0];
    
    echo "<h2>文件信息</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>字段</th><th>值</th></tr>";
    foreach ($file as $key => $value) {
        echo "<tr><td>{$key}</td><td>{$value}</td></tr>";
    }
    echo "</table>";
    
    // 检查文件是否存在
    $filePath = UPLOAD_DIR . $file['file_path'];
    echo "<h2>文件路径检查</h2>";
    echo "<p><strong>文件路径:</strong> {$filePath}</p>";
    echo "<p><strong>文件存在:</strong> " . (file_exists($filePath) ? "是" : "否") . "</p>";
    echo "<p><strong>文件可读:</strong> " . (is_readable($filePath) ? "是" : "否") . "</p>";
    
    if (file_exists($filePath)) {
        echo "<p><strong>文件大小:</strong> " . filesize($filePath) . " 字节</p>";
        echo "<p><strong>文件类型:</strong> " . mime_content_type($filePath) . "</p>";
        
        // 显示文件预览
        if (in_array($file['file_type'], ['image', 'video', 'audio'])) {
            echo "<h2>文件预览</h2>";
            if ($file['file_type'] === 'image') {
                echo "<img src='preview.php?file_id={$fileId}' style='max-width: 500px; max-height: 500px;'>";
            } elseif ($file['file_type'] === 'video') {
                echo "<video src='preview.php?file_id={$fileId}' controls style='max-width: 500px;'></audio>";
            } elseif ($file['file_type'] === 'audio') {
                echo "<audio src='preview.php?file_id={$fileId}' controls></audio>";
            }
        }
    }
} else {
    echo "<p>文件不存在或已删除</p>";
}

// 检查上传目录
$uploadDir = UPLOAD_DIR;
echo "<h2>上传目录检查</h2>";
echo "<p><strong>上传目录:</strong> {$uploadDir}</p>";
echo "<p><strong>目录存在:</strong> " . (is_dir($uploadDir) ? "是" : "否") . "</p>";

// 列出上传目录中的文件
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "<h3>上传目录内容</h3>";
    echo "<ul>";
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $filePath = $uploadDir . $f;
            if (is_dir($filePath)) {
                echo "<li><strong>{$f}</strong> (目录)</li>";
            } else {
                echo "<li>{$f} (" . filesize($filePath) . " 字节)</li>";
            }
        }
    }
    echo "</ul>";
}

// 检查files目录
$filesDir = UPLOAD_DIR . 'files/';
echo "<h2>Files目录检查</h2>";
echo "<p><strong>Files目录:</strong> {$filesDir}</p>";
echo "<p><strong>目录存在:</strong> " . (is_dir($filesDir) ? "是" : "否") . "</p>";

if (is_dir($filesDir)) {
    $files = scandir($filesDir);
    echo "<h3>Files目录内容</h3>";
    echo "<ul>";
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $filePath = $filesDir . $f;
            if (is_dir($filePath)) {
                echo "<li><strong>{$f}</strong> (目录)</li>";
                // 列出子目录内容
                $subFiles = scandir($filePath);
                echo "<ul>";
                foreach ($subFiles as $subF) {
                    if ($subF !== '.' && $subF !== '..') {
                        $subFilePath = $filePath . '/' . $subF;
                        if (is_dir($subFilePath)) {
                            echo "<li><strong>{$subF}</strong> (目录)</li>";
                        } else {
                            echo "<li>{$subF} (" . filesize($subFilePath) . " 字节)</li>";
                        }
                    }
                }
                echo "</ul>";
            } else {
                echo "<li>{$f} (" . filesize($filePath) . " 字节)</li>";
            }
        }
    }
    echo "</ul>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h2 {
    color: #333;
    margin-top: 30px;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 10px;
}
h3 {
    color: #666;
    margin-top: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
th {
    background-color: #4CAF50;
    color: white;
    font-weight: bold;
}
tr:hover {
    background-color: #f5f5f5;
}
p {
    background-color: white;
    padding: 15px;
    border-left: 4px solid #4CAF50;
    margin: 10px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
ul {
    background-color: white;
    padding: 15px 30px;
    border-left: 4px solid #4CAF50;
    margin: 10px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
li {
    margin: 5px 0;
}
img {
    border: 1px solid #ddd;
    padding: 10px;
    background-color: white;
    margin: 10px 0;
}
</style>