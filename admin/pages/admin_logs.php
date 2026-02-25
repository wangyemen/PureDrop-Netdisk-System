<div class="data-table">
    <div class="data-table-header">
        <h2>管理员日志</h2>
    </div>
    <div class="data-table-content">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>管理员ID</th>
                    <th>操作</th>
                    <th>目标类型</th>
                    <th>目标ID</th>
                    <th>详情</th>
                    <th>IP地址</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody id="adminLogTableBody">
                <tr><td colspan="8" style="text-align: center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="adminLogPagination"></div>
</div>

<script>
let currentAdminLogPage = 1;

loadAdminLogs(1);

function loadAdminLogs(page) {
    currentAdminLogPage = page;
    
    fetch(`api.php?action=admin_logs&page=${page}&limit=50`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAdminLogTable(data.logs);
                renderAdminLogPagination(data.total, data.page, data.limit);
            }
        });
}

function renderAdminLogTable(logs) {
    const tbody = document.getElementById('adminLogTableBody');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">暂无日志</td></tr>';
        return;
    }
    
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${log.id}</td>
            <td>${log.admin_id}</td>
            <td>${log.action}</td>
            <td>${log.target_type || '-'}</td>
            <td>${log.target_id || '-'}</td>
            <td>${log.details || '-'}</td>
            <td>${log.ip_address}</td>
            <td>${log.created_at}</td>
        </tr>
    `).join('');
}

function renderAdminLogPagination(total, page, limit) {
    const totalPages = Math.ceil(total / limit);
    const pagination = document.getElementById('adminLogPagination');
    
    let html = `<span class="pagination-info">共 ${total} 条，第 ${page} / ${totalPages} 页</span>`;
    
    if (page > 1) {
        html += `<button class="pagination-btn" onclick="loadAdminLogs(${page - 1})">上一页</button>`;
    }
    
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
        html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadAdminLogs(${i})">${i}</button>`;
    }
    
    if (page < totalPages) {
        html += `<button class="pagination-btn" onclick="loadAdminLogs(${page + 1})">下一页</button>`;
    }
    
    pagination.innerHTML = html;
}
</script>