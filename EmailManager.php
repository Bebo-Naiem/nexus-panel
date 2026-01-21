<?php
/**
 * Nexus Panel - Enhanced Email Manager with TLS Fix
 * Handles SMTP email sending with improved TLS/SSL support
 */

require_once 'config.php';

class EmailManager {
    private $pdo;
    private $smtpConfig;
    private $debugMode = true; // Enable for troubleshooting
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSmtpConfig();
    }
    
    private function loadSmtpConfig() {
        $stmt = $this->pdo->query("SELECT * FROM email_config WHERE is_active = 1 LIMIT 1");
        $this->smtpConfig = $stmt->fetch();
    }
    
    /**
     * Send email using template
     */
    public function sendTemplateEmail($toEmail, $toName, $templateName, $variables = []) {
        if (!$this->smtpConfig) {
            throw new Exception('SMTP configuration not found or not active');
        }
        
        // Get template
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE name = ? AND is_active = 1");
        $stmt->execute([$templateName]);
        $template = $stmt->fetch();
        
        if (!$template) {
            throw new Exception("Email template '$templateName' not found or inactive");
        }
        
        // Process template variables
        $subject = $this->processVariables($template['subject'], $variables);
        $body = $this->processVariables($template['body'], $variables);
        
        return $this->sendEmail($toEmail, $toName, $subject, $body, $template['is_html']);
    }
    
    /**
     * Send custom email with improved TLS handling
     */
    public function sendEmail($toEmail, $toName, $subject, $body, $isHtml = true) {
        if (!$this->smtpConfig) {
            throw new Exception('SMTP configuration not found or not active');
        }
        
        try {
            $smtpHost = $this->smtpConfig['smtp_host'];
            $smtpPort = $this->smtpConfig['smtp_port'];
            $smtpUsername = $this->smtpConfig['smtp_username'];
            $smtpPassword = $this->smtpConfig['smtp_password'];
            $encryption = $this->smtpConfig['smtp_encryption'];
            $fromEmail = $this->smtpConfig['from_email'];
            $fromName = $this->smtpConfig['from_name'];
            
            // Build message
            $boundary = md5(time());
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = "From: \"{$fromName}\" <{$fromEmail}>";
            $headers[] = "Reply-To: \"{$fromName}\" <{$fromEmail}>";
            $headers[] = "Date: " . date('r');
            $headers[] = "X-Mailer: PHP/" . phpversion();
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            
            $message = "--{$boundary}\r\n";
            if ($isHtml) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
                $message .= $body . "\r\n\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                $message .= strip_tags($body) . "\r\n\r\n";
            }
            $message .= "--{$boundary}--";
            
            // Connect based on encryption type
            $socket = $this->connectToSMTP($smtpHost, $smtpPort, $encryption);
            
            // Read server greeting
            $response = $this->readResponse($socket);
            $this->debugLog("Server greeting: $response");
            
            // Send EHLO
            $this->sendCommand($socket, "EHLO {$smtpHost}");
            $response = $this->readResponse($socket);
            $this->debugLog("EHLO response: $response");
            
            // Handle TLS if needed
            if ($encryption === 'tls') {
                $socket = $this->enableTLS($socket, $smtpHost);
            }
            
            // Authenticate
            $this->authenticate($socket, $smtpUsername, $smtpPassword);
            
            // Send email
            $this->sendCommand($socket, "MAIL FROM: <{$fromEmail}>");
            $response = $this->readResponse($socket);
            
            if (!$this->isSuccessResponse($response)) {
                throw new Exception('Server rejected sender: ' . $response);
            }
            
            $this->sendCommand($socket, "RCPT TO: <{$toEmail}>");
            $response = $this->readResponse($socket);
            
            if (!$this->isSuccessResponse($response)) {
                throw new Exception('Server rejected recipient: ' . $response);
            }
            
            $this->sendCommand($socket, "DATA");
            $response = $this->readResponse($socket);
            
            if (strpos($response, '354') === false) {
                throw new Exception('Server did not accept DATA: ' . $response);
            }
            
            // Send headers and body
            foreach ($headers as $header) {
                fputs($socket, "{$header}\r\n");
            }
            fputs($socket, "\r\n{$message}\r\n");
            fputs($socket, ".\r\n");
            
            $response = $this->readResponse($socket);
            
            // Close connection
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            if (!$this->isSuccessResponse($response)) {
                throw new Exception('Server did not accept message: ' . $response);
            }
            
            $this->logEmail($toEmail, $subject, 'sent');
            return true;
            
        } catch (Exception $e) {
            $this->logEmail($toEmail, $subject, 'failed', $e->getMessage());
            
            // Try fallback
            try {
                return $this->sendEmailFallback($toEmail, $toName, $subject, $body, $isHtml);
            } catch (Exception $fallbackException) {
                throw new Exception('Email sending failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Connect to SMTP server with proper encryption handling
     */
    private function connectToSMTP($host, $port, $encryption) {
        $errorNum = 0;
        $errorStr = '';
        $timeout = 30;
        
        // Create stream context with SSL/TLS options
        $contextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | 
                                 STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | 
                                 STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            ]
        ];
        
        $context = stream_context_create($contextOptions);
        
        if ($encryption === 'ssl') {
            // Direct SSL connection
            $this->debugLog("Connecting via SSL to ssl://{$host}:{$port}");
            $socket = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errorNum,
                $errorStr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            // Plain or TLS (STARTTLS)
            $this->debugLog("Connecting via TCP to {$host}:{$port}");
            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errorNum,
                $errorStr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }
        
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: {$errorStr} ({$errorNum})");
        }
        
        // Set timeout for operations
        stream_set_timeout($socket, $timeout);
        
        return $socket;
    }
    
    /**
     * Enable TLS encryption after STARTTLS command
     */
    private function enableTLS($socket, $host) {
        $this->sendCommand($socket, "STARTTLS");
        $response = $this->readResponse($socket);
        
        if (strpos($response, '220') === false) {
            throw new Exception('STARTTLS not accepted: ' . $response);
        }
        
        $this->debugLog("STARTTLS accepted, enabling encryption...");
        
        // Set stream context for TLS
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT | 
                       STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | 
                       STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        
        // Try to enable crypto
        $cryptoEnabled = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
        
        if (!$cryptoEnabled) {
            // Try alternative methods
            $methods = [
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
                STREAM_CRYPTO_METHOD_TLS_CLIENT,
                STREAM_CRYPTO_METHOD_SSLv23_CLIENT
            ];
            
            foreach ($methods as $method) {
                $this->debugLog("Trying crypto method: " . $this->getCryptoMethodName($method));
                if (@stream_socket_enable_crypto($socket, true, $method)) {
                    $cryptoEnabled = true;
                    $this->debugLog("Successfully enabled with method: " . $this->getCryptoMethodName($method));
                    break;
                }
            }
        }
        
        if (!$cryptoEnabled) {
            throw new Exception('Failed to enable TLS encryption after STARTTLS');
        }
        
        // Re-send EHLO after TLS
        $this->sendCommand($socket, "EHLO {$host}");
        $response = $this->readResponse($socket);
        $this->debugLog("EHLO after TLS: $response");
        
        return $socket;
    }
    
    /**
     * Authenticate with SMTP server
     */
    private function authenticate($socket, $username, $password) {
        $this->sendCommand($socket, "AUTH LOGIN");
        $response = $this->readResponse($socket);
        
        if (strpos($response, '334') === false) {
            throw new Exception('AUTH LOGIN not accepted: ' . $response);
        }
        
        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = $this->readResponse($socket);
        
        if (strpos($response, '334') === false) {
            throw new Exception('Username not accepted: ' . $response);
        }
        
        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = $this->readResponse($socket);
        
        if (strpos($response, '235') === false) {
            throw new Exception('Authentication failed: ' . $response);
        }
        
        $this->debugLog("Authentication successful");
    }
    
    /**
     * Send SMTP command
     */
    private function sendCommand($socket, $command) {
        $this->debugLog(">> $command");
        fputs($socket, "$command\r\n");
    }
    
    /**
     * Read SMTP response
     */
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            // Check if this is the last line (starts with code and space, not dash)
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        
        $this->debugLog("<< " . trim($response));
        return trim($response);
    }
    
    /**
     * Check if response indicates success
     */
    private function isSuccessResponse($response) {
        $code = substr($response, 0, 3);
        return in_array($code, ['250', '251', '354']);
    }
    
    /**
     * Get crypto method name for debugging
     */
    private function getCryptoMethodName($method) {
        $names = [
            STREAM_CRYPTO_METHOD_TLS_CLIENT => 'TLS_CLIENT',
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT => 'TLSv1.2_CLIENT',
            STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT => 'TLSv1.1_CLIENT',
            STREAM_CRYPTO_METHOD_SSLv23_CLIENT => 'SSLv23_CLIENT'
        ];
        
        return $names[$method] ?? 'UNKNOWN';
    }
    
    /**
     * Debug logging
     */
    private function debugLog($message) {
        if ($this->debugMode) {
            error_log("[EmailManager] $message");
        }
    }
    
    /**
     * Test SMTP configuration
     */
    public function testConnection() {
        if (!$this->smtpConfig) {
            return ['success' => false, 'error' => 'No active SMTP configuration found'];
        }
        
        try {
            $smtpHost = $this->smtpConfig['smtp_host'];
            $smtpPort = $this->smtpConfig['smtp_port'];
            $smtpUsername = $this->smtpConfig['smtp_username'];
            $smtpPassword = $this->smtpConfig['smtp_password'];
            $encryption = $this->smtpConfig['smtp_encryption'];
            
            $socket = $this->connectToSMTP($smtpHost, $smtpPort, $encryption);
            $response = $this->readResponse($socket);
            
            $this->sendCommand($socket, "EHLO {$smtpHost}");
            $this->readResponse($socket);
            
            if ($encryption === 'tls') {
                $socket = $this->enableTLS($socket, $smtpHost);
            }
            
            $this->authenticate($socket, $smtpUsername, $smtpPassword);
            
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            return ['success' => true, 'message' => 'SMTP connection successful'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'SMTP test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Process template variables
     */
    private function processVariables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }
    
    /**
     * Log email activity
     */
    private function logEmail($toEmail, $subject, $status, $error = null) {
        error_log("Email to $toEmail - Subject: $subject - Status: $status" . ($error ? " - Error: $error" : ""));
    }
    
    /**
     * Fallback email sending using PHP mail()
     */
    private function sendEmailFallback($toEmail, $toName, $subject, $body, $isHtml = true) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $isHtml ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
        $headers[] = "From: {$this->smtpConfig['from_name']} <{$this->smtpConfig['from_email']}>";
        $headers[] = "Reply-To: {$this->smtpConfig['from_email']}";
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        $result = mail($toEmail, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) {
            throw new Exception('Failed to send email using fallback method');
        }
        
        $this->logEmail($toEmail, $subject, 'sent');
        return true;
    }
}
?>