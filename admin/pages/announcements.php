<div class="data-table">
    <div class="data-table-header">
        <h2>公告列表</h2>
        <button class="btn" onclick="showCreateAnnouncementModal()">+ 发布公告</button>
    </div>
    <div class="data-table-content">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>标题</th>
                    <th>内容</th>
                    <th>状态</th>
                    <th>发布时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="announcementTableBody">
                <tr><td colspan="6" style="text-align: center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="announcementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">发布公告</h3>
            <span class="modal-close" onclick="closeAnnouncementModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>标题</label>
                <input type="text" id="announcementTitle" placeholder="公告标题">
            </div>
            <div class="form-group">
                <label>内容</label>
                <textarea id="announcementContent" placeholder="公告内容..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAnnouncementModal()">取消</button>
            <button class="btn" onclick="createAnnouncement()">发布</button>
        </div>
    </div>
</div>

<script>
loadAnnouncements();

function loadAnnouncements() {
    fetch('api.php?action=announcements')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAnnouncementTable(data.announcements);
            }
        });
}

function renderAnnouncementTable(announcements) {
    const tbody = document.getElementById('announcementTableBody');
    
    if (announcements.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">暂无公告</td></tr>';
        return;
    }
    
    tbody.innerHTML = announcements.map(announcement => `
        <tr>
            <td>${announcement.id}</td>
            <td>${announcement.title}</td>
            <td>${announcement.content.substring(0, 50)}${announcement.content.length > 50 ? '...' : ''}</td>
            <td>${announcement.is_active ? '启用' : '禁用'}</td>
            <td>${announcement.created_at}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(${announcement.id})">删除</button>
            </td>
        </tr>
    `).join('');
}

function showCreateAnnouncementModal() {
    document.getElementById('announcementModal').classList.add('show');
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').classList.remove('show');
    document.getElementById('announcementTitle').value = '';
    document.getElementById('announcementContent').value = '';
}

function createAnnouncement() {
    const title = document.getElementById('announcementTitle').value;
    const content = document.getElementById('announcementContent').value;
    
    if (!title || !content) {
        showToast('请填写完整信息', 'error');
        return;
    }
    
    fetch('api.php?action=announcement_create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `title=${encodeURIComponent(title)}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('发布成功', 'success');
            closeAnnouncementModal();
            loadAnnouncements();
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deleteAnnouncement(announcementId) {
    if (!confirm('确定要删除此公告吗？')) {
        return;
    }
    
    fetch('api.php?action=announcement_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `announcement_id=${announcementId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('删除成功', 'success');
            loadAnnouncements();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>