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
    'get_server_stats' => 'handleGetServerStats',
    'list_eggs' => 'handleListEggs',
    'get_egg' => 'handleGetEgg',
    'create_egg' => 'handleCreateEgg',
    'update_egg' => 'handleUpdateEgg',
    'delete_egg' => 'handleDeleteEgg',
    'get_smtp_config' => 'handleGetSmtpConfig',
    'save_smtp_config' => 'handleSaveSmtpConfig',
    'test_smtp' => 'handleTestSmtp',
    'list_email_templates' => 'handleListEmailTemplates',
    'get_email_template' => 'handleGetEmailTemplate',
    'save_email_template' => 'handleSaveEmailTemplate',
    'create_user_admin' => 'handleCreateUserAdmin',
    'import_egg' => 'handleImportEgg',
    'list_egg_files' => 'handleListEggFiles',
    'get_egg_file' => 'handleGetEggFile',
    'upload_egg_file' => 'handleUploadEggFile',
    'wings_list_servers' => 'handleWingsListServers',
    'wings_server_details' => 'handleWingsServerDetails',
    'wings_start_server' => 'handleWingsStartServer',
    'wings_stop_server' => 'handleWingsStopServer',
    'wings_restart_server' => 'handleWingsRestartServer',
    'wings_kill_server' => 'handleWingsKillServer',
    'wings_server_logs' => 'handleWingsServerLogs',
    'wings_send_command' => 'handleWingsSendCommand',
    'wings_server_stats' => 'handleWingsServerStats',
    'wings_system_resources' => 'handleWingsSystemResources',
    'check_update' => 'handleCheckUpdate',
    'perform_update' => 'handlePerformUpdate'/*,
    
    // Email Routes
    'get_smtp_config' => 'handleGetSmtpConfig',
    'save_smtp_config' => 'handleSaveSmtpConfig',
    'test_smtp' => 'handleTestSmtp',
    'list_email_templates' => 'handleListEmailTemplates',
    'get_email_template' => 'handleGetEmailTemplate',
    'save_email_template' => 'handleSaveEmailTemplate'*/
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
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $ownerId = $_POST['owner_id'] ?? 0;
    $eggId = $_POST['egg_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $image = $_POST['image'] ?? '';
    $description = $_POST['description'] ?? '';
    $memory = $_POST['memory'] ?? Config::DEFAULT_MEMORY;
    $cpuLimit = $_POST['cpu_limit'] ?? Config::DEFAULT_CPU_LIMIT;
    $diskSpace = $_POST['disk_space'] ?? Config::DEFAULT_DISK_SPACE;
    
    // Validate required fields
    if (empty($ownerId) || empty($eggId) || empty($name) || empty($image)) {
        throw new Exception('Owner, egg type, server name, and image are required');
    }
    
    // Validate that owner exists and is not admin
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$ownerId]);
    $owner = $stmt->fetch();
    
    if (!$owner) {
        throw new Exception('Invalid owner selected (ID Rec: ' . htmlspecialchars((string)$ownerId) . ')');
    }
    
    // Validate that egg exists
    $stmt = $pdo->prepare("SELECT id FROM eggs WHERE id = ?");
    $stmt->execute([$eggId]);
    $egg = $stmt->fetch();
    
    if (!$egg) {
        throw new Exception('Invalid egg type selected');
    }
    
    // Parse environment and ports
    $environment = [];
    $ports = [];
    
    if (!empty($_POST['environment'])) {
        $environment = json_decode($_POST['environment'], true);
        if (!is_array($environment)) {
            $environment = [];
        }
    }
    
    if (!empty($_POST['ports'])) {
        $ports = json_decode($_POST['ports'], true);
        if (!is_array($ports)) {
            $ports = [];
        }
    }
    
    // Create server record first
    $stmt = $pdo->prepare(
        "INSERT INTO servers (name, container_id, user_id, egg_id, description, memory_limit, cpu_limit, disk_limit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    // Generate temporary container ID (will be replaced by actual container ID)
    $tempContainerId = 'temp_' . uniqid();
    
    $stmt->execute([
        $name,
        $tempContainerId,
        $ownerId,
        $eggId,
        $description,
        $memory,
        $cpuLimit,
        $diskSpace
    ]);
    
    $serverId = $pdo->lastInsertId();
    
    // Store environment variables
    if (!empty($environment)) {
        $stmt = $pdo->prepare("INSERT INTO server_variables (server_id, variable_key, variable_value) VALUES (?, ?, ?)");
        foreach ($environment as $key => $value) {
            $stmt->execute([$serverId, $key, $value]);
        }
    }
    
    // Store port mappings
    if (!empty($ports)) {
        $stmt = $pdo->prepare("INSERT INTO server_allocations (server_id, ip_address, port) VALUES (?, ?, ?)");
        foreach ($ports as $hostPort => $containerPort) {
            $stmt->execute([$serverId, '0.0.0.0', $hostPort]);
        }
    }
    
    // Create actual Docker container
    $serverManager = new ServerManager($pdo);
    $settings = [
        'environment' => $environment,
        'ports' => $ports,
        'memory' => $memory . 'm',
        'cpu_limit' => $cpuLimit
    ];
    
    try {
        $result = $serverManager->createServer($ownerId, $name, $image, $settings);
        
        if ($result['success']) {
            // Update container ID in database
            $stmt = $pdo->prepare("UPDATE servers SET container_id = ? WHERE id = ?");
            $stmt->execute([$result['container_id'], $serverId]);
        }
        
        return $result;
    } catch (Exception $e) {
        // Clean up database entry if container creation fails
        $stmt = $pdo->prepare("DELETE FROM servers WHERE id = ?");
        $stmt->execute([$serverId]);
        
        throw new Exception('Failed to create container: ' . $e->getMessage());
    }
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

// Egg management functions
function handleListEggs() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->query("SELECT id, name, description, docker_image, created_at FROM eggs ORDER BY name ASC");
    $eggs = $stmt->fetchAll();
    
    return ['success' => true, 'eggs' => $eggs];
}

