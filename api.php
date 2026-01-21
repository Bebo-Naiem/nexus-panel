<?php
/**
 * Nexus Panel - Backend Controller API
 * Handles all API requests for the game server management system
 */

require_once 'config.php';
require_once 'db.php';
require_once 'DockerManager.php';
require_once 'ServerManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Define API routes
$routes = [
    'login' => 'handleLogin',
    'register' => 'handleRegister',
    'update_profile' => 'handleUpdateProfile',
    'get_my_servers' => 'handleGetMyServers',
    'list_users' => 'handleListUsers',
    'delete_user' => 'handleDeleteUser',
    'list_all_containers' => 'handleListAllContainers',
    'assign_server' => 'handleAssignServer',
    'start' => 'handleStartServer',
    'stop' => 'handleStopServer',
    'restart' => 'handleRestartServer',
    'get_logs' => 'handleGetLogs',
    'create_server' => 'handleCreateServer',
    'delete_server' => 'handleDeleteServer',
    'get_images' => 'handleGetImages',
    'pull_image' => 'handlePullImage',
    'get_server_stats' => 'handleGetServerStats'
];

if (!isset($routes[$action])) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $result = call_user_func($routes[$action]);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// Authentication functions
function handleLogin() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new Exception('Invalid credentials');
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    return ['success' => true, 'message' => 'Login successful', 'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ]];
}

function handleRegister() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('All fields are required');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        throw new Exception('Username or email already exists');
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
    $stmt->execute([$username, $email, $passwordHash]);
    
    return ['success' => true, 'message' => 'Registration successful'];
}

function handleUpdateProfile() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $userId = $_SESSION['user_id'];
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($newPassword)) {
        throw new Exception('New password is required');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$passwordHash, $userId]);
    
    return ['success' => true, 'message' => 'Profile updated successfully'];
}

function handleGetMyServers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT s.*, u.username 
        FROM servers s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$userId]);
    $servers = $stmt->fetchAll();
    
    return ['success' => true, 'servers' => $servers];
}

function handleListUsers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    
    return ['success' => true, 'users' => $users];
}

function handleDeleteUser() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $userId = $_POST['user_id'] ?? 0;
    
    if ($userId == $_SESSION['user_id']) {
        throw new Exception('Cannot delete yourself');
    }
    
    // Remove servers assigned to this user
    $stmt = $pdo->prepare("UPDATE servers SET user_id = NULL WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    return ['success' => true, 'message' => 'User deleted successfully'];
}

function handleListAllContainers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    // Get all Docker containers
    $output = shell_exec('docker ps -a --format=\'{{.ID}}\t{{.Names}}\t{{.Status}}\' 2>&1');
    $lines = explode("\n", trim($output));
    
    $containers = [];
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parts = explode("\t", $line);
        if (count($parts) >= 3) {
            $container = [
                'id' => $parts[0],
                'name' => $parts[1],
                'status' => $parts[2]
            ];
            
            // Get server assignment info
            $stmt = $pdo->prepare("SELECT user_id FROM servers WHERE container_id = ?");
            $stmt->execute([$container['id']]);
            $server = $stmt->fetch();
            
            $container['assigned_user_id'] = $server ? $server['user_id'] : null;
            $containers[] = $container;
        }
    }
    
    return ['success' => true, 'containers' => $containers];
}

function handleAssignServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    $userId = $_POST['user_id'] ?? null;
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if container exists in Docker
    $output = shell_exec("docker ps -a --filter id=$containerId --format='{{.ID}}' 2>&1");
    $exists = !empty(trim($output));
    
    if (!$exists) {
        throw new Exception('Container does not exist');
    }
    
    // Check if server record already exists
    $stmt = $pdo->prepare("SELECT id FROM servers WHERE container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if ($server) {
        // Update existing server assignment
        $stmt = $pdo->prepare("UPDATE servers SET user_id = ? WHERE container_id = ?");
        $stmt->execute([$userId, $containerId]);
    } else {
        // Create new server record
        $stmt = $pdo->prepare("INSERT INTO servers (name, container_id, user_id) VALUES (?, ?, ?)");
        $stmt->execute(["Server-$containerId", $containerId, $userId]);
    }
    
    return ['success' => true, 'message' => 'Server assignment updated'];
}

function handleStartServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if user has permission to control this server
    $stmt = $pdo->prepare("SELECT s.user_id FROM servers s WHERE s.container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    if ($server['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied');
    }
    
    $output = shell_exec("docker start $containerId 2>&1");
    
    // Update server status
    $stmt = $pdo->prepare("UPDATE servers SET status = 'running' WHERE container_id = ?");
    $stmt->execute([$containerId]);
    
    return ['success' => true, 'message' => 'Server started successfully'];
}

function handleStopServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if user has permission to control this server
    $stmt = $pdo->prepare("SELECT s.user_id FROM servers s WHERE s.container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    if ($server['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied');
    }
    
    $output = shell_exec("docker stop $containerId 2>&1");
    
    // Update server status
    $stmt = $pdo->prepare("UPDATE servers SET status = 'stopped' WHERE container_id = ?");
    $stmt->execute([$containerId]);
    
    return ['success' => true, 'message' => 'Server stopped successfully'];
}

function handleRestartServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if user has permission to control this server
    $stmt = $pdo->prepare("SELECT s.user_id FROM servers s WHERE s.container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    if ($server['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied');
    }
    
    $output = shell_exec("docker restart $containerId 2>&1");
    
    return ['success' => true, 'message' => 'Server restarted successfully'];
}

function handleGetLogs() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if user has permission to control this server
    $stmt = $pdo->prepare("SELECT s.user_id FROM servers s WHERE s.container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    if ($server['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied');
    }
    
    $output = shell_exec("docker logs --tail 50 $containerId 2>&1");
    
    return ['success' => true, 'logs' => $output ?: 'No logs available'];
}

function handleCreateServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $userId = $_SESSION['user_id'];
    $name = $_POST['name'] ?? '';
    $image = $_POST['image'] ?? '';
    
    if (empty($name) || empty($image)) {
        throw new Exception('Server name and image are required');
    }
    
    $serverManager = new ServerManager($pdo);
    
    $settings = [
        'environment' => $_POST['environment'] ?? [],
        'ports' => $_POST['ports'] ?? [],
        'memory' => $_POST['memory'] ?? Config::DEFAULT_MEMORY . 'm',
        'cpu_limit' => $_POST['cpu_limit'] ?? Config::DEFAULT_CPU_LIMIT
    ];
    
    $result = $serverManager->createServer($userId, $name, $image, $settings);
    
    return $result;
}

function handleDeleteServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $serverId = $_POST['server_id'] ?? 0;
    $userId = $_SESSION['user_id'];
    
    if (empty($serverId)) {
        throw new Exception('Server ID is required');
    }
    
    $serverManager = new ServerManager($pdo);
    $result = $serverManager->deleteServer($serverId, $userId);
    
    return $result;
}

function handleGetImages() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $serverManager = new ServerManager($pdo);
    $images = $serverManager->getAvailableImages();
    
    return ['success' => true, 'images' => $images];
}

function handlePullImage() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $imageName = $_POST['image_name'] ?? '';
    
    if (empty($imageName)) {
        throw new Exception('Image name is required');
    }
    
    $serverManager = new ServerManager($pdo);
    $result = $serverManager->pullImage($imageName);
    
    return $result;
}

function handleGetServerStats() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $containerId = $_POST['container_id'] ?? '';
    
    if (empty($containerId)) {
        throw new Exception('Container ID is required');
    }
    
    // Check if user has permission to control this server
    $stmt = $pdo->prepare("SELECT s.user_id FROM servers s WHERE s.container_id = ?");
    $stmt->execute([$containerId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    if ($server['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied');
    }
    
    $docker = new DockerManager($pdo);
    $stats = $docker->getContainerStats($containerId);
    
    return ['success' => true, 'stats' => $stats];
}

?>