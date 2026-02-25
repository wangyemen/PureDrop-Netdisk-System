<div class="data-table">
    <div class="data-table-header">
        <h2>存储方案</h2>
        <button class="btn" onclick="showCreatePlanModal()">+ 新建方案</button>
    </div>
    <div class="data-table-content">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>方案名称</th>
                    <th>存储空间</th>
                    <th>价格</th>
                    <th>描述</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="planTableBody">
                <tr><td colspan="7" style="text-align: center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="planModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">创建存储方案</h3>
            <span class="modal-close" onclick="closePlanModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>方案名称</label>
                <input type="text" id="planName" placeholder="例如：VIP 100GB">
            </div>
            <div class="form-group">
                <label>存储空间（字节）</label>
                <input type="number" id="planStorageSize" placeholder="例如：107374182400">
            </div>
            <div class="form-group">
                <label>价格（元）</label>
                <input type="number" id="planPrice" step="0.01" placeholder="例如：29.99">
            </div>
            <div class="form-group">
                <label>描述</label>
                <textarea id="planDescription" placeholder="方案描述..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePlanModal()">取消</button>
            <button class="btn" onclick="createPlan()">创建</button>
        </div>
    </div>
</div>

<script>
loadStoragePlans();

function loadStoragePlans() {
    fetch('api.php?action=storage_plans')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPlanTable(data.plans);
            }
        });
}

function renderPlanTable(plans) {
    const tbody = document.getElementById('planTableBody');
    
    if (plans.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">暂无存储方案</td></tr>';
        return;
    }
    
    tbody.innerHTML = plans.map(plan => `
        <tr>
            <td>${plan.id}</td>
            <td>${plan.name}</td>
            <td>${formatSize(plan.storage_size)}</td>
            <td>${plan.price > 0 ? '¥' + plan.price : '免费'}</td>
            <td>${plan.description || '-'}</td>
            <td>${plan.is_active ? '启用' : '禁用'}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deletePlan(${plan.id})">删除</button>
            </td>
        </tr>
    `).join('');
}

function showCreatePlanModal() {
    document.getElementById('planModal').classList.add('show');
}

function closePlanModal() {
    document.getElementById('planModal').classList.remove('show');
    document.getElementById('planName').value = '';
    document.getElementById('planStorageSize').value = '';
    document.getElementById('planPrice').value = '';
    document.getElementById('planDescription').value = '';
}

function createPlan() {
    const name = document.getElementById('planName').value;
    const storageSize = document.getElementById('planStorageSize').value;
    const price = document.getElementById('planPrice').value;
    const description = document.getElementById('planDescription').value;
    
    if (!name || !storageSize) {
        showToast('请填写完整信息', 'error');
        return;
    }
    
    fetch('api.php?action=storage_plan_create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}&storage_size=${storageSize}&price=${price}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('创建成功', 'success');
            closePlanModal();
            loadStoragePlans();
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deletePlan(planId) {
    if (!confirm('确定要删除此存储方案吗？')) {
        return;
    }
    
    fetch('api.php?action=storage_plan_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `plan_id=${planId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('删除成功', 'success');
            loadStoragePlans();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>