function handleGetEgg() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $eggId = $_POST['egg_id'] ?? $_GET['egg_id'] ?? 0;
    
    if (empty($eggId)) {
        throw new Exception('Egg ID is required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM eggs WHERE id = ?");
    $stmt->execute([$eggId]);
    $egg = $stmt->fetch();
    
    if (!$egg) {
        throw new Exception('Egg not found');
    }
    
    // Parse JSON fields
    $egg['vars'] = json_decode($egg['vars'], true) ?: [];
    $egg['config_files'] = json_decode($egg['config_files'], true) ?: [];
    $egg['config_startup'] = json_decode($egg['config_startup'], true) ?: [];
    $egg['config_logs'] = json_decode($egg['config_logs'], true) ?: [];
    
    return ['success' => true, 'egg' => $egg];
}

function handleCreateEgg() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $docker_image = $_POST['docker_image'] ?? '';
    $startup_command = $_POST['startup_command'] ?? '';
    
    if (empty($name) || empty($docker_image)) {
        throw new Exception('Name and Docker image are required');
    }
    
    // Prepare JSON fields
    $config_files = json_encode($_POST['config_files'] ?? []);
    $config_startup = json_encode($_POST['config_startup'] ?? []);
    $config_logs = json_encode($_POST['config_logs'] ?? []);
    $config_stop = $_POST['config_stop'] ?? 'stop';
    $vars = json_encode($_POST['vars'] ?? []);
    
    $stmt = $pdo->prepare(
        "INSERT INTO eggs (name, description, docker_image, startup_command, config_files, config_startup, config_logs, config_stop, vars) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->execute([
        $name,
        $description,
        $docker_image,
        $startup_command,
        $config_files,
        $config_startup,
        $config_logs,
        $config_stop,
        $vars
    ]);
    
    $eggId = $pdo->lastInsertId();
    
    return ['success' => true, 'message' => 'Egg created successfully', 'egg_id' => $eggId];
}

