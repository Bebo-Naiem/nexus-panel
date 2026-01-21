<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Syntax Check...\n";

// Mock environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'check_update'; // Use a safe action

try {
    ob_start(); // Capture output
    include 'api.php';
    $output = ob_get_clean();
    
    echo "Include successful.\n";
    echo "Output length: " . strlen($output) . "\n";
    echo "Output preview: " . substr($output, 0, 100) . "\n";
    
} catch (Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
?>
