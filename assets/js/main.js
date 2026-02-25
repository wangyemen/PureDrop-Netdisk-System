let currentFolderId = 0;
let currentView = 'list';
let currentSort = 'name';
let currentOrder = 'asc';
let currentFilter = 'all';
let currentSearch = '';
let files = [];
let selectedFiles = [];
let contextMenuFile = null;

document.addEventListener('DOMContentLoaded', function() {
    loadFiles();
    setupDragAndDrop();
    setupContextMenu();
});

function loadFiles() {
    const params = new URLSearchParams();
    params.append('parent_id', currentFolderId);
    if (currentFilter !== 'all') {
        params.append('type', currentFilter);
    }
    if (currentSearch) {
        params.append('search', currentSearch);
    }
    params.append('sort', currentSort);
    params.append('order', currentOrder);
    
    fetch(`api/files.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                files = data.files;
                renderFiles();
            }
        });
}

function renderFiles() {
    renderListView();
    renderGridView();
}

function renderListView() {
    const container = document.getElementById('fileListBody');
    
    if (files.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📁</div><div class="empty-state-text">暂无文件</div></div>';
        return;
    }
    
    container.innerHTML = files.map(file => `
        <div class="file-item ${selectedFiles.includes(file.id) ? 'selected' : ''}" 
             data-id="${file.id}" 
             data-type="${file.file_type}"
             oncontextmenu="showContextMenu(event, ${file.id})"
             ondblclick="handleFileDblClick(${file.id}, '${file.file_type}')"
             draggable="true"
             ondragstart="handleDragStart(event, ${file.id}, '${file.file_type}')"
             ondragover="handleDragOver(event, ${file.id}, '${file.file_type}')"
             ondrop="handleDrop(event, ${file.id}, '${file.file_type}')">
            <input type="checkbox" class="file-item-checkbox" 
                   ${selectedFiles.includes(file.id) ? 'checked' : ''} 
                   onclick="event.stopPropagation(); toggleFileSelection(${file.id})">
            <div class="file-item-icon">${file.icon}</div>
            <div class="file-item-name">${file.name}</div>
            <div class="file-item-info">${file.size_formatted}</div>
            <div class="file-item-info">${file.updated_at}</div>
            <div class="file-item-actions">
                <span class="file-item-action" onclick="event.stopPropagation(); downloadFile(${file.id})">⬇️</span>
                <span class="file-item-action" onclick="event.stopPropagation(); showShareModal(${file.id})">🔗</span>
                <span class="file-item-action" onclick="event.stopPropagation(); deleteFile(${file.id})">🗑️</span>
            </div>
        </div>
    `).join('');
}

function renderGridView() {
    const container = document.getElementById('fileGridBody');
    
    if (files.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📁</div><div class="empty-state-text">暂无文件</div></div>';
        return;
    }
    
    container.innerHTML = files.map(file => `
        <div class="file-grid-item ${selectedFiles.includes(file.id) ? 'selected' : ''}" 
             data-id="${file.id}" 
             data-type="${file.file_type}"
             oncontextmenu="showContextMenu(event, ${file.id})"
             ondblclick="handleFileDblClick(${file.id}, '${file.file_type}')"
             draggable="true"
             ondragstart="handleDragStart(event, ${file.id}, '${file.file_type}')"
             ondragover="handleDragOver(event, ${file.id}, '${file.file_type}')"
             ondrop="handleDrop(event, ${file.id}, '${file.file_type}')">
            <div class="file-grid-item-icon">${file.icon}</div>
            <div class="file-grid-item-name">${file.name}</div>
            <div class="file-grid-item-info">${file.size_formatted}</div>
        </div>
    `).join('');
}

function handleFileDblClick(fileId, fileType) {
    if (fileType === 'folder') {
        goToFolder(fileId);
    } else {
        previewFile(fileId);
    }
}

function goToFolder(folderId) {
    currentFolderId = folderId;
    selectedFiles = [];
    loadFiles();
    updateBreadcrumb();
}

function updateBreadcrumb() {
    fetch(`api/files.php?action=get_path&file_id=${currentFolderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('breadcrumb');
                container.innerHTML = data.path.map((item, index) => `
                    <span class="breadcrumb-item ${index === data.path.length - 1 ? 'current' : ''}" 
                          onclick="goToFolder(${item.id})">${item.name}</span>
                    ${index < data.path.length - 1 ? '<span class="breadcrumb-separator">/</span>' : ''}
                `).join('');
            }
        });
}

