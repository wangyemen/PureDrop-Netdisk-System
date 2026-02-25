<?php
session_start();
require_once __DIR__ . '/core/functions.php';

// 检查并创建必要的目录结构
function checkAndCreateDirectories() {
    $dirs = [
        UPLOAD_DIR,
        UPLOAD_DIR . 'files/',
        UPLOAD_DIR . 'chunks/',
        UPLOAD_DIR . 'thumbnails/'
    ];
    
    $results = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                $results[] = [
                    'path' => $dir,
                    'status' => 'created',
                    'message' => '目录已创建'
                ];
            } else {
                $results[] = [
                    'path' => $dir,
                    'status' => 'error',
                    'message' => '无法创建目录'
                ];
            }
        } else {
            $results[] = [
                'path' => $dir,
                'status' => 'exists',
                'message' => '目录已存在'
            ];
        }
        
        // 检查目录权限
        if (is_dir($dir)) {
            $results[] = [
                'path' => $dir,
                'status' => 'writable',
                'message' => '目录可写: ' . (is_writable($dir) ? '是' : '否')
            ];
        }
    }
    
    return $results;
}

// 清理上传中的文件
function cleanupUploadingFiles() {
    $db = getDB();
    
    // 获取所有上传中的记录
    $result = $db->query("SELECT * FROM upload_chunks WHERE status = 'uploading' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    $cleaned = 0;
    if ($result['success']) {
        foreach ($result['data'] as $chunk) {
            // 删除分片文件
            $chunkDir = UPLOAD_DIR . 'chunks/' . $chunk['upload_id'] . '/';
            if (is_dir($chunkDir)) {
                $files = glob($chunkDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($chunkDir);
            }
            
            // 删除数据库记录
            $db->query("DELETE FROM upload_chunks WHERE id = ?", [$chunk['id']]);
            $cleaned++;
        }
    }
    
    return $cleaned;
}

// 执行检查
$directoryResults = checkAndCreateDirectories();
$cleanedCount = cleanupUploadingFiles();

// 输出结果
echo "<h2>目录结构检查</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>路径</th><th>状态</th><th>消息</th></tr>";
foreach ($directoryResults as $result) {
    echo "<tr>";
    echo "<td>{$result['path']}</td>";
    echo "<td>{$result['status']}</td>";
    echo "<td>{$result['message']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>上传清理</h2>";
echo "<p>清理了 {$cleanedCount} 个过期的上传记录</p>";

echo "<h2>系统状态</h2>";
echo "<p><strong>PHP 版本:</strong> " . phpversion() . "</p>";
echo "<p><strong>上传最大大小:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>POST 最大大小:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>内存限制:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>GD 扩展:</strong> " . (extension_loaded('gd') ? '已启用' : '未启用') . "</p>";
echo "<p><strong>文件上传:</strong> " . (ini_get('file_uploads') ? '已启用' : '未启用') . "</p>";
echo "<p><strong>最大上传文件数:</strong> " . ini_get('max_file_uploads') . "</p>";

// 测试文件写入
$testFile = UPLOAD_DIR . 'test_write.txt';
$testResult = file_put_contents($testFile, '测试文件写入');
if ($testResult !== false) {
    unlink($testFile);
    echo "<p><strong>文件写入测试:</strong> 成功</p>";
} else {
    echo "<p><strong>文件写入测试:</strong> 失败</p>";
}

// 测试数据库连接
$db = getDB();
echo "<p><strong>数据库连接:</strong> 成功</p>";

// 显示当前时间
echo "<p><strong>当前时间:</strong> " . date('Y-m-d H:i:s') . "</p>";

// 显示配置信息
echo "<h2>配置信息</h2>";
echo "<p><strong>上传目录:</strong> " . UPLOAD_DIR . "</p>";
echo "<p><strong>网站URL:</strong> " . SITE_URL . "</p>";
echo "<p><strong>最大文件大小:</strong> " . formatSize(MAX_FILE_SIZE) . "</p>";
echo "<p><strong>分片大小:</strong> " . formatSize(CHUNK_SIZE) . "</p>";

// 检查系统设置
echo "<h2>系统设置检查</h2>";
$settings = [
    'require_extract_code' => getSetting('require_extract_code', false),
    'share_expiry_days' => getSetting('share_expiry_days', 7),
    'max_upload_size' => getSetting('max_upload_size', MAX_FILE_SIZE)
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>设置项</th><th>值</th></tr>";
foreach ($settings as $key => $value) {
    echo "<tr>";
    echo "<td>{$key}</td>";
    echo "<td>{$value}</td>";
    echo "</tr>";
}
echo "</table>";

// 检查文件表结构
echo "<h2>数据库表检查</h2>";
$tables = ['files', 'users', 'upload_chunks', 'file_shares', 'recycle_bin'];
$db = getDB();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>表名</th><th>状态</th><th>记录数</th></tr>";
foreach ($tables as $table) {
    $result = $db->query("SELECT COUNT(*) as count FROM {$table}");
    if ($result['success']) {
        echo "<tr>";
        echo "<td>{$table}</td>";
        echo "<td>存在</td>";
        echo "<td>{$result['data'][0]['count']}</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>{$table}</td>";
        echo "<td>不存在或有错误</td>";
        echo "<td>0</td>";
        echo "</tr>";
    }
}
echo "</table>";

// 检查文件表结构
echo "<h2>文件表结构检查</h2>";
$result = $db->query("DESCRIBE files");
if ($result['success']) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>字段名</th><th>类型</th><th>空</th><th>默认值</th></tr>";
    foreach ($result['data'] as $field) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>无法获取文件表结构</p>";
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
</style>