function handleUpdateEgg() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $eggId = $_POST['egg_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $docker_image = $_POST['docker_image'] ?? '';
    $startup_command = $_POST['startup_command'] ?? '';
    
    if (empty($eggId) || empty($name) || empty($docker_image)) {
        throw new Exception('Egg ID, name, and Docker image are required');
    }
    
    // Prepare JSON fields
    $config_files = json_encode($_POST['config_files'] ?? []);
    $config_startup = json_encode($_POST['config_startup'] ?? []);
    $config_logs = json_encode($_POST['config_logs'] ?? []);
    $config_stop = $_POST['config_stop'] ?? 'stop';
    $vars = json_encode($_POST['vars'] ?? []);
    
    $stmt = $pdo->prepare(
        "UPDATE eggs SET 
        name = ?, description = ?, docker_image = ?, startup_command = ?, 
        config_files = ?, config_startup = ?, config_logs = ?, config_stop = ?, vars = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?"
    );
    
    $stmt->execute([
        $name,
        $description,
        $docker_image,
        $startup_command,
        $config_files,
        $config_startup,
        $config_logs,
        $config_stop,
        $vars,
        $eggId
    ]);
    
    return ['success' => true, 'message' => 'Egg updated successfully'];
}

function handleDeleteEgg() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $eggId = $_POST['egg_id'] ?? 0;
    
    if (empty($eggId)) {
        throw new Exception('Egg ID is required');
    }
    
    // Check if egg is being used by any servers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM servers WHERE egg_id = ?");
    $stmt->execute([$eggId]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Cannot delete egg: it is being used by servers');
    }
    
    $stmt = $pdo->prepare("DELETE FROM eggs WHERE id = ?");
    $stmt->execute([$eggId]);
    
    return ['success' => true, 'message' => 'Egg deleted successfully'];
}

// Email management functions
function handleGetSmtpConfig() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->query("SELECT * FROM email_config ORDER BY is_active DESC, created_at DESC LIMIT 1");
    $config = $stmt->fetch();
    
    // Hide password for security
    if ($config && isset($config['smtp_password'])) {
        $config['smtp_password'] = str_repeat('*', strlen($config['smtp_password']));
    }
    
    return ['success' => true, 'config' => $config];
}

function handleSaveSmtpConfig() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $requiredFields = ['smtp_host', 'smtp_port', 'from_email', 'from_name'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Deactivate existing configs
    $pdo->query("UPDATE email_config SET is_active = 0");
    
    $stmt = $pdo->prepare(
        "INSERT INTO email_config (
            smtp_host, smtp_port, smtp_username, smtp_password, 
            smtp_encryption, from_email, from_name, is_active, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)"
    );
    
    $result = $stmt->execute([
        $_POST['smtp_host'],
        $_POST['smtp_port'],
        $_POST['smtp_username'] ?? '',
        $_POST['smtp_password'] ?? '',
        $_POST['smtp_encryption'] ?? 'tls',
        $_POST['from_email'],
        $_POST['from_name']
    ]);
    
    return ['success' => true, 'message' => 'SMTP configuration saved successfully'];
}

function handleTestSmtp() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    require_once 'EmailManager.php';
    $emailManager = new EmailManager($pdo);
    
    $result = $emailManager->testConnection();
    
    return $result;
}

function handleListEmailTemplates() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->query("SELECT id, name, subject, is_active, created_at FROM email_templates ORDER BY name ASC");
    $templates = $stmt->fetchAll();
    
    return ['success' => true, 'templates' => $templates];
}

function handleGetEmailTemplate() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $templateName = $_POST['template_name'] ?? $_GET['template_name'] ?? '';
    
    if (empty($templateName)) {
        throw new Exception('Template name is required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE name = ?");
    $stmt->execute([$templateName]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Template not found');
    }
    
    return ['success' => true, 'template' => $template];
}

function handleSaveEmailTemplate() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $requiredFields = ['name', 'subject', 'body'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $stmt = $pdo->prepare(
        "INSERT OR REPLACE INTO email_templates (name, subject, body, is_html, is_active, updated_at) 
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
    );
    
    $result = $stmt->execute([
        $_POST['name'],
        $_POST['subject'],
        $_POST['body'],
        isset($_POST['is_html']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0
    ]);
    
    return ['success' => true, 'message' => 'Email template saved successfully'];
}

function handleCreateUserAdmin() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('Username, email, and password are required');
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
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $passwordHash, $role]);
    
    // Send welcome email
    try {
        require_once 'EmailManager.php';
        $emailManager = new EmailManager($pdo);
        $emailManager->sendTemplateEmail(
            $email,
            $username,
            'welcome_user',
            [
                'username' => $username,
                'site_name' => Config::APP_NAME
            ]
        );
    } catch (Exception $e) {
        // Log email error but don't fail user creation
        error_log('Failed to send welcome email: ' . $e->getMessage());
    }
    
    return ['success' => true, 'message' => 'User created successfully'];
}

