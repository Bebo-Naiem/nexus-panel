<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Manage Users</h1>
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
</script>

<?php endif; ?>