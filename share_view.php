<?php
session_start();
require_once __DIR__ . '/core/functions.php';

$shareCode = $_GET['code'] ?? '';

if (empty($shareCode)) {
    die('分享链接无效');
}

$db = getDB();
$result = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);

if (!$result['success'] || empty($result['data'])) {
    die('分享不存在或已失效');
}

$share = $result['data'][0];

if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
    die('分享已过期');
}

$fileResult = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);

if (!$fileResult['success'] || empty($fileResult['data'])) {
    die('文件不存在');
}

$file = $fileResult['data'][0];

$verified = false;
$canAccess = false;

if (!$share['extract_code']) {
    $canAccess = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extract_code'])) {
    if ($_POST['extract_code'] === $share['extract_code']) {
        $verified = true;
        $canAccess = true;
    } else {
        $error = '提取码错误';
    }
}

$user = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分享文件 - PureDrop网盘</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .share-container { max-width: 800px; margin: 40px auto; }
        .share-card { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .share-header { text-align: center; margin-bottom: 30px; }
        .share-icon { font-size: 64px; margin-bottom: 20px; }
        .share-title { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 8px; }
        .share-info { color: #666; font-size: 14px; }
        .share-details { background: #f5f5f5; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .share-detail { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .share-detail:last-child { margin-bottom: 0; }
        .share-detail-label { color: #666; }
        .share-detail-value { font-weight: 600; color: #333; }
        .extract-code-form { margin-bottom: 20px; }
        .extract-code-form input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; text-align: center; letter-spacing: 4px; }
        .extract-code-form input:focus { outline: none; border-color: #667eea; }
        .preview-container { margin-top: 20px; }
        .preview-image { max-width: 100%; border-radius: 8px; }
        .preview-video, .preview-audio { width: 100%; border-radius: 8px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <a href="index.php" class="logo">📁 PureDrop网盘</a>
            <nav class="nav">
                <a href="index.php">首页</a>
                <?php if ($user): ?>
                <a href="index.php">我的文件</a>
                <?php else: ?>
                <a href="login.php">登录</a>
                <a href="register.php">注册</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <div class="share-container">
        <div class="share-card">
            <div class="share-header">
                <div class="share-icon"><?php echo getFileIcon($file['file_type'], $file['extension']); ?></div>
                <div class="share-title"><?php echo htmlspecialchars($file['name']); ?></div>
                <div class="share-info">
                    <?php if ($file['file_type'] === 'folder'): ?>
                    文件夹分享
                    <?php else: ?>
                    文件分享 - <?php echo formatSize($file['file_size']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($share['extract_code'] && !$canAccess): ?>
            <div class="extract-code-form">
                <form method="POST">
                    <input type="text" name="extract_code" placeholder="请输入提取码" maxlength="10" required>
                    <button type="submit" class="btn" style="width: 100%; margin-top: 12px;">验证提取码</button>
                </form>
                <?php if (isset($error)): ?>
                <div class="message error" style="margin-top: 12px;"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($canAccess): ?>
            <div class="share-details">
                <div class="share-detail">
                    <span class="share-detail-label">文件类型</span>
                    <span class="share-detail-value"><?php echo $file['file_type'] === 'folder' ? '文件夹' : getFileType($file['extension']); ?></span>
                </div>
                <?php if ($file['file_type'] !== 'folder'): ?>
                <div class="share-detail">
                    <span class="share-detail-label">文件大小</span>
                    <span class="share-detail-value"><?php echo formatSize($file['file_size']); ?></span>
                </div>
                <?php endif; ?>
                <div class="share-detail">
                    <span class="share-detail-label">分享时间</span>
                    <span class="share-detail-value"><?php echo $share['created_at']; ?></span>
                </div>
                <?php if ($share['expiry_date']): ?>
                <div class="share-detail">
                    <span class="share-detail-label">有效期至</span>
                    <span class="share-detail-value"><?php echo $share['expiry_date']; ?></span>
                </div>
                <?php endif; ?>
                <div class="share-detail">
                    <span class="share-detail-label">下载次数</span>
                    <span class="share-detail-value"><?php echo $share['download_count']; ?></span>
                </div>
                <div class="share-detail">
                    <span class="share-detail-label">浏览次数</span>
                    <span class="share-detail-value"><?php echo $share['view_count']; ?></span>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <?php if ($file['file_type'] !== 'folder'): ?>
                <a href="download.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?>" class="btn" style="flex: 1; justify-content: center;">⬇️ 下载文件</a>
                <?php endif; ?>
                
                <?php if ($user): ?>
                <button class="btn btn-secondary" style="flex: 1;" onclick="saveToMyDrive()">💾 保存到我的网盘</button>
                <?php else: ?>
                <a href="login.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">登录后保存</a>
                <?php endif; ?>
            </div>
            
            <?php if ($file['file_type'] === 'image'): ?>
            <div class="preview-container">
                <img src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?>" alt="<?php echo htmlspecialchars($file['name']); ?>" class="preview-image">
            </div>
            <?php elseif ($file['file_type'] === 'video'): ?>
            <div class="preview-container">
                <video src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?>" class="preview-video" controls></video>
            </div>
            <?php elseif ($file['file_type'] === 'audio'): ?>
            <div class="preview-container">
                <audio src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?>" class="preview-audio" controls></audio>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function saveToMyDrive() {
        fetch('api/share.php?action=save_to_my_drive', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `share_code=<?php echo $shareCode; ?>&parent_id=0`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('保存成功', 'success');
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    </script>
</body>
</html>