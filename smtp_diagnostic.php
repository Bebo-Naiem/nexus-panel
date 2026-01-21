<?php
/**
 * SMTP Diagnostic Tool
 * Run this script to diagnose SMTP/TLS connection issues
 * Usage: php smtp_diagnostic.php
 */

// Configuration - EDIT THESE VALUES
$config = [
    'host' => 'smtp.gmail.com',  // Your SMTP host
    'port' => 587,               // 587 for TLS, 465 for SSL, 25 for plain
    'encryption' => 'tls',       // 'tls', 'ssl', or 'none'
    'username' => 'your-email@gmail.com',
    'password' => 'your-app-password'
];

echo "=== SMTP Connection Diagnostic Tool ===\n\n";

// 1. Check PHP extensions
echo "[1] Checking PHP Extensions...\n";
$required = ['openssl', 'sockets'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "  ✓ $ext: Loaded\n";
    } else {
        echo "  ✗ $ext: NOT LOADED (Required!)\n";
    }
}
echo "\n";

// 2. Check OpenSSL info
echo "[2] OpenSSL Information...\n";
if (extension_loaded('openssl')) {
    $version = OPENSSL_VERSION_TEXT;
    echo "  Version: $version\n";
    
    $protocols = [];
    if (defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')) $protocols[] = 'TLS';
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $protocols[] = 'TLSv1.2';
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) $protocols[] = 'TLSv1.1';
    if (defined('STREAM_CRYPTO_METHOD_SSLv23_CLIENT')) $protocols[] = 'SSLv23';
    
    echo "  Supported protocols: " . implode(', ', $protocols) . "\n";
}
echo "\n";

// 3. Test basic TCP connection
echo "[3] Testing TCP Connection...\n";
$socket = @fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
if ($socket) {
    echo "  ✓ TCP connection successful\n";
    fclose($socket);
} else {
    echo "  ✗ TCP connection failed: $errstr ($errno)\n";
    exit(1);
}
echo "\n";

// 4. Test SMTP connection
echo "[4] Testing SMTP Connection...\n";
try {
    $contextOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    $context = stream_context_create($contextOptions);
    
    if ($config['encryption'] === 'ssl') {
        $connection = "ssl://{$config['host']}:{$config['port']}";
        echo "  Connecting to: $connection\n";
    } else {
        $connection = "tcp://{$config['host']}:{$config['port']}";
        echo "  Connecting to: $connection\n";
    }
    
    $smtp = @stream_socket_client(
        $connection,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$smtp) {
        throw new Exception("Connection failed: $errstr ($errno)");
    }
    
    echo "  ✓ SMTP socket created\n";
    
    // Read greeting
    $response = fgets($smtp, 1024);
    echo "  Server greeting: " . trim($response) . "\n";
    
    // Send EHLO
    fputs($smtp, "EHLO localhost\r\n");
    $response = '';
    while ($line = fgets($smtp, 1024)) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    echo "  EHLO response: " . trim($response) . "\n";
    
    // Test STARTTLS if needed
    if ($config['encryption'] === 'tls') {
        echo "\n[5] Testing STARTTLS...\n";
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 1024);
        echo "  STARTTLS response: " . trim($response) . "\n";
        
        if (strpos($response, '220') !== false) {
            echo "  ✓ STARTTLS accepted\n";
            echo "  Attempting to enable encryption...\n";
            
            // Try different crypto methods
            $methods = [
                'TLS_CLIENT' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
                'TLSv1.2' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                'TLSv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
                'SSLv23' => STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
            ];
            
            $success = false;
            foreach ($methods as $name => $method) {
                echo "  Trying $name... ";
                if (@stream_socket_enable_crypto($smtp, true, $method)) {
                    echo "✓ SUCCESS\n";
                    $success = true;
                    break;
                } else {
                    echo "✗ Failed\n";
                }
            }
            
            if ($success) {
                echo "  ✓ TLS encryption enabled\n";
                
                // Re-send EHLO after TLS
                fputs($smtp, "EHLO localhost\r\n");
                $response = '';
                while ($line = fgets($smtp, 1024)) {
                    $response .= $line;
                    if (preg_match('/^\d{3} /', $line)) break;
                }
                echo "  EHLO after TLS: " . trim(substr($response, 0, 50)) . "...\n";
            } else {
                echo "  ✗ Could not enable TLS encryption\n";
                echo "\n=== DIAGNOSIS ===\n";
                echo "The STARTTLS command was accepted, but PHP could not enable encryption.\n";
                echo "This usually means:\n";
                echo "  1. OpenSSL version is too old\n";
                echo "  2. PHP is compiled without proper SSL support\n";
                echo "  3. The server requires specific TLS versions\n\n";
                echo "SOLUTIONS:\n";
                echo "  - Update OpenSSL and recompile PHP\n";
                echo "  - Try using SSL (port 465) instead of TLS (port 587)\n";
                echo "  - Check server's TLS requirements\n";
            }
        }
    }
    
    // Close
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);
    
    echo "\n✓ Diagnostic complete\n";
    
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
if ($config['encryption'] === 'tls' && $config['port'] == 587) {
    echo "If TLS fails, try:\n";
    echo "  1. Use SSL instead (port 465, encryption='ssl')\n";
    echo "  2. Use 'none' encryption (not recommended for production)\n";
    echo "  3. Update PHP and OpenSSL to latest versions\n";
}
echo "\nFor Gmail users:\n";
echo "  - Use App Passwords instead of account password\n";
echo "  - Enable 'Less secure app access' if using regular password\n";
echo "  - SSL port 465 often works better than TLS port 587\n";
?>