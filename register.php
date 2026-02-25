<?php
session_start();
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/mail.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$allowRegister = getSetting('allow_register', true);
$enableEmailVerification = getSetting('enable_email_verification', false);

if (!$allowRegister) {
    $error = '注册功能已关闭';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowRegister) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $verificationCode = $_POST['verification_code'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = '请填写完整信息';
    } elseif (!validateUsername($username)) {
        $error = '用户名只能包含字母、数字和下划线，长度3-20位';
    } elseif (!validateEmail($email)) {
        $error = '邮箱格式不正确';
    } elseif (!validatePassword($password)) {
        $error = '密码长度不能少于6位';
    } elseif ($password !== $password2) {
        $error = '两次密码输入不一致';
    } elseif ($enableEmailVerification && empty($verificationCode)) {
        $error = '请输入邮箱验证码';
    } elseif ($enableEmailVerification && !verifyCode($email, $verificationCode)) {
        $error = '验证码错误或已过期';
    } else {
        $db = getDB();
        
        $result = $db->query("SELECT id FROM users WHERE username = ?", [$username]);
        if ($result['success'] && !empty($result['data'])) {
            $error = '用户名已存在';
        } else {
            $result = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($result['success'] && !empty($result['data'])) {
                $error = '邮箱已被注册';
            } else {
                $hashedPassword = hashPassword($password);
                $defaultStorage = getSetting('default_storage', 1073741824);
                
                $result = $db->query(
                    "INSERT INTO users (username, email, password, nickname, storage_total, membership_level, status) VALUES (?, ?, ?, ?, ?, 'free', 'active')",
                    [$username, $email, $hashedPassword, $username, $defaultStorage]
                );
                
                if ($result['success']) {
                    $userId = $result['insert_id'];
                    logOperation($userId, 'register', '用户注册');
                    
                    $success = '注册成功！正在跳转到登录页面...';
                    echo '<script>setTimeout(function(){window.location.href="login.php";}, 2000);</script>';
                } else {
                    $error = '注册失败，请稍后重试';
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
    <title>注册 - <?php echo getSetting('site_name', 'PureDrop网盘'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .register-container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 400px; padding: 40px; }
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
        .btn-secondary { background: #e0e0e0; color: #666; margin-top: 10px; }
        .btn-secondary:hover { background: #d0d0d0; }
    </style>
</head>
<body>
    <div class="register-container">
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
            <p>创建新账户</p>
        </div>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($allowRegister): ?>
        <form method="POST">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required autofocus placeholder="3-20位字母、数字或下划线">
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" id="email" required placeholder="请输入您的邮箱">
            </div>
            <?php if ($enableEmailVerification): ?>
            <div class="form-group">
                <label>邮箱验证码</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="verification_code" id="verification_code" required placeholder="请输入验证码" style="flex: 1;">
                    <button type="button" id="sendCodeBtn" class="btn" style="width: auto; padding: 12px 20px;">发送验证码</button>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="至少6位">
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password2" required placeholder="再次输入密码">
            </div>
            <button type="submit" class="btn">注册</button>
        </form>
        <?php endif; ?>
        
        <div class="links">
            <p>已有账户？<a href="login.php">立即登录</a></p>
        </div>
    </div>
    
    <?php if ($enableEmailVerification): ?>
    <script>
    let countdown = 0;
    let timer = null;
    
    document.getElementById('sendCodeBtn').addEventListener('click', function() {
        const email = document.getElementById('email').value;
        
        if (!email) {
            alert('请先输入邮箱地址');
            return;
        }
        
        if (!validateEmail(email)) {
            alert('邮箱格式不正确');
            return;
        }
        
        if (countdown > 0) {
            return;
        }
        
        const btn = this;
        btn.disabled = true;
        btn.textContent = '发送中...';
        
        fetch('api/send_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('验证码已发送到您的邮箱，请查收');
                startCountdown(btn);
            } else {
                alert(data.message || '发送失败，请稍后重试');
                btn.disabled = false;
                btn.textContent = '发送验证码';
            }
        })
        .catch(error => {
            console.error('发送验证码时发生错误:', error);
            alert('发送失败，请稍后重试');
            btn.disabled = false;
            btn.textContent = '发送验证码';
        });
    });
    
    function startCountdown(btn) {
        countdown = 60;
        btn.textContent = countdown + '秒后重试';
        
        timer = setInterval(function() {
            countdown--;
            if (countdown <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                btn.textContent = '发送验证码';
            } else {
                btn.textContent = countdown + '秒后重试';
            }
        }, 1000);
    }
    
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    </script>
    <?php endif; ?>
</body>
</html>