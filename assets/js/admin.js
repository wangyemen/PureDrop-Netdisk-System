function loadPage(page) {
    window.location.href = `?page=${page}`;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function formatSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    }
    return bytes + ' B';
}

function checkForUpdate() {
    fetch('api.php?action=check_update')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.has_update) {
                    showUpdateModal(data.local_version, data.latest_version, data.update_url);
                } else {
                    showToast('当前已是最新版本', 'success');
                }
            } else {
                showToast(data.message || '检查更新失败', 'error');
            }
        })
        .catch(error => {
            console.error('检查更新时发生错误:', error);
            showToast('检查更新失败', 'error');
        });
}

function showUpdateModal(localVersion, latestVersion, updateUrl) {
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;';
    modal.innerHTML = `
        <div style="background: white; border-radius: 12px; padding: 30px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 48px; margin-bottom: 10px;">🔄</div>
                <h2 style="font-size: 24px; color: #333; margin-bottom: 10px;">发现新版本</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    当前版本: v${localVersion}<br>
                    最新版本: v${latestVersion}
                </p>
                <p style="color: #666; margin-bottom: 20px;">
                    建议您更新到最新版本以获得更好的体验和安全性。
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="window.open('${updateUrl}', '_blank'); this.closest('div').parentElement.remove();" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    立即更新
                </button>
                <button onclick="this.closest('div').parentElement.remove();" style="flex: 1; padding: 12px; background: #e0e0e0; color: #666; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    关闭
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});
