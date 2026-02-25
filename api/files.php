<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => '请先登录'], 401);
}

$user = getCurrentUser();
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $parentId = (int)($_GET['parent_id'] ?? 0);
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $sort = $_GET['sort'] ?? 'name';
    $order = $_GET['order'] ?? 'asc';
    
    $db = getDB();
    
    $where = "WHERE user_id = ? AND parent_id = ? AND is_deleted = 0";
    $params = [$user['id'], $parentId];
    
    if (!empty($search)) {
        $where .= " AND name LIKE ?";
        $params[] = "%$search%";
    }
    
    if (!empty($type) && $type !== 'all') {
        $where .= " AND file_type = ?";
        $params[] = $type;
    }
    
    $orderBy = "ORDER BY ";
    switch ($sort) {
        case 'size':
            $orderBy .= "file_size $order";
            break;
        case 'date':
            $orderBy .= "created_at $order";
            break;
        case 'name':
        default:
            $orderBy .= "name $order";
            break;
    }
    
    $sql = "SELECT * FROM files $where $orderBy";
    $result = $db->query($sql, $params);
    
    if ($result['success']) {
        $files = [];
        foreach ($result['data'] as $file) {
            $fileSize = $file['file_size'];
            if ($file['file_type'] === 'folder') {
                $fileSize = calculateFolderSize($db, $file['id']);
            }
            
            $files[] = [
                'id' => $file['id'],
                'name' => $file['name'],
                'file_type' => $file['file_type'],
                'file_size' => $fileSize,
                'size_formatted' => formatSize($fileSize),
                'extension' => $file['extension'],
                'mime_type' => $file['mime_type'],
                'thumbnail' => $file['thumbnail'],
                'created_at' => $file['created_at'],
                'updated_at' => $file['updated_at'],
                'icon' => getFileIcon($file['file_type'], $file['extension'])
            ];
        }
        
        sendJsonResponse(['success' => true, 'files' => $files]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取文件列表失败']);
    }
}

function calculateFolderSize($db, $folderId) {
    $totalSize = 0;
    
    $result = $db->query("SELECT id, file_type, file_size FROM files WHERE parent_id = ? AND is_deleted = 0", [$folderId]);
    
    if ($result['success']) {
        foreach ($result['data'] as $item) {
            if ($item['file_type'] === 'folder') {
                $totalSize += calculateFolderSize($db, $item['id']);
            } else {
                $totalSize += $item['file_size'];
            }
        }
    }
    
    return $totalSize;
}

