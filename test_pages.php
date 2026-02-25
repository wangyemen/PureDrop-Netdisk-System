<?php
// 显示所有错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 测试profile.php的核心功能
echo "<h1>测试 profile.php</h1>";
try {
    session_start();
    require_once __DIR__ . '/core/functions.php';
    
    echo "<p><strong>✓</strong> 核心函数加载成功</p>";
    
    if (!isLoggedIn()) {
        echo "<p><strong>⚠</strong> 用户未登录</p>";
    } else {
        $user = getCurrentUser();
        if ($user) {
            echo "<p><strong>✓</strong> 获取用户信息成功</p>";
            echo "<p><strong>用户名:</strong> " . htmlspecialchars($user['username']) . "</p>";
        } else {
            echo "<p><strong>✗</strong> 获取用户信息失败</p>";
        }
    }
    
    // 测试函数
    echo "<h2>测试函数</h2>";
    
    // 测试 formatSize
    if (function_exists('formatSize')) {
        echo "<p><strong>✓</strong> formatSize 函数存在</p>";
        echo "<p><strong>测试结果:</strong> " . formatSize(1024) . "</p>";
    } else {
        echo "<p><strong>✗</strong> formatSize 函数不存在</p>";
    }
    
    // 测试 getMembershipLevelName
    if (function_exists('getMembershipLevelName')) {
        echo "<p><strong>✓</strong> getMembershipLevelName 函数存在</p>";
        echo "<p><strong>测试结果:</strong> " . getMembershipLevelName('free') . "</p>";
    } else {
        echo "<p><strong>✗</strong> getMembershipLevelName 函数不存在</p>";
    }
    
    // 测试 getStoragePlans
    if (function_exists('getStoragePlans')) {
        echo "<p><strong>✓</strong> getStoragePlans 函数存在</p>";
        $plans = getStoragePlans();
        echo "<p><strong>测试结果:</strong> 找到 " . count($plans) . " 个存储方案</p>";
    } else {
        echo "<p><strong>✗</strong> getStoragePlans 函数不存在</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>✗</strong> 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>错误行号:</strong> " . $e->getLine() . "</p>";
}

echo "<hr>";

// 测试share.php的核心功能
echo "<h1>测试 share.php</h1>";
try {
    session_start();
    require_once __DIR__ . '/core/functions.php';
    
    echo "<p><strong>✓</strong> 核心函数加载成功</p>";
    
    if (!isLoggedIn()) {
        echo "<p><strong>⚠</strong> 用户未登录</p>";
    } else {
        $user = getCurrentUser();
        if ($user) {
            echo "<p><strong>✓</strong> 获取用户信息成功</p>";
            echo "<p><strong>用户名:</strong> " . htmlspecialchars($user['username']) . "</p>";
        } else {
            echo "<p><strong>✗</strong> 获取用户信息失败</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p><strong>✗</strong> 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>错误行号:</strong> " . $e->getLine() . "</p>";
}

echo "<hr>";

// 测试recycle.php的核心功能
echo "<h1>测试 recycle.php</h1>";
try {
    session_start();
    require_once __DIR__ . '/core/functions.php';
    
    echo "<p><strong>✓</strong> 核心函数加载成功</p>";
    
    if (!isLoggedIn()) {
        echo "<p><strong>⚠</strong> 用户未登录</p>";
    } else {
        $user = getCurrentUser();
        if ($user) {
            echo "<p><strong>✓</strong> 获取用户信息成功</p>";
            echo "<p><strong>用户名:</strong> " . htmlspecialchars($user['username']) . "</p>";
        } else {
            echo "<p><strong>✗</strong> 获取用户信息失败</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p><strong>✗</strong> 错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>错误行号:</strong> " . $e->getLine() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h1 {
    color: #333;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 10px;
}
h2 {
    color: #666;
    margin-top: 20px;
}
p {
    background-color: white;
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
strong {
    color: #333;
}
hr {
    margin: 30px 0;
    border: 1px solid #e0e0e0;
}
</style>