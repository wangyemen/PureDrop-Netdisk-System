<?php

class Mailer {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $encryption;
    
    public function __construct() {
        $this->smtpHost = getSetting('smtp_host', '');
        $this->smtpPort = getSetting('smtp_port', '465');
        $this->smtpUsername = getSetting('smtp_username', '');
        $this->smtpPassword = getSetting('smtp_password', '');
        $this->fromEmail = getSetting('smtp_from_email', '');
        $this->fromName = getSetting('smtp_from_name', 'PureDrop网盘');
        $this->encryption = getSetting('smtp_encryption', 'ssl');
    }
    
    public function send($to, $subject, $body, $isHtml = false) {
        if (empty($this->smtpHost) || empty($this->smtpUsername) || empty($this->fromEmail)) {
            throw new Exception('邮件配置不完整，请先在管理后台配置SMTP设置');
        }
        
        $boundary = md5(time());
        
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHtml) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode(strip_tags($body))) . "\r\n";
            
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($body)) . "\r\n";
            
            $message .= "--{$boundary}--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message = $body;
        }
        
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        if ($this->encryption === 'ssl') {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            ini_set('SMTP', $this->smtpHost);
            ini_set('smtp_port', $this->smtpPort);
            ini_set('sendmail_from', $this->fromEmail);
            
            $result = mail($to, $subject, $message, $headers);
        } elseif ($this->encryption === 'tls') {
            ini_set('SMTP', $this->smtpHost);
            ini_set('smtp_port', $this->smtpPort);
            ini_set('sendmail_from', $this->fromEmail);
            
            $result = mail($to, $subject, $message, $headers);
        } else {
            $result = mail($to, $subject, $message, $headers);
        }
        
        if (!$result) {
            throw new Exception('邮件发送失败，请检查SMTP配置');
        }
        
        return true;
    }
    
    public function sendVerificationCode($email, $code) {
        $subject = '邮箱验证码 - ' . $this->fromName;
        $body = $this->getVerificationTemplate($code);
        return $this->send($email, $subject, $body, true);
    }
    
    private function getVerificationTemplate($code) {
        $siteName = getSetting('site_name', 'PureDrop网盘');
        $expiry = getSetting('verification_code_expiry', 10);
        
        $template = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>邮箱验证码</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .code { background: #667eea; color: white; font-size: 32px; font-weight: bold; padding: 15px 30px; text-align: center; border-radius: 5px; margin: 20px 0; letter-spacing: 5px; }
        .footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 {$siteName}</h1>
            <p>邮箱验证码</p>
        </div>
        <div class="content">
            <p>您好！</p>
            <p>您正在注册 {$siteName} 账户，请使用以下验证码完成邮箱验证：</p>
            <div class="code">{$code}</div>
            <p><strong>验证码有效期：{$expiry} 分钟</strong></p>
            <div class="warning">
                <p>⚠️ 安全提示：</p>
                <ul>
                    <li>请勿将验证码告知他人</li>
                    <li>验证码将在 {$expiry} 分钟后失效</li>
                    <li>如果您没有进行此操作，请忽略此邮件</li>
                </ul>
            </div>
            <p>如有疑问，请联系客服。</p>
        </div>
        <div class="footer">
            <p>此邮件由系统自动发送，请勿直接回复</p>
            <p>© {$siteName} - 保留所有权利</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $template;
    }
    
    public function testConnection() {
        try {
            $testEmail = $this->fromEmail;
            $subject = 'SMTP连接测试';
            $body = '这是一封测试邮件，用于验证SMTP配置是否正确。';
            return $this->send($testEmail, $subject, $body);
        } catch (Exception $e) {
            throw $e;
        }
    }
}

function sendVerificationEmail($email, $code) {
    $mailer = new Mailer();
    return $mailer->sendVerificationCode($email, $code);
}

function generateVerificationCode() {
    return sprintf('%06d', mt_rand(0, 999999));
}

function saveVerificationCode($email, $code) {
    $db = getDB();
    $expiry = getSetting('verification_code_expiry', 10);
    $expiryTime = date('Y-m-d H:i:s', strtotime("+{$expiry} minutes"));
    
    $result = $db->query(
        "INSERT INTO verification_codes (email, code, expiry_time) VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE code = ?, expiry_time = ?",
        [$email, $code, $expiryTime, $code, $expiryTime]
    );
    
    return $result['success'];
}

function verifyCode($email, $code) {
    $db = getDB();
    $result = $db->query(
        "SELECT * FROM verification_codes WHERE email = ? AND code = ? AND expiry_time > NOW() AND is_used = 0",
        [$email, $code]
    );
    
    if ($result['success'] && !empty($result['data'])) {
        $record = $result['data'][0];
        
        $db->query(
            "UPDATE verification_codes SET is_used = 1 WHERE id = ?",
            [$record['id']]
        );
        
        return true;
    }
    
    return false;
}
?>