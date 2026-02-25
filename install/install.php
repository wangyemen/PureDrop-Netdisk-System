<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$message = '';
$messageType = '';

function checkRequirements() {
    $errors = [];
    
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = 'PHP版本必须 >= 7.4.0';
    }
    
    if (!extension_loaded('mysqli')) {
        $errors[] = '缺少mysqli扩展';
    }
    
    if (!extension_loaded('gd')) {
        $errors[] = '缺少gd扩展';
    }
    
    if (!extension_loaded('mbstring')) {
        $errors[] = '缺少mbstring扩展';
    }
    
    if (!is_writable(__DIR__ . '/../config')) {
        $errors[] = 'config目录不可写';
    }
    
    if (!is_writable(__DIR__ . '/../uploads')) {
        $errors[] = 'uploads目录不可写';
    }
    
    return $errors;
}

function testDatabaseConnection($host, $user, $pass, $port = 3306) {
    $conn = @new mysqli($host, $user, $pass, '', $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    $conn->close();
    return ['success' => true];
}

function createDatabase($host, $user, $pass, $dbname, $port = 3306) {
    $conn = new mysqli($host, $user, $pass, '', $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        $conn->close();
        return ['success' => false, 'error' => $conn->error];
    }
    
    $conn->close();
    return ['success' => true];
}

function checkExistingDatabase($host, $user, $pass, $dbname, $port = 3306) {
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    
    // 检查是否存在必要的表
    $requiredTables = [
        'users',
        'files',
        'file_shares',
        'upload_chunks',
        'operation_logs',
        'admin_logs',
        'announcements',
        'storage_plans',
        'recycle_bin'
    ];
    
    $existingTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $existingTables[] = $row[0];
    }
    
    $missingTables = array_diff($requiredTables, $existingTables);
    $extraTables = array_diff($existingTables, $requiredTables);
    
    // 检查现有表的结构是否正确
    $structureIssues = [];
    if (empty($missingTables)) {
        // 检查users表结构
        $result = $conn->query("DESCRIBE users");
        $usersColumns = [];
        while ($row = $result->fetch_assoc()) {
            $usersColumns[] = $row['Field'];
        }
        
        $requiredUsersColumns = ['id', 'username', 'email', 'password', 'nickname', 'avatar', 'storage_used', 'storage_total', 'membership_level', 'status', 'created_at', 'last_login'];
        $missingUsersColumns = array_diff($requiredUsersColumns, $usersColumns);
        if (!empty($missingUsersColumns)) {
            $structureIssues[] = 'users表缺少必要字段: ' . implode(', ', $missingUsersColumns);
        }
    }
    
    $conn->close();
    
    return [
        'success' => true,
        'has_tables' => !empty($existingTables),
        'missing_tables' => $missingTables,
        'extra_tables' => $extraTables,
        'structure_issues' => $structureIssues,
        'needs_reinstall' => !empty($missingTables) || !empty($structureIssues)
    ];
}

function importDatabase($host, $user, $pass, $dbname, $port = 3306) {
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    
    // 检查现有数据库结构
    $checkResult = checkExistingDatabase($host, $user, $pass, $dbname, $port);
    if ($checkResult['success'] && $checkResult['has_tables']) {
        if ($checkResult['needs_reinstall']) {
            // 删除现有表
            $tablesToDrop = [
                'recycle_bin',
                'file_shares',
                'upload_chunks',
                'operation_logs',
                'admin_logs',
                'announcements',
                'storage_plans',
                'files',
                'users'
            ];
            
            foreach ($tablesToDrop as $table) {
                $conn->query("DROP TABLE IF EXISTS `$table`");
            }
        } else {
            // 数据库结构正确，不需要重新安装
            $conn->close();
            return ['success' => true, 'message' => '数据库结构已存在且正确'];
        }
    }
    
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        $conn->close();
        return ['success' => false, 'error' => 'database.sql文件不存在'];
    }
    
    $sql = file_get_contents($sqlFile);
    
    $conn->multi_query($sql);
    
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->errno) {
        $conn->close();
        return ['success' => false, 'error' => $conn->error];
    }
    
    $conn->close();
    return ['success' => true];
}

