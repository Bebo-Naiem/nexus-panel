<?php
$containerId = $_GET['container'] ?? '';
if (!$containerId) {
    echo "<div class='text-red-400'>Error: No container specified.</div>";
    return;
}

global $pdo;
$stmt = $pdo->prepare("SELECT name FROM servers WHERE container_id = ?");
$stmt->execute([$containerId]);
$server = $stmt->fetch();
$serverName = $server ? $server['name'] : 'Unknown Server';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold">File Manager</h1>
            <p class="text-slate-400 mt-1">Managing files for: <span class="text-primary font-medium"><?= htmlspecialchars($serverName) ?></span></p>
        </div>
        <div class="flex space-x-3">
            <button onclick="uploadFile()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
                <i class="fas fa-upload mr-2"></i> Upload
            </button>
            <button onclick="createNewFile()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm font-medium transition">
                <i class="fas fa-plus mr-2"></i> New File
            </button>
            <a href="?page=server-details&container=<?= urlencode($containerId) ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-sm font-medium transition border border-slate-700">
                Back to Details
            </a>
        </div>
    </div>

    <!-- Breadcrumbs -->
    <div class="flex items-center space-x-2 text-sm text-slate-400 bg-slate-800/50 px-4 py-2 rounded-lg border border-slate-700" id="breadcrumbs">
        <span class="cursor-pointer hover:text-primary" onclick="setPath('/')">/home/container</span>
    </div>

    <div class="glass rounded-xl border border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-800/50 border-b border-slate-700">
                        <th class="px-6 py-3 font-semibold text-slate-300">Name</th>
                        <th class="px-6 py-3 font-semibold text-slate-300">Size</th>
                        <th class="px-6 py-3 font-semibold text-slate-300">Modified</th>
                        <th class="px-6 py-3 font-semibold text-slate-300 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="fileList">
                    <!-- Files will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Editor Modal -->
<div id="editorModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-slate-900 border border-slate-700 rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col mx-4 shadow-2xl">
        <div class="p-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
            <h3 class="text-xl font-bold" id="editorTitle">Edit File</h3>
            <button onclick="closeEditor()" class="text-slate-400 hover:text-white transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="flex-1 p-0 overflow-hidden relative">
            <textarea id="fileEditor" class="w-full h-[60vh] p-4 bg-[#0f172a] text-slate-300 font-mono text-sm focus:outline-none resize-none" spellcheck="false"></textarea>
        </div>
        <div class="p-4 border-t border-slate-700 flex justify-between items-center bg-slate-800/50">
            <span class="text-sm text-slate-500" id="fileTypeInfo">Text file</span>
            <div class="flex space-x-3">
                <button onclick="closeEditor()" class="px-4 py-2 text-slate-400 hover:text-white transition">Cancel</button>
                <button onclick="saveFile()" class="px-6 py-2 bg-primary hover:bg-opacity-90 rounded-lg font-bold transition">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden File Input -->
<input type="file" id="fileInput" class="hidden" onchange="handleFileUpload(this)">

<script>
let currentPath = '/';
const containerId = '<?= $containerId ?>';

document.addEventListener('DOMContentLoaded', () => {
    loadFiles();
});

async function loadFiles() {
    const result = await apiCall('list_files', { container_id: containerId, path: currentPath });
    if (result.success) {
        renderFiles(result.files);
        renderBreadcrumbs();
    }
}

function renderFiles(files) {
    const tbody = document.getElementById('fileList');
    if (files.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 italic">This directory is empty.</td></tr>`;
        return;
    }

    // Sort: directories first, then alphabetical
    files.sort((a, b) => {
        if (a.is_directory !== b.is_directory) return b.is_directory ? 1 : -1;
        return a.name.localeCompare(b.name);
    });

    tbody.innerHTML = files.map(file => `
        <tr class="border-b border-slate-800/50 hover:bg-slate-800/30 transition group">
            <td class="px-6 py-4 flex items-center">
                <i class="fas ${file.is_directory ? 'fa-folder text-amber-400' : 'fa-file text-slate-400'} mr-3"></i>
                <span class="cursor-pointer font-medium hover:text-primary transition" 
                      onclick="${file.is_directory ? `setPath('${currentPath}/${file.name}')` : `editFile('${file.name}')`}">
                    ${file.name}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-500">${file.is_directory ? '-' : formatBytes(file.size)}</td>
            <td class="px-6 py-4 text-sm text-slate-500">${new Date(file.mtime * 1000).toLocaleString()}</td>
            <td class="px-6 py-4 text-right space-x-2">
                <button onclick="deleteFile('${file.name}')" class="p-2 text-slate-500 hover:text-red-400 transition opacity-0 group-hover:opacity-100">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function setPath(path) {
    currentPath = path.replace(/\/+/g, '/').replace(/\/$/, '') || '/';
    loadFiles();
}

function renderBreadcrumbs() {
    const bc = document.getElementById('breadcrumbs');
    const parts = currentPath.split('/').filter(p => p);
    let html = `<span class="cursor-pointer hover:text-primary transition" onclick="setPath('/')">/home/container</span>`;
    
    let pathAcc = '';
    parts.forEach(part => {
        pathAcc += '/' + part;
        html += ` <i class="fas fa-chevron-right text-[10px] text-slate-600"></i> `;
        html += `<span class="cursor-pointer hover:text-primary transition" onclick="setPath('${pathAcc}')">${part}</span>`;
    });
    bc.innerHTML = html;
}

let editingFileName = '';
async function editFile(name) {
    editingFileName = name;
    const path = (currentPath === '/' ? '' : currentPath) + '/' + name;
    
    const result = await apiCall('read_file', { container_id: containerId, path: path });
    if (result.success) {
        document.getElementById('fileEditor').value = result.content;
        document.getElementById('editorTitle').textContent = `Editing: ${name}`;
        document.getElementById('editorModal').classList.remove('hidden');
    }
}

function createNewFile() {
    const name = prompt('Enter file name:');
    if (name) {
        editingFileName = name;
        document.getElementById('fileEditor').value = '';
        document.getElementById('editorTitle').textContent = `New File: ${name}`;
        document.getElementById('editorModal').classList.remove('hidden');
    }
}

async function saveFile() {
    const content = document.getElementById('fileEditor').value;
    const path = (currentPath === '/' ? '' : currentPath) + '/' + editingFileName;
    
    const result = await apiCall('save_file', { container_id: containerId, path: path, content: content });
    if (result.success) {
        showToast('File saved successfully', 'success');
        closeEditor();
        loadFiles();
    }
}

async function deleteFile(name) {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) return;
    const path = (currentPath === '/' ? '' : currentPath) + '/' + name;
    const result = await apiCall('delete_file', { container_id: containerId, path: path });
    if (result.success) {
        showToast('Item deleted successfully', 'success');
        loadFiles();
    }
}

function uploadFile() {
    document.getElementById('fileInput').click();
}

async function handleFileUpload(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('container_id', containerId);
    formData.append('path', currentPath);
    formData.append('file', file);

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showToast('File uploaded successfully', 'success');
            loadFiles();
        } else {
            showToast(result.error || 'Upload failed', 'error');
        }
    } catch (e) {
        showToast('Network error during upload', 'error');
    }
    input.value = '';
}

function closeEditor() {
    document.getElementById('editorModal').classList.add('hidden');
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
}
</script>
