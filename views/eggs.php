<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Egg Management</h1>
        <button onclick="openCreateEggModal()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
            Create New Egg
        </button>
    </div>
    
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-xl font-semibold">Available Eggs</h2>
            <button onclick="loadEggs()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
                Refresh
            </button>
        </div>
        <div id="eggsContainer" class="p-6">
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="mt-4 text-slate-400">Loading eggs...</p>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Egg Modal -->
<div id="eggModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="glass rounded-xl p-6 border border-slate-700 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold" id="modalTitle">Create New Egg</h3>
            <button onclick="closeEggModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="eggForm">
            <input type="hidden" id="eggId" name="egg_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Egg Name *</label>
                    <input type="text" id="eggName" name="name" required
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Docker Image *</label>
                    <input type="text" id="dockerImage" name="docker_image" required
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                <textarea id="eggDescription" name="description" rows="3"
                          class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400"></textarea>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-300 mb-2">Startup Command</label>
                <input type="text" id="startupCommand" name="startup_command"
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400"
                       placeholder="e.g., java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-300 mb-2">Stop Command</label>
                <input type="text" id="stopCommand" name="config_stop" value="stop"
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <!-- Variables Section -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-sm font-medium text-slate-300">Environment Variables</label>
                    <button type="button" onclick="addVariable()" class="px-3 py-1 bg-slate-700 hover:bg-slate-600 rounded text-sm">
                        + Add Variable
                    </button>
                </div>
                <div id="variablesContainer" class="space-y-3">
                    <!-- Variables will be added here dynamically -->
                </div>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeEggModal()" 
                        class="flex-1 py-2 px-4 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 py-2 px-4 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
                    Save Egg
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentEggId = null;

document.addEventListener('DOMContentLoaded', async function() {
    await loadEggs();
});

async function loadEggs() {
    const result = await apiCall('list_eggs');
    
    if (result.success) {
        const container = document.getElementById('eggsContainer');
        const eggs = result.eggs || [];
        
        if (eggs.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-300 mb-2">No eggs found</h3>
                    <p class="text-slate-500">Create your first egg to get started.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                ${eggs.map(egg => `
                    <div class="glass rounded-lg p-5 border border-slate-700 hover:border-slate-600 transition">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-semibold text-lg">${egg.name}</h3>
                            <div class="flex space-x-2">
                                <button onclick="editEgg(${egg.id})" 
                                        class="px-2 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs">
                                    Edit
                                </button>
                                <button onclick="deleteEgg(${egg.id})" 
                                        class="px-2 py-1 bg-red-600 hover:bg-red-700 rounded text-xs">
                                    Delete
                                </button>
                            </div>
                        </div>
                        
                        <p class="text-sm text-slate-400 mb-3">${egg.description || 'No description'}</p>
                        
                        <div class="text-xs text-slate-500">
                            <div class="mb-1"><strong>Image:</strong> ${egg.docker_image}</div>
                            <div><strong>Created:</strong> ${new Date(egg.created_at).toLocaleDateString()}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

function openCreateEggModal() {
    currentEggId = null;
    document.getElementById('modalTitle').textContent = 'Create New Egg';
    document.getElementById('eggForm').reset();
    document.getElementById('variablesContainer').innerHTML = '';
    document.getElementById('eggModal').classList.remove('hidden');
}

async function editEgg(eggId) {
    const result = await apiCall('get_egg', { egg_id: eggId });
    
    if (result.success) {
        const egg = result.egg;
        currentEggId = eggId;
        
        document.getElementById('modalTitle').textContent = 'Edit Egg';
        document.getElementById('eggId').value = egg.id;
        document.getElementById('eggName').value = egg.name;
        document.getElementById('eggDescription').value = egg.description || '';
        document.getElementById('dockerImage').value = egg.docker_image;
        document.getElementById('startupCommand').value = egg.startup_command || '';
        document.getElementById('stopCommand').value = egg.config_stop || 'stop';
        
        // Load variables
        const varsContainer = document.getElementById('variablesContainer');
        varsContainer.innerHTML = '';
        
        if (Array.isArray(egg.vars) && egg.vars.length > 0) {
            egg.vars.forEach(variable => {
                addVariable(variable);
            });
        }
        
        document.getElementById('eggModal').classList.remove('hidden');
    }
}

async function deleteEgg(eggId) {
    if (!confirm('Are you sure you want to delete this egg?')) {
        return;
    }
    
    const result = await apiCall('delete_egg', { egg_id: eggId });
    
    if (result.success) {
        showToast('Egg deleted successfully', 'success');
        await loadEggs();
    }
}

function closeEggModal() {
    document.getElementById('eggModal').classList.add('hidden');
    document.getElementById('eggForm').reset();
    document.getElementById('variablesContainer').innerHTML = '';
    currentEggId = null;
}

function addVariable(variable = null) {
    const container = document.getElementById('variablesContainer');
    const varId = Date.now();
    
    const varDiv = document.createElement('div');
    varDiv.className = 'flex gap-2 items-start';
    varDiv.innerHTML = `
        <input type="text" placeholder="Name" value="${variable?.name || ''}"
               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 var-name">
        <input type="text" placeholder="Environment Variable" value="${variable?.env_variable || ''}"
               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 var-env">
        <input type="text" placeholder="Default Value" value="${variable?.default_value || ''}"
               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 var-default">
        <button type="button" onclick="this.parentElement.remove()" 
                class="px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">-</button>
    `;
    
    container.appendChild(varDiv);
}

document.getElementById('eggForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Collect variables
    const variables = [];
    document.querySelectorAll('#variablesContainer > div').forEach(row => {
        const name = row.querySelector('.var-name').value.trim();
        const envVar = row.querySelector('.var-env').value.trim();
        const defaultValue = row.querySelector('.var-default').value.trim();
        
        if (name && envVar) {
            variables.push({
                name: name,
                env_variable: envVar,
                default_value: defaultValue,
                description: name,
                required: true,
                user_viewable: true,
                user_editable: true
            });
        }
    });
    
    const formData = new FormData(this);
    const action = currentEggId ? 'update_egg' : 'create_egg';
    
    if (currentEggId) {
        formData.append('egg_id', currentEggId);
    }
    
    formData.append('vars', JSON.stringify(variables));
    formData.append('config_files', JSON.stringify({}));
    formData.append('config_startup', JSON.stringify({}));
    formData.append('config_logs', JSON.stringify({}));
    
    const result = await apiCall(action, Object.fromEntries(formData));
    
    if (result.success) {
        showToast(currentEggId ? 'Egg updated successfully' : 'Egg created successfully', 'success');
        closeEggModal();
        await loadEggs();
    }
});

// Close modal when clicking outside
document.getElementById('eggModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEggModal();
    }
});
</script>

<?php endif; ?>