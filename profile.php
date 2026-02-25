<?php
session_start();
require_once __DIR__ . '/core/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $nickname = trim($_POST['nickname'] ?? '');
        
        if (!empty($nickname)) {
            $db = getDB();
            $result = $db->query("UPDATE users SET nickname = ? WHERE id = ?", [$nickname, $user['id']]);
            
            if ($result['success']) {
                logOperation($user['id'], 'update_profile', '更新昵称: ' . $nickname);
                $success = '个人资料更新成功';
                $user['nickname'] = $nickname;
            } else {
                $error = '更新失败，请稍后重试';
            }
        }
    } elseif ($action === 'update_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPassword2 = $_POST['new_password2'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword)) {
            $error = '请填写完整信息';
        } elseif (!verifyPassword($oldPassword, $user['password'])) {
            $error = '原密码错误';
        } elseif (!validatePassword($newPassword)) {
            $error = '新密码长度不能少于6位';
        } elseif ($newPassword !== $newPassword2) {
            $error = '两次新密码输入不一致';
        } else {
            $hashedPassword = hashPassword($newPassword);
            $db = getDB();
            $result = $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user['id']]);
            
            if ($result['success']) {
                logOperation($user['id'], 'update_password', '修改密码');
                $success = '密码修改成功';
            } else {
                $error = '修改失败，请稍后重试';
            }
        }
    } elseif ($action === 'upload_avatar' && isset($_FILES['avatar'])) {
        $avatar = $_FILES['avatar'];
        
        if ($avatar['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $avatar['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $error = '只支持 JPG、PNG、GIF、WebP 格式的图片';
            } elseif ($avatar['size'] > 2097152) {
                $error = '图片大小不能超过 2MB';
            } else {
                $extension = pathinfo($avatar['name'], PATHINFO_EXTENSION);
                $avatarName = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
                $avatarPath = 'avatars/' . $avatarName;
                $fullPath = UPLOAD_DIR . $avatarPath;
                
                $avatarDir = UPLOAD_DIR . 'avatars/';
                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0755, true);
                }
                
                if (move_uploaded_file($avatar['tmp_name'], $fullPath)) {
                    $db = getDB();
                    $result = $db->query("UPDATE users SET avatar = ? WHERE id = ?", [$avatarPath, $user['id']]);
                    
                    if ($result['success']) {
                        logOperation($user['id'], 'update_avatar', '更新头像');
                        $success = '头像上传成功';
                        $user['avatar'] = $avatarPath;
                    } else {
                        $error = '头像保存失败';
                        unlink($fullPath);
                    }
                } else {
                    $error = '头像上传失败';
                }
            }
        } else {
            $error = '文件上传错误';
        }
    }
}

$storageUsed = $user['storage_used'];
$storageTotal = $user['storage_total'];
$storagePercent = $storageTotal > 0 ? round(($storageUsed / $storageTotal) * 100, 2) : 0;
$storageRemaining = $storageTotal - $storageUsed;

$db = getDB();
$fileCountResult = $db->query("SELECT COUNT(*) as count FROM files WHERE user_id = ? AND file_type != 'folder'", [$user['id']]);
$fileCount = $fileCountResult['success'] ? $fileCountResult['data'][0]['count'] : 0;

$folderCountResult = $db->query("SELECT COUNT(*) as count FROM files WHERE user_id = ? AND file_type = 'folder'", [$user['id']]);
$folderCount = $folderCountResult['success'] ? $folderCountResult['data'][0]['count'] : 0;