function setView(view) {
    currentView = view;
    
    document.getElementById('listViewBtn').classList.toggle('active', view === 'list');
    document.getElementById('gridViewBtn').classList.toggle('active', view === 'grid');
    document.getElementById('fileListView').style.display = view === 'list' ? 'block' : 'none';
    document.getElementById('fileGridView').style.display = view === 'grid' ? 'grid' : 'none';
}

function sortBy(sort) {
    if (currentSort === sort) {
        currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = sort;
        currentOrder = 'asc';
    }
    
    loadFiles();
}

function filterByType(type) {
    currentFilter = type;
    
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    loadFiles();
}

function handleSearch(event) {
    if (event.key === 'Enter') {
        currentSearch = document.getElementById('searchInput').value;
        loadFiles();
    }
}

function toggleFileSelection(fileId) {
    const index = selectedFiles.indexOf(fileId);
    if (index > -1) {
        selectedFiles.splice(index, 1);
    } else {
        selectedFiles.push(fileId);
    }
    renderFiles();
}

function toggleSelectAll() {
    const checkbox = document.getElementById('selectAll');
    if (checkbox.checked) {
        selectedFiles = files.map(f => f.id);
    } else {
        selectedFiles = [];
    }
    renderFiles();
}

function setupContextMenu() {
    document.addEventListener('click', function() {
        document.getElementById('contextMenu').classList.remove('show');
    });
}

function showContextMenu(event, fileId) {
    event.preventDefault();
    contextMenuFile = fileId;
    
    const menu = document.getElementById('contextMenu');
    menu.style.left = event.pageX + 'px';
    menu.style.top = event.pageY + 'px';
    menu.classList.add('show');
}

function showUploadModal() {
    document.getElementById('uploadModal').classList.add('show');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('show');
    document.getElementById('uploadList').innerHTML = '';
}

function setupDragAndDrop() {
    const area = document.getElementById('uploadArea');
    
    area.addEventListener('dragover', function(e) {
        e.preventDefault();
        area.classList.add('dragover');
    });
    
    area.addEventListener('dragleave', function(e) {
        e.preventDefault();
        area.classList.remove('dragover');
    });
    
    area.addEventListener('drop', function(e) {
        e.preventDefault();
        area.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
}

function handleFileSelect(event) {
    handleFiles(event.target.files);
}

function handleFiles(fileList) {
    Array.from(fileList).forEach(file => {
        uploadFile(file);
    });
}

async function uploadFile(file) {
    const chunkSize = 5 * 1024 * 1024;
    const totalChunks = Math.ceil(file.size / chunkSize);
    const fileMd5 = await calculateMD5(file);
    
    const initResponse = await fetch('api/upload.php?action=init', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `file_name=${encodeURIComponent(file.name)}&file_size=${file.size}&file_md5=${fileMd5}&parent_id=${currentFolderId}`
    });
    
    if (!initResponse.ok) {
        showUploadError(file.name, '初始化上传失败');
        return;
    }
    
    const initData = await initResponse.json();
    
    if (initData.exists) {
        showToast('文件已存在', 'info');
        return;
    }
    
    if (!initData.success) {
        showUploadError(file.name, initData.message || '初始化上传失败');
        return;
    }
    
    const uploadId = initData.upload_id;
    addUploadItem(file.name, totalChunks);
    
    try {
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_number', i);
            formData.append('chunk', chunk);
            
            const uploadResponse = await fetch('api/upload.php?action=upload', {
                method: 'POST',
                body: formData
            });
            
            if (!uploadResponse.ok) {
                throw new Error('分片上传失败');
            }
            
            const uploadData = await uploadResponse.json();
            if (!uploadData.success) {
                throw new Error(uploadData.message || '分片上传失败');
            }
            
            updateUploadProgress(file.name, i + 1, totalChunks);
        }
        
        const completeResponse = await fetch('api/upload.php?action=complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `upload_id=${uploadId}&parent_id=${currentFolderId}`
        });
        
        if (!completeResponse.ok) {
            throw new Error('完成上传失败');
        }
        
        const completeData = await completeResponse.json();
        
        if (completeData.success) {
            removeUploadItem(file.name);
            showToast('上传成功', 'success');
            loadFiles();
        } else {
            throw new Error(completeData.message || '完成上传失败');
        }
    } catch (error) {
        showUploadError(file.name, error.message || '上传失败');
    }
}

