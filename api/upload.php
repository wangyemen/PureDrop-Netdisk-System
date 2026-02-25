<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
}

$user = getCurrentUser();
$action = $_GET['action'] ?? '';

if ($action === 'init') {
    $fileName = $_POST['file_name'] ?? '';
    $fileSize = (int)($_POST['file_size'] ?? 0);
    $fileMd5 = $_POST['file_md5'] ?? '';
    $chunkSize = (int)($_POST['chunk_size'] ?? CHUNK_SIZE);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    
    if (empty($fileName) || $fileSize <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    
    $result = $db->query(
        "SELECT id FROM files WHERE user_id = ? AND md5 = ? AND is_deleted = 0",
        [$user['id'], $fileMd5]
    );
    
    if ($result['success'] && !empty($result['data'])) {
        sendJsonResponse([
            'success' => true,
            'exists' => true,
            'message' => '文件已存在',
            'file_id' => $result['data'][0]['id']
        ]);
    }
    
    $uploadId = md5($user['id'] . $fileName . $fileMd5 . time());
    $totalChunks = ceil($fileSize / $chunkSize);
    
    $result = $db->query(
        "INSERT INTO upload_chunks (user_id, upload_id, chunk_number, total_chunks, file_name, file_size, file_md5, chunk_path, status) VALUES (?, ?, 0, ?, ?, ?, ?, '', 'uploading')",
        [$user['id'], $uploadId, $totalChunks, $fileName, $fileSize, $fileMd5]
    );
    
    if ($result['success']) {
        sendJsonResponse([
            'success' => true,
            'upload_id' => $uploadId,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '初始化上传失败']);
    }
}

if ($action === 'upload') {
    $uploadId = $_POST['upload_id'] ?? '';
    $chunkNumber = (int)($_POST['chunk_number'] ?? 0);
    $file = $_FILES['chunk'] ?? null;
    
    if (empty($uploadId) || $chunkNumber < 0 || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "SELECT * FROM upload_chunks WHERE upload_id = ? AND user_id = ?",
        [$uploadId, $user['id']]
    );
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '上传任务不存在']);
    }
    
    $uploadInfo = $result['data'][0];
    $chunkDir = UPLOAD_DIR . 'chunks/' . $uploadId . '/';
    
    if (!is_dir($chunkDir)) {
        mkdir($chunkDir, 0755, true);
    }
    
    $chunkPath = $chunkDir . $chunkNumber;
    
    if (move_uploaded_file($file['tmp_name'], $chunkPath)) {
        $result = $db->query(
            "UPDATE upload_chunks SET chunk_number = ?, chunk_size = ?, chunk_path = ? WHERE upload_id = ?",
            [$chunkNumber, $file['size'], 'chunks/' . $uploadId . '/' . $chunkNumber, $uploadId]
        );
        
        sendJsonResponse([
            'success' => true,
            'chunk_number' => $chunkNumber
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '分片上传失败']);
    }
}

if ($action === 'check') {
    $uploadId = $_GET['upload_id'] ?? '';
    
    if (empty($uploadId)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "SELECT * FROM upload_chunks WHERE upload_id = ? AND user_id = ?",
        [$uploadId, $user['id']]
    );
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '上传任务不存在']);
    }
    
    $uploadInfo = $result['data'][0];
    $chunkDir = UPLOAD_DIR . 'chunks/' . $uploadId . '/';
    $uploadedChunks = [];
    
    if (is_dir($chunkDir)) {
        $files = scandir($chunkDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $uploadedChunks[] = (int)$file;
            }
        }
    }
    
    sendJsonResponse([
        'success' => true,
        'uploaded_chunks' => $uploadedChunks,
        'total_chunks' => $uploadInfo['total_chunks']
    ]);
}

