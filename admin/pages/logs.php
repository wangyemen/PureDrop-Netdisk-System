<div class="data-table">
    <div class="data-table-header">
        <h2>操作日志</h2>
    </div>
    <div class="data-table-content">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户ID</th>
                    <th>操作</th>
                    <th>详情</th>
                    <th>IP地址</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody id="logTableBody">
                <tr><td colspan="6" style="text-align: center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="logPagination"></div>
</div>

<script>
let currentLogPage = 1;

loadLogs(1);

function loadLogs(page) {
    currentLogPage = page;
    
    fetch(`api.php?action=logs&page=${page}&limit=50`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderLogTable(data.logs);
                renderLogPagination(data.total, data.page, data.limit);
            }
        });
}

function renderLogTable(logs) {
    const tbody = document.getElementById('logTableBody');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">暂无日志</td></tr>';
        return;
    }
    
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${log.id}</td>
            <td>${log.user_id}</td>
            <td>${log.action}</td>
            <td>${log.details || '-'}</td>
            <td>${log.ip_address}</td>
            <td>${log.created_at}</td>
        </tr>
    `).join('');
}

function renderLogPagination(total, page, limit) {
    const totalPages = Math.ceil(total / limit);
    const pagination = document.getElementById('logPagination');
    
    let html = `<span class="pagination-info">共 ${total} 条，第 ${page} / ${totalPages} 页</span>`;
    
    if (page > 1) {
        html += `<button class="pagination-btn" onclick="loadLogs(${page - 1})">上一页</button>`;
    }
    
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
        html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadLogs(${i})">${i}</button>`;
    }
    
    if (page < totalPages) {
        html += `<button class="pagination-btn" onclick="loadLogs(${page + 1})">下一页</button>`;
    }
    
    pagination.innerHTML = html;
}
</script>