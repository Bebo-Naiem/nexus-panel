<?php
/**
 * Nexus Panel - Database Connection & Auto-Migration
 * Author: Senior PHP Developer
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $dbPath;

    private function __construct() {
        $this->dbPath = __DIR__ . '/nexus.sqlite';
        $this->connect();
        $this->migrate();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $this->pdo = new PDO("sqlite:{$this->dbPath}");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    private function migrate() {
        // Create users table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT DEFAULT 'user' CHECK(role IN ('admin', 'user')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create servers table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                container_id TEXT UNIQUE NOT NULL,
                user_id INTEGER,
                status TEXT DEFAULT 'offline',
                description TEXT,
                memory_limit INTEGER DEFAULT 1024,
                cpu_limit INTEGER DEFAULT 100,
                disk_limit INTEGER DEFAULT 10240,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
                
        // Create server allocations table for port management
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS server_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                ip_address TEXT DEFAULT '0.0.0.0',
                port INTEGER NOT NULL,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
                UNIQUE(server_id, port)
            )
        ");
                
        // Create server variables table for environment variables
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS server_variables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                variable_key TEXT NOT NULL,
                variable_value TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            )
        ");
                
        // Create activity logs table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                server_id INTEGER,
                action TEXT NOT NULL,
                description TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
            )
        ");

        // Insert default admin if users table is empty
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $insertStmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, role) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute(['admin', 'admin@nexus.local', $passwordHash, 'admin']);
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Helper methods for common operations
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}

// Initialize database
$db = Database::getInstance();
$pdo = $db->getConnection();
?>