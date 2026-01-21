<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">System Update</h1>
        <div class="flex items-center space-x-2">
            <span id="currentVersion" class="text-sm font-mono bg-slate-800 px-3 py-1 rounded text-slate-400">
                Checking version...
            </span>
        </div>
    </div>
    
    <!-- Update Status Card -->
    <div class="glass rounded-xl border border-slate-700 p-8 text-center">
        <div id="statusIcon" class="mb-4">
            <svg class="w-16 h-16 mx-auto text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
        </div>
        
        <h2 id="statusTitle" class="text-2xl font-bold mb-2">Unknown Status</h2>
        <p id="statusMessage" class="text-slate-400 mb-6">Click "Check for Updates" to see if a new version is available.</p>
        
        <div class="flex justify-center space-x-4">
            <button onclick="checkForUpdates()" id="btnCheck" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Check for Updates
            </button>
            
            <button onclick="performUpdate()" id="btnUpdate" class="hidden px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-medium transition flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Update Now
            </button>
        </div>
    </div>
    
    <!-- Update Log -->
    <div id="logContainer" class="hidden">
        <h3 class="text-lg font-semibold mb-2">Update Log</h3>
        <div id="updateLog" class="terminal p-4 rounded-lg text-sm h-64 overflow-y-auto font-mono whitespace-pre-wrap"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkForUpdates(true); // Silent check on load
});

async function checkForUpdates(silent = false) {
    const btnCheck = document.getElementById('btnCheck');
    const btnUpdate = document.getElementById('btnUpdate');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statusIcon = document.getElementById('statusIcon');
    const currentVersion = document.getElementById('currentVersion');
    
    if (!silent) {
        btnCheck.disabled = true;
        btnCheck.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Checking...';
    }

    try {
        const result = await apiCall('check_update');
        
        currentVersion.textContent = result.current_hash ? result.current_hash.substring(0, 7) : 'Unknown';
        
        if (result.success) {
            if (result.update_available) {
                // Update Available
                statusTitle.textContent = 'Update Available';
                statusTitle.className = 'text-2xl font-bold mb-2 text-green-400';
                statusMessage.innerHTML = `Running version <span class="font-mono text-slate-300 mx-1">${result.current_hash.substring(0, 7)}</span><br>Latest version <span class="font-mono text-green-400 mx-1">${result.remote_hash.substring(0, 7)}</span><br><br><span class="text-sm text-slate-500">${result.message}</span>`;
                statusIcon.innerHTML = '<svg class="w-16 h-16 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                
                btnUpdate.classList.remove('hidden');
            } else {
                // Up to Date
                statusTitle.textContent = 'System is Up to Date';
                statusTitle.className = 'text-2xl font-bold mb-2 text-slate-200';
                statusMessage.textContent = 'You are running the latest version of Nexus Panel.';
                statusIcon.innerHTML = '<svg class="w-16 h-16 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                
                btnUpdate.classList.add('hidden');
            }
        } else {
            // Error
            statusTitle.textContent = 'Update Check Failed';
            statusTitle.className = 'text-2xl font-bold mb-2 text-red-400';
            statusMessage.textContent = result.error || 'Could not verify update status. Check git configuration.';
            statusIcon.innerHTML = '<svg class="w-16 h-16 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        }
    } catch (error) {
        console.error('Update check failed:', error);
        if (!silent) showToast('Failed to check for updates', 'error');
    } finally {
        if (!silent) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Check Again';
        }
    }
}

async function performUpdate() {
    if (!confirm('Are you sure you want to update? This will overwrite core files. Make sure you have backups.')) {
        return;
    }
    
    const btnUpdate = document.getElementById('btnUpdate');
    const logContainer = document.getElementById('logContainer');
    const updateLog = document.getElementById('updateLog');
    
    btnUpdate.disabled = true;
    btnUpdate.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Updating...';
    
    logContainer.classList.remove('hidden');
    updateLog.textContent = 'Starting update process...\n';
    
    try {
        const result = await apiCall('perform_update');
        
        updateLog.textContent += result.output + '\n';
        
        if (result.success) {
            updateLog.textContent += '\nUPDATE SUCCESSFUL! Reloading page in 3 seconds...';
            showToast('System updated successfully', 'success');
            setTimeout(() => window.location.reload(), 3000);
        } else {
            updateLog.textContent += '\nUPDATE FAILED: ' + (result.error || 'Unknown error');
            btnUpdate.disabled = false;
            btnUpdate.innerHTML = 'Retry Update';
        }
    } catch (error) {
        updateLog.textContent += '\nCRITICAL ERROR: ' + error;
        btnUpdate.disabled = false;
        btnUpdate.innerHTML = 'Retry Update';
    }
}
</script>

<?php endif; ?>
