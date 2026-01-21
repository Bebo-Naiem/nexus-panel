<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("SELECT * FROM servers");
    $servers = $stmt->fetchAll();
    echo "Servers in DB:\n";
    foreach ($servers as $server) {
        echo "ID: " . $server['id'] . " | Name: " . $server['name'] . " | ContainerID: " . $server['container_id'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
