<?php
session_start();
require_once __DIR__ . '/core/functions.php';

// 检查是否有分享码
$shareCode = $_GET['share_code'] ?? $_GET['code'] ?? '';

if (!empty($shareCode)) {
    // 处理分享码访问
    $db = getDB();
    $shareResult = $db->query("SELECT * FROM file_shares WHERE share_code = ? AND is_active = 1", [$shareCode]);
    
    if ($shareResult['success'] && !empty($shareResult['data'])) {
        $share = $shareResult['data'][0];
        
        // 检查是否过期
        if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
            $error = '分享已过期';
        } else {
            // 获取文件信息
            $fileResult = $db->query("SELECT * FROM files WHERE id = ? AND is_deleted = 0", [$share['file_id']]);
            
            if ($fileResult['success'] && !empty($fileResult['data'])) {
                $file = $fileResult['data'][0];
                
                // 更新浏览次数
                $db->query("UPDATE file_shares SET view_count = view_count + 1 WHERE id = ?", [$share['id']]);
                
                $pageTitle = '文件分享';
                
                // 如果是文件夹，获取文件夹内容
                if ($file['file_type'] === 'folder') {
                    $currentFolderId = $file['id'];
                    $folderPath = [$file];
                    
                    // 获取当前文件夹的内容
                    $contentResult = $db->query(
                        "SELECT * FROM files WHERE parent_id = ? AND is_deleted = 0 ORDER BY file_type ASC, name ASC",
                        [$currentFolderId]
                    );
                    $folderContents = $contentResult['success'] ? $contentResult['data'] : [];
                }
            } else {
                $error = '分享的文件不存在或已删除';
            }
        }
    } else {
        $error = '无效的分享链接';
    }
} else {
    // 处理登录用户的分享列表
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
    
    $pageTitle = '我的分享';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? '我的分享'; ?> - <?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container" style="padding: 20px;">
        <?php if (!empty($shareCode)): ?>
            <!-- 分享文件页面 -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" style="background: #ffebee; color: #c62828; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3>❌ 分享错误</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                    <a href="index.php" class="btn" style="margin-top: 10px;">返回首页</a>
                </div>
            <?php else: ?>
                <div class="card" style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 class="page-title" style="color: #333; margin-bottom: 30px;">📤 文件分享</h2>
                    
                    <!-- 文件夹分享 -->
                    <?php if ($file['file_type'] === 'folder'): ?>
                        <div class="file-info" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
                            <div class="file-icon" style="width: 80px; height: 80px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 36px;">
                                📁
                            </div>
                            <div class="file-details" style="flex: 1;">
                                <h3 style="color: #333; margin: 0 0 10px 0;"><?php echo htmlspecialchars($file['name']); ?></h3>
                                <p style="color: #666; margin: 0 0 5px 0;">文件夹</p>
                                <p style="color: #999; margin: 0;">分享时间: <?php echo $share['created_at']; ?></p>
                            </div>
                        </div>
                        
                        <?php if ($share['extract_code']): ?>
                            <div class="extract-code-section" style="margin-bottom: 30px;">
                                <h4 style="color: #333; margin-bottom: 15px;">需要提取码</h4>
                                <input type="text" id="extractCode" placeholder="请输入提取码" style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                                <button onclick="verifyExtractCode()" class="btn" style="margin-top: 10px;">验证提取码</button>
                            </div>
                        <?php endif; ?>
                        
                        <div id="folderContent" <?php echo $share['extract_code'] ? 'style="display: none;"' : ''; ?>>
                            <?php if (empty($folderContents)): ?>
                                <p style="color: #999; text-align: center; padding: 40px 0;">文件夹为空</p>
                            <?php else: ?>
                                <div class="data-table" style="border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f5f5f5;">
                                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0;">名称</th>
                                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0;">大小</th>
                                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0;">类型</th>
                                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0;">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($folderContents as $item): ?>
                                                <tr style="border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='transparent'">
                                                    <td style="padding: 12px 15px;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span style="font-size: 24px;"><?php echo $item['file_type'] === 'folder' ? '📁' : getFileIcon($item['file_type']); ?></span>
                                                            <span style="color: #333;"><?php echo htmlspecialchars($item['name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 12px 15px; color: #666;">
                                                        <?php echo $item['file_type'] === 'folder' ? '-' : formatSize($item['file_size']); ?>
                                                    </td>
                                                    <td style="padding: 12px 15px; color: #666;">
                                                        <?php echo $item['file_type'] === 'folder' ? '文件夹' : $item['extension'] ?? '文件'; ?>
                                                    </td>
                                                    <td style="padding: 12px 15px;">
                                                        <?php if ($item['file_type'] === 'folder'): ?>
                                                            <span style="color: #999; font-size: 12px;">不支持下载</span>
                                                        <?php elseif (in_array($item['file_type'], ['image', 'video', 'audio'])): ?>
                                                            <a href="preview.php?file_id=<?php echo $item['id']; ?>&share_code=<?php echo $shareCode; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" target="_blank" class="btn btn-sm" style="margin-right: 8px;">预览</a>
                                                            <a href="api/share.php?action=download_file&share_code=<?php echo $shareCode; ?>&file_id=<?php echo $item['id']; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" class="btn btn-sm">下载</a>
                                                        <?php else: ?>
                                                            <a href="api/share.php?action=download_file&share_code=<?php echo $shareCode; ?>&file_id=<?php echo $item['id']; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" class="btn btn-sm">下载</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    
                    <!-- 文件分享 -->
                    <?php else: ?>
                        <div class="file-info" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
                            <div class="file-icon" style="width: 80px; height: 80px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 36px;">
                                📄
                            </div>
                            <div class="file-details" style="flex: 1;">
                                <h3 style="color: #333; margin: 0 0 10px 0;"><?php echo htmlspecialchars($file['name']); ?></h3>
                                <p style="color: #666; margin: 0 0 5px 0;">大小: <?php echo formatSize($file['file_size']); ?></p>
                                <p style="color: #999; margin: 0;">分享时间: <?php echo $share['created_at']; ?></p>
                            </div>
                        </div>
                        
                        <?php if ($share['extract_code']): ?>
                            <div class="extract-code-section" style="margin-bottom: 30px;">
                                <h4 style="color: #333; margin-bottom: 15px;">需要提取码</h4>
                                <input type="text" id="extractCode" placeholder="请输入提取码" style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                                <button onclick="verifyExtractCode()" class="btn" style="margin-top: 10px;">验证提取码</button>
                            </div>
                        <?php endif; ?>
                        
                        <div id="fileContent" <?php echo $share['extract_code'] ? 'style="display: none;"' : ''; ?>>
                            <?php if (in_array($file['file_type'], ['image', 'video', 'audio'])): ?>
                                <div class="preview-section" style="margin-bottom: 30px;">
                                    <h4 style="color: #333; margin-bottom: 15px;">在线预览</h4>
                                    <div class="preview-container" style="max-width: 100%; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                                        <?php if ($file['file_type'] === 'image'): ?>
                                            <img src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" style="max-width: 100%; max-height: 600px; display: block; margin: 0 auto;">
                                        <?php elseif ($file['file_type'] === 'video'): ?>
                                            <video src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" controls style="max-width: 100%; max-height: 600px; display: block; margin: 0 auto;"></video>
                                        <?php elseif ($file['file_type'] === 'audio'): ?>
                                            <audio src="preview.php?file_id=<?php echo $file['id']; ?>&share_code=<?php echo $shareCode; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" controls style="width: 100%; display: block; margin: 0 auto;"></audio>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <a href="api/share.php?action=download&share_code=<?php echo $shareCode; ?><?php echo $share['extract_code'] ? '&extract_code=' . $share['extract_code'] : ''; ?>" class="btn" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.3s;">
                                    📥 下载文件
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- 我的分享页面 -->
            <div class="page-header" style="margin-bottom: 30px;">
                <h1 class="page-title" style="color: #333;">我的分享</h1>
            </div>
            <div class="data-table" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
                <div class="data-table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f5f5f5; border-bottom: 1px solid #e0e0e0;">
                    <h2 style="color: #333; margin: 0;">我的分享</h2>
                </div>
                <div class="data-table-content">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f9f9f9;">
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">文件名</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">类型</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">提取码</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">有效期</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">下载次数</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">浏览次数</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">创建时间</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">操作</th>
                            </tr>
                        </thead>
                        <tbody id="shareTableBody">
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #999;">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function getFileIcon(fileType) {
        const icons = {
            'image': '🖼️',
            'video': '🎬',
            'audio': '🎵',
            'document': '📄',
            'archive': '📦',
            'code': '💻'
        };
        return icons[fileType] || '📄';
    }
    
    <?php if (empty($shareCode)): ?>
    loadShares();
    
    function loadShares() {
        fetch('api/share.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderShares(data.shares);
                }
            });
    }
    
    function renderShares(shares) {
        const tbody = document.getElementById('shareTableBody');
        
        if (shares.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #999;">暂无分享</td></tr>';
            return;
        }
        
        tbody.innerHTML = shares.map(share => `
            <tr style="border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='transparent'">
                <td style="padding: 12px 15px; color: #333;">${share.file_name}</td>
                <td style="padding: 12px 15px; color: #666;">${share.file_type === 'folder' ? '文件夹' : '文件'}</td>
                <td style="padding: 12px 15px; color: #666;">${share.extract_code || '无'}</td>
                <td style="padding: 12px 15px; color: #666;">${share.expiry_date || '永久'}</td>
                <td style="padding: 12px 15px; color: #666;">${share.download_count}</td>
                <td style="padding: 12px 15px; color: #666;">${share.view_count}</td>
                <td style="padding: 12px 15px; color: #666;">${share.created_at}</td>
                <td style="padding: 12px 15px;">
                    <button class="btn btn-sm" style="margin-right: 8px;" onclick="copyShareUrl('${share.share_url}')">复制链接</button>
                    ${share.is_active ? `<button class="btn btn-sm btn-danger" onclick="cancelShare(${share.id})">取消</button>` : '<span style="color: #999;">已失效</span>'}
                </td>
            </tr>
        `).join('');
    }
    
    function cancelShare(shareId) {
        if (!confirm('确定要取消此分享吗？')) {
            return;
        }
        
        fetch('api/share.php?action=cancel', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `share_id=${shareId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('分享已取消', 'success');
                loadShares();
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    <?php else: ?>
    function verifyExtractCode() {
        const extractCode = document.getElementById('extractCode').value.trim();
        if (!extractCode) {
            showToast('请输入提取码', 'error');
            return;
        }
        
        fetch('api/share.php?action=verify_code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `share_code=<?php echo $shareCode; ?>&extract_code=${extractCode}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const folderContent = document.getElementById('folderContent');
                const fileContent = document.getElementById('fileContent');
                if (folderContent) {
                    folderContent.style.display = 'block';
                }
                if (fileContent) {
                    fileContent.style.display = 'block';
                }
                showToast('提取码正确', 'success');
            } else {
                showToast('提取码错误', 'error');
            }
        });
    }
    <?php endif; ?>
    
    function copyShareUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('链接已复制', 'success');
        });
    }
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>