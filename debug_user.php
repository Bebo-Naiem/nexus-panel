<?php
require_once 'db.php';
global $pdo;

echo "List of Users:\n";
$stmt = $pdo->query("SELECT id, username, role FROM users");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['username'] . " | Role: " . $row['role'] . "\n";
}

echo "\nChecking Admin User manual query:\n";
// Assumes admin is usually ID 1
$id = 1;
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
print_r($user);
?>
