<?php
session_start();
require_once __DIR__ . '/../core/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();

if (!$user || $user['membership_level'] !== 'premium') {
    header('Location: ../index.php');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';

$localVersion = trim(file_get_contents(__DIR__ . '/../version.txt'));
$latestVersion = getLatestVersion();
$hasUpdate = version_compare($latestVersion, $localVersion, '>');

function getLatestVersion() {
    $url = 'https://raw.githubusercontent.com/wangyemen/PureDrop-Netdisk-System/main/version.txt';
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    return $content ? trim($content) : false;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 250px; background: #1a1a2e; color: white; }
        .admin-sidebar-header { padding: 20px; border-bottom: 1px solid #16213e; }
        .admin-sidebar-header h2 { font-size: 20px; font-weight: 700; }
        .admin-menu { padding: 10px 0; }
        .admin-menu-item { padding: 12px 20px; color: #a0a0a0; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 12px; }
        .admin-menu-item:hover { background: #16213e; color: white; }
        .admin-menu-item.active { background: #667eea; color: white; }
        .admin-content { flex: 1; background: #f5f5f5; }
        .admin-header { background: white; padding: 20px; border-bottom: 1px solid #e0e0e0; }
        .admin-header h1 { font-size: 24px; font-weight: 600; color: #333; }
        .admin-main { padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-label { font-size: 14px; color: #666; margin-bottom: 8px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #667eea; }
        .stat-change { font-size: 12px; color: #4caf50; margin-top: 4px; }
        .data-table { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
        .data-table-header { padding: 20px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .data-table-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .data-table-content { overflow-x: auto; }
        .data-table table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 16px 20px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .data-table th { background: #f5f5f5; font-weight: 600; color: #666; font-size: 13px; }
        .data-table tr:hover { background: #f9f9f9; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-disabled { background: #ffebee; color: #c62828; }
        .membership-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .membership-free { background: #e0e0e0; color: #666; }
        .membership-vip { background: linear-gradient(135deg, #f5af19 0%, #f12711 100%); color: white; }
        .membership-premium { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 20px; }
        .pagination-btn { padding: 8px 16px; background: white; border: 1px solid #e0e0e0; border-radius: 6px; cursor: pointer; transition: all 0.3s; }
        .pagination-btn:hover { border-color: #667eea; color: #667eea; }
        .pagination-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .pagination-info { color: #666; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .danger-zone { border: 2px solid #f44336; border-radius: 12px; padding: 24px; margin-top: 30px; }
        .danger-zone h3 { color: #f44336; margin-bottom: 16px; }
        .danger-zone p { color: #666; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <h2>
                    <?php 
                    $siteLogo = getSetting('site_logo', '');
                    if ($siteLogo): 
                    ?>
                        <img src="../<?php echo htmlspecialchars($siteLogo); ?>" alt="Logo" style="height: 24px; vertical-align: middle; margin-right: 8px;">
                    <?php else: ?>
                        📁
                    <?php endif; ?>
                    <?php echo getSetting('site_name', 'PureDrop'); ?>管理
                </h2>
            </div>
            <div class="admin-menu">
                <div class="admin-menu-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>" onclick="loadPage('dashboard')">
                    <span>📊</span>
                    <span>仪表盘</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'users' ? 'active' : ''; ?>" onclick="loadPage('users')">
                    <span>👥</span>
                    <span>用户管理</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'settings' ? 'active' : ''; ?>" onclick="loadPage('settings')">
                    <span>⚙️</span>
                    <span>系统设置</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'storage' ? 'active' : ''; ?>" onclick="loadPage('storage')">
                    <span>💾</span>
                    <span>存储方案</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'announcements' ? 'active' : ''; ?>" onclick="loadPage('announcements')">
                    <span>📢</span>
                    <span>公告管理</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'logs' ? 'active' : ''; ?>" onclick="loadPage('logs')">
                    <span>📋</span>
                    <span>操作日志</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'admin_logs' ? 'active' : ''; ?>" onclick="loadPage('admin_logs')">
                    <span>🛡️</span>
                    <span>管理员日志</span>
                </div>
                <div class="admin-menu-item <?php echo $page === 'danger' ? 'active' : ''; ?>" onclick="loadPage('danger')">
                    <span>⚠️</span>
                    <span>危险区域</span>
                </div>
            </div>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h1><?php echo getPageTitle($page); ?></h1>
                    <div style="margin-top: 8px; color: #666;">欢迎回来，<?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 14px; color: #666;">当前版本</div>
                    <div style="font-size: 18px; font-weight: 600; color: #667eea;">
                        v<?php echo htmlspecialchars($localVersion); ?>
                        <?php if ($hasUpdate): ?>
                            <span style="background: #f44336; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">有新版本</span>
                        <?php endif; ?>
                    </div>
                    <button onclick="checkForUpdate()" style="margin-top: 10px; padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; transition: all 0.3s;">
                        🔄 检查更新
                    </button>
                    <button onclick="window.location.href='../index.php'" style="margin-top: 5px; padding: 6px 12px; background: #4caf50; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; transition: all 0.3s;">
                        🏠 网站首页
                    </button>
                </div>
            </div>
            
            <div class="admin-main" id="adminMain">
                <?php include 'pages/' . $page . '.php'; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    
    <?php if ($hasUpdate && $latestVersion): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('updateDismissed') !== '<?php echo $latestVersion; ?>') {
            const updateModal = document.createElement('div');
            updateModal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;';
            updateModal.innerHTML = `
                <div style="background: white; border-radius: 12px; padding: 30px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 48px; margin-bottom: 10px;">🔄</div>
                        <h2 style="font-size: 24px; color: #333; margin-bottom: 10px;">发现新版本</h2>
                        <p style="color: #666; margin-bottom: 20px;">
                            当前版本: v<?php echo $localVersion; ?><br>
                            最新版本: v<?php echo $latestVersion; ?>
                        </p>
                        <p style="color: #666; margin-bottom: 20px;">
                            建议您更新到最新版本以获得更好的体验和安全性。
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="window.open('https://pddisk.xo.je/', '_blank')" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            立即更新
                        </button>
                        <button style="flex: 1; padding: 12px; background: #e0e0e0; color: #666; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            稍后提醒
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(updateModal);
            
            const dismissButton = updateModal.querySelector('button:last-child');
            dismissButton.addEventListener('click', function() {
                localStorage.setItem('updateDismissed', '<?php echo $latestVersion; ?>');
                updateModal.remove();
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>

<?php
function getPageTitle($page) {
    $titles = [
        'dashboard' => '仪表盘',
        'users' => '用户管理',
        'settings' => '系统设置',
        'storage' => '存储方案',
        'announcements' => '公告管理',
        'logs' => '操作日志',
        'admin_logs' => '管理员日志',
        'danger' => '危险区域'
    ];
    return $titles[$page] ?? '管理后台';
}
?>