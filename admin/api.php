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

$action = $_GET['action'] ?? '';

if ($action === 'dashboard') {
    $db = getDB();
    
    $totalUsersResult = $db->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $totalUsersResult['success'] ? $totalUsersResult['data'][0]['count'] : 0;
    
    $totalFilesResult = $db->query("SELECT COUNT(*) as count FROM files WHERE file_type != 'folder'");
    $totalFiles = $totalFilesResult['success'] ? $totalFilesResult['data'][0]['count'] : 0;
    
    $totalStorageResult = $db->query("SELECT SUM(file_size) as total FROM files WHERE file_type != 'folder'");
    $totalStorage = $totalStorageResult['success'] ? ($totalStorageResult['data'][0]['total'] ?: 0) : 0;
    
    $todayUploadsResult = $db->query("SELECT COUNT(*) as count FROM files WHERE DATE(created_at) = CURDATE()");
    $todayUploads = $todayUploadsResult['success'] ? $todayUploadsResult['data'][0]['count'] : 0;
    
    $storageTrendResult = $db->query("SELECT DATE(created_at) as date, SUM(file_size) as size FROM files WHERE file_type != 'folder' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
    $storageTrend = $storageTrendResult['success'] ? $storageTrendResult['data'] : [];
    
    $userGrowthResult = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
    $userGrowth = $userGrowthResult['success'] ? $userGrowthResult['data'] : [];
    
    sendJsonResponse([
        'success' => true,
        'stats' => [
            'total_users' => $totalUsers,
            'total_files' => $totalFiles,
            'total_storage' => $totalStorage,
            'today_uploads' => $todayUploads
        ],
        'storage_trend' => $storageTrend,
        'user_growth' => $userGrowth
    ]);
}

if ($action === 'users') {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $result = $db->query("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);
    
    if ($result['success']) {
        $users = [];
        foreach ($result['data'] as $user) {
            $users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'storage_used' => $user['storage_used'],
                'storage_total' => $user['storage_total'],
                'membership_level' => $user['membership_level'],
                'status' => $user['status'],
                'last_login' => $user['last_login'],
                'created_at' => $user['created_at']
            ];
        }
        
        $countResult = $db->query("SELECT COUNT(*) as count FROM users $where", $params);
        $total = $countResult['success'] ? $countResult['data'][0]['count'] : 0;
        
        sendJsonResponse([
            'success' => true,
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取用户列表失败']);
    }
}

if ($action === 'user_update') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $storageTotal = (int)($_POST['storage_total'] ?? 0);
    $membershipLevel = $_POST['membership_level'] ?? '';
    
    if ($userId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    
    if (!empty($status)) {
        $result = $db->query("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
        if ($result['success']) {
            logAdminAction($_SESSION['user_id'], 'update_user_status', 'user', $userId, "更新用户状态: $status");
        }
    }
    
    if ($storageTotal > 0) {
        $result = $db->query("UPDATE users SET storage_total = ? WHERE id = ?", [$storageTotal, $userId]);
        if ($result['success']) {
            logAdminAction($_SESSION['user_id'], 'update_user_storage', 'user', $userId, "更新用户存储空间: $storageTotal");
        }
    }
    
    if (!empty($membershipLevel)) {
        $result = $db->query("UPDATE users SET membership_level = ? WHERE id = ?", [$membershipLevel, $userId]);
        if ($result['success']) {
            logAdminAction($_SESSION['user_id'], 'update_user_membership', 'user', $userId, "更新用户会员等级: $membershipLevel");
        }
    }
    
    sendJsonResponse(['success' => true, 'message' => '更新成功']);
}

if ($action === 'user_delete') {
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    if ($userId === $_SESSION['user_id']) {
        sendJsonResponse(['success' => false, 'message' => '不能删除自己']);
    }
    
    $db = getDB();
    
    $result = $db->query("DELETE FROM users WHERE id = ?", [$userId]);
    
    if ($result['success']) {
        logAdminAction($_SESSION['user_id'], 'delete_user', 'user', $userId, "删除用户 ID: $userId");
        sendJsonResponse(['success' => true, 'message' => '用户已删除']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '删除失败']);
    }
}

if ($action === 'settings') {
    $db = getDB();
    
    // 调试信息
    error_log('开始获取设置数据');
    
    $result = $db->query("SELECT * FROM system_settings ORDER BY setting_key");
    
    error_log('查询结果: ' . print_r($result, true));
    
    if ($result['success']) {
        $settings = [];
        foreach ($result['data'] as $setting) {
            $settings[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type'],
                'description' => $setting['description']
            ];
        }
        
        error_log('设置数据: ' . print_r($settings, true));
        
        // 默认设置值
        $defaultSettings = [
            'site_name' => [
                'value' => 'PureDrop网盘',
                'type' => 'string',
                'description' => '网站名称'
            ],
            'site_logo' => [
                'value' => '',
                'type' => 'string',
                'description' => '网站Logo'
            ],
            'site_url' => [
                'value' => '',
                'type' => 'string',
                'description' => '网站URL'
            ],
            'allow_register' => [
                'value' => '1',
                'type' => 'boolean',
                'description' => '是否允许注册'
            ],
            'default_storage' => [
                'value' => '1073741824',
                'type' => 'number',
                'description' => '默认存储空间(字节)'
            ],
            'max_login_attempts' => [
                'value' => '5',
                'type' => 'number',
                'description' => '最大登录尝试次数'
            ],
            'enable_captcha' => [
                'value' => '0',
                'type' => 'boolean',
                'description' => '是否启用验证码'
            ],
            'default_share_expiry' => [
                'value' => '7',
                'type' => 'number',
                'description' => '默认分享有效期(天)'
            ],
            'require_extract_code' => [
                'value' => '0',
                'type' => 'boolean',
                'description' => '是否强制提取码'
            ],
            'max_file_size' => [
                'value' => '2147483648',
                'type' => 'number',
                'description' => '最大文件大小(字节)'
            ],
            'smtp_host' => [
                'value' => '',
                'type' => 'string',
                'description' => 'SMTP服务器'
            ],
            'smtp_port' => [
                'value' => '465',
                'type' => 'number',
                'description' => 'SMTP端口'
            ],
            'smtp_username' => [
                'value' => '',
                'type' => 'string',
                'description' => 'SMTP用户名'
            ],
            'smtp_password' => [
                'value' => '',
                'type' => 'string',
                'description' => 'SMTP密码'
            ],
            'smtp_from_email' => [
                'value' => '',
                'type' => 'string',
                'description' => '发件人邮箱'
            ],
            'smtp_from_name' => [
                'value' => 'PureDrop网盘',
                'type' => 'string',
                'description' => '发件人名称'
            ],
            'smtp_encryption' => [
                'value' => 'ssl',
                'type' => 'string',
                'description' => 'SMTP加密方式'
            ],
            'enable_email_verification' => [
                'value' => '0',
                'type' => 'boolean',
                'description' => '是否启用邮箱验证'
            ],
            'verification_code_expiry' => [
                'value' => '10',
                'type' => 'number',
                'description' => '验证码有效期(分钟)'
            ]
        ];
        
        // 合并默认设置和数据库设置
        foreach ($defaultSettings as $key => $defaultValue) {
            if (!isset($settings[$key])) {
                $settings[$key] = $defaultValue;
            }
        }
        
        error_log('最终设置数据: ' . print_r($settings, true));
        
        sendJsonResponse(['success' => true, 'settings' => $settings]);
    } else {
        // 如果数据库查询失败，返回默认设置
        $defaultSettings = [
            'site_name' => [
                'value' => 'PureDrop网盘',
                'type' => 'string',
                'description' => '网站名称'
            ],
            'site_logo' => [
                'value' => '',
                'type' => 'string',
                'description' => '网站Logo'
            ],
            'site_url' => [
                'value' => '',
                'type' => 'string',
                'description' => '网站URL'
            ],
            'allow_register' => [
                'value' => '1',
                'type' => 'boolean',
                'description' => '是否允许注册'
            ],
            'default_storage' => [
                'value' => '1073741824',
                'type' => 'number',
                'description' => '默认存储空间(字节)'
            ],
            'max_login_attempts' => [
                'value' => '5',
                'type' => 'number',
                'description' => '最大登录尝试次数'
            ],
            'enable_captcha' => [
                'value' => '0',
                'type' => 'boolean',
                'description' => '是否启用验证码'
            ],
            'default_share_expiry' => [
                'value' => '7',
                'type' => 'number',
                'description' => '默认分享有效期(天)'
            ],
            'require_extract_code' => [
                'value' => '0',
                'type' => 'boolean',
                'description' => '是否强制提取码'
            ],
            'max_file_size' => [
                'value' => '2147483648',
                'type' => 'number',
                'description' => '最大文件大小(字节)'
            ]
        ];
        
        error_log('数据库查询失败，返回默认设置');
        sendJsonResponse(['success' => true, 'settings' => $defaultSettings]);
    }
}

function getSettingDescription($key) {
    $descriptions = [
        'site_name' => '网站名称',
        'site_logo' => '网站Logo',
        'site_url' => '网站URL',
        'allow_register' => '是否允许注册',
        'default_storage' => '默认存储空间(字节)',
        'max_login_attempts' => '最大登录尝试次数',
        'enable_captcha' => '是否启用验证码',
        'default_share_expiry' => '默认分享有效期(天)',
        'require_extract_code' => '是否强制提取码',
        'max_file_size' => '最大文件大小(字节)',
        'smtp_host' => 'SMTP服务器',
        'smtp_port' => 'SMTP端口',
        'smtp_username' => 'SMTP用户名',
        'smtp_password' => 'SMTP密码',
        'smtp_from_email' => '发件人邮箱',
        'smtp_from_name' => '发件人名称',
        'smtp_encryption' => 'SMTP加密方式',
        'enable_email_verification' => '是否启用邮箱验证',
        'verification_code_expiry' => '验证码有效期(分钟)'
    ];
    return $descriptions[$key] ?? '网站设置';
}

function getSettingType($key) {
    $types = [
        'site_name' => 'string',
        'site_logo' => 'string',
        'site_url' => 'string',
        'allow_register' => 'boolean',
        'default_storage' => 'number',
        'max_login_attempts' => 'number',
        'enable_captcha' => 'boolean',
        'default_share_expiry' => 'number',
        'require_extract_code' => 'boolean',
        'max_file_size' => 'number',
        'smtp_host' => 'string',
        'smtp_port' => 'number',
        'smtp_username' => 'string',
        'smtp_password' => 'string',
        'smtp_from_email' => 'string',
        'smtp_from_name' => 'string',
        'smtp_encryption' => 'string',
        'enable_email_verification' => 'boolean',
        'verification_code_expiry' => 'number'
    ];
    return $types[$key] ?? 'string';
}

if ($action === 'settings_update') {
    try {
        $db = getDB();
        
        // 处理Logo文件上传
        $siteLogo = '';
        if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $filePath)) {
                $siteLogo = 'uploads/' . $fileName;
            } else {
                throw new Exception('Logo上传失败');
            }
        } elseif (isset($_POST['site_logo'])) {
            $siteLogo = $_POST['site_logo'];
        }
        
        // 准备设置数据
        $settings = [
            'site_name' => $_POST['site_name'] ?? '',
            'site_logo' => $siteLogo,
            'site_url' => $_POST['site_url'] ?? '',
            'allow_register' => $_POST['allow_register'] ?? '1',
            'default_storage' => $_POST['default_storage'] ?? '5368709120',
            'max_login_attempts' => $_POST['max_login_attempts'] ?? '5',
            'enable_captcha' => $_POST['enable_captcha'] ?? '0',
            'default_share_expiry' => $_POST['default_share_expiry'] ?? '7',
            'require_extract_code' => $_POST['require_extract_code'] ?? '0',
            'max_file_size' => $_POST['max_file_size'] ?? '1073741824',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '465',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'ssl',
            'enable_email_verification' => $_POST['enable_email_verification'] ?? '0',
            'verification_code_expiry' => $_POST['verification_code_expiry'] ?? '10'
        ];
        
        // 保存设置到数据库
        foreach ($settings as $key => $value) {
            $settingType = getSettingType($key);
            $result = $db->query(
                "INSERT INTO system_settings (setting_key, setting_value, setting_type, description) 
                 VALUES (?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?",
                [$key, $value, $settingType, getSettingDescription($key), $value, $settingType]
            );
            
            if ($result['success']) {
                logAdminAction($_SESSION['user_id'], 'update_setting', 'setting', $key, "更新设置: $key = $value");
            }
        }
        
        sendJsonResponse(['success' => true, 'message' => '设置已保存']);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'storage_plans') {
    $db = getDB();
    $result = $db->query("SELECT * FROM storage_plans ORDER BY storage_size ASC");
    
    if ($result['success']) {
        sendJsonResponse(['success' => true, 'plans' => $result['data']]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取存储方案失败']);
    }
}

if ($action === 'storage_plan_create') {
    $name = trim($_POST['name'] ?? '');
    $storageSize = (int)($_POST['storage_size'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name) || $storageSize <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "INSERT INTO storage_plans (name, storage_size, price, description) VALUES (?, ?, ?, ?)",
        [$name, $storageSize, $price, $description]
    );
    
    if ($result['success']) {
        logAdminAction($_SESSION['user_id'], 'create_storage_plan', 'storage_plan', $result['insert_id'], "创建存储方案: $name");
        sendJsonResponse(['success' => true, 'message' => '存储方案创建成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '创建失败']);
    }
}

if ($action === 'storage_plan_delete') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    
    if ($planId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("UPDATE storage_plans SET is_active = 0 WHERE id = ?", [$planId]);
    
    if ($result['success']) {
        logAdminAction($_SESSION['user_id'], 'delete_storage_plan', 'storage_plan', $planId, "删除存储方案 ID: $planId");
        sendJsonResponse(['success' => true, 'message' => '存储方案已删除']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '删除失败']);
    }
}

if ($action === 'announcements') {
    $db = getDB();
    $result = $db->query("SELECT * FROM announcements ORDER BY created_at DESC");
    
    if ($result['success']) {
        sendJsonResponse(['success' => true, 'announcements' => $result['data']]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取公告失败']);
    }
}

if ($action === 'announcement_create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title) || empty($content)) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query(
        "INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)",
        [$title, $content, $_SESSION['user_id']]
    );
    
    if ($result['success']) {
        logAdminAction($_SESSION['user_id'], 'create_announcement', 'announcement', $result['insert_id'], "创建公告: $title");
        sendJsonResponse(['success' => true, 'message' => '公告发布成功']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '发布失败']);
    }
}

if ($action === 'announcement_delete') {
    $announcementId = (int)($_POST['announcement_id'] ?? 0);
    
    if ($announcementId <= 0) {
        sendJsonResponse(['success' => false, 'message' => '参数错误']);
    }
    
    $db = getDB();
    $result = $db->query("DELETE FROM announcements WHERE id = ?", [$announcementId]);
    
    if ($result['success']) {
        logAdminAction($_SESSION['user_id'], 'delete_announcement', 'announcement', $announcementId, "删除公告 ID: $announcementId");
        sendJsonResponse(['success' => true, 'message' => '公告已删除']);
    } else {
        sendJsonResponse(['success' => false, 'message' => '删除失败']);
    }
}

if ($action === 'logs') {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    $result = $db->query("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    
    if ($result['success']) {
        $logs = [];
        foreach ($result['data'] as $log) {
            $logs[] = [
                'id' => $log['id'],
                'user_id' => $log['user_id'],
                'action' => $log['action'],
                'details' => $log['details'],
                'ip_address' => $log['ip_address'],
                'created_at' => $log['created_at']
            ];
        }
        
        $countResult = $db->query("SELECT COUNT(*) as count FROM operation_logs");
        $total = $countResult['success'] ? $countResult['data'][0]['count'] : 0;
        
        sendJsonResponse([
            'success' => true,
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取日志失败']);
    }
}

if ($action === 'admin_logs') {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    $result = $db->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    
    if ($result['success']) {
        $logs = [];
        foreach ($result['data'] as $log) {
            $logs[] = [
                'id' => $log['id'],
                'admin_id' => $log['admin_id'],
                'action' => $log['action'],
                'target_type' => $log['target_type'],
                'target_id' => $log['target_id'],
                'details' => $log['details'],
                'ip_address' => $log['ip_address'],
                'created_at' => $log['created_at']
            ];
        }
        
        $countResult = $db->query("SELECT COUNT(*) as count FROM admin_logs");
        $total = $countResult['success'] ? $countResult['data'][0]['count'] : 0;
        
        sendJsonResponse([
            'success' => true,
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => '获取日志失败']);
    }
}

if ($action === 'delete_all_data') {
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm !== 'DELETE_ALL_DATA') {
        sendJsonResponse(['success' => false, 'message' => '确认码错误']);
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        $db->query("DELETE FROM admin_logs");
        $db->query("DELETE FROM operation_logs");
        $db->query("DELETE FROM recycle_bin");
        $db->query("DELETE FROM file_shares");
        $db->query("DELETE FROM upload_chunks");
        $db->query("DELETE FROM files");
        $db->query("DELETE FROM announcements");
        $db->query("DELETE FROM storage_plans");
        $db->query("DELETE FROM users WHERE id != ?", [$_SESSION['user_id']]);
        
        $db->commit();
        
        $uploadDir = UPLOAD_DIR;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            }
        }
        
        logAdminAction($_SESSION['user_id'], 'delete_all_data', null, null, '删除所有数据');
        
        sendJsonResponse(['success' => true, 'message' => '所有数据已删除']);
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
    }
}

if ($action === 'check_update') {
    $localVersion = trim(file_get_contents(__DIR__ . '/../version.txt'));
    
    $url = 'https://raw.githubusercontent.com/wangyemen/PureDrop-Netdisk-System/main/version.txt';
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);
    
    $latestVersion = @file_get_contents($url, false, $context);
    
    if ($latestVersion === false) {
        sendJsonResponse(['success' => false, 'message' => '无法获取最新版本信息']);
    }
    
    $latestVersion = trim($latestVersion);
    $hasUpdate = version_compare($latestVersion, $localVersion, '>');
    
    sendJsonResponse([
        'success' => true,
        'local_version' => $localVersion,
        'latest_version' => $latestVersion,
        'has_update' => $hasUpdate,
        'update_url' => 'https://pddisk.xo.je/'
    ]);
}

if ($action === 'test_email') {
    require_once __DIR__ . '/../core/mail.php';
    
    try {
        $mailer = new Mailer();
        $result = $mailer->testConnection();
        sendJsonResponse(['success' => true, 'message' => '邮件发送成功']);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

sendJsonResponse(['success' => false, 'message' => '未知操作']);
?>