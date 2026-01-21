<?php
/**
 * Nexus Panel - Main Frontend Application
 * Game Server Management System Interface
 */

require_once 'db.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'user';

// Get current page
$page = $_GET['page'] ?? ($isLoggedIn ? 'dashboard' : 'login');

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Panel - Game Server Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass { backdrop-filter: blur(12px); background: rgba(15, 23, 42, 0.7); }
        .terminal { background-color: #0f172a; color: #10b981; font-family: 'Courier New', monospace; }
        .status-dot { width: 12px; height: 12px; border-radius: 50%; }
        .status-running { background-color: #10b981; box-shadow: 0 0 10px #10b981; }
        .status-stopped { background-color: #ef4444; }
        .status-offline { background-color: #64748b; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php if ($isLoggedIn): ?>
        <!-- Sidebar Layout -->
        <div class="flex h-screen">
            <!-- Sidebar -->
            <div class="w-64 bg-slate-800 border-r border-slate-700 flex flex-col">
                <div class="p-6 border-b border-slate-700">
                    <h1 class="text-2xl font-bold text-primary">Nexus Panel</h1>
                    <p class="text-sm text-slate-400 mt-1">Server Management</p>
                </div>
                
                <div class="p-4 border-b border-slate-700">
                    <a href="https://github.com/Bebo-Naiem/nexus-panel" target="_blank" class="flex items-center p-2 rounded-lg hover:bg-slate-700 transition text-slate-300">
                        <i class="fab fa-github text-xl mr-3"></i>
                        <span>GitHub Repository</span>
                    </a>
                </div>
                
                <nav class="flex-1 p-4">
                    <ul class="space-y-2">
                        <li>
                            <a href="?page=dashboard" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'dashboard' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="?page=servers" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'servers' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                </svg>
                                My Servers
                            </a>
                        </li>
                        <li>
                            <a href="?page=profile" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'profile' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile
                            </a>
                        </li>
                        
                        <?php if ($userRole === 'admin'): ?>
                        <li class="border-t border-slate-700 pt-4 mt-4">
                            <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Administration</h3>
                        </li>
                        <li>
                            <a href="?page=admin-users" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'admin-users' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                Manage Users
                            </a>
                        </li>
                        <li>
                            <a href="?page=admin-servers" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'admin-servers' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                </svg>
                                All Containers
                            </a>
                        </li>
                        <li>
                            <a href="?page=create-server" class="flex items-center p-3 rounded-lg hover:bg-slate-700 transition <?= $page === 'create-server' ? 'bg-slate-700 text-primary' : 'text-slate-300' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Create Server
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="p-4 border-t border-slate-700">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-400">Logged in as:</span>
                        <span class="px-2 py-1 bg-slate-700 rounded text-xs font-medium"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                    </div>
                    <a href="?logout=1" class="block w-full text-center py-2 px-4 bg-red-600 hover:bg-red-700 rounded-lg transition text-sm font-medium">
                        Logout
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="flex-1 overflow-auto">
                <div class="p-6">
                    <?php
                    switch ($page) {
                        case 'dashboard':
                            include 'views/dashboard.php';
                            break;
                        case 'servers':
                            include 'views/servers.php';
                            break;
                        case 'server-details':
                            include 'views/server-details.php';
                            break;
                        case 'profile':
                            include 'views/profile.php';
                            break;
                        case 'admin-users':
                            include 'views/admin-users.php';
                            break;
                        case 'admin-servers':
                            include 'views/admin-servers.php';
                            break;
                        case 'create-server':
                            include 'views/create-server.php';
                            break;
                        default:
                            include 'views/dashboard.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Login/Register Page -->
        <?php include 'views/login.php'; ?>
    <?php endif; ?>
    
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
    
    <script>
        // Global toast function
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 'bg-blue-600'
            }`;
            toast.innerHTML = `<div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">×</button>
            </div>`;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // AJAX helper function
        async function apiCall(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(data).forEach(key => formData.append(key, data[key]));
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (!result.success && result.error) {
                    showToast(result.error, 'error');
                }
                
                return result;
            } catch (error) {
                showToast('Network error occurred', 'error');
                return { error: 'Network error' };
            }
        }
    </script>
</body>
</html>