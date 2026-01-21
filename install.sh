#!/bin/bash

# Nexus Panel Installation Script
# Ubuntu 24.04 LTS Setup with Nginx, PHP 8.3, SQLite, and Docker
# Project Repository: https://github.com/Bebo-Naiem/nexus-panel

set -euo pipefail  # Exit on error, undefined vars, and pipe failures

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================="
echo "  Nexus Panel Installation Script"
echo "  Game Server Management System"
echo "========================================="
echo -e "${NC}"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root (use sudo)${NC}" 
   exit 1
fi

echo -e "${GREEN}✓ Running with root privileges${NC}"
echo ""

# Set web root directory to standard location
INSTALL_DIR="/var/www/nexus-panel"
echo -e "${GREEN}✓ Installation directory: $INSTALL_DIR${NC}"
echo ""

# Update system packages
echo -e "${BLUE}Updating system packages...${NC}"
apt update -y
apt upgrade -y
echo -e "${GREEN}✓ System packages updated${NC}"
echo ""

# Install required packages
echo -e "${BLUE}Installing required packages...${NC}"
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
    software-properties-common || {
    echo -e "${RED}Failed to install required packages${NC}"
    exit 1
}
echo -e "${GREEN}✓ Required packages installed${NC}"
echo ""

# Start and enable services
echo -e "${BLUE}Starting and enabling services...${NC}"
systemctl start nginx || { echo -e "${RED}Failed to start nginx${NC}"; exit 1; }
systemctl enable nginx
systemctl start php8.3-fpm || { echo -e "${RED}Failed to start php8.3-fpm${NC}"; exit 1; }
systemctl enable php8.3-fpm
systemctl start docker || { echo -e "${RED}Failed to start docker${NC}"; exit 1; }
systemctl enable docker
echo -e "${GREEN}✓ Services started and enabled${NC}"
echo ""

# Add www-data to docker group
echo -e "${BLUE}Configuring Docker permissions...${NC}"
usermod -aG docker www-data
echo -e "${GREEN}✓ Added www-data to docker group${NC}"
echo ""

# Create installation directory if it doesn't exist
echo -e "${BLUE}Creating installation directory...${NC}"
mkdir -p "$INSTALL_DIR"

# Copy files with error checking
cp -r . "$INSTALL_DIR" || {
    echo -e "${RED}Failed to copy files to $INSTALL_DIR${NC}"
    exit 1
}

# Set proper ownership and permissions
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"

# Handle database file
DB_FILE="$INSTALL_DIR/nexus.sqlite"
if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
fi
chown www-data:www-data "$DB_FILE"
chmod 664 "$DB_FILE"

echo -e "${GREEN}✓ Directory permissions configured${NC}"
echo ""

# Configure Nginx
echo -e "${BLUE}Configuring Nginx...${NC}"
NGINX_CONFIG="/etc/nginx/sites-available/nexus"

cat > "$NGINX_CONFIG" << EOF
server {
    listen 80;
    server_name _;
    root $INSTALL_DIR;
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

    # Main location - handles routing for the PHP application
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing - critical for the PHP application
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        
        # Security settings
        fastcgi_param SCRIPT_FILENAME \$request_filename;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ ^/\.user\.ini {
        deny all;
    }

    # Optimize static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        log_not_found off;
    }

    # Protect sensitive files
    location ~* \.(htaccess|htpasswd|ini|log|sh|sql|conf)$ {
        deny all;
    }
}
EOF

echo -e "${GREEN}✓ Nginx configuration created${NC}"
echo ""

# Disable default site and enable nexus site
echo -e "${BLUE}Enabling Nexus site...${NC}"
rm -f /etc/nginx/sites-enabled/default
ln -sf "$NGINX_CONFIG" /etc/nginx/sites-enabled/ || {
    echo -e "${RED}Failed to enable Nexus site${NC}"
    exit 1
}
echo -e "${GREEN}✓ Site enabled${NC}"
echo ""

# Test Nginx configuration
echo -e "${BLUE}Testing Nginx configuration...${NC}"
if nginx -t; then
    echo -e "${GREEN}✓ Nginx configuration test passed${NC}"
else
    echo -e "${RED}✗ Nginx configuration test failed${NC}"
    exit 1
fi
echo ""

# Reload Nginx
echo -e "${BLUE}Reloading Nginx...${NC}"
systemctl reload nginx || {
    echo -e "${RED}Failed to reload Nginx${NC}"
    exit 1
}
echo -e "${GREEN}✓ Nginx reloaded${NC}"
echo ""

# Final checks
echo -e "${BLUE}Performing final checks...${NC}"

# Check if services are running
if systemctl is-active --quiet nginx; then
    echo -e "${GREEN}✓ Nginx is running${NC}"
else
    echo -e "${RED}✗ Nginx is not running${NC}"
    exit 1
fi

if systemctl is-active --quiet php8.3-fpm; then
    echo -e "${GREEN}✓ PHP-FPM is running${NC}"
else
    echo -e "${RED}✗ PHP-FPM is not running${NC}"
    exit 1
fi

if systemctl is-active --quiet docker; then
    echo -e "${GREEN}✓ Docker is running${NC}"
else
    echo -e "${RED}✗ Docker is not running${NC}"
    exit 1
fi

# Check if www-data can run docker commands
echo -e "${BLUE}Checking Docker permissions for www-data...${NC}"
if sudo -u www-data docker ps >/dev/null 2>&1; then
    echo -e "${GREEN}✓ www-data can execute Docker commands${NC}"
else
    echo -e "${YELLOW}⚠ www-data cannot execute Docker commands${NC}"
    echo -e "${YELLOW}  You may need to reboot the system for group changes to take effect${NC}"
fi

echo ""
echo -e "${GREEN}========================================="
echo "  INSTALLATION COMPLETE!"
echo "========================================="
echo -e "${NC}"
echo -e "${GREEN}Nexus Panel has been successfully installed!${NC}"
echo ""
echo -e "${BLUE}Access your panel at:${NC} http://$(hostname -I | awk '{print $1}')"
echo ""
echo -e "${YELLOW}Default Admin Credentials:${NC}"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo -e "${YELLOW}Important Notes:${NC}"
echo "• Change the default admin password after first login"
echo "• Ensure your firewall allows traffic on port 80"
echo "• Make sure Docker containers exist before assigning them to users"
echo "• The database file is located at: $INSTALL_DIR/nexus.sqlite"
echo ""
echo -e "${BLUE}To start using Nexus Panel:${NC}"
echo "1. Visit the URL above in your browser"
echo "2. Login with the default admin credentials"
echo "3. Create users and assign Docker containers"
echo ""
echo -e "${BLUE}Need help? Check the GitHub repository for documentation.${NC}"
echo ""