<?php
/**
 * Nexus Panel - Server Management System
 * Advanced server creation and management
 */

require_once 'config.php';
require_once 'DockerManager.php';
require_once 'db.php';

class ServerManager {
    private $pdo;
    private $docker;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->docker = new DockerManager($pdo);
    }
    
    /**
     * Create a new server/container
     */
    public function createServer($userId, $name, $image, $eggId, $settings = []) {
        // Check if user can create more servers
        $userServerCount = $this->getUserServerCount($userId);
        if ($userServerCount >= Config::MAX_SERVERS_PER_USER) {
            throw new Exception('Maximum server limit reached');
        }
        
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
            // Prepare container settings
            $containerName = $this->generateContainerName($userId, $name);
            $envVars = is_array($settings['environment'] ?? null) ? $settings['environment'] : (json_decode($settings['environment'] ?? '{}', true) ?: []);
            $portMappings = is_array($settings['ports'] ?? null) ? $settings['ports'] : (json_decode($settings['ports'] ?? '{}', true) ?: []);
            $resources = [
                'memory' => ($settings['memory'] ?? Config::DEFAULT_MEMORY) . 'm',
                'cpu' => ($settings['cpu_limit'] ?? Config::DEFAULT_CPU_LIMIT) / 100
            ];
            
            // Create the container
            $result = $this->docker->createContainer(
                $containerName,
                $image,
                $envVars,
                $portMappings,
                $resources
            );
            
            if (!$result['success']) {
                throw new Exception('Failed to create container: ' . $result['error']);
            }
            
            // Store server in database
            $stmt = $this->pdo->prepare("
                INSERT INTO servers (name, container_id, user_id, egg_id, status, description, memory_limit, cpu_limit, disk_limit) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $description = $settings['description'] ?? '';
            $stmt->execute([
                $name, 
                $result['container_id'], 
                $userId, 
                $eggId,
                'stopped',
                $description,
                $settings['memory'] ?? Config::DEFAULT_MEMORY,
                $settings['cpu_limit'] ?? Config::DEFAULT_CPU_LIMIT,
                $settings['disk_space'] ?? Config::DEFAULT_DISK_SPACE
            ]);
            
            $serverId = $this->pdo->lastInsertId();
            
            // Save environment variables
            foreach ($envVars as $key => $value) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO server_variables (server_id, variable_key, variable_value) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$serverId, $key, $value]);
            }
            
            // Save port allocations
            foreach ($portMappings as $hostPort => $containerPort) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO server_allocations (server_id, port) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$serverId, $hostPort]);
            }
            
            // Log activity
            $this->logActivity($userId, $serverId, 'server_create', "Server '$name' created");
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'server_id' => $serverId,
                'container_id' => $result['container_id']
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Delete a server
     */
    public function deleteServer($serverId, $userId) {
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
            // Check ownership
            $stmt = $this->pdo->prepare("
                SELECT container_id, name FROM servers WHERE id = ? AND (user_id = ? OR ? = (SELECT id FROM users WHERE role = 'admin'))
            ");
            $stmt->execute([$serverId, $userId, $userId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                throw new Exception('Server not found or access denied');
            }
            
            // Stop and remove container
            $this->docker->stopContainer($server['container_id']);
            $result = $this->docker->removeContainer($server['container_id'], true);
            
            if (!$result['success']) {
                throw new Exception('Failed to remove container: ' . $result['error']);
            }
            
            // Remove from database
            $stmt = $this->pdo->prepare("DELETE FROM servers WHERE id = ?");
            $stmt->execute([$serverId]);
            
            // Log activity
            $this->logActivity($userId, $serverId, 'server_delete', "Server '{$server['name']}' deleted");
            
            $this->pdo->commit();
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get user's servers
     */
    public function getUserServers($userId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.username 
            FROM servers s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$userId]);
        $servers = $stmt->fetchAll();
        
        // Add Docker stats to each server
        foreach ($servers as &$server) {
            $stats = $this->docker->getContainerStats($server['container_id']);
            $server['stats'] = $stats;
            
            // Get environment variables
            $envStmt = $this->pdo->prepare("SELECT variable_key, variable_value FROM server_variables WHERE server_id = ?");
            $envStmt->execute([$server['id']]);
            $server['environment'] = $envStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Get allocated ports
            $portStmt = $this->pdo->prepare("SELECT port FROM server_allocations WHERE server_id = ?");
            $portStmt->execute([$server['id']]);
            $allocatedPorts = $portStmt->fetchAll(PDO::FETCH_COLUMN);
            $server['allocated_ports'] = $allocatedPorts;
        }
        
        return $servers;
    }
    
    /**
     * Get all servers (admin only)
     */
    public function getAllServers() {
        $stmt = $this->pdo->query("
            SELECT s.*, u.username 
            FROM servers s 
            LEFT JOIN users u ON s.user_id = u.id 
            ORDER BY s.created_at DESC
        ");
        $servers = $stmt->fetchAll();
        
        // Add Docker stats to each server
        foreach ($servers as &$server) {
            $stats = $this->docker->getContainerStats($server['container_id']);
            $server['stats'] = $stats;
            
            // Get environment variables
            $envStmt = $this->pdo->prepare("SELECT variable_key, variable_value FROM server_variables WHERE server_id = ?");
            $envStmt->execute([$server['id']]);
            $server['environment'] = $envStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Get allocated ports
            $portStmt = $this->pdo->prepare("SELECT port FROM server_allocations WHERE server_id = ?");
            $portStmt->execute([$server['id']]);
            $allocatedPorts = $portStmt->fetchAll(PDO::FETCH_COLUMN);
            $server['allocated_ports'] = $allocatedPorts;
        }
        
        return $servers;
    }
    
    /**
     * Get user's server count
     */
    private function getUserServerCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM servers WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Generate unique container name
     */
    private function generateContainerName($userId, $name) {
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $cleanName = substr($cleanName, 0, 32);
        return "nexus_" . $userId . "_" . strtolower($cleanName) . "_" . uniqid();
    }
    
    /**
     * Control server state
     */
    public function controlServer($serverId, $userId, $action) {
        // Check ownership
        $stmt = $this->pdo->prepare("
            SELECT container_id, name FROM servers WHERE id = ? AND (user_id = ? OR ? = (SELECT id FROM users WHERE role = 'admin'))
        ");
        $stmt->execute([$serverId, $userId, $userId]);
        $server = $stmt->fetch();
        
        if (!$server) {
            throw new Exception('Server not found or access denied');
        }
        
        // Perform action
        switch ($action) {
            case 'start':
                $result = $this->docker->startContainer($server['container_id']);
                $newStatus = 'running';
                $actionDesc = 'started';
                break;
            case 'stop':
                $result = $this->docker->stopContainer($server['container_id']);
                $newStatus = 'stopped';
                $actionDesc = 'stopped';
                break;
            case 'restart':
                $result = $this->docker->restartContainer($server['container_id']);
                $newStatus = 'running';
                $actionDesc = 'restarted';
                break;
            default:
                throw new Exception('Invalid action');
        }
        
        if (!$result) {
            throw new Exception("Failed to $action server");
        }
        
        // Update status in database
        $stmt = $this->pdo->prepare("UPDATE servers SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $serverId]);
        
        // Log activity
        $this->logActivity($userId, $serverId, "server_$action", "Server '{$server['name']}' $actionDesc");
        
        return ['success' => true];
    }
    
    /**
     * Get server logs
     */
    public function getServerLogs($serverId, $userId, $lines = 50) {
        // Check ownership
        $stmt = $this->pdo->prepare("
            SELECT container_id, name FROM servers WHERE id = ? AND (user_id = ? OR ? = (SELECT id FROM users WHERE role = 'admin'))
        ");
        $stmt->execute([$serverId, $userId, $userId]);
        $server = $stmt->fetch();
        
        if (!$server) {
            throw new Exception('Server not found or access denied');
        }
        
        return $this->docker->getContainerLogs($server['container_id'], $lines);
    }
    
    /**
     * Get available images
     */
    public function getAvailableImages() {
        return $this->docker->getImages();
    }
    
    /**
     * Pull a new image
     */
    public function pullImage($imageName) {
        return $this->docker->pullImage($imageName);
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $serverId, $action, $description) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (user_id, server_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $serverId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}
?>