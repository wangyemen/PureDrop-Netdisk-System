<div class="danger-zone">
    <h3>⚠️ 危险操作</h3>
    <p>以下操作将永久删除所有数据，包括用户、文件、日志等。此操作不可恢复，请谨慎操作！</p>
    
    <div class="form-group">
        <label>确认码</label>
        <input type="text" id="confirmCode" placeholder="请输入 DELETE_ALL_DATA 确认删除">
    </div>
    
    <button class="btn btn-danger" onclick="deleteAllData()">删除所有数据</button>
</div>

<script>
function deleteAllData() {
    const confirmCode = document.getElementById('confirmCode').value;
    
    if (confirmCode !== 'DELETE_ALL_DATA') {
        showToast('确认码错误', 'error');
        return;
    }
    
    if (!confirm('警告：此操作将删除所有数据，包括用户、文件、日志等！\n\n此操作不可恢复，确定要继续吗？')) {
        return;
    }
    
    if (!confirm('最后确认：真的要删除所有数据吗？')) {
        return;
    }
    
    fetch('api.php?action=delete_all_data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `confirm=${encodeURIComponent(confirmCode)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('所有数据已删除', 'success');
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>