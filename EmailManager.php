<?php
/**
 * Nexus Panel - Email Manager
 * Handles SMTP email sending with templates
 */

require_once 'config.php';

class EmailManager {
    private $pdo;
    private $smtpConfig;
    
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
     * Send custom email
     */
    public function sendEmail($toEmail, $toName, $subject, $body, $isHtml = true) {
        if (!$this->smtpConfig) {
            throw new Exception('SMTP configuration not found or not active');
        }
        
        try {
            // Create SMTP connection based on configuration
            $smtpHost = $this->smtpConfig['smtp_host'];
            $smtpPort = $this->smtpConfig['smtp_port'];
            $smtpUsername = $this->smtpConfig['smtp_username'];
            $smtpPassword = $this->smtpConfig['smtp_password'];
            $encryption = $this->smtpConfig['smtp_encryption'];
            $fromEmail = $this->smtpConfig['from_email'];
            $fromName = $this->smtpConfig['from_name'];
            
            // Build the message
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
            
            // Use fsockopen for SMTP connection
            $socket = null;
            $errorNum = 0;
            $errorStr = '';
            
            // Connect with appropriate encryption
            if ($encryption === 'ssl') {
                $socket = fsockopen("ssl://{$smtpHost}", $smtpPort, $errorNum, $errorStr, 30);
            } elseif ($encryption === 'tls') {
                $socket = fsockopen("tcp://{$smtpHost}", $smtpPort, $errorNum, $errorStr, 30);
            } else {
                $socket = fsockopen($smtpHost, $smtpPort, $errorNum, $errorStr, 30);
            }
            
            if (!$socket) {
                throw new Exception("Could not connect to SMTP server: {$errorStr} ({$errorNum})");
            }
            
            // Read initial server response
            $response = fgets($socket, 1024);
            
            // Send EHLO command
            $ehloCommand = $encryption === 'ssl' ? "EHLO {$smtpHost}" : "EHLO {$smtpHost}";
            fputs($socket, "{$ehloCommand}\r\n");
            $response = fgets($socket, 1024);
            
            // If using TLS, initiate STARTTLS
            if ($encryption === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 1024);
                
                // Upgrade connection to TLS
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    throw new Exception('Failed to enable TLS encryption');
                }
                
                // Re-send EHLO after TLS upgrade
                fputs($socket, "EHLO {$smtpHost}\r\n");
                $response = fgets($socket, 1024);
            }
            
