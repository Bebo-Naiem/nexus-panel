<?php
/**
 * Nexus Panel - Docker Manager
 * Advanced Docker container management
 */

require_once 'config.php';

class DockerManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all Docker containers
     */
    public function getAllContainers() {
        $output = shell_exec('docker ps -a --format=\'{{.ID}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}\' 2>&1');
        $lines = explode("\n", trim($output));
        
        $containers = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parts = explode("\t", $line);
            if (count($parts) >= 4) {
                $container = [
                    'id' => $parts[0],
                    'name' => $parts[1],
                    'status' => $parts[2],
                    'ports' => $parts[3]
                ];
                
                // Get additional info
                $details = $this->getContainerDetails($container['id']);
                $container = array_merge($container, $details);
                
                $containers[] = $container;
            }
        }
        
        return $containers;
    }
    
    /**
     * Get detailed container information
     */
    public function getContainerDetails($containerId) {
        $statsOutput = shell_exec("docker inspect $containerId 2>&1");
        $stats = json_decode($statsOutput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !$stats) {
            return [
                'image' => 'Unknown',
                'command' => 'Unknown',
                'created' => 'Unknown',
                'labels' => []
            ];
        }
        
        $container = $stats[0];
        
        return [
            'image' => $container['Config']['Image'] ?? 'Unknown',
            'command' => implode(' ', $container['Config']['Cmd'] ?? []),
            'created' => $container['Created'] ?? 'Unknown',
            'labels' => $container['Config']['Labels'] ?? [],
            'ports' => $this->formatPorts($container['NetworkSettings']['Ports'] ?? [])
        ];
    }
    
    /**
     * Format container ports for display
     */
    private function formatPorts($portMap) {
        $ports = [];
        foreach ($portMap as $containerPort => $hostMapping) {
            if ($hostMapping && isset($hostMapping[0]['HostIp'], $hostMapping[0]['HostPort'])) {
                $ports[] = $hostMapping[0]['HostIp'] . ':' . $hostMapping[0]['HostPort'] . '->' . $containerPort;
            }
        }
        return implode(', ', $ports);
    }
    
    /**
     * Start a container
     */
    public function startContainer($containerId) {
        $output = shell_exec("docker start $containerId 2>&1");
        return $output !== null;
    }
    
    /**
     * Stop a container
     */
    public function stopContainer($containerId) {
        $output = shell_exec("docker stop $containerId 2>&1");
        return $output !== null;
    }
    
    /**
     * Restart a container
     */
    public function restartContainer($containerId) {
        $output = shell_exec("docker restart $containerId 2>&1");
        return $output !== null;
    }
    
    /**
     * Get container logs
     */
    public function getContainerLogs($containerId, $lines = 50) {
        $output = shell_exec("docker logs --tail $lines $containerId 2>&1");
        return $output ?: 'No logs available';
    }
    
    /**
     * Get container stats
     */
    public function getContainerStats($containerId) {
        $output = shell_exec("docker stats --no-stream --format='{{.CPUPerc}},{{.MemUsage}},{{.NetIO}},{{.BlockIO}}' $containerId 2>&1");
        
        if (!$output || strpos($output, 'No such') !== false) {
            return [
                'cpu_percent' => 'N/A',
                'memory_usage' => 'N/A',
                'network_io' => 'N/A',
                'block_io' => 'N/A'
            ];
        }
        
        $stats = explode(',', trim($output));
        return [
            'cpu_percent' => $stats[0] ?? 'N/A',
            'memory_usage' => $stats[1] ?? 'N/A',
            'network_io' => $stats[2] ?? 'N/A',
            'block_io' => $stats[3] ?? 'N/A'
        ];
    }
    
    /**
     * Create a new container
     */
    public function createContainer($name, $image, $envVars = [], $portMappings = [], $resources = []) {
        $cmd = "docker run -d --name '$name'";
        
        // Add environment variables
        foreach ($envVars as $key => $value) {
            $cmd .= " -e $key='$value'";
        }
        
        // Add port mappings
        foreach ($portMappings as $hostPort => $containerPort) {
            $cmd .= " -p $hostPort:$containerPort";
        }
        
        // Add resource limits
        if (isset($resources['memory'])) {
            $cmd .= " -m {$resources['memory']}";
        }
        if (isset($resources['cpu'])) {
            $cmd .= " --cpus {$resources['cpu']}";
        }
        
        // Add image
        $cmd .= " $image";
        
        $output = shell_exec("$cmd 2>&1");
        
        if (strpos($output, 'Error') !== false) {
            return ['success' => false, 'error' => $output];
        }
        
        return ['success' => true, 'container_id' => trim($output)];
    }
    
    /**
     * Remove a container
     */
    public function removeContainer($containerId, $force = false) {
        $flag = $force ? '-f ' : '';
        $output = shell_exec("docker rm {$flag}{$containerId} 2>&1");
        
        if (strpos($output, 'Error') !== false) {
            return ['success' => false, 'error' => $output];
        }
        
        return ['success' => true];
    }
    
    /**
     * Get available Docker images
     */
    public function getImages() {
        $output = shell_exec('docker images --format=\'{{.Repository}}:{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}\' 2>&1');
        $lines = explode("\n", trim($output));
        
        $images = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parts = explode("\t", $line);
            if (count($parts) >= 3) {
                $images[] = [
                    'repository_tag' => $parts[0],
                    'size' => $parts[1],
                    'created' => $parts[2]
                ];
            }
        }
        
        return $images;
    }
    
    /**
     * Pull a Docker image
     */
    public function pullImage($imageName) {
        $output = shell_exec("docker pull $imageName 2>&1");
        
        if (strpos($output, 'Error') !== false) {
            return ['success' => false, 'error' => $output];
        }
        
        return ['success' => true];
    }
}
?>