function addUploadItem(fileName, totalChunks) {
    const container = document.getElementById('uploadList');
    const item = document.createElement('div');
    item.className = 'upload-item';
    item.id = 'upload-' + fileName;
    item.innerHTML = `
        <div class="upload-item-info">
            <span class="upload-item-name">${fileName}</span>
            <span class="upload-item-status">0 / ${totalChunks}</span>
        </div>
        <div class="upload-item-progress">
            <div class="upload-item-progress-bar" style="width: 0%;"></div>
        </div>
    `;
    container.appendChild(item);
}

function updateUploadProgress(fileName, current, total) {
    const item = document.getElementById('upload-' + fileName);
    if (item) {
        const percent = (current / total) * 100;
        item.querySelector('.upload-item-status').textContent = `${current} / ${total}`;
        item.querySelector('.upload-item-progress-bar').style.width = percent + '%';
    }
}

function removeUploadItem(fileName) {
    const item = document.getElementById('upload-' + fileName);
    if (item) {
        item.remove();
    }
}

function showUploadError(fileName, errorMessage) {
    const item = document.getElementById('upload-' + fileName);
    if (item) {
        item.classList.add('upload-error');
        item.innerHTML = `
            <div class="upload-item-info">
                <span class="upload-item-name">${fileName}</span>
                <span class="upload-item-status error">上传失败</span>
            </div>
            <div class="upload-item-progress">
                <div class="upload-item-progress-bar error" style="width: 100%;"></div>
            </div>
            <div class="upload-item-error">
                ${errorMessage}
            </div>
        `;
        showToast(`上传失败: ${errorMessage}`, 'error');
    }
}

function showCreateFolderModal() {
    document.getElementById('createFolderModal').classList.add('show');
    document.getElementById('folderName').focus();
}

function closeCreateFolderModal() {
    document.getElementById('createFolderModal').classList.remove('show');
    document.getElementById('folderName').value = '';
}

function createFolder() {
    const folderName = document.getElementById('folderName').value.trim();
    
    if (!folderName) {
        showToast('请输入文件夹名称', 'error');
        return;
    }
    
    fetch('api/files.php?action=create_folder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `folder_name=${encodeURIComponent(folderName)}&parent_id=${currentFolderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('文件夹创建成功', 'success');
            closeCreateFolderModal();
            loadFiles();
        } else {
            showToast(data.message, 'error');
        }
    });
}

function showRenameModal(fileId) {
    const id = fileId || contextMenuFile;
    const file = files.find(f => f.id === id);
    
    if (file) {
        document.getElementById('renameFileId').value = id;
        document.getElementById('newFileName').value = file.name;
        document.getElementById('renameModal').classList.add('show');
    }
}

function closeRenameModal() {
    document.getElementById('renameModal').classList.remove('show');
}

function renameFile() {
    const fileId = document.getElementById('renameFileId').value;
    const newName = document.getElementById('newFileName').value.trim();
    
    if (!newName) {
        showToast('请输入新名称', 'error');
        return;
    }
    
    fetch('api/files.php?action=rename', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `file_id=${fileId}&new_name=${encodeURIComponent(newName)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('重命名成功', 'success');
            closeRenameModal();
            loadFiles();
        } else {
            showToast(data.message, 'error');
        }
    });
}

function downloadFile(fileId) {
    const id = fileId || contextMenuFile;
    window.location.href = `download.php?file_id=${id}`;
}

function deleteFile(fileId) {
    const id = fileId || contextMenuFile;
    
    if (!confirm('确定要删除此文件吗？')) {
        return;
    }
    
    fetch('api/files.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `file_ids[]=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('删除成功', 'success');
            loadFiles();
        } else {
            showToast(data.message, 'error');
        }
    });
}

