<div class="stats-grid" id="statsGrid">
    <div class="stat-card">
        <div class="stat-label">总用户数</div>
        <div class="stat-value" id="totalUsers">-</div>
        <div class="stat-change">注册用户</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">总文件数</div>
        <div class="stat-value" id="totalFiles">-</div>
        <div class="stat-change">所有文件</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">总存储空间</div>
        <div class="stat-value" id="totalStorage">-</div>
        <div class="stat-change">已使用</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">今日上传</div>
        <div class="stat-value" id="todayUploads">-</div>
        <div class="stat-change">今日新增</div>
    </div>
</div>

<div class="data-table">
    <div class="data-table-header">
        <h2>存储趋势（近30天）</h2>
    </div>
    <div class="data-table-content">
        <canvas id="storageChart" style="width: 100%; height: 300px;"></canvas>
    </div>
</div>

<div class="data-table" style="margin-top: 20px;">
    <div class="data-table-header">
        <h2>用户增长趋势（近30天）</h2>
    </div>
    <div class="data-table-content">
        <canvas id="userChart" style="width: 100%; height: 300px;"></canvas>
    </div>
</div>

<script>
loadDashboard();

function loadDashboard() {
    fetch('api.php?action=dashboard')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalUsers').textContent = data.stats.total_users;
                document.getElementById('totalFiles').textContent = data.stats.total_files;
                document.getElementById('totalStorage').textContent = formatSize(data.stats.total_storage);
                document.getElementById('todayUploads').textContent = data.stats.today_uploads;
                
                renderStorageChart(data.storage_trend);
                renderUserChart(data.user_growth);
            }
        });
}

function renderStorageChart(data) {
    const ctx = document.getElementById('storageChart').getContext('2d');
    const labels = data.map(item => item.date);
    const values = data.map(item => item.size);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '存储空间（字节）',
                data: values,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatSize(value);
                        }
                    }
                }
            }
        }
    });
}

function renderUserChart(data) {
    const ctx = document.getElementById('userChart').getContext('2d');
    const labels = data.map(item => item.date);
    const values = data.map(item => item.count);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '新增用户',
                data: values,
                backgroundColor: '#667eea',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>