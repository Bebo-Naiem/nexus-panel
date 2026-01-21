<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="glass rounded-xl p-6 border border-slate-700 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold">Create New User</h3>
            <button onclick="closeCreateUserModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="createUserForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Username *</label>
                <input type="text" name="username" required
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Email *</label>
                <input type="email" name="email" required
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Password *</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                <p class="mt-1 text-sm text-slate-500">Minimum 6 characters</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Role</label>
                <select name="role" class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="closeCreateUserModal()" 
                        class="flex-1 py-2 px-4 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 py-2 px-4 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Manage Users</h1>
        <button onclick="openCreateUserModal()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
            Add New User
        </button>
    </div>
    
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700">
            <h2 class="text-xl font-semibold">Registered Users</h2>
        </div>
        <div id="usersContainer" class="p-6">
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="mt-4 text-slate-400">Loading users...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    await loadUsers();
});

async function loadUsers() {
    const result = await apiCall('list_users');
    
    if (result.success) {
        const container = document.getElementById('usersContainer');
        const users = result.users || [];
        
        if (users.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-300 mb-2">No users found</h3>
                    <p class="text-slate-500">Users will appear here once they register.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Username</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Email</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Role</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Created</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${users.map(user => `
                            <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center mr-3">
                                            <span class="text-primary font-medium">${user.username.charAt(0).toUpperCase()}</span>
                                        </div>
                                        ${user.username}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-300">${user.email}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                                        user.role === 'admin' ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-700 text-slate-300'
                                    }">
                                        ${user.role}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-400 text-sm">${new Date(user.created_at).toLocaleDateString()}</td>
                                <td class="py-3 px-4">
                                    ${user.role !== 'admin' ? `
                                        <button onclick="deleteUser(${user.id})" 
                                                class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-sm font-medium transition">
                                            Delete
                                        </button>
                                    ` : `
                                        <span class="text-slate-500 text-sm">Administrator</span>
                                    `}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This will also unassign all their servers.')) {
        return;
    }
    
    const result = await apiCall('delete_user', { user_id: userId });
    
    if (result.success) {
        showToast('User deleted successfully', 'success');
        await loadUsers();
    }
}

function openCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
    document.getElementById('createUserForm').reset();
}

document.getElementById('createUserForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const result = await apiCall('create_user_admin', Object.fromEntries(formData));
    
    if (result.success) {
        showToast('User created successfully', 'success');
        closeCreateUserModal();
        await loadUsers();
    }
});
</script>

<?php endif; ?>