function showShareModal(fileId) {
    const id = fileId || contextMenuFile;
    document.getElementById('shareFileId').value = id;
    document.getElementById('shareResult').style.display = 'none';
    document.getElementById('shareModal').classList.add('show');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('show');
}

function createShare() {
    const fileId = document.getElementById('shareFileId').value;
    const extractCode = document.getElementById('extractCode').value.trim();
    const expiryDays = document.getElementById('expiryDays').value;
    
    fetch('api/share.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `file_id=${fileId}&extract_code=${encodeURIComponent(extractCode)}&expiry_days=${expiryDays}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('shareUrl').value = data.share_url;
            document.getElementById('shareResult').style.display = 'block';
            showToast('分享创建成功', 'success');
        } else {
            showToast(data.message, 'error');
        }
    });
}

function copyShareUrl() {
    const input = document.getElementById('shareUrl');
    input.select();
    document.execCommand('copy');
    showToast('链接已复制', 'success');
}

function previewFile(fileId) {
    const id = fileId || contextMenuFile;
    const file = files.find(f => f.id === id);
    
    if (!file) return;
    
    if (file.file_type === 'folder') {
        goToFolder(id);
        return;
    }
    
    if (['image', 'video', 'audio'].includes(file.file_type)) {
        document.getElementById('previewTitle').textContent = file.name;
        const content = document.getElementById('previewContent');
        
        if (file.file_type === 'image') {
            content.innerHTML = `<img src="preview.php?file_id=${id}" class="preview-image" alt="${file.name}">`;
        } else if (file.file_type === 'video') {
            content.innerHTML = `<video src="preview.php?file_id=${id}" class="preview-video" controls autoplay></video>`;
        } else if (file.file_type === 'audio') {
            content.innerHTML = `<audio src="preview.php?file_id=${id}" class="preview-audio" controls autoplay></audio>`;
        }
        
        document.getElementById('previewModal').classList.add('show');
    } else {
        showToast('此文件类型不支持预览', 'info');
    }
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.remove('show');
    document.getElementById('previewContent').innerHTML = '';
}

function openFile() {
    const fileId = contextMenuFile;
    const file = files.find(f => f.id === fileId);
    
    if (file && file.file_type === 'folder') {
        goToFolder(fileId);
    } else {
        previewFile(fileId);
    }
}

function moveFile() {
    showToast('移动功能开发中', 'info');
}

function copyFile() {
    showToast('复制功能开发中', 'info');
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

async function calculateMD5(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const wordArray = CryptoJS.lib.WordArray.create(e.target.result);
            const md5 = CryptoJS.MD5(wordArray).toString();
            resolve(md5);
        };
        reader.onerror = function() {
            reject(new Error('读取文件失败'));
        };
        reader.readAsArrayBuffer(file);
    });
}

function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('show');
}

// 拖拽移动功能
let draggedFileId = null;
let draggedFileType = null;

function handleDragStart(event, fileId, fileType) {
    event.dataTransfer.setData('text/plain', fileId);
    event.dataTransfer.setData('text/fileType', fileType);
    draggedFileId = fileId;
    draggedFileType = fileType;
}

function handleDragOver(event, fileId, fileType) {
    event.preventDefault();
    // 只允许拖拽到文件夹
    if (fileType === 'folder') {
        event.currentTarget.classList.add('drag-over');
    }
}

function handleDrop(event, fileId, fileType) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    
    // 只允许拖拽到文件夹
    if (fileType === 'folder') {
        const droppedFileId = parseInt(event.dataTransfer.getData('text/plain'));
        
        // 不能拖拽到自己
        if (droppedFileId !== fileId) {
            moveFileToFolder(droppedFileId, fileId);
        }
    }
}

function moveFileToFolder(fileId, folderId) {
    console.log('拖拽移动:', { fileId, folderId });
    fetch('api/files.php?action=move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `file_ids[]=${fileId}&target_parent_id=${folderId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('拖拽移动响应:', data);
        if (data.success) {
            showToast('移动成功', 'success');
            loadFiles();
        } else {
            showToast(data.message || '移动失败', 'error');
        }
    })
    .catch(error => {
        console.error('拖拽移动错误:', error);
        showToast('移动失败: ' + error.message, 'error');
    });
}

function moveFile() {
    if (selectedFiles.length > 0) {
        showMoveModal();
    } else if (contextMenuFile) {
        showMoveModal();
    }
}