// Egg file management functions
function handleListEggFiles() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $eggsDir = __DIR__ . '/eggs';
    $eggFiles = [];
    
    if (is_dir($eggsDir)) {
        $files = scandir($eggsDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = $eggsDir . '/' . $file;
                $content = json_decode(file_get_contents($filePath), true);
                
                if ($content && isset($content['meta']['name'])) {
                    $eggFiles[] = [
                        'filename' => $file,
                        'name' => $content['meta']['name'],
                        'description' => $content['meta']['description'] ?? '',
                        'author' => $content['meta']['author'] ?? 'Unknown',
                        'version' => $content['meta']['version'] ?? 'N/A',
                        'docker_image' => $content['features']['docker_image'] ?? 'N/A',
                        'size' => filesize($filePath),
                        'modified' => date('Y-m-d H:i:s', filemtime($filePath))
                    ];
                }
            }
        }
    }
    
    return ['success' => true, 'egg_files' => $eggFiles];
}

function handleGetEggFile() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $filename = $_POST['filename'] ?? $_GET['filename'] ?? '';
    
    if (empty($filename) || !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $filename)) {
        throw new Exception('Invalid filename');
    }
    
    $filePath = __DIR__ . '/eggs/' . $filename;
    
    if (!file_exists($filePath)) {
        throw new Exception('Egg file not found');
    }
    
    $content = file_get_contents($filePath);
    $parsed = json_decode($content, true);
    
    if (!$parsed) {
        throw new Exception('Invalid JSON in egg file');
    }
    
    return ['success' => true, 'egg_data' => $parsed];
}

function handleImportEgg() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename) || !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $filename)) {
        throw new Exception('Invalid filename');
    }
    
    $filePath = __DIR__ . '/eggs/' . $filename;
    
    if (!file_exists($filePath)) {
        throw new Exception('Egg file not found');
    }
    
    $content = file_get_contents($filePath);
    $eggData = json_decode($content, true);
    
    if (!$eggData) {
        throw new Exception('Invalid JSON in egg file');
    }
    
    // Validate required fields
    if (!isset($eggData['meta']['name']) || !isset($eggData['features']['docker_image'])) {
        throw new Exception('Missing required fields in egg file');
    }
    
    // Insert or update egg in database
    $name = $eggData['meta']['name'];
    $description = $eggData['meta']['description'] ?? '';
    $dockerImage = $eggData['features']['docker_image'];
    $startupCommand = $eggData['features']['startup_command'] ?? '';
    $stopCommand = $eggData['features']['stop_command'] ?? 'stop';
    
    $configFiles = json_encode($eggData['config']['files'] ?? []);
    $configStartup = json_encode($eggData['config']['startup'] ?? []);
    $configLogs = json_encode($eggData['config']['logs'] ?? []);
    $configStop = $eggData['config']['stop'] ?? $stopCommand;
    $vars = json_encode($eggData['variables'] ?? []);
    
    // Check if egg with this name already exists
    $stmt = $pdo->prepare("SELECT id FROM eggs WHERE name = ?");
    $stmt->execute([$name]);
    $existingEgg = $stmt->fetch();
    
    if ($existingEgg) {
        // Update existing egg
        $stmt = $pdo->prepare(
            "UPDATE eggs SET 
            description = ?, 
            docker_image = ?, 
            startup_command = ?, 
            config_files = ?, 
            config_startup = ?, 
            config_logs = ?, 
            config_stop = ?, 
            vars = ?, 
            updated_at = CURRENT_TIMESTAMP 
            WHERE name = ?"
        );
        
        $stmt->execute([
            $description,
            $dockerImage,
            $startupCommand,
            $configFiles,
            $configStartup,
            $configLogs,
            $configStop,
            $vars,
            $name
        ]);
        
        return ['success' => true, 'message' => 'Egg updated successfully'];
    } else {
        // Insert new egg
        $stmt = $pdo->prepare(
            "INSERT INTO eggs (name, description, docker_image, startup_command, config_files, config_startup, config_logs, config_stop, vars) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $name,
            $description,
            $dockerImage,
            $startupCommand,
            $configFiles,
            $configStartup,
            $configLogs,
            $configStop,
            $vars
        ]);
        
        return ['success' => true, 'message' => 'Egg imported successfully'];
    }
}

