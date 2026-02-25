<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

$action = $_GET['action'] ?? '';

// 分享相关操作不需要登录
$shareActions = ['verify', 'verify_code', 'download', 'download_file'];
if (!in_array($action, $shareActions)) {
    if (!isLoggedIn()) {
        sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    
    $user = getCurrentUser();
}

if (!in_array($action, $shareActions) && !isset($user)) {
    sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
}

if ($action === 'create') {
    $fileId = (int)($_POST['file_id'] ?? 0);
    $extractCode = trim($_POST['extract_code'] ?? '');
    $expiryDays = (int)($_POST['expiry_days'] ?? 7);
    
    if ($fileId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0", [$fileId, $user['id']]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    $file = $result['data'][0];
    
    $requireExtractCode = getSetting('require_extract_code', false);
    if ($requireExtractCode && empty($extractCode)) {
        sendJsonResponse(['success' => false, 'message' => '请设置提取码']);
    }
    
    if (empty($extractCode)) {
        $extractCode = null;
    }
    
    $expiryDate = null;
    if ($expiryDays > 0) {
        $expiryDate = date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days'));
    }
    
    $shareCode = generateShareCode();
    
    $result = $db->query(
        "INSERT INTO file_shares (user_id, file_id, share_code, extract_code, expiry_date) VALUES (?, ?, ?, ?, ?)",
        [$user['id'], $fileId, $shareCode, $extractCode, $expiryDate]
    );
    
    if ($result['success']) {
        logOperation($user['id'], 'share', '分享文件: ' . $file['name']);
        sendJsonResponse([
            'success' => true,
            'share_id' => $result['insert_id'],
            'share_code' => $shareCode,
            'share_url' => SITE_URL . 'share.php?code=' . $shareCode,
            'message' => '分享创建成功'
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '创建分享失败']);
    }
}

if ($action === 'list') {
    $db = getDB();
    $result = $db->query(
        "SELECT fs.*, f.name as file_name, f.file_type, f.file_size FROM file_shares fs 
         JOIN files f ON fs.file_id = f.id 
         WHERE fs.user_id = ? 
         ORDER BY fs.created_at DESC",
        [$user['id']]
    );
    
    if ($result['success']) {
        $shares = [];
        foreach ($result['data'] as $share) {
            $shares[] = [
                'id' => $share['id'],
                'file_id' => $share['file_id'],
                'file_name' => $share['file_name'],
                'file_type' => $share['file_type'],
                'file_size' => $share['file_size'],
                'share_code' => $share['share_code'],
                'extract_code' => $share['extract_code'],
                'expiry_date' => $share['expiry_date'],
                'download_count' => $share['download_count'],
                'view_count' => $share['view_count'],
                'is_active' => $share['is_active'],
                'created_at' => $share['created_at'],
                'share_url' => SITE_URL . 'share.php?code=' . $share['share_code']
            ];
        }
        
        sendJsonResponse(['success' => true, 'shares' => $shares]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取分享列表失败']);
    }
}

if ($action === 'cancel') {
    $shareId = (int)($_POST['share_id'] ?? 0);
    
    if ($shareId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "UPDATE file_shares SET is_active = 0 WHERE id = ? AND user_id = ?",
        [$shareId, $user['id']]
    );
    
    if ($result['success']) {
        logOperation($user['id'], 'cancel_share', '取消分享 ID: ' . $shareId);
        sendJsonResponse(['success' => true, 'message' => '分享已取消']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '取消分享失败']);
    }
}

if ($action === 'verify' || $action === 'verify_code') {
    $shareCode = $_POST['share_code'] ?? '';
    $extractCode = trim($_POST['extract_code'] ?? '');
    
    if (empty($shareCode)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '分享不存在或已失效']);
    }
    
    $share = $result['data'][0];
    
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        sendJsonResponse(['success' => false, 'message' => '分享已过期']);
    }
    
    if ($share['extract_code'] && $share['extract_code'] !== $extractCode) {
        sendJsonResponse(['success' => false, 'message' => '提取码错误']);
    }
    
    sendJsonResponse(['success' => true, 'message' => '验证成功']);
}

if ($action === 'download') {
    $shareCode = $_GET['share_code'] ?? $_GET['code'] ?? '';
    $extractCode = $_GET['extract_code'] ?? '';
    
    if (empty($shareCode)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '分享不存在或已失效']);
    }
    
    $share = $result['data'][0];
    
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        sendJsonResponse(['success' => false, 'message' => '分享已过期']);
    }
    
    if ($share['extract_code'] && $share['extract_code'] !== $extractCode) {
        sendJsonResponse(['success' => false, 'message' => '提取码错误']);
    }
    
    $result = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    $file = $result['data'][0];
    
    $filePath = UPLOAD_DIR . $file['file_path'];
    
    if (!file_exists($filePath)) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    // 更新下载次数
    $db->query("UPDATE file_shares SET download_count = download_count + 1 WHERE id = ?", [$share['id']]);
    
    // 发送文件
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    header('Content-Disposition: attachment; filename="' . urlencode($file['name']) . '"');
    header('Cache-Control: public, max-age=31536000');
    
    readfile($filePath);
    exit;
}

