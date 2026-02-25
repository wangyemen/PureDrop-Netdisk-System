<?php
session_start();
require_once __DIR__ . '/core/functions.php';

if (!$installed) {
    header('Location: install/install.php');
    exit;
}

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

if ($user['status'] === 'disabled') {
    session_destroy();
    header('Location: login.php?error=disabled');
    exit;
}

$storageUsed = $user['storage_used'];
$storageTotal = $user['storage_total'];
$storagePercent = $storageTotal > 0 ? round(($storageUsed / $storageTotal) * 100, 2) : 0;

$announcements = getAnnouncements();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <?php 
                $siteLogo = getSetting('site_logo', '');
                if ($siteLogo): 
                ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="Logo" style="height: 32px; vertical-align: middle; margin-right: 8px;">
                <?php else: ?>
                    📁
                <?php endif; ?>
                <?php echo getSetting('site_name', 'PureDrop网盘'); ?>
            </a>
            <nav class="nav">
                <a href="index.php" class="active">文件</a>
                <a href="share.php">分享</a>
                <a href="recycle.php">回收站</a>
            </nav>
            <div class="user-menu">
                <div class="user-dropdown">
                    <?php if ($user['avatar']): ?>
                    <img src="uploads/<?php echo $user['avatar']; ?>" alt="头像" class="user-avatar" onclick="toggleUserMenu()">
                    <?php else: ?>
                    <div class="user-avatar" onclick="toggleUserMenu()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">👤</div>
                    <?php endif; ?>
                    <div class="dropdown-menu" id="userDropdown">
                        <div class="dropdown-item" onclick="location.href='profile.php'">👤 个人资料</div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item" onclick="location.href='logout.php'">🚪 退出登录</div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="main">
        <aside class="sidebar">
            <div class="sidebar-menu">
                <div class="sidebar-item active" onclick="goToFolder(0)">
                    <span class="sidebar-item-icon">📁</span>
                    <span class="sidebar-item-text">全部文件</span>
                </div>
                <div class="sidebar-item" onclick="filterByType('image')">
                    <span class="sidebar-item-icon">🖼️</span>
                    <span class="sidebar-item-text">图片</span>
                </div>
                <div class="sidebar-item" onclick="filterByType('video')">
                    <span class="sidebar-item-icon">🎬</span>
                    <span class="sidebar-item-text">视频</span>
                </div>
                <div class="sidebar-item" onclick="filterByType('audio')">
                    <span class="sidebar-item-icon">🎵</span>
                    <span class="sidebar-item-text">音乐</span>
                </div>
                <div class="sidebar-item" onclick="filterByType('document')">
                    <span class="sidebar-item-icon">📄</span>
                    <span class="sidebar-item-text">文档</span>
                </div>
            </div>
            
            <div class="storage-info">
                <div class="storage-title">存储空间</div>
                <div class="storage-progress">
                    <div class="storage-progress-bar" style="width: <?php echo $storagePercent; ?>%;"></div>
                </div>
                <div class="storage-text"><?php echo formatSize($storageUsed); ?> / <?php echo formatSize($storageTotal); ?></div>
            </div>
        </aside>
        
        <main class="content">
            <?php if (!empty($announcements)): ?>
            <div class="message info" style="margin-bottom: 20px;">
                <?php foreach ($announcements as $announcement): ?>
                <div style="margin-bottom: 8px;">
                    <strong>📢 <?php echo htmlspecialchars($announcement['title']); ?></strong>
                    <p style="margin-top: 4px;"><?php echo htmlspecialchars($announcement['content']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn" onclick="showUploadModal()">📤 上传文件</button>
                    <button class="btn btn-secondary" onclick="showCreateFolderModal()">📁 新建文件夹</button>
                </div>
                <div class="toolbar-right">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="搜索文件..." onkeyup="handleSearch(event)">
                        <span class="search-box-icon">🔍</span>
                    </div>
                    <div class="view-toggle">
                        <div class="view-toggle-btn active" id="listViewBtn" onclick="setView('list')">📋</div>
                        <div class="view-toggle-btn" id="gridViewBtn" onclick="setView('grid')">⊞</div>
                    </div>
                </div>
            </div>
            
            <div class="filter-tabs">
                <div class="filter-tab active" data-type="all" onclick="filterByType('all')">全部</div>
                <div class="filter-tab" data-type="image" onclick="filterByType('image')">图片</div>
                <div class="filter-tab" data-type="video" onclick="filterByType('video')">视频</div>
                <div class="filter-tab" data-type="audio" onclick="filterByType('audio')">音乐</div>
                <div class="filter-tab" data-type="document" onclick="filterByType('document')">文档</div>
                <div class="filter-tab" data-type="other" onclick="filterByType('other')">其他</div>
            </div>
            
            <div class="breadcrumb" id="breadcrumb">
                <span class="breadcrumb-item current">根目录</span>
            </div>
            
            <div id="fileListContainer">
                <div class="file-list" id="fileListView">
                    <div class="file-list-header">
                        <div style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></div>
                        <div class="file-list-header-sort" onclick="sortBy('name')">名称</div>
                        <div class="file-list-header-sort" onclick="sortBy('size')">大小</div>
                        <div class="file-list-header-sort" onclick="sortBy('date')">修改时间</div>
                        <div style="width: 150px;">操作</div>
                    </div>
                    <div id="fileListBody">
                        <div class="loading"><div class="loading-spinner"></div></div>
                    </div>
                </div>
                
                <div class="file-grid" id="fileGridView" style="display: none;">
                    <div id="fileGridBody">
                        <div class="loading"><div class="loading-spinner"></div></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="uploadModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">上传文件</h3>
                <span class="modal-close" onclick="closeUploadModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                    <div class="upload-area-icon">📤</div>
                    <div class="upload-area-text">点击或拖拽文件到此处上传</div>
                    <div class="upload-area-hint">支持多文件上传，最大 <?php echo formatSize(MAX_FILE_SIZE); ?></div>
                </div>
                <input type="file" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(event)">
                <div class="upload-list" id="uploadList"></div>
            </div>
        </div>
    </div>
    
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">新建文件夹</h3>
                <span class="modal-close" onclick="closeCreateFolderModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>文件夹名称</label>
                    <input type="text" id="folderName" placeholder="请输入文件夹名称">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateFolderModal()">取消</button>
                <button class="btn" onclick="createFolder()">创建</button>
            </div>
        </div>
    </div>
    
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">重命名</h3>
                <span class="modal-close" onclick="closeRenameModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="renameFileId">
                <div class="form-group">
                    <label>新名称</label>
                    <input type="text" id="newFileName" placeholder="请输入新名称">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRenameModal()">取消</button>
                <button class="btn" onclick="renameFile()">确定</button>
            </div>
        </div>
    </div>
    
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">分享文件</h3>
                <span class="modal-close" onclick="closeShareModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="shareFileId">
                <div class="form-group">
                    <label>提取码（可选）</label>
                    <input type="text" id="extractCode" placeholder="留空则不需要提取码">
                </div>
                <div class="form-group">
                    <label>有效期（天）</label>
                    <input type="number" id="expiryDays" value="7" min="0" placeholder="0表示永久有效">
                </div>
                <div class="form-group" id="shareResult" style="display: none;">
                    <label>分享链接</label>
                    <input type="text" id="shareUrl" readonly onclick="this.select()">
                    <button class="btn btn-sm" onclick="copyShareUrl()" style="margin-top: 8px;">复制链接</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeShareModal()">取消</button>
                <button class="btn" onclick="createShare()">创建分享</button>
            </div>
        </div>
    </div>
    
    <div id="previewModal" class="modal">
        <div class="modal-content preview-modal">
            <div class="modal-header">
                <h3 class="modal-title" id="previewTitle">预览</h3>
                <span class="modal-close" onclick="closePreviewModal()">&times;</span>
            </div>
            <div class="modal-body preview-content" id="previewContent"></div>
        </div>
    </div>
    
    <div id="contextMenu" class="context-menu">
        <div class="context-menu-item" onclick="openFile()">📂 打开</div>
        <div class="context-menu-item" onclick="previewFile()">👁️ 预览</div>
        <div class="context-menu-item" onclick="downloadFile()">⬇️ 下载</div>
        <div class="context-menu-item" onclick="showRenameModal()">✏️ 重命名</div>
        <div class="context-menu-item" onclick="showShareModal()">🔗 分享</div>
        <div class="context-menu-item" onclick="showMoveModal()">📁 移动</div>
        <div class="context-menu-item" onclick="showCopyModal()">📋 复制</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="deleteFile()">🗑️ 删除</div>
    </div>
    
    <div id="moveModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">移动文件</h3>
                <span class="modal-close" onclick="closeMoveModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>选择目标文件夹</label>
                    <div id="folderTree" style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeMoveModal()">取消</button>
                <button class="btn" onclick="confirmMove()">确定</button>
            </div>
        </div>
    </div>
    
    <div id="copyModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">复制文件</h3>
                <span class="modal-close" onclick="closeCopyModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>选择目标文件夹</label>
                    <div id="copyFolderTree" style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCopyModal()">取消</button>
                <button class="btn" onclick="confirmCopy()">确定</button>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>