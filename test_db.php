<?php
/**
 * Nexus Panel - Database Test Script
 * Verifies database connectivity and basic operations
 */

require_once 'db.php';

echo "=== Nexus Panel Database Test ===\n";

try {
    // Test database connection
    $pdo = $db->getConnection();
    echo "✓ Database connection established\n";
    
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "✓ Users table exists, {$userCount} users found\n";
    
    // Test servers table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM servers");
    $serverCount = $stmt->fetch()['count'];
    echo "✓ Servers table exists, {$serverCount} servers found\n";
    
    // Show admin user if exists
    $stmt = $pdo->query("SELECT username, role FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll();
    if (!empty($admins)) {
        echo "✓ Admin users found:\n";
        foreach ($admins as $admin) {
            echo "  - {$admin['username']} ({$admin['role']})\n";
        }
    }
    
    echo "\n=== Database Test Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>