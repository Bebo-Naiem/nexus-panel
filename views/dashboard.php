<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Dashboard</h1>
        <div class="text-sm text-slate-400">
            Welcome back, <span class="text-primary font-medium"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glass rounded-xl p-6 border border-slate-700">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-blue-500/20 mr-4">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-400">Total Servers</p>
                    <p id="totalServers" class="text-2xl font-bold">Loading...</p>
                </div>
            </div>
        </div>
        
        <div class="glass rounded-xl p-6 border border-slate-700">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-green-500/20 mr-4">
                    <div class="w-6 h-6 rounded-full bg-green-400 status-running"></div>
                </div>
                <div>
                    <p class="text-sm text-slate-400">Running</p>
                    <p id="runningServers" class="text-2xl font-bold">Loading...</p>
                </div>
            </div>
        </div>
        
        <div class="glass rounded-xl p-6 border border-slate-700">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-red-500/20 mr-4">
                    <div class="w-6 h-6 rounded-full bg-red-400 status-stopped"></div>
                </div>
                <div>
                    <p class="text-sm text-slate-400">Stopped</p>
                    <p id="stoppedServers" class="text-2xl font-bold">Loading...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Servers Grid -->
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700">
            <h2 class="text-xl font-semibold">My Servers</h2>
        </div>
        <div id="serversContainer" class="p-6">
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="mt-4 text-slate-400">Loading servers...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Load servers
    await loadServers();
});

async function loadServers() {
    const result = await apiCall('get_my_servers');
    
    if (result.success) {
        const container = document.getElementById('serversContainer');
        const servers = result.servers || [];
        
        // Update stats
        updateStats(servers);
        
        if (servers.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-300 mb-2">No servers assigned</h3>
                    <p class="text-slate-500">Contact your administrator to get access to servers.</p>
                </div>
            `;
            return;
        }
        
        // Render servers grid
        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                ${servers.map(server => `
                    <div class="glass rounded-lg p-5 border border-slate-700 hover:border-slate-600 transition">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-semibold text-lg truncate">${server.name}</h3>
                            <div class="flex items-center">
                                <div class="status-dot ${getStatusClass(server.status)} mr-2"></div>
                                <span class="text-xs capitalize">${server.status || 'unknown'}</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-slate-400 mb-1">Container ID</p>
                            <p class="font-mono text-sm bg-slate-800 px-2 py-1 rounded truncate">${server.container_id}</p>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button onclick="controlServer('${server.container_id}', 'start')" 
                                    class="flex-1 py-2 px-3 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition">
                                Start
                            </button>
                            <button onclick="controlServer('${server.container_id}', 'stop')" 
                                    class="flex-1 py-2 px-3 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition">
                                Stop
                            </button>
                            <button onclick="controlServer('${server.container_id}', 'restart')" 
                                    class="flex-1 py-2 px-3 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-sm font-medium transition">
                                Restart
                            </button>
                        </div>
                        
                        <div class="flex space-x-2 mt-3">
                            <a href="?page=servers&container=${server.container_id}" 
                               class="flex-1 py-2 px-3 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm font-medium transition text-center">
                                Console
                            </a>
                            <a href="?page=server-details&container=${server.container_id}" 
                               class="flex-1 py-2 px-3 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition text-center">
                                Details
                            </a>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

function updateStats(servers) {
    const total = servers.length;
    const running = servers.filter(s => s.status === 'running').length;
    const stopped = servers.filter(s => s.status === 'stopped').length;
    
    document.getElementById('totalServers').textContent = total;
    document.getElementById('runningServers').textContent = running;
    document.getElementById('stoppedServers').textContent = stopped;
}

function getStatusClass(status) {
    switch (status) {
        case 'running': return 'status-running';
        case 'stopped': return 'status-stopped';
        default: return 'status-offline';
    }
}

async function controlServer(containerId, action) {
    const result = await apiCall(action, { container_id: containerId });
    
    if (result.success) {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)} command sent successfully`, 'success');
        // Refresh servers after a delay to show updated status
        setTimeout(loadServers, 1000);
    }
}

function viewLogs(containerId) {
    window.location.href = `?page=servers&container=${containerId}`;
}
</script>