<?php
session_start();
require_once __DIR__ . '/../core/functions.php';



$user = null;
if (isLoggedIn()) {
    $user = getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">文件</a>
                <a href="share.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'share.php' ? 'active' : ''; ?>">分享</a>
                <a href="recycle.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'recycle.php' ? 'active' : ''; ?>">回收站</a>
            </nav>
            <?php if ($user): ?>
            <div class="user-menu">
                <div class="user-dropdown">
                    <?php if ($user['avatar']): ?>
                    <img src="uploads/<?php echo $user['avatar']; ?>" alt="头像" class="user-avatar" onclick="toggleUserMenu()">
                    <?php else: ?>
                    <div class="user-avatar" onclick="toggleUserMenu()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">👤</div>
                    <?php endif; ?>
                    <div class="dropdown-menu" id="userDropdown">
                        <div class="dropdown-item" onclick="location.href='profile.php'">👤 个人资料</div>
                        <?php if ($user['membership_level'] === 'premium'): ?>
                        <div class="dropdown-item" onclick="location.href='admin/index.php'">🛡️ 管理后台</div>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item" onclick="location.href='logout.php'">🚪 退出登录</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div>
                <a href="login.php" class="btn btn-sm">登录</a>
                <a href="register.php" class="btn btn-sm">注册</a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <script>
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    </script>