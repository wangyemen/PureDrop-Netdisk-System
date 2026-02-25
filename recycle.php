<?php
session_start();
require_once __DIR__ . '/core/functions.php';



if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = '回收站';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回收站 - PureDrop网盘</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container" style="padding: 20px;">
        <div class="page-header" style="margin-bottom: 30px;">
            <h1 class="page-title" style="color: #333;">回收站</h1>
        </div>
        <div class="data-table" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="data-table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f5f5f5; border-bottom: 1px solid #e0e0e0;">
                <h2 style="color: #333; margin: 0;">回收站</h2>
                <button class="btn btn-danger" onclick="clearRecycleBin()">清空回收站</button>
            </div>
            <div class="data-table-content">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">文件名</th>
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">删除时间</th>
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">过期时间</th>
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #666; border-bottom: 1px solid #e0e0e0;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="recycleTableBody">
                        <tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">加载中...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
    loadRecycleBin();
    
    function loadRecycleBin() {
        fetch('api/files.php?action=recycle_list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderRecycleBin(data.items);
                }
            });
    }
    
    function renderRecycleBin(items) {
        const tbody = document.getElementById('recycleTableBody');
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">回收站为空</td></tr>';
            return;
        }
        
        tbody.innerHTML = items.map(item => `
            <tr style="border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='transparent'">
                <td style="padding: 12px 15px; color: #333;">${item.name}</td>
                <td style="padding: 12px 15px; color: #666;">${item.deleted_at}</td>
                <td style="padding: 12px 15px; color: #666;">${item.expire_at}</td>
                <td style="padding: 12px 15px;">
                    <button class="btn btn-sm" style="margin-right: 8px;" onclick="restoreFile(${item.file_id})">恢复</button>
                    <button class="btn btn-sm btn-danger" onclick="deletePermanently(${item.file_id})">永久删除</button>
                </td>
            </tr>
        `).join('');
    }
    
    function restoreFile(fileId) {
        if (!confirm('确定要恢复此文件吗？')) {
            return;
        }
        
        fetch('api/files.php?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `file_id=${fileId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('恢复成功', 'success');
                loadRecycleBin();
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    
    function deletePermanently(fileId) {
        if (!confirm('确定要永久删除此文件吗？此操作不可恢复！')) {
            return;
        }
        
        fetch('api/files.php?action=delete_permanent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `file_id=${fileId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('删除成功', 'success');
                loadRecycleBin();
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    
    function clearRecycleBin() {
        if (!confirm('确定要清空回收站吗？此操作不可恢复！')) {
            return;
        }
        
        fetch('api/files.php?action=clear_recycle_bin', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('回收站已清空', 'success');
                loadRecycleBin();
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>