<?php
// Simulate a create_server request
$_POST['action'] = 'create_server';
$_POST['owner_id'] = 1;
$_POST['egg_id'] = 1;
$_POST['name'] = 'TestServer';
$_POST['image'] = 'itzg/minecraft-server:latest';
$_POST['memory'] = 1024;
$_POST['cpu_limit'] = 100;
$_POST['disk_space'] = 10240;
$_POST['environment'] = '{"EULA":"TRUE"}';
$_POST['ports'] = '{"25565":"25565"}';

// Start session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "--- START API OUTPUT ---\n";
try {
    include 'api.php';
} catch (Throwable $e) {
    echo "\nFATAL ERROR UNCAUGHT: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
echo "\n--- END API OUTPUT ---\n";
?>
