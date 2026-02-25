<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
}

$user = getCurrentUser();
$action = $_GET['action'] ?? '';

if ($action === 'batch_download') {
    $fileIds = $_POST['file_ids'] ?? [];
    
    if (empty($fileIds) || !is_array($fileIds)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $filesToDownload = [];
    
    foreach ($fileIds as $fileId) {
        $fileId = (int)$fileId;
        if ($fileId <= 0) continue;
        
        $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0", [$fileId, $user['id']]);
        
        if ($result['success'] && !empty($result['data'])) {
            $file = $result['data'][0];
            
            if ($file['file_type'] === 'folder') {
                $filesToDownload = array_merge($filesToDownload, getFolderFiles($db, $file, $user['id']));
            } else {
                $filesToDownload[] = $file;
            }
        }
    }
    
    if (empty($filesToDownload)) {
        sendJsonResponse(['success' => false, 'message' => '没有可下载的文件']);
    }
    
    $zipName = 'download_' . time() . '.zip';
    $zipPath = UPLOAD_DIR . 'temp/' . $zipName;
    
    $tempDir = UPLOAD_DIR . 'temp/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        sendJsonResponse(['success' => false, 'message' => '创建压缩包失败']);
    }
    
    foreach ($filesToDownload as $file) {
        $filePath = UPLOAD_DIR . $file['file_path'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, $file['name']);
        }
    }
    
    $zip->close();
    
    logOperation($user['id'], 'batch_download', '批量下载 ' . count($filesToDownload) . ' 个文件');
    
    sendJsonResponse([
        'success' => true,
        'download_url' => 'api/batch_download.php?zip=' . urlencode($zipName),
        'message' => '压缩包创建成功'
    ]);
}

function getFolderFiles($db, $folder, $userId) {
    $files = [];
    
    $result = $db->query("SELECT * FROM files WHERE parent_id = ? AND user_id = ? AND is_deleted = 0", [$folder['id'], $userId]);
    
    if ($result['success']) {
        foreach ($result['data'] as $file) {
            if ($file['file_type'] === 'folder') {
                $files = array_merge($files, getFolderFiles($db, $file, $userId));
            } else {
                $files[] = $file;
            }
        }
    }
    
    return $files;
}

sendJsonResponse(['success' => false, 'message' => '未知操作']);
?>