if ($action === 'download_file') {
    $shareCode = $_GET['share_code'] ?? '';
    $fileId = (int)($_GET['file_id'] ?? 0);
    $extractCode = $_GET['extract_code'] ?? '';
    
    if (empty($shareCode) || $fileId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '分享不存在或已失效']);
    }
    
    $share = $result['data'][0];
    
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        sendJsonResponse(['success' => false, 'message' => '分享已过期']);
    }
    
    if ($share['extract_code'] && $share['extract_code'] !== $extractCode) {
        sendJsonResponse(['success' => false, 'message' => '提取码错误']);
    }
    
    // 检查文件是否在分享的文件夹中
    $shareFileResult = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
    if (!$shareFileResult['success'] || empty($shareFileResult['data'])) {
        sendJsonResponse(['success' => false, 'message' => '分享文件不存在']);
    }
    
    $shareFile = $shareFileResult['data'][0];
    
    if ($shareFile['file_type'] !== 'folder') {
        sendJsonResponse(['success' => false, 'message' => '只能下载文件夹中的文件']);
    }
    
    // 获取请求的文件信息
    $fileResult = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$fileId]);
    if (!$fileResult['success'] || empty($fileResult['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    $file = $fileResult['data'][0];
    
    // 检查文件是否在分享的文件夹中
    if ($file['parent_id'] !== $share['file_id']) {
        sendJsonResponse(['success' => false, 'message' => '文件不在分享的文件夹中']);
    }
    
    $filePath = UPLOAD_DIR . $file['file_path'];
    
    if (!file_exists($filePath)) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    // 更新下载次数
    $db->query("UPDATE file_shares SET download_count = download_count + 1 WHERE id = ?", [$share['id']]);
    
    // 发送文件
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    header('Content-Disposition: attachment; filename="' . urlencode($file['name']) . '"');
    header('Cache-Control: public, max-age=31536000');
    
    readfile($filePath);
    exit;
}

if ($action === 'save_to_my_drive') {
    $shareCode = $_POST['share_code'] ?? '';
    $parentId = (int)($_POST['parent_id'] ?? 0);
    
    if (empty($shareCode)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    if (!isLoggedIn()) {
        sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '分享不存在或已失效']);
    }
    
    $share = $result['data'][0];
    
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        sendJsonResponse(['success' => false, 'message' => '分享已过期']);
    }
    
    $result = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    $file = $result['data'][0];
    
    $newName = $file['name'];
    $counter = 1;
    
    while (true) {
        $checkResult = $db->query(
            "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ?",
            [$user['id'], $parentId, $newName]
        );
        
        if (!$checkResult['success'] || empty($checkResult['data'])) {
            break;
        }
        
        $pathInfo = pathinfo($file['name']);
        $newName = $pathInfo['filename'] . ' (' . $counter . ')';
        if (isset($pathInfo['extension'])) {
            $newName .= '.' . $pathInfo['extension'];
        }
        $counter++;
    }
    
    if ($file['file_type'] === 'folder') {
        $result = $db->query(
            "INSERT INTO files (user_id, parent_id, name, file_type, file_size) VALUES (?, ?, ?, 'folder', 0)",
            [$user['id'], $parentId, $newName]
        );
        
        if ($result['success']) {
            $newFolderId = $result['insert_id'];
            copyFolderFiles($db, $file['id'], $newFolderId, $user['id']);
            
            $db->query("UPDATE file_shares SET download_count = download_count + 1 WHERE id = ?", [$share['id']]);
            logOperation($user['id'], 'save_shared_file', '保存分享文件: ' . $file['name']);
            
            sendJsonResponse(['success' => true, 'message' => '保存成功']);
        }
    } else {
        $result = $db->query(
            "INSERT INTO files (user_id, parent_id, name, original_name, file_type, file_size, file_path, mime_type, extension, md5, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $user['id'],
                $parentId,
                $newName,
                $file['original_name'],
                $file['file_type'],
                $file['file_size'],
                $file['file_path'],
                $file['mime_type'],
                $file['extension'],
                $file['md5'],
                $file['thumbnail']
            ]
        );
        
        if ($result['success']) {
            updateUserStorage($user['id'], $file['file_size']);
            
            $db->query("UPDATE file_shares SET download_count = download_count + 1 WHERE id = ?", [$share['id']]);
            logOperation($user['id'], 'save_shared_file', '保存分享文件: ' . $file['name']);
            
            sendJsonResponse(['success' => true, 'message' => '保存成功']);
        }
    }
    
    sendJsonResponse(['success' => false, 'message' => '保存失败']);
}

function copyFolderFiles($db, $sourceFolderId, $targetFolderId, $userId) {
    $result = $db->query("SELECT * FROM files WHERE parent_id = ? AND is_deleted = 0", [$sourceFolderId]);
    
    if ($result['success']) {
        foreach ($result['data'] as $file) {
            if ($file['file_type'] === 'folder') {
                $insertResult = $db->query(
                    "INSERT INTO files (user_id, parent_id, name, file_type, file_size) VALUES (?, ?, ?, 'folder', 0)",
                    [$userId, $targetFolderId, $file['name']]
                );
                
                if ($insertResult['success']) {
                    $newFolderId = $insertResult['insert_id'];
                    copyFolderFiles($db, $file['id'], $newFolderId, $userId);
                }
            } else {
                $db->query(
                    "INSERT INTO files (user_id, parent_id, name, original_name, file_type, file_size, file_path, mime_type, extension, md5, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $targetFolderId,
                        $file['name'],
                        $file['original_name'],
                        $file['file_type'],
                        $file['file_size'],
                        $file['file_path'],
                        $file['mime_type'],
                        $file['extension'],
                        $file['md5'],
                        $file['thumbnail']
                    ]
                );
                updateUserStorage($userId, $file['file_size']);
            }
        }
    }
}

sendJsonResponse(['success' => false, 'message' => '未知操作']);
?>