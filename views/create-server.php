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
                
                <!-- Egg File Import Section - Moved to Top -->
                <div class="glass rounded-lg border border-blue-600 bg-blue-500/10 p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Quick Start: Import Egg Configuration
                    </h3>
                    <p class="text-sm text-slate-400 mb-4">Upload or select a pre-configured egg file to auto-fill server settings</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Select Existing Egg File</label>
                            <select id="eggFileSelect" 
                                    class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                                <option value="">Choose egg file...</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="importSelectedEggFile()" 
                                    class="w-full py-3 bg-green-600 hover:bg-green-700 rounded-lg font-medium transition">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Import to Database
                            </button>
                        </div>
                        <div class="flex items-end">
                            <label class="w-full py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition text-center cursor-pointer">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Upload New Egg
                                <input type="file" accept=".json" onchange="handleEggFileUpload(event)" class="hidden">
                            </label>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        💡 Tip: Egg files contain pre-configured settings for game servers (Minecraft, Rust, etc.)
                    </p>
                </div>
                
                <!-- Owner Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Server Owner *
                        <span class="text-xs text-slate-500 ml-2">(Who will manage this server)</span>
                    </label>
                    <select name="owner_id" id="ownerSelect" required
                            class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                        <option value="">Select Owner...</option>
                    </select>
                </div>
                
                <!-- Egg Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Server Type (Egg) *
                        <span class="text-xs text-slate-500 ml-2">(Defines server software and configuration)</span>
                    </label>
                    <select name="egg_id" id="eggSelect" required
                            class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                        <option value="">Select Server Type...</option>
                    </select>
                    <p class="mt-1 text-sm text-slate-500">Choose a server configuration template from database</p>
                </div>
                
                <!-- Server Name and Image -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Server Name *</label>
                        <input type="text" name="name" id="serverName" required placeholder="My-Awesome-Server"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Docker Image *</label>
                        <div class="flex gap-2">
                            <input type="text" name="image" id="imageInput" required placeholder="itzg/minecraft-server:latest"
                                   class="flex-1 px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                            <button type="button" id="pullImageButton" 
                                    class="px-4 py-3 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition disabled:opacity-50">
                                Pull
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Docker Hub image name</p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description (Optional)</label>
                    <textarea name="description" rows="2" placeholder="Production Minecraft server for creative building"
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
                            <p class="mt-1 text-xs text-slate-500">Min: 128MB, Max: 65536MB (64GB)</p>
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
                            <p class="mt-1 text-xs text-slate-500">Min: 1GB (1024MB), Max: 1TB (1048576MB)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Environment Variables -->
                <div class="glass rounded-lg border border-slate-700 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Environment Variables</h3>
                        <button type="button" id="addEnvVar" class="px-3 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm">
                            + Add Variable
                        </button>
                    </div>
                    <p class="text-sm text-slate-400 mb-4">Configure server-specific settings (auto-populated from egg selection)</p>
                    <div id="envVarsContainer" class="space-y-3">
                        <div class="flex gap-2 env-var-row">
                            <input type="text" placeholder="SERVER_PORT" 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-key">
                            <input type="text" placeholder="25565" 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-value">
                            <button type="button" class="remove-env-var px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Port Mappings -->
                <div class="glass rounded-lg border border-slate-700 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Port Mappings</h3>
                        <button type="button" id="addPortMapping" class="px-3 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm">
                            + Add Port
                        </button>
                    </div>
                    <p class="text-sm text-slate-400 mb-4">Map host ports to container ports (e.g., 25565 → 25565 for Minecraft)</p>
                    <div id="portsContainer" class="space-y-3">
                        <div class="flex gap-2 items-center port-mapping-row">
                            <input type="number" placeholder="25565" 
                                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 host-port">
                            <span class="text-slate-400 text-xl">→</span>
                            <input type="number" placeholder="25565" 
                                   class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 container-port">
                            <button type="button" class="remove-port-mapping px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-700">
                    <a href="?page=admin-servers" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                            class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-medium rounded-lg hover:opacity-90 transition duration-200">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Server
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let usersCache = [];
let eggsCache = [];
let eggFilesCache = [];

