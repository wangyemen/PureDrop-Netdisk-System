<div class="data-table">
    <div class="data-table-header">
        <h2>系统设置</h2>
        <button class="btn" onclick="saveSettings()">保存设置</button>
    </div>
    <div class="data-table-content" style="padding: 20px;">
        <form id="settingsForm">
            <h3 style="margin-bottom: 20px; color: #333;">基础配置</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>网站名称</label>
                    <input type="text" id="site_name" name="site_name">
                </div>
                <div class="form-group">
                    <label>网站Logo</label>
                    <input type="file" id="site_logo_file" name="site_logo_file" accept="image/*">
                    <div id="logoPreview" style="margin-top: 10px; display: none;">
                        <img id="logoPreviewImg" src="" style="max-width: 100px; max-height: 100px; border-radius: 4px;">
                        <button type="button" class="btn btn-sm btn-danger" style="margin-top: 5px;" onclick="removeLogo()">删除Logo</button>
                    </div>
                    <input type="hidden" id="site_logo" name="site_logo">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>网站URL</label>
                    <input type="url" id="site_url" name="site_url">
                </div>
                <div class="form-group">
                    <label>默认存储空间（字节）</label>
                    <input type="number" id="default_storage" name="default_storage">
                </div>
            </div>
            
            <h3 style="margin: 30px 0 20px; color: #333;">安全配置</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>最大登录尝试次数</label>
                    <input type="number" id="max_login_attempts" name="max_login_attempts">
                </div>
                <div class="form-group">
                    <label>启用验证码</label>
                    <select id="enable_captcha" name="enable_captcha">
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </div>
            </div>
            
            <h3 style="margin: 30px 0 20px; color: #333;">分享设置</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>默认分享有效期（天）</label>
                    <input type="number" id="default_share_expiry" name="default_share_expiry">
                </div>
                <div class="form-group">
                    <label>强制提取码</label>
                    <select id="require_extract_code" name="require_extract_code">
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </div>
            </div>
            
            <h3 style="margin: 30px 0 20px; color: #333;">文件设置</h3>
            <div class="form-group">
                <label>最大文件大小（字节）</label>
                <input type="number" id="max_file_size" name="max_file_size">
            </div>
        </form>
    </div>
</div>

<script>
loadSettings();

function loadSettings() {
    fetch('api.php?action=settings')
        .then(response => {
            if (!response.ok) {
                throw new Error('网络响应错误: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('获取设置数据:', data);
            if (data.success) {
                Object.keys(data.settings).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        console.log('设置 ' + key + ' 的值:', data.settings[key].value);
                        element.value = data.settings[key].value;
                    } else {
                        console.warn('找不到元素:', key);
                    }
                });
                
                // 显示Logo预览
                const siteLogo = data.settings.site_logo ? data.settings.site_logo.value : '';
                if (siteLogo) {
                    document.getElementById('site_logo').value = siteLogo;
                    document.getElementById('logoPreviewImg').src = '../' + siteLogo;
                    document.getElementById('logoPreview').style.display = 'block';
                }
            } else {
                console.error('获取设置失败:', data.message);
                showToast('获取设置失败', 'error');
            }
        })
        .catch(error => {
            console.error('加载设置时发生错误:', error);
            showToast('加载设置失败', 'error');
        });
}

// Logo预览功能
document.getElementById('site_logo_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreviewImg').src = e.target.result;
            document.getElementById('logoPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

function removeLogo() {
    document.getElementById('site_logo_file').value = '';
    document.getElementById('site_logo').value = '';
    document.getElementById('logoPreview').style.display = 'none';
}

function saveSettings() {
    try {
        const formData = new FormData();
        
        // 添加所有设置字段
        formData.append('site_name', document.getElementById('site_name').value);
        formData.append('site_url', document.getElementById('site_url').value);
        formData.append('allow_register', document.getElementById('allow_register').value);
        formData.append('default_storage', document.getElementById('default_storage').value);
        formData.append('max_login_attempts', document.getElementById('max_login_attempts').value);
        formData.append('enable_captcha', document.getElementById('enable_captcha').value);
        formData.append('default_share_expiry', document.getElementById('default_share_expiry').value);
        formData.append('require_extract_code', document.getElementById('require_extract_code').value);
        formData.append('max_file_size', document.getElementById('max_file_size').value);
        
        // 添加当前Logo值（如果没有上传新文件）
        const currentLogo = document.getElementById('site_logo').value;
        if (currentLogo && !document.getElementById('site_logo_file').files[0]) {
            formData.append('site_logo', currentLogo);
        }
        
        // 添加Logo文件（如果有上传）
        const logoFile = document.getElementById('site_logo_file').files[0];
        if (logoFile) {
            formData.append('site_logo_file', logoFile);
        }
        
        console.log('保存设置数据:', formData);
        
        fetch('api.php?action=settings_update', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('网络响应错误: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('保存设置响应:', data);
            if (data.success) {
                showToast('设置已保存', 'success');
                // 重新加载设置以更新Logo预览
                loadSettings();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('保存设置时发生错误:', error);
            showToast('保存设置失败', 'error');
        });
    } catch (error) {
        console.error('构建设置对象时发生错误:', error);
        showToast('保存设置失败', 'error');
    }
}
</script>