<?php
$containerId = $_GET['container'] ?? '';
$serverId = $_GET['server'] ?? '';

// Function to get status class for CSS
function getStatusClass($status) {
    switch ($status) {
        case 'running': return 'status-running';
        case 'stopped': return 'status-stopped';
        default: return 'status-offline';
    }
}

// Fetch server details if container ID is provided
$serverDetails = null;
if ($containerId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.*, u.username 
        FROM servers s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.container_id = ?
    ");
    $stmt->execute([$containerId]);
    $serverDetails = $stmt->fetch();
}
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Server Details</h1>
        <a href="?page=servers" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition">
            ← Back to Console
        </a>
    </div>
    
    <?php if ($serverDetails): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Server Info Card -->
        <div class="lg:col-span-2 glass rounded-xl border border-slate-700">
            <div class="p-6 border-b border-slate-700">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold"><?= htmlspecialchars($serverDetails['name']) ?></h2>
                        <p class="text-slate-400"><?= htmlspecialchars($serverDetails['description'] ?: 'No description') ?></p>
                    </div>
                    <div class="flex items-center">
                        <div class="status-dot <?= getStatusClass($serverDetails['status']) ?> mr-2"></div>
                        <span class="capitalize"><?= $serverDetails['status'] ?></span>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-medium text-slate-300 mb-3">Basic Information</h3>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Container ID</dt>
                                <dd class="font-mono text-sm"><?= substr(htmlspecialchars($serverDetails['container_id']), 0, 12) ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Owner</dt>
                                <dd><?= htmlspecialchars($serverDetails['username'] ?: 'Unassigned') ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Created</dt>
                                <dd><?= date('M j, Y g:i A', strtotime($serverDetails['created_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-slate-300 mb-3">Resource Limits</h3>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Memory</dt>
                                <dd><?= $serverDetails['memory_limit'] ?> MB</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">CPU</dt>
                                <dd><?= $serverDetails['cpu_limit'] ?>%</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Disk</dt>
                                <dd><?= $serverDetails['disk_limit'] ?> MB</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                <?php if (!empty($serverDetails['allocated_ports'])): ?>
                <div class="mt-6">
                    <h3 class="font-medium text-slate-300 mb-3">Allocated Ports</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($serverDetails['allocated_ports'] as $port): ?>
                            <span class="px-3 py-1 bg-slate-700 rounded text-sm">#<?= $port ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($serverDetails['environment'])): ?>
                <div class="mt-6">
                    <h3 class="font-medium text-slate-300 mb-3">Environment Variables</h3>
                    <div class="space-y-2">
                        <?php foreach ($serverDetails['environment'] as $key => $value): ?>
                            <div class="flex justify-between bg-slate-800 rounded px-3 py-2">
                                <span class="font-mono text-sm"><?= htmlspecialchars($key) ?></span>
                                <span class="font-mono text-sm text-slate-400"><?= htmlspecialchars($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-6 flex space-x-3">
                    <button onclick="controlServer('<?= $serverDetails['container_id'] ?>', 'start')" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition">
                        Start
                    </button>
                    <button onclick="controlServer('<?= $serverDetails['container_id'] ?>', 'stop')" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition">
                        Stop
                    </button>
                    <button onclick="controlServer('<?= $serverDetails['container_id'] ?>', 'restart')" 
                            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-sm font-medium transition">
                        Restart
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Server Stats Card -->
        <div class="glass rounded-xl border border-slate-700">
            <div class="p-6 border-b border-slate-700">
                <h2 class="text-xl font-semibold">Real-time Stats</h2>
            </div>
            <div class="p-6 space-y-4" id="statsContainer">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    <p class="mt-2 text-slate-400">Loading stats...</p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="glass rounded-xl p-12 text-center border border-slate-700">
        <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-lg font-medium text-slate-300 mb-2">Server Not Found</h3>
        <p class="text-slate-500 mb-6">The requested server does not exist or you don't have access to it.</p>
        <a href="?page=dashboard" class="inline-block px-6 py-3 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
            Go to Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
let statsInterval;

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($serverDetails): ?>
    // Load initial stats
    loadStats('<?= $serverDetails['container_id'] ?>');
    
    // Set up periodic stats update
    statsInterval = setInterval(function() {
        loadStats('<?= $serverDetails['container_id'] ?>');
    }, 5000); // Update every 5 seconds
    
    // Clear interval when leaving page
    window.addEventListener('beforeunload', () => {
        if (statsInterval) clearInterval(statsInterval);
    });
    <?php endif; ?>
});

async function loadStats(containerId) {
    const result = await apiCall('get_server_stats', { container_id: containerId });
    
    if (result.success) {
        const statsContainer = document.getElementById('statsContainer');
        const stats = result.stats;
        
        statsContainer.innerHTML = `
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-400">CPU Usage</span>
                        <span class="font-medium">\${stats.cpu_percent}</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: \${parseFloat(stats.cpu_percent) || 0}%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-400">Memory Usage</span>
                        <span class="font-medium">\${stats.memory_usage.split(' ')[0]}</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: \${Math.min(100, parseFloat(stats.memory_usage.split('/')[0].replace('MiB', '').replace('GiB', '')) * 100 / 1024) || 0}%"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-700">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">
                            \${stats.network_io.split(' ')[0]}
                        </div>
                        <div class="text-xs text-slate-400">Network</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-secondary">
                            \${stats.block_io.split(' ')[0]}
                        </div>
                        <div class="text-xs text-slate-400">Block I/O</div>
                    </div>
                </div>
            </div>
        `;
    }
}

async function controlServer(containerId, action) {
    const result = await apiCall(action, { container_id: containerId });
    
    if (result.success) {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)} command sent successfully`, 'success');
        // Reload page to update status
        setTimeout(() => location.reload(), 1000);
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'running': return 'status-running';
        case 'stopped': return 'status-stopped';
        default: return 'status-offline';
    }
}
</script>