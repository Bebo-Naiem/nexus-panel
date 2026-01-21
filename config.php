<?php
/**
 * Nexus Panel - Configuration File
 * System-wide configuration settings
 */

class Config {
    // Database settings
    public const DB_PATH = __DIR__ . '/nexus.sqlite';
    
    // Docker settings
    public const DOCKER_SOCKET = '/var/run/docker.sock';
    public const DEFAULT_CONTAINER_MEMORY = '1024m';
    public const DEFAULT_CONTAINER_CPU = 1;
    
    // Security settings
    public const SESSION_LIFETIME = 3600; // 1 hour
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOGIN_LOCKOUT_TIME = 900; // 15 minutes
    
    // System settings
    public const APP_NAME = 'Nexus Panel';
    public const APP_VERSION = '1.0.0';
    public const TIMEZONE = 'UTC';
    
    // Feature flags
    public const ENABLE_USER_REGISTRATION = true;
    public const REQUIRE_EMAIL_VERIFICATION = false;
    
    // Resource limits
    public const MAX_SERVERS_PER_USER = 5;
    public const DEFAULT_DISK_SPACE = 10240; // 10GB in MB
    public const DEFAULT_MEMORY = 1024; // 1GB in MB
    public const DEFAULT_CPU_LIMIT = 100; // Percentage
    
    public static function init() {
        // Set timezone
        date_default_timezone_set(self::TIMEZONE);
        
        // Start session with security settings
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            session_set_cookie_params([
                'lifetime' => self::SESSION_LIFETIME,
                'httponly' => true,
                'secure' => isset($_SERVER['HTTPS']),
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
}

// Initialize configuration
Config::init();
?>