function checkExistingAdmin($host, $user, $pass, $dbname, $port = 3306) {
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    
    // 检查users表是否存在
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows === 0) {
        $conn->close();
        return ['success' => true, 'has_admin' => false];
    }
    
    // 检查是否有管理员账户
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE membership_level = 'premium' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hasAdmin = $result->num_rows > 0;
    $adminInfo = null;
    
    if ($hasAdmin) {
        $adminInfo = $result->fetch_assoc();
    }
    
    $stmt->close();
    $conn->close();
    
    return [
        'success' => true,
        'has_admin' => $hasAdmin,
        'admin_info' => $adminInfo
    ];
}

function createAdmin($host, $user, $pass, $dbname, $adminUser, $adminPass, $adminEmail, $port = 3306) {
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        return ['success' => false, 'error' => $conn->connect_error];
    }
    
    $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, nickname, storage_total, membership_level, status) VALUES (?, ?, ?, ?, 10737418240, 'premium', 'active')");
    $stmt->bind_param("ssss", $adminUser, $adminEmail, $hashedPassword, $adminUser);
    
    if (!$stmt->execute()) {
        $conn->close();
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $adminId = $conn->insert_id;
    $stmt->close();
    
    $action = '管理员账户创建';
    $stmt = $conn->prepare("INSERT INTO operation_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $details = "创建管理员账户: $adminUser";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param("isss", $adminId, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    return ['success' => true];
}

function writeConfig($config) {
    $content = "<?php\n";
    $content .= "define('DB_HOST', '{$config['db_host']}');\n";
    $content .= "define('DB_USER', '{$config['db_user']}');\n";
    $content .= "define('DB_PASS', '{$config['db_pass']}');\n";
    $content .= "define('DB_NAME', '{$config['db_name']}');\n";
    $content .= "define('DB_PORT', {$config['db_port']});\n";
    $content .= "define('SITE_URL', '{$config['site_url']}');\n";
    $content .= "define('SITE_NAME', 'PureDrop网盘');\n";
    $content .= "define('UPLOAD_DIR', __DIR__ . '/../uploads/');\n";
    $content .= "define('MAX_FILE_SIZE', 2147483648);\n";
    $content .= "define('CHUNK_SIZE', 5242880);\n";
    $content .= "\$installed = true;\n";
    
    $configFile = __DIR__ . '/../config/config.php';
    
    // 先删除旧的配置文件
    if (file_exists($configFile)) {
        @unlink($configFile);
    }
    
    $result = file_put_contents($configFile, $content);
    
    if ($result === false) {
        return ['success' => false, 'error' => '无法写入配置文件'];
    }
    
    // 验证文件是否正确写入
    if (!file_exists($configFile)) {
        return ['success' => false, 'error' => '配置文件未创建成功'];
    }
    
    return ['success' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? 3306;
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        $dbname = $_POST['db_name'] ?? 'puredrop';
        
        $result = testDatabaseConnection($host, $user, $pass, $port);
        if (!$result['success']) {
            $message = '数据库连接失败: ' . $result['error'];
            $messageType = 'error';
        } else {
            $_SESSION['install'] = [
                'db_host' => $host,
                'db_port' => $port,
                'db_user' => $user,
                'db_pass' => $pass,
                'db_name' => $dbname
            ];
            header('Location: install.php?step=3');
            exit;
        }
    } elseif ($step === 3 && isset($_POST['action']) && $_POST['action'] === 'install_database') {
        $install = $_SESSION['install'];
        
        $response = [];
        
        $response['steps'] = [];
        
        // Step 1: 创建数据库
        $response['steps'][] = ['name' => '创建数据库', 'status' => 'pending'];
        $result = createDatabase($install['db_host'], $install['db_user'], $install['db_pass'], $install['db_name'], $install['db_port']);
        if (!$result['success']) {
            $response['success'] = false;
            $response['error'] = '创建数据库失败: ' . $result['error'];
            echo json_encode($response);
            exit;
        }
        $response['steps'][0]['status'] = 'completed';
        
        // Step 2: 检查现有数据库结构
        $response['steps'][] = ['name' => '检查数据库结构', 'status' => 'pending'];
        $checkResult = checkExistingDatabase($install['db_host'], $install['db_user'], $install['db_pass'], $install['db_name'], $install['db_port']);
        if (!$checkResult['success']) {
            $response['success'] = false;
            $response['error'] = '检查数据库结构失败: ' . $checkResult['error'];
            echo json_encode($response);
            exit;
        }
        
        if ($checkResult['has_tables']) {
            if ($checkResult['needs_reinstall']) {
                $response['steps'][] = ['name' => '删除旧表结构', 'status' => 'pending'];
                // 旧表将在importDatabase中删除
            }
        }
        $response['steps'][1]['status'] = 'completed';
        
        // Step 3: 导入数据库结构
        $response['steps'][] = ['name' => '导入数据库结构', 'status' => 'pending'];
        $result = importDatabase($install['db_host'], $install['db_user'], $install['db_pass'], $install['db_name'], $install['db_port']);
        if (!$result['success']) {
            $response['success'] = false;
            $response['error'] = '导入数据库失败: ' . $result['error'];
            echo json_encode($response);
            exit;
        }
        $response['steps'][2]['status'] = 'completed';
        
        // Step 4: 检查是否为更新
        if (isset($checkResult['has_tables']) && $checkResult['has_tables']) {
            if (isset($checkResult['needs_reinstall']) && $checkResult['needs_reinstall']) {
                $response['steps'][] = ['name' => '数据库已更新', 'status' => 'completed'];
            } else {
                $response['steps'][] = ['name' => '数据库结构正确', 'status' => 'completed'];
            }
        } else {
            $response['steps'][] = ['name' => '数据库安装完成', 'status' => 'completed'];
        }
        
        $response['success'] = true;
        echo json_encode($response);
        exit;
    } elseif ($step === 3 && isset($_GET['check_admin'])) {
        $install = $_SESSION['install'];
        
        $adminCheck = checkExistingAdmin($install['db_host'], $install['db_user'], $install['db_pass'], $install['db_name'], $install['db_port']);
        
        if (!$adminCheck['success']) {
            echo json_encode(['success' => false, 'error' => $adminCheck['error']]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'has_admin' => $adminCheck['has_admin'],
            'admin_info' => $adminCheck['admin_info']
        ]);
        exit;
    } elseif ($step === 4) {
        $install = $_SESSION['install'];
        $adminUser = $_POST['admin_user'] ?? '';
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';
        $adminEmail = $_POST['admin_email'] ?? '';
        $siteUrl = $_POST['site_url'] ?? '';
        
        if (empty($adminUser) || empty($adminPass) || empty($adminEmail)) {
            $message = '请填写完整的管理员信息';
            $messageType = 'error';
        } elseif ($adminPass !== $adminPass2) {
            $message = '两次密码输入不一致';
            $messageType = 'error';
        } elseif (strlen($adminPass) < 6) {
            $message = '密码长度不能少于6位';
            $messageType = 'error';
        } else {
            $result = createAdmin($install['db_host'], $install['db_user'], $install['db_pass'], $install['db_name'], $adminUser, $adminPass, $adminEmail, $install['db_port']);
            if (!$result['success']) {
                $message = '创建管理员失败: ' . $result['error'];
                $messageType = 'error';
            } else {
                $install['site_url'] = $siteUrl;
                $result = writeConfig($install);
                if (!$result['success']) {
                    $message = '写入配置文件失败: ' . $result['error'];
                    $messageType = 'error';
                } else {
                    unset($_SESSION['install']);
                    header('Location: install.php?step=5');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureDrop网盘 - 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 600px; padding: 40px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #667eea; font-size: 32px; font-weight: 700; }
        .logo p { color: #666; margin-top: 5px; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
        .steps::before { content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: #e0e0e0; z-index: 1; }
        .step { position: relative; z-index: 2; text-align: center; }
        .step-number { width: 32px; height: 32px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-weight: 600; transition: all 0.3s; }
        .step.active .step-number { background: #667eea; color: white; }
        .step.completed .step-number { background: #4caf50; color: white; }
        .step-label { font-size: 12px; color: #999; }
        .step.active .step-label { color: #667eea; font-weight: 600; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #e0e0e0; color: #666; }
        .btn-secondary:hover { background: #d0d0d0; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .message.error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .check-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .check-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .check-icon.success { background: #4caf50; color: white; }
        .check-icon.error { background: #f44336; color: white; }
        .check-text { flex: 1; color: #333; }
        .check-text.error { color: #f44336; }
        .success-icon { text-align: center; font-size: 80px; margin-bottom: 20px; }
        .success-title { text-align: center; font-size: 24px; color: #4caf50; margin-bottom: 15px; }
        .success-info { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success-info p { margin: 8px 0; color: #666; }
        .success-info strong { color: #333; }
        .progress-container { width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin-bottom: 10px; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 4px; width: 0%; transition: width 0.3s ease; }
        .progress-text { color: #666; font-size: 14px; margin-bottom: 10px; }
        .progress-steps { margin-top: 15px; }
        .progress-step { padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #666; }
        .progress-step.completed { color: #4caf50; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>📁 PureDrop网盘</h1>
            <p>安装向导</p>
        </div>
        
        <div class="steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">环境检查</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">数据库配置</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">安装数据库</div>
            </div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-label">管理员设置</div>
            </div>
            <div class="step <?php echo $step >= 5 ? 'active' : ''; ?> <?php echo $step > 5 ? 'completed' : ''; ?>">
                <div class="step-number">5</div>
                <div class="step-label">完成</div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
        <h2 style="margin-bottom: 20px; color: #333;">环境检查</h2>
        <?php
        $errors = checkRequirements();
        $checks = [
            ['name' => 'PHP版本 >= 7.4.0', 'pass' => version_compare(PHP_VERSION, '7.4.0', '>=')],
            ['name' => 'mysqli扩展', 'pass' => extension_loaded('mysqli')],
            ['name' => 'gd扩展', 'pass' => extension_loaded('gd')],
            ['name' => 'mbstring扩展', 'pass' => extension_loaded('mbstring')],
            ['name' => 'config目录可写', 'pass' => is_writable(__DIR__ . '/../config')],
            ['name' => 'uploads目录可写', 'pass' => is_writable(__DIR__ . '/../uploads')]
        ];
        foreach ($checks as $check):
        ?>
        <div class="check-item">
            <div class="check-icon <?php echo $check['pass'] ? 'success' : 'error'; ?>">
                <?php echo $check['pass'] ? '✓' : '✗'; ?>
            </div>
            <div class="check-text <?php echo $check['pass'] ? '' : 'error'; ?>"><?php echo $check['name']; ?></div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($errors)): ?>
        <div style="margin-top: 30px; text-align: center;">
            <a href="install.php?step=2" class="btn">下一步</a>
        </div>
        <?php else: ?>
        <div style="margin-top: 30px; text-align: center;">
            <p style="color: #f44336; margin-bottom: 15px;">请先解决以上问题后再继续</p>
            <a href="install.php?step=1" class="btn">重新检查</a>
        </div>
        <?php endif; ?>
        
        <?php elseif ($step === 2): ?>
        <h2 style="margin-bottom: 20px; color: #333;">数据库配置</h2>
        <form method="POST">
            <div class="form-group">
                <label>数据库主机</label>
                <input type="text" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库端口</label>
                <input type="number" name="db_port" value="3306" required>
            </div>
            <div class="form-group">
                <label>数据库用户名</label>
                <input type="text" name="db_user" required>
            </div>
            <div class="form-group">
                <label>数据库密码</label>
                <input type="password" name="db_pass">
            </div>
            <div class="form-group">
                <label>数据库名称</label>
                <input type="text" name="db_name" value="puredrop" required>
            </div>
            <div style="margin-top: 30px; text-align: center;">
                <a href="install.php?step=1" class="btn btn-secondary">上一步</a>
                <button type="submit" class="btn">下一步</button>
            </div>
        </form>
        
        <?php elseif ($step === 3): ?>
        <h2 style="margin-bottom: 20px; color: #333;">安装数据库</h2>
        <p style="color: #666; margin-bottom: 30px;">系统将自动创建数据库并导入表结构，请稍候...</p>
        
        <div style="margin-bottom: 30px;">
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="progress-text" id="progressText">准备开始...</div>
            <div class="progress-steps" id="progressSteps"></div>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <button id="startInstallBtn" class="btn">开始安装</button>
            <a href="install.php?step=4" id="nextStepBtn" class="btn" style="display: none;">下一步</a>
            <a href="install.php?step=2" class="btn btn-secondary" style="margin-left: 10px;">上一步</a>
        </div>
        
        <script>
        document.getElementById('startInstallBtn').addEventListener('click', function() {
            startInstall();
        });
        
        function startInstall() {
            const startBtn = document.getElementById('startInstallBtn');
            const nextBtn = document.getElementById('nextStepBtn');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressSteps = document.getElementById('progressSteps');
            
            startBtn.disabled = true;
            progressText.textContent = '正在安装...';
            
            fetch('install.php?step=3', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=install_database'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示成功消息
                    progressText.textContent = '安装完成！';
                    progressBar.style.width = '100%';
                    
                    // 显示步骤状态
                    progressSteps.innerHTML = data.steps.map(step => 
                        `<div class="progress-step completed">${step.name} ✓</div>`
                    ).join('');
                    
                    // 检查是否已有管理员账户
                    fetch('install.php?step=3&check_admin=1')
                    .then(response => response.json())
                    .then(adminData => {
                        if (adminData.success && adminData.has_admin) {
                            // 已有管理员，直接跳转到步骤5
                            progressText.textContent = '检测到已有管理员账户，跳过管理员设置';
                            setTimeout(() => {
                                window.location.href = 'install.php?step=5';
                            }, 1500);
                        } else {
                            // 没有管理员，显示下一步按钮
                            startBtn.style.display = 'none';
                            nextBtn.style.display = 'inline-block';
                        }
                    })
                    .catch(error => {
                        // 检查失败，显示下一步按钮
                        startBtn.style.display = 'none';
                        nextBtn.style.display = 'inline-block';
                    });
                } else {
                    // 显示错误消息
                    progressText.textContent = '安装失败：' + data.error;
                    progressText.style.color = '#f44336';
                    startBtn.disabled = false;
                }
            })
            .catch(error => {
                progressText.textContent = '安装失败：网络错误';
                progressText.style.color = '#f44336';
                startBtn.disabled = false;
            });
        }
        </script>
        
        <?php elseif ($step === 4): ?>
        <h2 style="margin-bottom: 20px; color: #333;">管理员设置</h2>
        <form method="POST">
            <div class="form-group">
                <label>管理员用户名</label>
                <input type="text" name="admin_user" required>
            </div>
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="admin_pass" required>
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="admin_pass2" required>
            </div>
            <div class="form-group">
                <label>管理员邮箱</label>
                <input type="email" name="admin_email" required>
            </div>
            <div class="form-group">
                <label>网站URL</label>
                <input type="url" name="site_url" placeholder="http://yourdomain.com" required>
            </div>
            <div style="margin-top: 30px; text-align: center;">
                <a href="install.php?step=3" class="btn btn-secondary">上一步</a>
                <button type="submit" class="btn">完成安装</button>
            </div>
        </form>
        
        <?php elseif ($step === 5): ?>
        <div class="success-icon">🎉</div>
        <div class="success-title">安装完成！</div>
        <div class="success-info">
            <p><strong>恭喜！</strong>PureDrop网盘已成功安装。</p>
            <p>请删除 install 目录以确保安全。</p>
            <p>您现在可以使用管理员账户登录系统。</p>
        </div>
        <div style="text-align: center;">
            <a href="../index.php" class="btn">进入首页</a>
            <a href="../admin/index.php" class="btn btn-secondary" style="margin-left: 10px;">进入管理后台</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>