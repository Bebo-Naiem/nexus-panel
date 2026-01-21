<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Panel - Wings Daemon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        dark: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .status-running { color: #10b981; }
        .status-stopped { color: #ef4444; }
        .status-starting { color: #f59e0b; }
        .status-stopping { color: #f59e0b; }
        .console-output {
            background-color: #1e2b3a;
            color: #c0cace;
            font-family: monospace;
            height: 300px;
            overflow-y: auto;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-dark-900 to-dark-800 min-h-screen text-white">
    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">
                <i class="fas fa-wind mr-3"></i>Wings Daemon
            </h1>
            <button onclick="refreshServers()" class="px-4 py-2 bg-primary hover:bg-secondary rounded-lg transition">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>

        <!-- System Resources -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass rounded-lg border border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-blue-500/20 mr-4">
                        <i class="fas fa-microchip text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">CPU</h3>
                        <p class="text-2xl font-bold" id="cpuUsage">--%</p>
                    </div>
                </div>
            </div>
            <div class="glass rounded-lg border border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-green-500/20 mr-4">
                        <i class="fas fa-memory text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Memory</h3>
                        <p class="text-2xl font-bold" id="memoryUsage">--%</p>
                    </div>
                </div>
            </div>
            <div class="glass rounded-lg border border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-yellow-500/20 mr-4">
                        <i class="fas fa-hdd text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Disk</h3>
                        <p class="text-2xl font-bold" id="diskUsage">--%</p>
                    </div>
                </div>
            </div>
            <div class="glass rounded-lg border border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-purple-500/20 mr-4">
                        <i class="fas fa-server text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Docker</h3>
                        <p class="text-2xl font-bold" id="dockerStatus">--</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Server List -->
        <div class="glass rounded-lg border border-slate-700 p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Managed Servers</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="py-3 px-4 text-left">Name</th>
                            <th class="py-3 px-4 text-left">Owner</th>
                            <th class="py-3 px-4 text-left">Type</th>
                            <th class="py-3 px-4 text-left">Status</th>
                            <th class="py-3 px-4 text-left">Memory</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="serversTableBody">
                        <!-- Server rows will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Console -->
        <div class="glass rounded-lg border border-slate-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Server Console</h2>
                <div class="flex space-x-2">
                    <select id="serverSelectConsole" class="px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                        <option value="">Select Server...</option>
                    </select>
                </div>
            </div>
            <div class="console-output mb-4" id="consoleOutput">
                <!-- Console output will be shown here -->
            </div>
            <div class="flex space-x-2">
                <input type="text" id="commandInput" placeholder="Enter command..." class="flex-1 px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                <button onclick="sendCommand()" class="px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-medium transition">
                    Send Command
                </button>
            </div>
        </div>
    </div>

    <script>
        let servers = [];

        // Load servers and system resources on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemResources();
            loadServers();
        });

        async function loadSystemResources() {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=wings_system_resources'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const resources = data.resources;
                    document.getElementById('cpuUsage').textContent = resources.cpu.load_average[0].toFixed(2) + '%';
                    document.getElementById('memoryUsage').textContent = resources.memory.percent + '%';
                    document.getElementById('diskUsage').textContent = resources.disk.percent + '%';
                    document.getElementById('dockerStatus').textContent = resources.docker.status;
                }
            } catch (error) {
                console.error('Error loading system resources:', error);
            }
        }

        async function loadServers() {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=wings_list_servers'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    servers = data.servers;
                    renderServersTable();
                    updateServerSelect();
                }
            } catch (error) {
                console.error('Error loading servers:', error);
            }
        }

        function renderServersTable() {
            const tbody = document.getElementById('serversTableBody');
            tbody.innerHTML = '';

            servers.forEach(server => {
                const row = document.createElement('tr');
                row.className = 'border-b border-slate-700 hover:bg-slate-800/30';
                row.innerHTML = `
                    <td class="py-3 px-4">${server.name}</td>
                    <td class="py-3 px-4">${server.owner_name}</td>
                    <td class="py-3 px-4">${server.egg_name}</td>
                    <td class="py-3 px-4">
                        <span class="status-${server.status}">
                            <i class="fas fa-circle mr-2"></i>${server.status}
                        </span>
                    </td>
                    <td class="py-3 px-4">${server.memory_limit} MB</td>
                    <td class="py-3 px-4">
                        <div class="flex space-x-2">
                            <button onclick="startServer('${server.id}')" class="px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-sm transition">
                                <i class="fas fa-play"></i>
                            </button>
                            <button onclick="stopServer('${server.id}')" class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-sm transition">
                                <i class="fas fa-stop"></i>
                            </button>
                            <button onclick="restartServer('${server.id}')" class="px-3 py-1 bg-yellow-600 hover:bg-yellow-700 rounded text-sm transition">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button onclick="killServer('${server.id}')" class="px-3 py-1 bg-red-800 hover:bg-red-900 rounded text-sm transition">
                                <i class="fas fa-skull"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateServerSelect() {
            const select = document.getElementById('serverSelectConsole');
            select.innerHTML = '<option value="">Select Server...</option>';
            
            servers.forEach(server => {
                const option = document.createElement('option');
                option.value = server.id;
                option.textContent = `${server.name} (${server.status})`;
                select.appendChild(option);
            });
        }

        async function startServer(serverId) {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=wings_start_server&server_id=${serverId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadServers();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error starting server:', error);
                alert('Error starting server: ' + error.message);
            }
        }

        async function stopServer(serverId) {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=wings_stop_server&server_id=${serverId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadServers();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error stopping server:', error);
                alert('Error stopping server: ' + error.message);
            }
        }

        async function restartServer(serverId) {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=wings_restart_server&server_id=${serverId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadServers();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error restarting server:', error);
                alert('Error restarting server: ' + error.message);
            }
        }

        async function killServer(serverId) {
            if (!confirm('Are you sure you want to kill this server? This will force stop it.')) {
                return;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=wings_kill_server&server_id=${serverId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadServers();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error killing server:', error);
                alert('Error killing server: ' + error.message);
            }
        }

        async function sendCommand() {
            const serverId = document.getElementById('serverSelectConsole').value;
            const command = document.getElementById('commandInput').value;
            
            if (!serverId) {
                alert('Please select a server');
                return;
            }
            
            if (!command) {
                alert('Please enter a command');
                return;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=wings_send_command&server_id=${serverId}&command=${encodeURIComponent(command)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    document.getElementById('commandInput').value = '';
                    // Optionally update console output
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error sending command:', error);
                alert('Error sending command: ' + error.message);
            }
        }

        async function refreshServers() {
            loadSystemResources();
            loadServers();
        }
    </script>
</body>
</html>