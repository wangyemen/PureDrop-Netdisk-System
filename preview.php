<?php
session_start();
require_once __DIR__ . '/core/functions.php';

$fileId = (int)($_GET['file_id'] ?? 0);
$shareCode = $_GET['share_code'] ?? '';

if (empty($fileId) && empty($shareCode)) {
    header('HTTP/1.1 400 Bad Request');
    exit('参数错误');
}

$db = getDB();
$file = null;

if (!empty($shareCode)) {
    $shareResult = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if ($shareResult['success'] && !empty($shareResult['data'])) {
        $share = $shareResult['data'][0];
        
        if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
            header('HTTP/1.1 403 Forbidden');
            exit('分享已过期');
        }
        
        // 如果有fileId，说明是分享文件夹中的文件
        if (!empty($fileId)) {
            // 检查分享的文件是否是文件夹
            $shareFileResult = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
            if ($shareFileResult['success'] && !empty($shareFileResult['data'])) {
                $shareFile = $shareFileResult['data'][0];
                
                // 如果分享的是文件夹，获取请求的文件
                if ($shareFile['file_type'] === 'folder') {
                    $result = $db->query("SELECT * FROM files WHERE id = ? AND parent_id = ? AND is_deleted = 0", [$fileId, $share['file_id']]);
                    
                    if ($result['success'] && !empty($result['data'])) {
                        $file = $result['data'][0];
                        $db->query("UPDATE file_shares SET view_count = view_count + 1 WHERE id = ?", [$share['id']]);
                    }
                }
            }
        } else {
            // 分享的是单个文件
            $result = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
            
            if ($result['success'] && !empty($result['data'])) {
                $file = $result['data'][0];
                $db->query("UPDATE file_shares SET view_count = view_count + 1 WHERE id = ?", [$share['id']]);
            }
        }
    }
} elseif (!empty($fileId)) {
    if (!isLoggedIn()) {
        header('HTTP/1.1 401 Unauthorized');
        exit('请先登录');
    }
    
    $user = getCurrentUser();
    $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0", [$fileId, $user['id']]);
    
    if ($result['success'] && !empty($result['data'])) {
        $file = $result['data'][0];
    }
}

if (!$file) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

if ($file['file_type'] === 'folder') {
    header('HTTP/1.1 400 Bad Request');
    exit('无法预览文件夹');
}

$filePath = UPLOAD_DIR . $file['file_path'];

if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

if ($file['file_type'] === 'image') {
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=31536000');
    readfile($filePath);
} elseif ($file['file_type'] === 'video' || $file['file_type'] === 'audio') {
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    $fileSize = filesize($filePath);
    
    if ($range) {
        list($start, $end) = explode('-', substr($range, 6));
        $start = (int)$start;
        $end = $end ? (int)$end : $fileSize - 1;
        $length = $end - $start + 1;
        
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . $length);
    } else {
        $start = 0;
        $length = $fileSize;
        header('Content-Length: ' . $fileSize);
    }
    
    header('Content-Type: ' . $file['mime_type']);
    header('Accept-Ranges: bytes');
    
    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
} else {
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: inline; filename="' . $file['name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
}
?>