$membershipName = getMembershipLevelName($user['membership_level']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人主页 - PureDrop网盘</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .profile-header { background: white; border-radius: 12px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .profile-info { display: flex; align-items: center; gap: 30px; }
        .avatar-section { position: relative; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #667eea; }
        .avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; }
        .avatar-upload { position: absolute; bottom: 0; right: 0; background: #667eea; color: white; border: none; border-radius: 50%; width: 36px; height: 36px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .avatar-upload:hover { background: #5568d3; }
        .avatar-upload input { display: none; }
        .user-details { flex: 1; }
        .user-name { font-size: 28px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .user-email { color: #666; font-size: 14px; margin-bottom: 15px; }
        .user-stats { display: flex; gap: 30px; flex-wrap: wrap; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 12px; color: #999; }
        .membership-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .membership-free { background: #e0e0e0; color: #666; }
        .membership-vip { background: linear-gradient(135deg, #f5af19 0%, #f12711 100%); color: white; }
        .membership-premium { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .profile-content { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .profile-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .btn-full { width: 100%; }
        .storage-progress { background: #f0f0f0; border-radius: 10px; height: 20px; overflow: hidden; margin: 15px 0; }
        .storage-progress-bar { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s; }
        .storage-info { display: flex; justify-content: space-between; font-size: 14px; color: #666; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .message.error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        @media (max-width: 768px) {
            .profile-content { grid-template-columns: 1fr; }
            .profile-info { flex-direction: column; text-align: center; }
            .user-stats { justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="profile-container">
        <?php if (isset($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-info">
                <div class="avatar-section">
                    <?php if ($user['avatar']): ?>
                    <img src="uploads/<?php echo $user['avatar']; ?>" alt="头像" class="avatar">
                    <?php else: ?>
                    <div class="avatar-placeholder">👤</div>
                    <?php endif; ?>
                    <label class="avatar-upload">
                        📷
                        <input type="file" name="avatar" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                    </label>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div style="margin-bottom: 15px;">
                        <span class="membership-badge membership-<?php echo $user['membership_level']; ?>"><?php echo $membershipName; ?></span>
                    </div>
                    <div class="user-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $fileCount; ?></div>
                            <div class="stat-label">文件</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $folderCount; ?></div>
                            <div class="stat-label">文件夹</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo formatSize($storageUsed); ?></div>
                            <div class="stat-label">已用空间</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form id="avatarForm" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" name="avatar">
            </form>
        </div>
        
        <div class="profile-content">
            <div class="profile-card">
                <div class="card-title">存储空间</div>
                <div class="storage-info">
                    <span>已使用: <?php echo formatSize($storageUsed); ?></span>
                    <span>总计: <?php echo formatSize($storageTotal); ?></span>
                </div>
                <div class="storage-progress">
                    <div class="storage-progress-bar" style="width: <?php echo $storagePercent; ?>%;"></div>
                </div>
                <div class="storage-info">
                    <span>使用率: <?php echo $storagePercent; ?>%</span>
                    <span>剩余: <?php echo formatSize($storageRemaining); ?></span>
                </div>
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px; color: #333;">升级存储方案</h3>
                    <?php $plans = getStoragePlans(); ?>
                    <?php if (!empty($plans)): ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($plans as $plan): ?>
                        <div style="padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='#667eea'" onmouseout="this.style.borderColor='#e0e0e0'">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($plan['name']); ?></div>
                                    <div style="font-size: 12px; color: #999;"><?php echo formatSize($plan['storage_size']); ?></div>
                                </div>
                                <div style="font-weight: 700; color: #667eea;">
                                    <?php if ($plan['price'] > 0): ?>
                                    ¥<?php echo number_format($plan['price'], 2); ?>
                                    <?php else: ?>
                                    免费
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color: #999;">暂无可用方案</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-card">
                <div class="card-title">个人资料</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?: ''); ?>" placeholder="请输入昵称">
                    </div>
                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background: #f5f5f5;">
                    </div>
                    <button type="submit" class="btn btn-full">保存修改</button>
                </form>
            </div>
            
            <div class="profile-card">
                <div class="card-title">修改密码</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label>原密码</label>
                        <input type="password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label>新密码</label>
                        <input type="password" name="new_password" required placeholder="至少6位">
                    </div>
                    <div class="form-group">
                        <label>确认新密码</label>
                        <input type="password" name="new_password2" required>
                    </div>
                    <button type="submit" class="btn btn-full">修改密码</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>