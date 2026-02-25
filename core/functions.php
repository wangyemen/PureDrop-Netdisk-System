<?php
if (!file_exists(__DIR__ . '/../config/config.php')) {
    die('系统未安装，请先运行安装程序 <a href="install/install.php">点击安装</a>');
}

require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            DB_PORT
        );
        
        if ($this->connection->connect_error) {
            die('数据库连接失败: ' . $this->connection->connect_error);
        }
        
        $this->connection->set_charset('utf8mb4');
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        
        if ($stmt === false) {
            return ['success' => false, 'error' => $this->connection->error];
        }
        
        if (!empty($params)) {
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            
            array_unshift($values, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($values));
        }
        
        $result = $stmt->execute();
        
        if ($result === false) {
            return ['success' => false, 'error' => $stmt->error];
        }
        
        $data = $stmt->get_result();
        $rows = [];
        
        if ($data) {
            while ($row = $data->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $rows,
            'insert_id' => $this->connection->insert_id,
            'affected_rows' => $this->connection->affected_rows
        ];
    }
    
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

function getDB() {
    return Database::getInstance();
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function generateShareCode() {
    return md5(uniqid(mt_rand(), true) . time());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $result = $db->query("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }
    
    return null;
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['membership_level'] === 'premium';
}

function logOperation($userId, $action, $details = null) {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $db->query(
        "INSERT INTO operation_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
        [$userId, $action, $details, $ip, $userAgent]
    );
}

function logAdminAction($adminId, $action, $targetType = null, $targetId = null, $details = null) {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $db->query(
        "INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
        [$adminId, $action, $targetType, $targetId, $details, $ip]
    );
}

function getSetting($key, $default = null) {
    $db = getDB();
    $result = $db->query("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?", [$key]);
    
    if ($result['success'] && !empty($result['data'])) {
        $value = $result['data'][0]['setting_value'];
        $type = $result['data'][0]['setting_type'];
        
        switch ($type) {
            case 'number':
                return (int)$value;
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    return $default;
}

function setSetting($key, $value, $type = 'string', $description = null) {
    $db = getDB();
    
    if ($type === 'json') {
        $value = json_encode($value);
    }
    
    $result = $db->query(
        "INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?, description = ?",
        [$key, $value, $type, $description, $value, $type, $description]
    );
    
    return $result['success'];
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getMimeType($extension) {
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip'
    ];
    
    return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
}

function getFileType($extension) {
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $videoExts = ['mp4', 'webm', 'avi', 'mov', 'mkv'];
    $audioExts = ['mp3', 'wav', 'ogg', 'flac', 'aac'];
    $documentExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    
    $extension = strtolower($extension);
    
    if (in_array($extension, $imageExts)) {
        return 'image';
    } elseif (in_array($extension, $videoExts)) {
        return 'video';
    } elseif (in_array($extension, $audioExts)) {
        return 'audio';
    } elseif (in_array($extension, $documentExts)) {
        return 'document';
    }
    
    return 'other';
}

function createThumbnail($sourcePath, $destPath, $width = 200, $height = 200) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    switch ($mimeType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $newWidth = (int)($sourceWidth * $ratio);
    $newHeight = (int)($sourceHeight * $ratio);
    
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $result = imagejpeg($thumbnail, $destPath, 85);
    
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return $result;
}

function getFilePath($fileId) {
    $db = getDB();
    $result = $db->query("SELECT file_path FROM files WHERE id = ?", [$fileId]);
    
    if ($result['success'] && !empty($result['data'])) {
        return UPLOAD_DIR . $result['data'][0]['file_path'];
    }
    
    return null;
}

function getUserStorageUsed($userId) {
    $db = getDB();
    $result = $db->query("SELECT storage_used FROM users WHERE id = ?", [$userId]);
    
    if ($result['success'] && !empty($result['data'])) {
        return (int)$result['data'][0]['storage_used'];
    }
    
    return 0;
}

function updateUserStorage($userId, $delta) {
    $db = getDB();
    $result = $db->query(
        "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
        [$delta, $userId]
    );
    
    return $result['success'];
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

function isImage($extension) {
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    return in_array(strtolower($extension), $imageExts);
}

function isVideo($extension) {
    $videoExts = ['mp4', 'webm', 'avi', 'mov', 'mkv'];
    return in_array(strtolower($extension), $videoExts);
}

function isAudio($extension) {
    $audioExts = ['mp3', 'wav', 'ogg', 'flac', 'aac'];
    return in_array(strtolower($extension), $audioExts);
}

function isDocument($extension) {
    $documentExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    return in_array(strtolower($extension), $documentExts);
}

function getFileIcon($fileType, $extension = '') {
    $icons = [
        'folder' => '📁',
        'image' => '🖼️',
        'video' => '🎬',
        'audio' => '🎵',
        'document' => '📄',
        'pdf' => '📕',
        'zip' => '📦',
        'code' => '💻'
    ];
    
    if ($fileType === 'folder') {
        return $icons['folder'];
    }
    
    if ($fileType === 'image') {
        return $icons['image'];
    }
    
    if ($fileType === 'video') {
        return $icons['video'];
    }
    
    if ($fileType === 'audio') {
        return $icons['audio'];
    }
    
    if ($extension === 'pdf') {
        return $icons['pdf'];
    }
    
    if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return $icons['zip'];
    }
    
    if (in_array($extension, ['php', 'js', 'html', 'css', 'py', 'java', 'cpp'])) {
        return $icons['code'];
    }
    
    return $icons['document'];
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getAnnouncements() {
    $db = getDB();
    $result = $db->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
    
    if ($result['success']) {
        return $result['data'];
    }
    
    return [];
}

function getStoragePlans() {
    $db = getDB();
    $result = $db->query("SELECT * FROM storage_plans WHERE is_active = 1 ORDER BY storage_size ASC");
    
    if ($result['success']) {
        return $result['data'];
    }
    
    return [];
}

function getMembershipLevelName($level) {
    $levels = [
        'free' => '免费用户',
        'vip' => 'VIP会员',
        'premium' => '高级会员'
    ];
    
    return $levels[$level] ?? $level;
}
?>