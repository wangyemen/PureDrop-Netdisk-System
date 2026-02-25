<?php
require_once __DIR__ . '/core/functions.php';

$db = getDB();

$settingTypes = [
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

echo "开始更新设置类型...\n";

foreach ($settingTypes as $key => $type) {
    $result = $db->query(
        "UPDATE system_settings SET setting_type = ? WHERE setting_key = ?",
        [$type, $key]
    );
    
    if ($result['success']) {
        echo "✓ 更新 {$key} 类型为 {$type}\n";
    } else {
        echo "✗ 更新 {$key} 失败: " . $result['error'] . "\n";
    }
}

echo "\n设置类型更新完成！\n";

echo "\n当前设置值：\n";
$settings = ['enable_email_verification', 'smtp_host', 'smtp_port'];
foreach ($settings as $key) {
    $value = getSetting($key, 'default');
    $type = gettype($value);
    echo "{$key}: {$value} (type: {$type})\n";
}
?>