function copyFile() {
    if (selectedFiles.length > 0) {
        showCopyModal();
    } else if (contextMenuFile) {
        showCopyModal();
    }
}

let selectedTargetFolder = null;

function showMoveModal() {
    document.getElementById('moveModal').classList.add('show');
    loadFolderTree('folderTree');
}

function closeMoveModal() {
    document.getElementById('moveModal').classList.remove('show');
    selectedTargetFolder = null;
}

function showCopyModal() {
    document.getElementById('copyModal').classList.add('show');
    loadFolderTree('copyFolderTree');
}

function closeCopyModal() {
    document.getElementById('copyModal').classList.remove('show');
    selectedTargetFolder = null;
}

function loadFolderTree(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">加载中...</div>';
    
    fetch('api/files.php?action=list&parent_id=0')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const folders = data.files.filter(f => f.file_type === 'folder');
                container.innerHTML = renderFolderTree(folders, containerId);
            }
        });
}

function renderFolderTree(folders, containerId, level = 0) {
    let html = `
        <div class="folder-tree-item" 
             data-id="0" 
             data-level="${level}"
             onclick="selectTargetFolder(0, '${containerId}')"
             style="padding-left: ${level * 20 + 10}px; padding: 8px 10px; cursor: pointer; transition: background 0.3s; border-radius:4px; margin: 2px 0; font-weight: 600;">
            <span style="font-size: 16px;">🏠</span>
            <span style="margin-left: 8px;">根目录</span>
        </div>
    `;
    
    if (folders.length === 0) {
        html += '<div style="padding: 20px; text-align: center; color: #999;">暂无文件夹</div>';
    } else {
        html += folders.map(folder => `
            <div class="folder-tree-item" 
                 data-id="${folder.id}" 
                 data-level="${level}"
                 onclick="selectTargetFolder(${folder.id}, '${containerId}')"
                 style="padding-left: ${level * 20 + 10}px; padding: 8px 10px; cursor: pointer; transition: background 0.3s; border-radius:4px; margin: 2px 0;">
                <span style="font-size: 16px;">📁</span>
                <span style="margin-left: 8px;">${folder.name}</span>
            </div>
        `).join('');
    }
    
    return html;
}

function selectTargetFolder(folderId, containerId) {
    selectedTargetFolder = folderId;
    
    const container = document.getElementById(containerId);
    const items = container.querySelectorAll('.folder-tree-item');
    items.forEach(item => {
        item.style.background = 'transparent';
        item.style.color = '#333';
    });
    
    const selectedItem = container.querySelector(`.folder-tree-item[data-id="${folderId}"]`);
    if (selectedItem) {
        selectedItem.style.background = '#e8f0fe';
        selectedItem.style.color = '#667eea';
    }
}

function confirmMove() {
    if (selectedTargetFolder === null || selectedTargetFolder === undefined) {
        showToast('请选择目标文件夹', 'error');
        return;
    }
    
    const fileIds = selectedFiles.length > 0 ? selectedFiles : [contextMenuFile];
    
    fetch('api/files.php?action=move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fileIds.map(id => `file_ids[]=${id}`).join('&') + `&target_parent_id=${selectedTargetFolder}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('移动响应:', data);
        if (data.success) {
            showToast(`成功移动 ${data.moved_count} 个文件`, 'success');
            closeMoveModal();
            loadFiles();
        } else {
            showToast(data.message || '移动失败', 'error');
        }
    })
    .catch(error => {
        console.error('移动错误:', error);
        showToast('移动失败: ' + error.message, 'error');
    });
}

function confirmCopy() {
    if (selectedTargetFolder === null || selectedTargetFolder === undefined) {
        showToast('请选择目标文件夹', 'error');
        return;
    }
    
    const fileIds = selectedFiles.length > 0 ? selectedFiles : [contextMenuFile];
    
    fetch('api/files.php?action=copy', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fileIds.map(id => `file_ids[]=${id}`).join('&') + `&target_parent_id=${selectedTargetFolder}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`成功复制 ${data.copied_count} 个文件`, 'success');
            closeCopyModal();
            loadFiles();
        } else {
            showToast(data.message, 'error');
        }
    });
}
