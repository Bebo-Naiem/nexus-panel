<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Create Server</h1>
        <a href="?page=admin-servers" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition">
            ← Back to Servers
        </a>
    </div>
    
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700">
            <h2 class="text-xl font-semibold">New Server Configuration</h2>
        </div>
        <div class="p-6">
            <form id="createServerForm" class="space-y-6">
                <!-- Owner Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Server Owner *</label>
                    <select name="owner_id" id="ownerSelect" required
                            class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                        <option value="">Select Owner...</option>
                    </select>
                </div>
                
                <!-- Egg Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Server Type (Egg) *</label>
                    <select name="egg_id" id="eggSelect" required
                            class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                        <option value="">Select Server Type...</option>
                    </select>
                    <p class="mt-1 text-sm text-slate-500">Choose a server configuration template</p>
                </div>
                
                <!-- Egg File Import Section -->
                <div class="glass rounded-lg border border-slate-700 p-6">
                    <h3 class="text-lg font-semibold mb-4">Egg File Management</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Import Egg from File</label>
                            <select id="eggFileSelect" 
                                    class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                                <option value="">Select Egg File...</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="importSelectedEgg()" 
                                    class="w-full py-3 bg-green-600 hover:bg-green-700 rounded-lg font-medium transition">
                                Import Selected Egg
                            </button>
                        </div>
                        <div class="flex items-end">
                            <label class="w-full py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition text-center cursor-pointer">
                                Upload Egg File
                                <input type="file" accept=".json" onchange="uploadEggFile(event)" class="hidden">
                            </label>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Upload custom egg files in JSON format or import existing ones</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Server Name</label>
                        <input type="text" name="name" id="serverName" required
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Docker Image</label>
                        <div class="flex gap-2">
                            <input type="text" name="image" id="imageInput" required readonly
                                   class="flex-1 px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                            <button type="button" id="pullImageButton" 
                                    class="px-4 py-3 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition disabled:opacity-50">
                                Pull
                            </button>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400"></textarea>
                </div>
                
                <!-- Resource Management -->
                <div class="glass rounded-lg border border-slate-700 p-6">
                    <h3 class="text-lg font-semibold mb-4">Resource Allocation</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Memory Limit (MB) *</label>
                            <input type="number" name="memory" id="memoryLimit" value="<?= Config::DEFAULT_MEMORY ?>" min="128" max="65536" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                            <p class="mt-1 text-xs text-slate-500">Min: 128MB, Max: 65536MB</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">CPU Limit (%) *</label>
                            <input type="number" name="cpu_limit" id="cpuLimit" value="<?= Config::DEFAULT_CPU_LIMIT ?>" min="10" max="100" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                            <p class="mt-1 text-xs text-slate-500">Min: 10%, Max: 100%</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Disk Space (MB) *</label>
                            <input type="number" name="disk_space" id="diskSpace" value="<?= Config::DEFAULT_DISK_SPACE ?>" min="1024" max="1048576" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                            <p class="mt-1 text-xs text-slate-500">Min: 1024MB, Max: 1048576MB</p>
                        </div>
                    </div>
                </div>
                
                <!-- Environment Variables -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Environment Variables</label>
                    <div id="envVarsContainer" class="space-y-3">
                        <div class="flex gap-2">
                            <input type="text" placeholder="KEY" 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-key">
                            <input type="text" placeholder="Value" 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-value">
                            <button type="button" class="remove-env-var px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
                        </div>
                    </div>
                    <button type="button" id="addEnvVar" class="mt-2 px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm">
                        + Add Variable
                    </button>
                </div>
                
                <!-- Port Mappings -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Port Mappings</label>
                    <div id="portsContainer" class="space-y-3">
                        <div class="flex gap-2">
                            <input type="number" placeholder="Host Port" 
                                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 host-port">
                            <span class="flex items-center text-slate-400">→</span>
                            <input type="number" placeholder="Container Port" 
                                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 container-port">
                            <button type="button" class="remove-port-mapping px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
                        </div>
                    </div>
                    <button type="button" id="addPortMapping" class="mt-2 px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm">
                        + Add Mapping
                    </button>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="?page=admin-servers" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-medium rounded-lg hover:opacity-90 transition duration-200">
                        Create Server
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Load initial data
    await Promise.all([
        loadUsers(),
        loadEggs()
    ]);
    
    // Add environment variable row
    document.getElementById('addEnvVar').addEventListener('click', function() {
        const container = document.getElementById('envVarsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'flex gap-2';
        newRow.innerHTML = `
            <input type="text" placeholder="KEY" 
                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-key">
            <input type="text" placeholder="Value" 
                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-value">
            <button type="button" class="remove-env-var px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
        `;
        container.appendChild(newRow);
        
        // Add event to remove button
        newRow.querySelector('.remove-env-var').addEventListener('click', function() {
            container.removeChild(newRow);
        });
    });
    
    // Add port mapping row
    document.getElementById('addPortMapping').addEventListener('click', function() {
        const container = document.getElementById('portsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'flex gap-2';
        newRow.innerHTML = `
            <input type="number" placeholder="Host Port" 
                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 host-port">
            <span class="flex items-center text-slate-400">→</span>
            <input type="number" placeholder="Container Port" 
                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 container-port">
            <button type="button" class="remove-port-mapping px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
        `;
        container.appendChild(newRow);
        
        // Add event to remove button
        newRow.querySelector('.remove-port-mapping').addEventListener('click', function() {
            container.removeChild(newRow);
        });
    });
    
    // Add event listeners to existing remove buttons
    document.querySelectorAll('.remove-env-var').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
    
    document.querySelectorAll('.remove-port-mapping').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
    
    // Pull image button
    document.getElementById('pullImageButton').addEventListener('click', async function() {
        const imageName = document.getElementById('imageInput').value.trim();
        if (!imageName) {
            showToast('Please enter an image name', 'error');
            return;
        }
        
        this.disabled = true;
        this.textContent = 'Pulling...';
        
        const result = await apiCall('pull_image', { image_name: imageName });
        
        if (result.success) {
            showToast(`Image ${imageName} pulled successfully`, 'success');
        }
        
        this.disabled = false;
        this.textContent = 'Pull';
    });
    
    // Form submission
    document.getElementById('createServerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Collect environment variables
        const envVars = {};
        document.querySelectorAll('#envVarsContainer .flex').forEach(row => {
            const keyInput = row.querySelector('.env-key');
            const valueInput = row.querySelector('.env-value');
            const key = keyInput.value.trim();
            const value = valueInput.value.trim();
            
            if (key && value) {
                envVars[key] = value;
            }
        });
        
        // Collect port mappings
        const ports = {};
        document.querySelectorAll('#portsContainer .flex').forEach(row => {
            const hostPortInput = row.querySelector('.host-port');
            const containerPortInput = row.querySelector('.container-port');
            const hostPort = hostPortInput.value.trim();
            const containerPort = containerPortInput.value.trim();
            
            if (hostPort && containerPort) {
                ports[hostPort] = containerPort;
            }
        });
        
        const formData = new FormData(this);
        
        const result = await apiCall('create_server', {
            name: formData.get('name'),
            image: formData.get('image'),
            description: formData.get('description'),
            owner_id: formData.get('owner_id'),
            egg_id: formData.get('egg_id'),
            memory: formData.get('memory'),
            cpu_limit: formData.get('cpu_limit'),
            disk_space: formData.get('disk_space'),
            environment: JSON.stringify(envVars),
            ports: JSON.stringify(ports)
        });
        
        if (result.success) {
            showToast('Server created successfully!', 'success');
            setTimeout(() => {
                window.location.href = '?page=admin-servers';
            }, 1500);
        }
    });
    
    // Load users for owner selection
    async function loadUsers() {
        const result = await apiCall('list_users');
        
        if (result.success) {
            const select = document.getElementById('ownerSelect');
            select.innerHTML = '<option value="">Select Owner...</option>';
            
            result.users.forEach(user => {
                if (user.role !== 'admin') { // Don't show admins as owners
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.username} (${user.email})`;
                    select.appendChild(option);
                }
            });
        }
    }
    
    // Load eggs for server type selection
    async function loadEggs() {
        const result = await apiCall('list_eggs');
        
        if (result.success) {
            const select = document.getElementById('eggSelect');
            select.innerHTML = '<option value="">Select Server Type...</option>';
            
            result.eggs.forEach(egg => {
                const option = document.createElement('option');
                option.value = egg.id;
                option.textContent = `${egg.name}`;
                option.dataset.description = egg.description || '';
                option.dataset.dockerImage = egg.docker_image || '';
                select.appendChild(option);
            });
        }
    }
    
    // Handle egg selection
    document.getElementById('eggSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            // Populate docker image
            const dockerImage = selectedOption.dataset.dockerImage;
            if (dockerImage) {
                document.getElementById('imageInput').value = dockerImage;
            }
            
            // Set default server name based on egg
            const serverName = selectedOption.textContent.replace(/\s+/g, '-').toLowerCase();
            document.getElementById('serverName').placeholder = `My-${serverName}-server`;
            
            // Load egg-specific environment variables
            loadEggVariables(selectedOption.value);
        } else {
            // Clear fields when no egg selected
            document.getElementById('imageInput').value = '';
            document.getElementById('serverName').placeholder = '';
            clearEggVariables();
        }
    });
    
    // Load egg-specific variables
    async function loadEggVariables(eggId) {
        const result = await apiCall('get_egg', { egg_id: eggId });
        
        if (result.success && result.egg.vars) {
            try {
                const vars = JSON.parse(result.egg.vars);
                const container = document.getElementById('envVarsContainer');
                
                // Clear existing rows except the first one
                const rows = container.querySelectorAll('.flex');
                for (let i = 1; i < rows.length; i++) {
                    rows[i].remove();
                }
                
                // Add egg variables
                Object.entries(vars).forEach(([key, defaultValue]) => {
                    const newRow = document.createElement('div');
                    newRow.className = 'flex gap-2';
                    newRow.innerHTML = `
                        <input type="text" value="${key}" readonly
                               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-key">
                        <input type="text" value="${defaultValue}" placeholder="Enter value" 
                               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-value">
                        <button type="button" class="remove-env-var px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
                    `;
                    container.appendChild(newRow);
                    
                    // Add event to remove button
                    newRow.querySelector('.remove-env-var').addEventListener('click', function() {
                        container.removeChild(newRow);
                    });
                });
            } catch (e) {
                console.error('Error parsing egg variables:', e);
            }
        }
    }
    
    // Clear egg variables
    function clearEggVariables() {
        const container = document.getElementById('envVarsContainer');
        const rows = container.querySelectorAll('.flex');
        
        // Keep first row, clear inputs
        const firstRow = rows[0];
        firstRow.querySelector('.env-key').value = '';
        firstRow.querySelector('.env-value').value = '';
        
        // Remove additional rows
        for (let i = 1; i < rows.length; i++) {
            rows[i].remove();
        }
    }
    
    // Add file-based egg import functionality
    document.getElementById('eggSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            // Populate docker image
            const dockerImage = selectedOption.dataset.dockerImage;
            if (dockerImage) {
                document.getElementById('imageInput').value = dockerImage;
            }
            
            // Set default server name based on egg
            const serverName = selectedOption.textContent.replace(/\s+/g, '-').toLowerCase();
            document.getElementById('serverName').placeholder = `My-${serverName}-server`;
            
            // Load egg-specific environment variables
            loadEggVariables(selectedOption.value);
        } else {
            // Clear fields when no egg selected
            document.getElementById('imageInput').value = '';
            document.getElementById('serverName').placeholder = '';
            clearEggVariables();
        }
    });
});

// Function to load eggs from files
async function loadEggFiles() {
    const result = await apiCall('list_egg_files');
    
    if (result.success) {
        const select = document.getElementById('eggFileSelect');
        select.innerHTML = '<option value="">Select Egg File...</option>';
        
        result.egg_files.forEach(eggFile => {
            const option = document.createElement('option');
            option.value = eggFile.filename;
            option.textContent = `${eggFile.name} (${eggFile.filename})`;
            option.dataset.description = eggFile.description || '';
            option.dataset.dockerImage = eggFile.docker_image || '';
            select.appendChild(option);
        });
    }
}

// Import egg from selected file
async function importSelectedEgg() {
    const select = document.getElementById('eggFileSelect');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) {
        showToast('Please select an egg file to import', 'error');
        return;
    }
    
    const result = await apiCall('import_egg', { filename: selectedOption.value });
    
    if (result.success) {
        showToast(result.message, 'success');
        // Reload eggs to show the imported one
        await loadEggs();
        // Reload egg files
        await loadEggFiles();
    }
}

// Handle egg file upload
function uploadEggFile(event) {
    const fileInput = event.target;
    const file = fileInput.files[0];
    
    if (!file) {
        return;
    }
    
    if (file.type !== 'application/json') {
        showToast('Only JSON files are allowed', 'error');
        fileInput.value = ''; // Clear the input
        return;
    }
    
    const formData = new FormData();
    formData.append('egg_file', file);
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            showToast(`Uploading: ${Math.round(percentComplete)}%`, 'info');
        }
    });
    
    xhr.addEventListener('load', function() {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message, 'success');
                // Reload egg files
                loadEggFiles();
                // Clear the file input
                fileInput.value = '';
            } else {
                showToast(response.error || 'Upload failed', 'error');
            }
        } catch (e) {
            showToast('Upload failed: Invalid response', 'error');
        }
    });
    
    xhr.addEventListener('error', function() {
        showToast('Upload failed: Network error', 'error');
        fileInput.value = '';
    });
    
    xhr.open('POST', 'api.php?action=upload_egg_file');
    // Add CSRF token if needed
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    }
    xhr.send(formData);
}
</script>

<?php endif; ?>