if ($action === 'complete') {
    $uploadId = $_POST['upload_id'] ?? '';
    $parentId = (int)($_POST['parent_id'] ?? 0);
    
    if (empty($uploadId)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "SELECT * FROM upload_chunks WHERE upload_id = ? AND user_id = ?",
        [$uploadId, $user['id']]
    );
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '上传任务不存在']);
    }
    
    $uploadInfo = $result['data'][0];
    $chunkDir = UPLOAD_DIR . 'chunks/' . $uploadId . '/';
    
    if (!is_dir($chunkDir)) {
        sendJsonResponse(['success' => false, 'message' => '分片目录不存在']);
    }
    
    $extension = pathinfo($uploadInfo['file_name'], PATHINFO_EXTENSION);
    $fileType = getFileType($extension);
    $mimeType = getMimeType($extension);
    
    $userDir = 'files/' . $user['id'] . '/';
    $filePath = $userDir . date('Y/m/d/') . $uploadInfo['file_md5'] . '.' . $extension;
    $fullPath = UPLOAD_DIR . $filePath;
    $fileDir = dirname($fullPath);
    
    if (!is_dir($fileDir)) {
        mkdir($fileDir, 0755, true);
    }
    
    $chunkFiles = [];
    for ($i = 0; $i < $uploadInfo['total_chunks']; $i++) {
        $chunkPath = $chunkDir . $i;
        if (!file_exists($chunkPath)) {
            sendJsonResponse(['success' => false, 'message' => '分片 ' . $i . ' 不存在']);
        }
        $chunkFiles[] = $chunkPath;
    }
    
    $destFile = fopen($fullPath, 'wb');
    if (!$destFile) {
        sendJsonResponse(['success' => false, 'message' => '无法创建目标文件']);
    }
    
    foreach ($chunkFiles as $chunkFile) {
        $chunkData = file_get_contents($chunkFile);
        fwrite($destFile, $chunkData);
    }
    fclose($destFile);
    
    $actualMd5 = md5_file($fullPath);
    if ($actualMd5 !== $uploadInfo['file_md5']) {
        unlink($fullPath);
        sendJsonResponse(['success' => false, 'message' => '文件校验失败']);
    }
    
    $thumbnail = null;
    if ($fileType === 'image') {
        $thumbnailDir = 'thumbnails/' . $user['id'] . '/';
        $thumbnailPath = $thumbnailDir . $uploadInfo['file_md5'] . '.jpg';
        $fullThumbnailPath = UPLOAD_DIR . $thumbnailPath;
        
        if (createThumbnail($fullPath, $fullThumbnailPath, 200, 200)) {
            $thumbnail = $thumbnailPath;
        }
    }
    
    $db->beginTransaction();
    
    $result = $db->query(
        "INSERT INTO files (user_id, parent_id, name, original_name, file_type, file_size, file_path, mime_type, extension, md5, thumbnail, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $user['id'],
            $parentId,
            $uploadInfo['file_name'],
            $uploadInfo['file_name'],
            $fileType,
            $uploadInfo['file_size'],
            $filePath,
            $mimeType,
            $extension,
            $uploadInfo['file_md5'],
            $thumbnail,
            0
        ]
    );
    
    if (!$result['success']) {
        $db->rollback();
        unlink($fullPath);
        sendJsonResponse(['success' => false, 'message' => '保存文件信息失败']);
    }
    
    $fileId = $result['insert_id'];
    
    $result = $db->query(
        "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
        [$uploadInfo['file_size'], $user['id']]
    );
    
    if (!$result['success']) {
        $db->rollback();
        unlink($fullPath);
        sendJsonResponse(['success' => false, 'message' => '更新存储空间失败']);
    }
    
    $result = $db->query(
        "UPDATE upload_chunks SET status = 'completed', file_id = ? WHERE upload_id = ?",
        [$fileId, $uploadId]
    );
    
    if (!$result['success']) {
        $db->rollback();
        unlink($fullPath);
        sendJsonResponse(['success' => false, 'message' => '更新上传状态失败']);
    }
    
    $db->commit();
    
    foreach ($chunkFiles as $chunkFile) {
        unlink($chunkFile);
    }
    rmdir($chunkDir);
    
    logOperation($user['id'], 'upload', '上传文件: ' . $uploadInfo['file_name']);
    
    sendJsonResponse([
        'success' => true,
        'file_id' => $fileId,
        'message' => '上传成功'
    ]);
}

if ($action === 'cancel') {
    $uploadId = $_POST['upload_id'] ?? '';
    
    if (empty($uploadId)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "SELECT * FROM upload_chunks WHERE upload_id = ? AND user_id = ?",
        [$uploadId, $user['id']]
    );
    
    if ($result['success'] && !empty($result['data'])) {
        $chunkDir = UPLOAD_DIR . 'chunks/' . $uploadId . '/';
        if (is_dir($chunkDir)) {
            $files = scandir($chunkDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($chunkDir . $file);
                }
            }
            rmdir($chunkDir);
        }
        
        $db->query("DELETE FROM upload_chunks WHERE upload_id = ?", [$uploadId]);
    }
    
    sendJsonResponse(['success' => true, 'message' => '上传已取消']);
}

sendJsonResponse(['success' => false, 'message' => '未知操作']);
?>