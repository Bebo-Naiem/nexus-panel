<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">All Containers</h1>
        <a href="?page=create-server" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
            Create Server
        </a>
    </div>
    
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-xl font-semibold">Docker Containers</h2>
            <button onclick="loadContainers()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
                Refresh
            </button>
        </div>
        <div id="containersContainer" class="p-6">
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="mt-4 text-slate-400">Loading containers...</p>
            </div>
        </div>
    </div>
</div>

<!-- Assign Server Modal -->
<div id="assignModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="glass rounded-xl p-6 border border-slate-700 max-w-md w-full mx-4">
        <h3 class="text-xl font-semibold mb-4">Assign Server</h3>
        <form id="assignForm">
            <input type="hidden" id="containerIdInput" name="container_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Container Name</label>
                <div id="containerNameDisplay" class="px-4 py-3 bg-slate-800 rounded-lg text-slate-300"></div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-300 mb-2">Assign to User</label>
                <select id="userIdSelect" name="user_id" required
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                    <option value="">Select a user...</option>
                </select>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeAssignModal()" 
                        class="flex-1 py-2 px-4 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 py-2 px-4 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
                    Assign
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let usersCache = [];

document.addEventListener('DOMContentLoaded', async function() {
    await Promise.all([
        loadUsers(),
        loadContainers()
    ]);
});

async function loadUsers() {
    const result = await apiCall('list_users');
    if (result.success) {
        usersCache = result.users || [];
    }
}

async function loadContainers() {
    const result = await apiCall('list_all_containers');
    
    if (result.success) {
        const container = document.getElementById('containersContainer');
        const containers = result.containers || [];
        
        if (containers.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-300 mb-2">No containers found</h3>
                    <p class="text-slate-500">Run some Docker containers to see them here.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Container ID</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Name</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Status</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Assigned To</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${containers.map(container => {
                            const assignedUser = usersCache.find(u => u.id == container.assigned_user_id);
                            return `
                                <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition">
                                    <td class="py-3 px-4 font-mono text-sm">
                                        <span class="bg-slate-800 px-2 py-1 rounded">${container.id.substring(0, 12)}</span>
                                    </td>
                                    <td class="py-3 px-4 font-medium">${container.name}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium ${
                                            container.status.includes('Up') ? 'bg-green-500/20 text-green-400' :
                                            container.status.includes('Exited') ? 'bg-red-500/20 text-red-400' :
                                            'bg-slate-500/20 text-slate-400'
                                        }">
                                            ${container.status}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        ${assignedUser ? 
                                            `<span class="px-2 py-1 bg-slate-700 rounded text-sm">${assignedUser.username}</span>` : 
                                            '<span class="text-slate-500 text-sm">Unassigned</span>'
                                        }
                                    </td>
                                    <td class="py-3 px-4">
                                        <button onclick="openAssignModal('${container.id}', '${container.name}', ${container.assigned_user_id || 'null'})" 
                                                class="px-3 py-1 bg-primary hover:bg-opacity-90 rounded text-sm font-medium transition">
                                            ${assignedUser ? 'Reassign' : 'Assign'}
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
}

function openAssignModal(containerId, containerName, currentUserId) {
    document.getElementById('containerIdInput').value = containerId;
    document.getElementById('containerNameDisplay').textContent = containerName;
    
    const select = document.getElementById('userIdSelect');
    select.innerHTML = '<option value="">Select a user...</option>';
    
    usersCache.forEach(user => {
        if (user.role !== 'admin') {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.username} (${user.email})`;
            if (user.id == currentUserId) option.selected = true;
            select.appendChild(option);
        }
    });
    
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
    document.getElementById('assignForm').reset();
}

document.getElementById('assignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const result = await apiCall('assign_server', {
        container_id: formData.get('container_id'),
        user_id: formData.get('user_id') || null
    });
    
    if (result.success) {
        showToast('Server assignment updated successfully', 'success');
        closeAssignModal();
        await loadContainers();
    }
});

// Close modal when clicking outside
document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAssignModal();
    }
});
</script>

<?php endif; ?>