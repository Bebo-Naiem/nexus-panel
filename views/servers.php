<?php
$containerId = $_GET['container'] ?? '';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Server Console</h1>
        <a href="?page=dashboard" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition">
            ← Back to Dashboard
        </a>
    </div>
    
    <?php if ($containerId): ?>
    <div class="glass rounded-xl border border-slate-700">
        <div class="p-6 border-b border-slate-700">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">Container: <?= htmlspecialchars($containerId) ?></h2>
                <div class="flex space-x-2">
                    <button onclick="controlServer('<?= $containerId ?>', 'start')" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition">
                        Start
                    </button>
                    <button onclick="controlServer('<?= $containerId ?>', 'stop')" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition">
                        Stop
                    </button>
                    <button onclick="controlServer('<?= $containerId ?>', 'restart')" 
                            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-sm font-medium transition">
                        Restart
                    </button>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <div class="terminal rounded-lg p-4 h-96 overflow-y-auto font-mono text-sm leading-relaxed">
                <div id="logContent" class="whitespace-pre-wrap">
                    <span class="text-slate-500">Initializing console...</span>
                </div>
            </div>
            
            <div class="mt-4 flex justify-between items-center">
                <div class="text-sm text-slate-400">
                    Auto-refreshing every 2 seconds
                </div>
                <button onclick="loadLogs()" class="px-4 py-2 bg-primary hover:bg-opacity-90 rounded-lg text-sm font-medium transition">
                    Refresh Now
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="glass rounded-xl p-12 text-center border border-slate-700">
        <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-lg font-medium text-slate-300 mb-2">No Server Selected</h3>
        <p class="text-slate-500 mb-6">Please select a server from your dashboard to view its console.</p>
        <a href="?page=dashboard" class="inline-block px-6 py-3 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
            Go to Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
let logPollingInterval;

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($containerId): ?>
    // Start polling for logs
    loadLogs();
    logPollingInterval = setInterval(loadLogs, 2000);
    
    // Clear interval when leaving page
    window.addEventListener('beforeunload', () => {
        if (logPollingInterval) clearInterval(logPollingInterval);
    });
    <?php endif; ?>
});

async function loadLogs() {
    <?php if ($containerId): ?>
    const result = await apiCall('get_logs', { container_id: '<?= $containerId ?>' });
    
    if (result.success) {
        const logContent = document.getElementById('logContent');
        logContent.innerHTML = result.logs ? 
            `<span class="text-green-400">${escapeHtml(result.logs)}</span>` : 
            '<span class="text-slate-500">No logs available</span>';
        
        // Auto-scroll to bottom
        logContent.parentElement.scrollTop = logContent.parentElement.scrollHeight;
    }
    <?php endif; ?>
}

async function controlServer(containerId, action) {
    const result = await apiCall(action, { container_id: containerId });
    
    if (result.success) {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)} command sent successfully`, 'success');
        // Reload logs after control action
        setTimeout(loadLogs, 1000);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>