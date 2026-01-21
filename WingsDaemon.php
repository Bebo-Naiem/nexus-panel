<?php
/**
 * Nexus Panel - Wings Daemon Simulator
 * Manages game server containers and provides Wings-like functionality
 */

require_once 'config.php';

class WingsDaemon {
    private $pdo;
    private $dockerManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->dockerManager = new DockerManager($pdo);
    }
    
    /**
     * Get all managed servers
     */
    public function getServers() {
        $stmt = $this->pdo->query("
            SELECT s.*, u.username as owner_name, e.name as egg_name
            FROM servers s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN eggs e ON s.egg_id = e.id
            WHERE s.status != 'deleted'
            ORDER BY s.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get server details by ID
     */
    public function getServer($serverId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.username as owner_name, e.name as egg_name, e.docker_image
            FROM servers s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN eggs e ON s.egg_id = e.id
            WHERE s.id = ?
        ");
        $stmt->execute([$serverId]);
        return $stmt->fetch();
    }
    
    /**
     * Start a server
     */
    public function startServer($serverId) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            // Get server variables
            $vars = $this->getServerVariables($serverId);
            
            // Start the container
            $result = $this->dockerManager->startContainer($server['container_id']);
            
            if ($result['success']) {
                // Update server status
                $this->updateServerStatus($serverId, 'running');
                $this->logServerActivity($serverId, 'start', 'Server started successfully');
                return ['success' => true, 'message' => 'Server started successfully'];
            } else {
                throw new Exception($result['error']);
            }
        } catch (Exception $e) {
            $this->logServerActivity($serverId, 'start_failed', 'Failed to start server: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Stop a server
     */
    public function stopServer($serverId) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            $result = $this->dockerManager->stopContainer($server['container_id']);
            
            if ($result['success']) {
                $this->updateServerStatus($serverId, 'offline');
                $this->logServerActivity($serverId, 'stop', 'Server stopped successfully');
                return ['success' => true, 'message' => 'Server stopped successfully'];
            } else {
                throw new Exception($result['error']);
            }
        } catch (Exception $e) {
            $this->logServerActivity($serverId, 'stop_failed', 'Failed to stop server: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restart a server
     */
    public function restartServer($serverId) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            // Stop the server first
            $this->stopServer($serverId);
            
            // Wait a moment
            sleep(2);
            
            // Start the server
            $result = $this->startServer($serverId);
            
            $this->logServerActivity($serverId, 'restart', 'Server restarted successfully');
            return $result;
        } catch (Exception $e) {
            $this->logServerActivity($serverId, 'restart_failed', 'Failed to restart server: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Kill a server forcefully
     */
    public function killServer($serverId) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            $result = $this->dockerManager->killContainer($server['container_id']);
            
            if ($result['success']) {
                $this->updateServerStatus($serverId, 'offline');
                $this->logServerActivity($serverId, 'kill', 'Server killed forcefully');
                return ['success' => true, 'message' => 'Server killed successfully'];
            } else {
                throw new Exception($result['error']);
            }
        } catch (Exception $e) {
            $this->logServerActivity($serverId, 'kill_failed', 'Failed to kill server: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get server logs
     */
    public function getServerLogs($serverId, $lines = 100) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            $result = $this->dockerManager->getContainerLogs($server['container_id'], $lines);
            return $result;
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve logs: ' . $e->getMessage());
        }
    }
    
    /**
     * Send command to server console
     */
    public function sendCommand($serverId, $command) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            $result = $this->dockerManager->executeCommand($server['container_id'], $command);
            
            if ($result['success']) {
                $this->logServerActivity($serverId, 'command', "Executed command: $command");
                return ['success' => true, 'message' => 'Command executed successfully'];
            } else {
                throw new Exception($result['error']);
            }
        } catch (Exception $e) {
            $this->logServerActivity($serverId, 'command_failed', "Failed to execute command '$command': " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get server statistics
     */
    public function getServerStats($serverId) {
        $server = $this->getServer($serverId);
        if (!$server) {
            throw new Exception('Server not found');
        }
        
        try {
            $stats = $this->dockerManager->getContainerStats($server['container_id']);
            
            // Add resource limits from server config
            $stats['limits'] = [
                'memory_mb' => $server['memory_limit'],
                'cpu_percent' => $server['cpu_limit'],
                'disk_mb' => $server['disk_limit']
            ];
            
            return ['success' => true, 'stats' => $stats];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get server variables/environment
     */
    public function getServerVariables($serverId) {
        $stmt = $this->pdo->prepare("
            SELECT variable_key, variable_value 
            FROM server_variables 
            WHERE server_id = ?
        ");
        $stmt->execute([$serverId]);
        $rows = $stmt->fetchAll();
        
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['variable_key']] = $row['variable_value'];
        }
        
        return $variables;
    }
    
    /**
     * Update server status
     */
    private function updateServerStatus($serverId, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE servers 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$status, $serverId]);
    }
    
    /**
     * Log server activity
     */
    private function logServerActivity($serverId, $action, $description) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (server_id, action, description, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$serverId, $action, $description]);
    }
    
    /**
     * Get system resources
     */
    public function getSystemResources() {
        $resources = [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'docker' => $this->getDockerInfo()
        ];
        
        return $resources;
    }
    
    /**
     * Get CPU usage
     */
    private function getCpuUsage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                'load_average' => $load,
                'cores' => $this->getCpuCores()
            ];
        }
        return ['load_average' => [0, 0, 0], 'cores' => 1];
    }
    
    /**
     * Get CPU cores
     */
    private function getCpuCores() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 1; // Simplified for Windows
        } else {
            return (int)shell_exec('nproc');
        }
    }
    
    /**
     * Get memory usage
     */
    private function getMemoryUsage() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows - simplified approach
            return [
                'total' => 0,
                'used' => 0,
                'free' => 0,
                'percent' => 0
            ];
        } else {
            // Linux approach
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            
            $total_kb = (int)($total[1] ?? 0);
            $available_kb = (int)($available[1] ?? 0);
            $used_kb = $total_kb - $available_kb;
            
            return [
                'total' => round($total_kb / 1024, 2), // MB
                'used' => round($used_kb / 1024, 2),   // MB
                'free' => round($available_kb / 1024, 2), // MB
                'percent' => $total_kb > 0 ? round(($used_kb / $total_kb) * 100, 2) : 0
            ];
        }
    }
    
    /**
     * Get disk usage
     */
    private function getDiskUsage() {
        $disk_total = disk_total_space('/');
        $disk_free = disk_free_space('/');
        $disk_used = $disk_total - $disk_free;
        
        return [
            'total' => round($disk_total / (1024 * 1024 * 1024), 2), // GB
            'used' => round($disk_used / (1024 * 1024 * 1024), 2),   // GB
            'free' => round($disk_free / (1024 * 1024 * 1024), 2),   // GB
            'percent' => $disk_total > 0 ? round(($disk_used / $disk_total) * 100, 2) : 0
        ];
    }
    
    /**
     * Get Docker information
     */
    private function getDockerInfo() {
        try {
            $version = shell_exec('docker --version 2>&1');
            $info = shell_exec('docker info --format "{{.ContainersRunning}}/{{.Containers}} containers" 2>&1');
            
            return [
                'version' => trim($version),
                'containers_info' => trim($info),
                'status' => 'running'
            ];
        } catch (Exception $e) {
            return [
                'version' => 'unknown',
                'containers_info' => 'unknown',
                'status' => 'error'
            ];
        }
    }
}
?>