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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Server Name</label>
                        <input type="text" name="name" required
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Docker Image</label>
                        <div class="flex gap-2">
                            <input type="text" name="image" id="imageInput" required
                                   class="flex-1 px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                            <button type="button" id="pullImageButton" 
                                    class="px-4 py-3 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
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
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Memory Limit (MB)</label>
                        <input type="number" name="memory" value="<?= Config::DEFAULT_MEMORY ?>" min="128" max="65536"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">CPU Limit (%)</label>
                        <input type="number" name="cpu_limit" value="<?= Config::DEFAULT_CPU_LIMIT ?>" min="10" max="100"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Disk Space (MB)</label>
                        <input type="number" name="disk_space" value="<?= Config::DEFAULT_DISK_SPACE ?>" min="1024" max="1048576"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
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
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php endif; ?>