document.addEventListener('DOMContentLoaded', async function() {
    // Load initial data
    await Promise.all([
        loadUsers(),
        loadEggs(),
        loadEggFiles()
    ]);
    
    setupEventListeners();
});

async function loadUsers() {
    try {
        const result = await apiCall('list_users');
        
        if (result.success) {
            usersCache = result.users || [];
            const select = document.getElementById('ownerSelect');
            select.innerHTML = '<option value="">Select Owner...</option>';
            
            usersCache.forEach(user => {
                if (user.role !== 'admin') {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.username} (${user.email})`;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showToast('Failed to load users', 'error');
    }
}

async function loadEggs() {
    try {
        const result = await apiCall('list_eggs');
        
        if (result.success) {
            eggsCache = result.eggs || [];
            const select = document.getElementById('eggSelect');
            select.innerHTML = '<option value="">Select Server Type...</option>';
            
            eggsCache.forEach(egg => {
                const option = document.createElement('option');
                option.value = egg.id;
                option.textContent = egg.name;
                option.dataset.description = egg.description || '';
                option.dataset.dockerImage = egg.docker_image || '';
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading eggs:', error);
        showToast('Failed to load server types', 'error');
    }
}

async function loadEggFiles() {
    try {
        const result = await apiCall('list_egg_files');
        
        if (result.success) {
            eggFilesCache = result.egg_files || [];
            const select = document.getElementById('eggFileSelect');
            select.innerHTML = '<option value="">Choose egg file...</option>';
            
            eggFilesCache.forEach(eggFile => {
                const option = document.createElement('option');
                option.value = eggFile.filename;
                option.textContent = `${eggFile.name} - ${eggFile.docker_image}`;
                option.dataset.description = eggFile.description || '';
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading egg files:', error);
        showToast('Failed to load egg files', 'error');
    }
}

function setupEventListeners() {
    // Egg selection handler
    document.getElementById('eggSelect').addEventListener('change', async function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            const dockerImage = selectedOption.dataset.dockerImage;
            if (dockerImage) {
                document.getElementById('imageInput').value = dockerImage;
            }
            
            const serverName = selectedOption.textContent.replace(/\s+/g, '-').toLowerCase();
            document.getElementById('serverName').placeholder = `my-${serverName}-server`;
            
            await loadEggVariables(selectedOption.value);
        } else {
            document.getElementById('imageInput').value = '';
            document.getElementById('serverName').placeholder = '';
            clearVariables();
        }
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
        
        try {
            const result = await apiCall('pull_image', { image_name: imageName });
            
            if (result.success) {
                showToast(`Image ${imageName} pulled successfully`, 'success');
            }
        } catch (error) {
            console.error('Error pulling image:', error);
        } finally {
            this.disabled = false;
            this.textContent = 'Pull';
        }
    });
    
    // Add environment variable
    document.getElementById('addEnvVar').addEventListener('click', function() {
        addEnvVarRow();
    });
    
    // Add port mapping
    document.getElementById('addPortMapping').addEventListener('click', function() {
        addPortMappingRow();
    });
    
    // Setup initial remove buttons
    setupRemoveButtons();
    
    // Form submission
    document.getElementById('createServerForm').addEventListener('submit', handleFormSubmit);
}

function addEnvVarRow(key = '', value = '', readonly = false) {
    const container = document.getElementById('envVarsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'flex gap-2 env-var-row';
    newRow.innerHTML = `
        <input type="text" value="${key}" placeholder="VARIABLE_NAME" ${readonly ? 'readonly' : ''}
               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-key">
        <input type="text" value="${value}" placeholder="value" 
               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 env-value">
        <button type="button" class="remove-env-var px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    container.appendChild(newRow);
    
    newRow.querySelector('.remove-env-var').addEventListener('click', function() {
        newRow.remove();
    });
}

function addPortMappingRow(hostPort = '', containerPort = '') {
    const container = document.getElementById('portsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'flex gap-2 items-center port-mapping-row';
    newRow.innerHTML = `
        <input type="number" value="${hostPort}" placeholder="8080" 
               class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 host-port">
        <span class="text-slate-400 text-xl">→</span>
        <input type="number" value="${containerPort}" placeholder="80" 
               class="w-1/3 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 container-port">
        <button type="button" class="remove-port-mapping px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    container.appendChild(newRow);
    
    newRow.querySelector('.remove-port-mapping').addEventListener('click', function() {
        newRow.remove();
    });
}

function setupRemoveButtons() {
    document.querySelectorAll('.remove-env-var').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.env-var-row').remove();
        });
    });
    
    document.querySelectorAll('.remove-port-mapping').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.port-mapping-row').remove();
        });
    });
}

async function loadEggVariables(eggId) {
    try {
        const result = await apiCall('get_egg', { egg_id: eggId });
        
        if (result.success && result.egg) {
            clearVariables();
            
            // Parse and add variables
            const vars = typeof result.egg.vars === 'string' ? JSON.parse(result.egg.vars) : result.egg.vars;
            
            if (Array.isArray(vars)) {
                vars.forEach(varObj => {
                    if (varObj.env_variable && varObj.default_value !== undefined) {
                        addEnvVarRow(varObj.env_variable, varObj.default_value, false);
                    }
                });
            } else if (typeof vars === 'object') {
                Object.entries(vars).forEach(([key, value]) => {
                    addEnvVarRow(key, value, false);
                });
            }
        }
    } catch (error) {
        console.error('Error loading egg variables:', error);
        showToast('Failed to load egg variables', 'error');
    }
}

function clearVariables() {
    const envContainer = document.getElementById('envVarsContainer');
    const portContainer = document.getElementById('portsContainer');
    
    envContainer.innerHTML = '';
    addEnvVarRow(); // Add one empty row
    
    portContainer.innerHTML = '';
    addPortMappingRow(); // Add one empty row
}

async function importSelectedEggFile() {
    const select = document.getElementById('eggFileSelect');
    const selectedFilename = select.value;
    
    if (!selectedFilename) {
        showToast('Please select an egg file to import', 'error');
        return;
    }
    
    try {
        const result = await apiCall('import_egg', { filename: selectedFilename });
        
        if (result.success) {
            showToast(result.message, 'success');
            await loadEggs();
            select.value = '';
        }
    } catch (error) {
        console.error('Error importing egg:', error);
    }
}

async function handleEggFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (!file.name.endsWith('.json')) {
        showToast('Only JSON files are allowed', 'error');
        event.target.value = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_egg_file');
    formData.append('egg_file', file);
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            await loadEggFiles();
        } else {
            showToast(result.error || 'Upload failed', 'error');
        }
    } catch (error) {
        console.error('Error uploading egg file:', error);
        showToast('Upload failed', 'error');
    } finally {
        event.target.value = '';
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Validate required fields
    const ownerId = document.getElementById('ownerSelect').value;
    const eggId = document.getElementById('eggSelect').value;
    const serverName = document.getElementById('serverName').value.trim();
    const image = document.getElementById('imageInput').value.trim();
    
    if (!ownerId || !eggId || !serverName || !image) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    // Collect environment variables
    const envVars = {};
    document.querySelectorAll('.env-var-row').forEach(row => {
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
    document.querySelectorAll('.port-mapping-row').forEach(row => {
        const hostPortInput = row.querySelector('.host-port');
        const containerPortInput = row.querySelector('.container-port');
        const hostPort = hostPortInput.value.trim();
        const containerPort = containerPortInput.value.trim();
        
        if (hostPort && containerPort) {
            ports[hostPort] = containerPort;
        }
    });
    
    const formData = new FormData(e.target);
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Creating...';
    
    try {
        const result = await apiCall('create_server', {
            name: serverName,
            image: image,
            description: formData.get('description'),
            owner_id: ownerId,
            egg_id: eggId,
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
    } catch (error) {
        console.error('Error creating server:', error);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}
</script>

<?php endif; ?>