function handleUploadEggFile() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    if (!isset($_FILES['egg_file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['egg_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        throw new Exception('File too large (max 10MB)');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($extension !== 'json') {
        throw new Exception('Only JSON files are allowed');
    }
    
    // Sanitize filename
    $basename = pathinfo($file['name'], PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
    $filename = $basename . '.json';
    
    $targetPath = __DIR__ . '/eggs/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Validate JSON content
    $content = file_get_contents($targetPath);
    $parsed = json_decode($content, true);
    
    if (!$parsed) {
        unlink($targetPath); // Remove invalid file
        throw new Exception('Uploaded file contains invalid JSON');
    }
    
    if (!isset($parsed['meta']['name']) || !isset($parsed['features']['docker_image'])) {
        unlink($targetPath); // Remove invalid file
        throw new Exception('Uploaded egg file is missing required fields');
    }
    
    return [
        'success' => true, 
        'message' => 'Egg file uploaded successfully',
        'filename' => $filename,
        'name' => $parsed['meta']['name']
    ];
}

// Wings Daemon Functions
function handleWingsListServers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    $servers = $wings->getServers();
    return ['success' => true, 'servers' => $servers];
}

function handleWingsServerDetails() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? $_GET['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    $server = $wings->getServer($serverId);
    if (!$server) {
        throw new Exception('Server not found');
    }
    
    return ['success' => true, 'server' => $server];
}

function handleWingsStartServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->startServer($serverId);
}

function handleWingsStopServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->stopServer($serverId);
}

function handleWingsRestartServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->restartServer($serverId);
}

function handleWingsKillServer() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->killServer($serverId);
}

function handleWingsServerLogs() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? $_GET['server_id'] ?? '';
    $lines = $_POST['lines'] ?? $_GET['lines'] ?? 100;
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    $logs = $wings->getServerLogs($serverId, (int)$lines);
    return ['success' => true, 'logs' => $logs];
}

function handleWingsSendCommand() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    $command = $_POST['command'] ?? '';
    if (empty($serverId) || empty($command)) {
        throw new Exception('Server ID and command required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->sendCommand($serverId, $command);
}

function handleWingsServerStats() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        throw new Exception('Server ID required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    return $wings->getServerStats($serverId);
}

function handleWingsSystemResources() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    require_once 'WingsDaemon.php';
    $wings = new WingsDaemon($pdo);
    
    $resources = $wings->getSystemResources();
    return ['success' => true, 'resources' => $resources];
}

// Update Management Functions
function handleCheckUpdate() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }

    if (!is_dir('.git')) {
        return [
            'success' => true,
            'current_hash' => 'Unknown (Not a git repo)',
            'remote_hash' => 'Unknown',
            'update_available' => false,
            'message' => 'System was not installed via Git. Updates must be done manually.'
        ];
    }

    // Get current hash
    $currentHash = trim(shell_exec('git rev-parse HEAD'));

    // Fetch remote
    shell_exec('git fetch origin');

    // Get remote hash
    $remoteHash = trim(shell_exec('git rev-parse origin/main'));

    $updateAvailable = ($currentHash !== $remoteHash);

    return [
        'success' => true,
        'current_hash' => $currentHash,
        'remote_hash' => $remoteHash,
        'update_available' => $updateAvailable,
        'message' => $updateAvailable ? 'New version available.' : 'System is up to date.'
    ];
}

function handlePerformUpdate() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }

    if (!is_dir('.git')) {
        throw new Exception('Not a git repository. Cannot update automatically.');
    }

    // Use exec to capture exit code to determine real success
    $output = [];
    $returnVar = 0;
    exec('git pull origin main 2>&1', $output, $returnVar);
    
    $outputString = implode("\n", $output);
    $success = ($returnVar === 0);

    return [
        'success' => $success,
        'output' => $outputString
    ];
}
?>
