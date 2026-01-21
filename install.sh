#!/bin/bash

# Nexus Panel Installation Script
# Ubuntu 24.04 LTS Setup with Nginx, PHP 8.3, SQLite, and Docker

set -e  # Exit on any error

echo "========================================="
echo "  Nexus Panel Installation Script"
echo "  Game Server Management System"
echo "========================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)" 
   exit 1
fi

echo "✓ Running with root privileges"
echo ""

# Get current directory (this will be our web root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "✓ Installation directory: $SCRIPT_DIR"
echo ""

# Update system packages
echo "Updating system packages..."
apt update -y
apt upgrade -y
echo "✓ System packages updated"
echo ""

# Install required packages
echo "Installing required packages..."
apt install -y \
    nginx \
    php8.3-fpm \
    php8.3-sqlite3 \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    docker.io \
    docker-compose-v2 \
    git \
    curl \
    software-properties-common
echo "✓ Required packages installed"
echo ""

# Start and enable services
echo "Starting and enabling services..."
systemctl start nginx
systemctl enable nginx
systemctl start php8.3-fpm
systemctl enable php8.3-fpm
systemctl start docker
systemctl enable docker
echo "✓ Services started and enabled"
echo ""

# Add www-data to docker group
echo "Configuring Docker permissions..."
usermod -aG docker www-data
echo "✓ Added www-data to docker group"
echo ""

# Set proper permissions for the installation directory
echo "Setting directory permissions..."
chown -R www-data:www-data "$SCRIPT_DIR"
chmod -R 755 "$SCRIPT_DIR"
chmod 664 "$SCRIPT_DIR/nexus.sqlite" 2>/dev/null || touch "$SCRIPT_DIR/nexus.sqlite" && chown www-data:www-data "$SCRIPT_DIR/nexus.sqlite" && chmod 664 "$SCRIPT_DIR/nexus.sqlite"
echo "✓ Directory permissions configured"
echo ""

# Configure Nginx
echo "Configuring Nginx..."
NGINX_CONFIG="/etc/nginx/sites-available/nexus"

cat > "$NGINX_CONFIG" << EOF
server {
    listen 80;
    server_name _;
    root $SCRIPT_DIR;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Logging
    access_log /var/log/nginx/nexus_access.log;
    error_log /var/log/nginx/nexus_error.log;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to backup and source files
    location ~ \.(bak|cache|conf|dist|fla|in|log|psd|sh|sql|sw[op])$ {
        deny all;
    }

    # Static assets with cache
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

echo "✓ Nginx configuration created"
echo ""

# Disable default site and enable nexus site
echo "Enabling Nexus site..."
rm -f /etc/nginx/sites-enabled/default
ln -sf "$NGINX_CONFIG" /etc/nginx/sites-enabled/
echo "✓ Site enabled"
echo ""

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t
echo "✓ Nginx configuration test passed"
echo ""

# Reload Nginx
echo "Reloading Nginx..."
systemctl reload nginx
echo "✓ Nginx reloaded"
echo ""

# Final checks
echo "Performing final checks..."

# Check if services are running
if systemctl is-active --quiet nginx; then
    echo "✓ Nginx is running"
else
    echo "✗ Nginx is not running"
    exit 1
fi

if systemctl is-active --quiet php8.3-fpm; then
    echo "✓ PHP-FPM is running"
else
    echo "✗ PHP-FPM is not running"
    exit 1
fi

if systemctl is-active --quiet docker; then
    echo "✓ Docker is running"
else
    echo "✗ Docker is not running"
    exit 1
fi

# Check if www-data can run docker commands
if sudo -u www-data docker ps >/dev/null 2>&1; then
    echo "✓ www-data can execute Docker commands"
else
    echo "✗ www-data cannot execute Docker commands"
    echo "  You may need to reboot the system for group changes to take effect"
fi

echo ""
echo "========================================="
echo "  INSTALLATION COMPLETE!"
echo "========================================="
echo ""
echo "Nexus Panel has been successfully installed!"
echo ""
echo "Access your panel at: http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Default Admin Credentials:"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo "Important Notes:"
echo "• Change the default admin password after first login"
echo "• Ensure your firewall allows traffic on port 80"
echo "• Make sure Docker containers exist before assigning them to users"
echo "• The database file is located at: $SCRIPT_DIR/nexus.sqlite"
echo ""
echo "To start using Nexus Panel:"
echo "1. Visit the URL above in your browser"
echo "2. Login with the default admin credentials"
echo "3. Create users and assign Docker containers"
echo ""
echo "Need help? Check the GitHub repository for documentation."
echo ""