if ($action === 'create_folder') {
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $folderName = trim($_POST['folder_name'] ?? '');
    
    if (empty($folderName)) {
        sendJsonResponse(['success' => false, 'message' => '文件夹名称不能为空']);
    }
    
    $db = getDB();
    
    $result = $db->query(
        "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ? AND is_deleted = 0",
        [$user['id'], $parentId, $folderName]
    );
    
    if ($result['success'] && !empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件夹已存在']);
    }
    
    $result = $db->query(
        "INSERT INTO files (user_id, parent_id, name, file_type, file_size) VALUES (?, ?, ?, 'folder', 0)",
        [$user['id'], $parentId, $folderName]
    );
    
    if ($result['success']) {
        logOperation($user['id'], 'create_folder', '创建文件夹: ' . $folderName);
        sendJsonResponse(['success' => true, 'folder_id' => $result['insert_id'], 'message' => '文件夹创建成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '创建文件夹失败']);
    }
}

if ($action === 'rename') {
    $fileId = (int)($_POST['file_id'] ?? 0);
    $newName = trim($_POST['new_name'] ?? '');
    
    if ($fileId <= 0 || empty($newName)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    
    $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJsonResponse(['success' => false, 'message' => '文件不存在']);
    }
    
    $file = $result['data'][0];
    
    $result = $db->query(
        "UPDATE files SET name = ? WHERE id = ? AND user_id = ?",
        [$newName, $fileId, $user['id']]
    );
    
    if ($result['success']) {
        logOperation($user['id'], 'rename', '重命名: ' . $file['name'] . ' -> ' . $newName);
        sendJsonResponse(['success' => true, 'message' => '重命名成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '重命名失败']);
    }
}

if ($action === 'move') {
    $fileIds = $_POST['file_ids'] ?? [];
    $targetParentId = (int)($_POST['target_parent_id'] ?? 0);
    
    if (empty($fileIds) || !is_array($fileIds)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $movedCount = 0;
    $skippedCount = 0;
    
    foreach ($fileIds as $fileId) {
        $fileId = (int)$fileId;
        if ($fileId <= 0) continue;
        
        // 检查文件是否已经在目标文件夹中
        $checkResult = $db->query("SELECT parent_id FROM files WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
        if ($checkResult['success'] && !empty($checkResult['data'])) {
            $currentParentId = $checkResult['data'][0]['parent_id'];
            if ($currentParentId == $targetParentId) {
                $skippedCount++;
                continue;
            }
        }
        
        $result = $db->query(
            "UPDATE files SET parent_id = ? WHERE id = ? AND user_id = ?",
            [$targetParentId, $fileId, $user['id']]
        );
        
        if ($result['success']) {
            $movedCount++;
        }
    }
    
    if ($movedCount > 0) {
        logOperation($user['id'], 'move', '移动 ' . $movedCount . ' 个文件');
        sendJsonResponse(['success' => true, 'moved_count' => $movedCount, 'skipped_count' => $skippedCount, 'message' => '移动成功']);
    } else if ($skippedCount > 0) {
        sendJsonResponse(['success' => true, 'moved_count' => 0, 'skipped_count' => $skippedCount, 'message' => '文件已在目标文件夹中']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '移动失败']);
    }
}

if ($action === 'copy') {
    $fileIds = $_POST['file_ids'] ?? [];
    $targetParentId = (int)($_POST['target_parent_id'] ?? 0);
    
    if (empty($fileIds) || !is_array($fileIds)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $copiedCount = 0;
    
    foreach ($fileIds as $fileId) {
        $fileId = (int)$fileId;
        if ($fileId <= 0) continue;
        
        $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
        
        if ($result['success'] && !empty($result['data'])) {
            $file = $result['data'][0];
            
            if ($file['file_type'] === 'folder') {
                $newName = $file['name'] . ' - 副本';
                $result = $db->query(
                    "INSERT INTO files (user_id, parent_id, name, file_type, file_size) VALUES (?, ?, ?, 'folder', 0)",
                    [$user['id'], $targetParentId, $newName]
                );
                
                if ($result['success']) {
                    $newFolderId = $result['insert_id'];
                    $copiedCount++;
                    
                    $childResult = $db->query("SELECT * FROM files WHERE parent_id = ?", [$fileId]);
                    if ($childResult['success']) {
                        foreach ($childResult['data'] as $child) {
                            copyFileRecursive($db, $child, $newFolderId, $user['id']);
                        }
                    }
                }
            } else {
                $newName = $file['name'];
                $counter = 1;
                
                while (true) {
                    $checkResult = $db->query(
                        "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ?",
                        [$user['id'], $targetParentId, $newName]
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
                
                $result = $db->query(
                    "INSERT INTO files (user_id, parent_id, name, original_name, file_type, file_size, file_path, mime_type, extension, md5, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $user['id'],
                        $targetParentId,
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
                    $copiedCount++;
                    updateUserStorage($user['id'], $file['file_size']);
                }
            }
        }
    }
    
    if ($copiedCount > 0) {
        logOperation($user['id'], 'copy', '复制 ' . $copiedCount . ' 个文件');
        sendJsonResponse(['success' => true, 'copied_count' => $copiedCount, 'message' => '复制成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '复制失败']);
    }
}

function copyFileRecursive($db, $file, $parentId, $userId) {
    if ($file['file_type'] === 'folder') {
        $result = $db->query(
            "INSERT INTO files (user_id, parent_id, name, file_type, file_size) VALUES (?, ?, ?, 'folder', 0)",
            [$userId, $parentId, $file['name']]
        );
        
        if ($result['success']) {
            $newFolderId = $result['insert_id'];
            
            $childResult = $db->query("SELECT * FROM files WHERE parent_id = ?", [$file['id']]);
            if ($childResult['success']) {
                foreach ($childResult['data'] as $child) {
                    copyFileRecursive($db, $child, $newFolderId, $userId);
                }
            }
        }
    } else {
        $db->query(
            "INSERT INTO files (user_id, parent_id, name, original_name, file_type, file_size, file_path, mime_type, extension, md5, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $parentId,
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

if ($action === 'delete') {
    $fileIds = $_POST['file_ids'] ?? [];
    
    if (empty($fileIds) || !is_array($fileIds)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $deletedCount = 0;
    $totalSize = 0;
    
    foreach ($fileIds as $fileId) {
        $fileId = (int)$fileId;
        if ($fileId <= 0) continue;
        
        $result = $db->query("SELECT * FROM files WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
        
        if ($result['success'] && !empty($result['data'])) {
            $file = $result['data'][0];
            
            $fileSize = getFileTotalSize($db, $fileId);
            $totalSize += $fileSize;
            
            $result = $db->query(
                "UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = ? OR parent_id IN (SELECT id FROM (SELECT id FROM files WHERE parent_id = ?) AS temp)",
                [$fileId, $fileId]
            );
            
            if ($result['success']) {
                $deletedCount++;
            }
        }
    }
    
    if ($deletedCount > 0) {
        updateUserStorage($user['id'], -$totalSize);
        
        $expireAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        foreach ($fileIds as $fileId) {
            $fileId = (int)$fileId;
            $db->query(
                "INSERT INTO recycle_bin (user_id, file_id, original_path, expire_at) VALUES (?, ?, ?, ?)",
                [$user['id'], $fileId, '/', $expireAt]
            );
        }
        
        logOperation($user['id'], 'delete', '删除 ' . $deletedCount . ' 个文件');
        sendJsonResponse(['success' => true, 'deleted_count' => $deletedCount, 'message' => '删除成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '删除失败']);
    }
}

function getFileTotalSize($db, $fileId) {
    $result = $db->query("SELECT file_type, file_size FROM files WHERE id = ?", [$fileId]);
    if (!$result['success'] || empty($result['data'])) {
        return 0;
    }
    
    $file = $result['data'][0];
    
    if ($file['file_type'] === 'folder') {
        $childResult = $db->query("SELECT id FROM files WHERE parent_id = ?", [$fileId]);
        if ($childResult['success']) {
            $totalSize = 0;
            foreach ($childResult['data'] as $child) {
                $totalSize += getFileTotalSize($db, $child['id']);
            }
            return $totalSize;
        }
    }
    
    return $file['file_size'];
}

if ($action === 'get_path') {
    $fileId = (int)($_GET['file_id'] ?? 0);
    
    if ($fileId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $path = [];
    $currentId = $fileId;
    
    while ($currentId > 0) {
        $result = $db->query("SELECT id, name, parent_id FROM files WHERE id = ?", [$currentId]);
        
        if ($result['success'] && !empty($result['data'])) {
            $file = $result['data'][0];
            array_unshift($path, [
                'id' => $file['id'],
                'name' => $file['name']
            ]);
            $currentId = $file['parent_id'];
        } else {
            break;
        }
    }
    
    array_unshift($path, ['id' => 0, 'name' => '根目录']);
    
    sendJsonResponse(['success' => true, 'path' => $path]);
}

if ($action === 'recycle_list') {
    $db = getDB();
    $result = $db->query(
        "SELECT rb.*, f.name, f.file_type FROM recycle_bin rb 
         JOIN files f ON rb.file_id = f.id 
         WHERE rb.user_id = ? 
         ORDER BY rb.deleted_at DESC",
        [$user['id']]
    );
    
    if ($result['success']) {
        $items = [];
        foreach ($result['data'] as $item) {
            $items[] = [
                'id' => $item['id'],
                'file_id' => $item['file_id'],
                'name' => $item['name'],
                'file_type' => $item['file_type'],
                'deleted_at' => $item['deleted_at'],
                'expire_at' => $item['expire_at']
            ];
        }
        sendJsonResponse(['success' => true, 'items' => $items]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取回收站列表失败']);
    }
}

if ($action === 'restore') {
    $fileId = (int)($_POST['file_id'] ?? 0);
    
    if ($fileId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("UPDATE files SET is_deleted = 0, deleted_at = NULL WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
    
    if ($result['success']) {
        $db->query("DELETE FROM recycle_bin WHERE file_id = ? AND user_id = ?", [$fileId, $user['id']]);
        sendJsonResponse(['success' => true, 'message' => '恢复成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '恢复失败']);
    }
}

if ($action === 'delete_permanent') {
    $fileId = (int)($_POST['file_id'] ?? 0);
    
    if ($fileId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("DELETE FROM files WHERE id = ? AND user_id = ?", [$fileId, $user['id']]);
    
    if ($result['success']) {
        $db->query("DELETE FROM recycle_bin WHERE file_id = ? AND user_id = ?", [$fileId, $user['id']]);
        sendJsonResponse(['success' => true, 'message' => '删除成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '删除失败']);
    }
}

if ($action === 'clear_recycle_bin') {
    $db = getDB();
    $result = $db->query("SELECT file_id FROM recycle_bin WHERE user_id = ?", [$user['id']]);
    
    if ($result['success']) {
        foreach ($result['data'] as $item) {
            $db->query("DELETE FROM files WHERE id = ? AND user_id = ?", [$item['file_id'], $user['id']]);
        }
        $db->query("DELETE FROM recycle_bin WHERE user_id = ?", [$user['id']]);
        sendJsonResponse(['success' => true, 'message' => '回收站已清空']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '清空失败']);
    }
}

sendJsonResponse(['success' => false, 'message' => '未知操作']);
?>