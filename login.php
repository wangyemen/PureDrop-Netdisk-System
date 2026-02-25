<?php
session_start();
require_once __DIR__ . '/core/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $db = getDB();
        $result = $db->query("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        
        if ($result['success'] && !empty($result['data'])) {
            $user = $result['data'][0];
            
            if ($user['status'] === 'disabled') {
                $error = '账户已被禁用，请联系管理员';
            } elseif (verifyPassword($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                logOperation($user['id'], 'login', '用户登录');
                
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $db->query("UPDATE users SET remember_token = ? WHERE id = ?", [$token, $user['id']]);
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                }
                
                header('Location: index.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 400px; padding: 40px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #667eea; font-size: 32px; font-weight: 700; }
        .logo p { color: #666; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn { display: block; width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; text-align: center; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .message.error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #667eea; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .checkbox { display: flex; align-items: center; margin-bottom: 20px; }
        .checkbox input { margin-right: 8px; }
        .checkbox label { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>
                <?php 
                $siteLogo = getSetting('site_logo', '');
                if ($siteLogo): 
                ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="Logo" style="height: 40px; vertical-align: middle; margin-right: 8px;">
                <?php else: ?>
                    📁
                <?php endif; ?>
                <?php echo getSetting('site_name', 'PureDrop网盘'); ?>
            </h1>
            <p>登录您的账户</p>
        </div>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>用户名/邮箱</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <div class="checkbox">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">记住我</label>
            </div>
            <button type="submit" class="btn">登录</button>
        </form>
        
        <div class="links">
            <p>还没有账户？<a href="register.php">立即注册</a></p>
        </div>
    </div>
</body>
</html>