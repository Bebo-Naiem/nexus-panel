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
                egg_id INTEGER,
                status TEXT DEFAULT 'offline',
                description TEXT,
                memory_limit INTEGER DEFAULT 1024,
                cpu_limit INTEGER DEFAULT 100,
                disk_limit INTEGER DEFAULT 10240,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (egg_id) REFERENCES eggs(id) ON DELETE SET NULL
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
        
        // Create eggs table for Pterodactyl-like functionality
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS eggs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                docker_image TEXT NOT NULL,
                startup_command TEXT,
                config_files TEXT,
                config_startup TEXT,
                config_logs TEXT,
                config_stop TEXT,
                vars TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create email configuration table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS email_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                smtp_host TEXT NOT NULL,
                smtp_port INTEGER DEFAULT 587,
                smtp_username TEXT,
                smtp_password TEXT,
                smtp_encryption TEXT DEFAULT 'tls',
                from_email TEXT NOT NULL,
                from_name TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create email templates table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS email_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                is_html BOOLEAN DEFAULT 1,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default email templates if table is empty
        $templateStmt = $this->pdo->prepare("SELECT COUNT(*) FROM email_templates");
        $templateStmt->execute();
        if ($templateStmt->fetchColumn() == 0) {
            // Welcome email template
            $this->pdo->prepare(
                "INSERT INTO email_templates (name, subject, body, is_html) VALUES (?, ?, ?, ?)"
            )->execute([
                'welcome_user',
                'Welcome to Nexus Panel',
                '<h2>Welcome {{username}}!</h2><p>Your account has been successfully created on Nexus Panel.</p><p>You can now login with your credentials and start managing your game servers.</p>',
                1
            ]);
            
            // Password reset template
            $this->pdo->prepare(
                "INSERT INTO email_templates (name, subject, body, is_html) VALUES (?, ?, ?, ?)"
            )->execute([
                'password_reset',
                'Password Reset Request',
                '<h2>Password Reset</h2><p>Hello {{username}},</p><p>You have requested to reset your password. Click the link below to reset it:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>If you didn\'t request this, please ignore this email.</p>',
                1
            ]);
            
            // Account activation template
            $this->pdo->prepare(
                "INSERT INTO email_templates (name, subject, body, is_html) VALUES (?, ?, ?, ?)"
            )->execute([
                'account_activation',
                'Activate Your Account',
                '<h2>Account Activation</h2><p>Hello {{username}},</p><p>Please click the link below to activate your account:</p><p><a href="{{activation_link}}">Activate Account</a></p>',
                1
            ]);
        }
        
        // Insert default eggs if table is empty
        $eggStmt = $this->pdo->prepare("SELECT COUNT(*) FROM eggs");
        $eggStmt->execute();
        if ($eggStmt->fetchColumn() == 0) {
            // Insert Minecraft egg
            $this->pdo->prepare(
                "INSERT INTO eggs (name, description, docker_image, startup_command, config_files, config_startup, config_logs, config_stop, vars) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                'Minecraft Java',
                'Standard Minecraft Java Edition server',
                'itzg/minecraft-server:latest',
                'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                '{"server.properties":{"parser":"properties","find":{"level-name":"{{WORLD}}","server-port":{{SERVER_PORT}},"server-ip":"0.0.0.0","motd":"{{SERVER_NAME}}","max-players":{{MAX_PLAYERS}},"difficulty":{{DIFFICULTY}},"gamemode":{{GAME_MODE}}}}}',
                '{"done":["Done"]}',
                '{"custom":false,"location":"logs/latest.log","ignorelist":["*.gz"]}',
                'stop',
                '[{"name":"SERVER_JARFILE","description":"The file name of the server jar","env_variable":"SERVER_JARFILE","default_value":"server.jar","required":true,"user_viewable":true,"user_editable":true},{"name":"MAX_PLAYERS","description":"Maximum number of players","env_variable":"MAX_PLAYERS","default_value":"20","required":true,"user_viewable":true,"user_editable":true},{"name":"WORLD","description":"World name","env_variable":"WORLD","default_value":"world","required":true,"user_viewable":true,"user_editable":true}]'
            ]);
            
            // Insert Rust egg
            $this->pdo->prepare(
                "INSERT INTO eggs (name, description, docker_image, startup_command, config_files, config_startup, config_logs, config_stop, vars) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                'Rust',
                'Rust game server',
                'rust/rust:latest',
                '/steamcmd/rust/RustDedicated -batchmode +server.port {{SERVER_PORT}} +server.identity "{{SERVER_NAME}}" +rcon.port {{RCON_PORT}} +rcon.password "{{RCON_PASSWORD}}" +server.maxplayers {{MAX_PLAYERS}}',
                '{}',
                '{"done":["Loading Prefab"],"timeout":120}',
                '{"custom":false,"location":"output.log","ignorelist":["*.gz"]}',
                '^C',
                '[{"name":"RCON_PORT","description":"RCON port","env_variable":"RCON_PORT","default_value":"28016","required":true,"user_viewable":true,"user_editable":true},{"name":"RCON_PASSWORD","description":"RCON password","env_variable":"RCON_PASSWORD","default_value":"change_me","required":true,"user_viewable":false,"user_editable":true},{"name":"MAX_PLAYERS","description":"Maximum number of players","env_variable":"MAX_PLAYERS","default_value":"100","required":true,"user_viewable":true,"user_editable":true}]'
            ]);
        }

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