<div class="data-table">
    <div class="data-table-header">
        <h2>用户列表</h2>
        <div>
            <input type="text" id="userSearch" placeholder="搜索用户名或邮箱..." style="padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; width: 250px;">
            <button class="btn" onclick="searchUsers()">搜索</button>
        </div>
    </div>
    <div class="data-table-content">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>存储使用</th>
                    <th>会员等级</th>
                    <th>状态</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <tr><td colspan="8" style="text-align: center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="userPagination"></div>
</div>

<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">编辑用户</h3>
            <span class="modal-close" onclick="closeUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editUserId">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" id="editUsername" disabled>
            </div>
            <div class="form-group">
                <label>状态</label>
                <select id="editStatus">
                    <option value="active">正常</option>
                    <option value="disabled">禁用</option>
                </select>
            </div>
            <div class="form-group">
                <label>会员等级</label>
                <select id="editMembershipLevel">
                    <option value="free">免费用户</option>
                    <option value="vip">VIP会员</option>
                    <option value="premium">高级会员</option>
                </select>
            </div>
            <div class="form-group">
                <label>存储空间（字节）</label>
                <input type="number" id="editStorageTotal">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeUserModal()">取消</button>
            <button class="btn" onclick="saveUser()">保存</button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentSearch = '';

loadUsers(1);

function loadUsers(page, search = '') {
    currentPage = page;
    currentSearch = search;
    
    let url = `api.php?action=users&page=${page}&limit=20`;
    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderUserTable(data.users);
                renderPagination(data.total, data.page, data.limit);
            }
        });
}

function searchUsers() {
    const search = document.getElementById('userSearch').value;
    loadUsers(1, search);
}

function renderUserTable(users) {
    const tbody = document.getElementById('userTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">暂无数据</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td>${formatSize(user.storage_used)} / ${formatSize(user.storage_total)}</td>
            <td><span class="membership-badge membership-${user.membership_level}">${getMembershipName(user.membership_level)}</span></td>
            <td><span class="status-badge status-${user.status}">${user.status === 'active' ? '正常' : '禁用'}</span></td>
            <td>${user.created_at}</td>
            <td>
                <button class="btn btn-sm" onclick="editUser(${user.id})">编辑</button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">删除</button>
            </td>
        </tr>
    `).join('');
}

function renderPagination(total, page, limit) {
    const totalPages = Math.ceil(total / limit);
    const pagination = document.getElementById('userPagination');
    
    let html = `<span class="pagination-info">共 ${total} 条，第 ${page} / ${totalPages} 页</span>`;
    
    if (page > 1) {
        html += `<button class="pagination-btn" onclick="loadUsers(${page - 1}, '${currentSearch}')">上一页</button>`;
    }
    
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
        html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadUsers(${i}, '${currentSearch}')">${i}</button>`;
    }
    
    if (page < totalPages) {
        html += `<button class="pagination-btn" onclick="loadUsers(${page + 1}, '${currentSearch}')">下一页</button>`;
    }
    
    pagination.innerHTML = html;
}

function editUser(userId) {
    fetch(`api.php?action=users&page=1&limit=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.users.find(u => u.id === userId);
                if (user) {
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('editUsername').value = user.username;
                    document.getElementById('editStatus').value = user.status;
                    document.getElementById('editMembershipLevel').value = user.membership_level;
                    document.getElementById('editStorageTotal').value = user.storage_total;
                    document.getElementById('userModal').classList.add('show');
                }
            }
        });
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('show');
}

function saveUser() {
    const userId = document.getElementById('editUserId').value;
    const status = document.getElementById('editStatus').value;
    const storageTotal = document.getElementById('editStorageTotal').value;
    const membershipLevel = document.getElementById('editMembershipLevel').value;
    
    fetch('api.php?action=user_update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}&status=${status}&storage_total=${storageTotal}&membership_level=${membershipLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('保存成功', 'success');
            closeUserModal();
            loadUsers(currentPage, currentSearch);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deleteUser(userId) {
    if (!confirm('确定要删除此用户吗？此操作不可恢复！')) {
        return;
    }
    
    fetch('api.php?action=user_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('删除成功', 'success');
            loadUsers(currentPage, currentSearch);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function getMembershipName(level) {
    const names = { free: '免费', vip: 'VIP', premium: '高级' };
    return names[level] || level;
}
</script>