            // Authenticate using AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '334') === false) {
                fclose($socket);
                throw new Exception('SMTP server did not accept authentication request');
            }
            
            // Send encoded username
            fputs($socket, base64_encode($smtpUsername) . "\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '334') === false) {
                fclose($socket);
                throw new Exception('SMTP server did not accept username');
            }
            
            // Send encoded password
            fputs($socket, base64_encode($smtpPassword) . "\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '235') === false) {
                fclose($socket);
                throw new Exception('SMTP authentication failed: ' . $response);
            }
            
            // Send MAIL FROM
            fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '250') === false) {
                fclose($socket);
                throw new Exception('SMTP server rejected sender: ' . $response);
            }
            
            // Send RCPT TO
            fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '250') === false && strpos($response, '251') === false) {
                fclose($socket);
                throw new Exception('SMTP server rejected recipient: ' . $response);
            }
            
            // Send DATA command
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '354') === false) {
                fclose($socket);
                throw new Exception('SMTP server did not accept data: ' . $response);
            }
            
            // Send headers
            foreach ($headers as $header) {
                fputs($socket, "{$header}\r\n");
            }
            
            // Send message body
            fputs($socket, "\r\n{$message}\r\n");
            
            // End data with .
            fputs($socket, ".\r\n");
            $response = fgets($socket, 1024);
            
            // Close connection
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            // Check if email was accepted
            if (strpos($response, '250') === false) {
                throw new Exception('SMTP server did not accept message: ' . $response);
            }
            
            // Log email activity
            $this->logEmail($toEmail, $subject, 'sent');
            
            return true;
        } catch (Exception $e) {
            // If SMTP fails, try fallback method
            try {
                return $this->sendEmailFallback($toEmail, $toName, $subject, $body, $isHtml);
            } catch (Exception $fallbackException) {
                $this->logEmail($toEmail, $subject, 'failed', $e->getMessage() . ' (fallback failed: ' . $fallbackException->getMessage() . ')');
                throw new Exception('Email sending failed: ' . $e->getMessage() . ' (and fallback also failed)');
            }
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
     * Test SMTP configuration
     */
    public function testConnection() {
        if (!$this->smtpConfig) {
            return ['success' => false, 'error' => 'No active SMTP configuration found'];
        }
        
        try {
            // Test basic SMTP connection without sending an actual email
            $smtpHost = $this->smtpConfig['smtp_host'];
            $smtpPort = $this->smtpConfig['smtp_port'];
            $smtpUsername = $this->smtpConfig['smtp_username'];
            $smtpPassword = $this->smtpConfig['smtp_password'];
            $encryption = $this->smtpConfig['smtp_encryption'];
            
            // Use fsockopen for SMTP connection
            $socket = null;
            $errorNum = 0;
            $errorStr = '';
            
            if ($encryption === 'ssl') {
                $socket = fsockopen("ssl://{$smtpHost}", $smtpPort, $errorNum, $errorStr, 30);
            } elseif ($encryption === 'tls') {
                $socket = fsockopen("tcp://{$smtpHost}", $smtpPort, $errorNum, $errorStr, 30);
            } else {
                $socket = fsockopen($smtpHost, $smtpPort, $errorNum, $errorStr, 30);
            }
            
            if (!$socket) {
                return ['success' => false, 'error' => "Could not connect to SMTP server: {$errorStr} ({$errorNum})"];
            }
            
            // Read initial server response
            $response = fgets($socket, 1024);
            
            // Send EHLO command
            $ehloCommand = $encryption === 'ssl' ? "EHLO {$smtpHost}" : "EHLO {$smtpHost}";
            fputs($socket, "{$ehloCommand}\r\n");
            $response = fgets($socket, 1024);
            
            // If using TLS, initiate STARTTLS
            if ($encryption === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 1024);
                
                // Upgrade connection to TLS
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return ['success' => false, 'error' => 'Failed to enable TLS encryption'];
                }
                
                // Re-send EHLO after TLS upgrade
                fputs($socket, "EHLO {$smtpHost}\r\n");
                $response = fgets($socket, 1024);
            }
            
            // Authenticate using AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '334') === false) {
                fclose($socket);
                return ['success' => false, 'error' => 'SMTP server did not accept authentication request'];
            }
            
            // Send encoded username
            fputs($socket, base64_encode($smtpUsername) . "\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '334') === false) {
                fclose($socket);
                return ['success' => false, 'error' => 'SMTP server did not accept username'];
            }
            
            // Send encoded password
            fputs($socket, base64_encode($smtpPassword) . "\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '235') === false) {
                fclose($socket);
                return ['success' => false, 'error' => 'SMTP authentication failed: ' . $response];
            }
            
            // Close connection
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return ['success' => true, 'message' => 'SMTP connection successful'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'SMTP connection test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all email templates
     */
    public function getTemplates() {
        $stmt = $this->pdo->query("SELECT * FROM email_templates ORDER BY name ASC");
        return $stmt->fetchAll();
    }
    
    /**
     * Get specific template
     */
    public function getTemplate($templateName) {
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE name = ?");
        $stmt->execute([$templateName]);
        return $stmt->fetch();
    }
    
    /**
     * Create or update template
     */
    public function saveTemplate($name, $subject, $body, $isHtml = true, $isActive = true) {
        $stmt = $this->pdo->prepare(
            "INSERT OR REPLACE INTO email_templates (name, subject, body, is_html, is_active, updated_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        
        return $stmt->execute([$name, $subject, $body, $isHtml ? 1 : 0, $isActive ? 1 : 0]);
    }
    
    /**
     * Get SMTP configuration
     */
    public function getSmtpConfig() {
        return $this->smtpConfig;
    }
    
    /**
     * Save SMTP configuration
     */
    public function saveSmtpConfig($config) {
        // Deactivate existing configs
        $this->pdo->query("UPDATE email_config SET is_active = 0");
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_config (
                smtp_host, smtp_port, smtp_username, smtp_password, 
                smtp_encryption, from_email, from_name, is_active, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)"
        );
        
        $result = $stmt->execute([
            $config['smtp_host'],
            $config['smtp_port'],
            $config['smtp_username'],
            $config['smtp_password'],
            $config['smtp_encryption'],
            $config['from_email'],
            $config['from_name']
        ]);
        
        if ($result) {
            $this->loadSmtpConfig(); // Reload config
        }
        
        return $result;
    }
    
    /**
     * Log email activity
     */
    private function logEmail($toEmail, $subject, $status, $error = null) {
        // In a real implementation, you might want to log emails to a database table
        error_log("Email to $toEmail - Subject: $subject - Status: $status" . ($error ? " - Error: $error" : ""));
    }
    
    /**
     * Fallback email sending using PHP mail function
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
        
        // Log email activity
        $this->logEmail($toEmail, $subject, 'sent');
        
        return true;
    }
}
?>