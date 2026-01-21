#!/bin/bash

# ==========================================================
# NEXUS PANEL AUTO-INSTALLER (Ubuntu 24.04)
# Target Directory: /var/www/nexus-panel
# ==========================================================

set -euo pipefail

# Configuration
TARGET_DIR="/var/www/nexus-panel"
REPO_URL="https://github.com/Bebo-Naiem/nexus-panel.git"
NGINX_CONF="/etc/nginx/sites-available/nexus-panel"
PHP_SOCKET="/run/php/php8.3-fpm.sock"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 1. ROOT PRIVILEGE CHECK
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: This script must be run as root.${NC}"
   echo "Please run: sudo $0"
   exit 1
fi

echo -e "${BLUE}========================================="
echo "    Nexus Panel Installer"
echo "    Target: $TARGET_DIR"
echo "========================================="
echo -e "${NC}"

# 2. DEPENDENCY INSTALLATION & APACHE REMOVAL
echo -e "${BLUE}[1/5] Installing Dependencies...${NC}"

# Kill/Remove Apache to prevent Port 80 conflicts
if dpkg -l | grep -q apache2; then
    echo -e "${YELLOW}Removing Apache2 to prevent conflicts...${NC}"
    systemctl stop apache2 2>/dev/null || true
    systemctl disable apache2 2>/dev/null || true
    apt-get purge -y apache2 apache2-utils apache2-bin apache2-data 2>/dev/null || true
    apt-get autoremove -y 2>/dev/null || true
fi

apt-get update -qq
# Install Nginx, PHP 8.3, Docker, and system tools
apt-get install -y -qq \
    git curl wget unzip \
    nginx \
    php8.3 php8.3-fpm php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml \
    docker.io docker-compose-v2 \
    net-tools psmisc lsof acl

# Enable Services
systemctl enable docker
systemctl start docker
systemctl enable php8.3-fpm
systemctl start php8.3-fpm

# 3. DIRECTORY SETUP (/var/www/nexus-panel)
echo -e "${BLUE}[2/5] Setting up Directory...${NC}"

# Ensure parent directory exists
mkdir -p /var/www

# Check if target exists
if [ -d "$TARGET_DIR" ]; then
    echo -e "${YELLOW}Directory exists. Updating...${NC}"
    
    # If directory is empty, clone. If not, pull.
    if [ -z "$(ls -A $TARGET_DIR)" ]; then
        git clone "$REPO_URL" "$TARGET_DIR"
    else
        # Navigate and pull
        cd "$TARGET_DIR"
        
        # Check if it is a git repo
        if [ -d ".git" ]; then
            git pull || echo -e "${YELLOW}Git pull failed, using existing files...${NC}"
        else
            echo -e "${YELLOW}Not a git repo. Proceeding with existing files...${NC}"
        fi
    fi
else
    echo -e "${YELLOW}Cloning fresh repository...${NC}"
    git clone "$REPO_URL" "$TARGET_DIR"
fi

# 4. CONFIGURATION & PERMISSIONS
echo -e "${BLUE}[3/5] Configuring System...${NC}"
cd "$TARGET_DIR"

# Initialize Database
if [ -f "test_db.php" ]; then
    php test_db.php
fi

# Set Ownership (www-data needs access)
chown -R www-data:www-data "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
find "$TARGET_DIR" -type f -name "*.php" -exec chmod 644 {} \;

# Specific Write Permissions
mkdir -p logs storage
chmod -R 775 logs storage
chown -R www-data:www-data logs storage

if [ -f "nexus.sqlite" ]; then
    chmod 664 nexus.sqlite
    chown www-data:www-data nexus.sqlite
fi

# Allow www-data to use Docker
usermod -aG docker www-data
chmod 666 /var/run/docker.sock 2>/dev/null || true

# 5. NGINX SETUP
echo -e "${BLUE}[4/5] Generating Nginx Config...${NC}"

# Backup existing config if any
if [ -f "$NGINX_CONF" ]; then mv "$NGINX_CONF" "$NGINX_CONF.bak"; fi

cat > "$NGINX_CONF" << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root $TARGET_DIR;
    index index.php index.html;

    access_log /var/log/nginx/nexus_access.log;
    error_log /var/log/nginx/nexus_error.log;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    location = / {
        try_files /index.php /index.php;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. { deny all; }
    location ~ \.(env|sqlite|log)$ { deny all; }
}
EOF

# Link site and remove default
rm -f /etc/nginx/sites-enabled/default
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/nexus-panel

# 6. PORT CLEANUP & STARTUP
echo -e "${BLUE}[5/5] Starting Server...${NC}"

# Stop Nginx to clear locks
systemctl stop nginx 2>/dev/null || true

# Kill anything blocking Port 80 (Fixes 'Job failed' error)
if command -v fuser &> /dev/null; then
    fuser -k 80/tcp 2>/dev/null || true
fi

# Additional check with lsof
if command -v lsof &> /dev/null; then
    PID=$(lsof -t -i:80)
    if [ ! -z "$PID" ]; then kill -9 $PID 2>/dev/null || true; fi
fi

# Wait a moment for ports to free up
sleep 2

# Test and Start
if nginx -t; then
    systemctl start nginx
    
    if systemctl is-active --quiet nginx; then
        echo -e "${GREEN}=========================================${NC}"
        echo -e "${GREEN}  INSTALLATION SUCCESSFUL${NC}"
        echo -e "${GREEN}  Folder: $TARGET_DIR${NC}"
        echo -e "${GREEN}  URL:    http://${HOSTNAME:-localhost}${NC}"
        echo -e "${GREEN}=========================================${NC}"
        
        # Final Permissions check
        chown -R www-data:www-data "$TARGET_DIR"
    else
        echo -e "${RED}Nginx failed to start.${NC}"
        echo "Showing recent logs:"
        journalctl -xeu nginx --no-pager | tail -n 20
        exit 1
    fi
else
    echo -e "${RED}Nginx Configuration Invalid.${NC}"
    nginx -t
    exit 1
fi