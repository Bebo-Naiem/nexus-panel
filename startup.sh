#!/bin/bash

# ==========================================================
# NEXUS PANEL - FULL INSTALLER & REPAIR (Ubuntu 24.04)
# Target: /var/www/nexus-panel
# Fixes: Port 80, Permissions, and "File Download" Issue
# ==========================================================

set -u

# --- CONFIGURATION ---
TARGET_DIR="/var/www/nexus-panel"
REPO_URL="https://github.com/Bebo-Naiem/nexus-panel.git"
NGINX_CONF="/etc/nginx/sites-available/nexus-panel"

# --- COLORS ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 1. ROOT CHECK
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: This script must be run as root.${NC}"
   echo "Run: sudo ./startup.sh"
   exit 1
fi

echo -e "${BLUE}>>> Starting Nexus Panel Installation...${NC}"

# 2. CLEANUP & DEPENDENCIES
echo -e "${BLUE}[1/5] Installing Dependencies...${NC}"

# Stop services to prevent locks
systemctl stop nginx 2>/dev/null
systemctl stop apache2 2>/dev/null

# Install Stack
apt-get update -qq
apt-get install -y -qq \
    git curl unzip \
    nginx \
    php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip \
    docker.io docker-compose-v2 \
    net-tools psmisc lsof

# 3. DIRECTORY SETUP
echo -e "${BLUE}[2/5] Setting up /var/www/nexus-panel...${NC}"

# CLEANUP: User requested to delete files in target directory first
# We must be careful not to delete the script itself if running from the target directory
CURRENT_DIR=$(pwd)
if [[ "$CURRENT_DIR" != "$TARGET_DIR" ]]; then
    echo -e "${YELLOW}Cleaning target directory ($TARGET_DIR)...${NC}"
    rm -rf "$TARGET_DIR"
    mkdir -p "$TARGET_DIR"
else
    echo -e "${YELLOW}Running from inside target directory. Skipping cleanup to prevent self-deletion.${NC}"
fi

mkdir -p /var/www

# Check if we are running from a source folder (not target) containing valid files
if [ -f "./index.php" ] && [ -f "./config.php" ] && [[ "$CURRENT_DIR" != "$TARGET_DIR" ]]; then
    # We are running from the source folder, so we copy files
    echo -e "${GREEN}Running from source. Installing to $TARGET_DIR...${NC}"
    cp -r . "$TARGET_DIR"
    cd "$TARGET_DIR"
elif [[ "$CURRENT_DIR" == "$TARGET_DIR" ]]; then
     # Already in target, just ensure we use what's here
     echo -e "${GREEN}Using existing files in $TARGET_DIR${NC}"
else
    # Fallback to Git if we are not in a valid source or target
    echo -e "${BLUE}Cloning from GitHub...${NC}"
    
    # Ensure dir exists if we just cleaned it
    if [ ! -d "$TARGET_DIR" ]; then mkdir -p "$TARGET_DIR"; fi
    
    git clone "$REPO_URL" "$TARGET_DIR"
    cd "$TARGET_DIR"
fi

# Database Init
if [ -f "test_db.php" ]; then php test_db.php; fi

# 4. PERMISSIONS
echo -e "${BLUE}[3/5] Fixing Permissions...${NC}"
chown -R www-data:www-data "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
# Ensure index.php specifically is readable
chmod 644 "$TARGET_DIR/index.php"

# Storage & Logs
mkdir -p logs storage
chown -R www-data:www-data logs storage
chmod -R 775 logs storage

# Docker Permissions
usermod -aG docker www-data
chmod 666 /var/run/docker.sock 2>/dev/null || true

# 5. PHP SOCKET DETECTION
# Find the exact socket path to avoid "502 Bad Gateway"
PHP_SOCKET=$(find /run/php -name "php8.3-fpm.sock" | head -n 1)
if [ -z "$PHP_SOCKET" ]; then
    # Fallback search
    PHP_SOCKET=$(find /run/php -name "php*-fpm.sock" | head -n 1)
fi

if [ -z "$PHP_SOCKET" ]; then
    echo -e "${RED}Error: PHP-FPM socket not found. Reinstalling PHP...${NC}"
    apt-get install --reinstall -y php8.3-fpm
    systemctl start php8.3-fpm
    PHP_SOCKET="/run/php/php8.3-fpm.sock"
fi
echo -e "${GREEN}Using PHP Socket: $PHP_SOCKET${NC}"

# 6. NGINX CONFIGURATION (THE FIX FOR DOWNLOADING FILES)
echo -e "${BLUE}[4/5] configuring Nginx...${NC}"

# We write a manual FastCGI block to ensure variables are passed correctly
cat > "$NGINX_CONF" << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root $TARGET_DIR;
    index index.php index.html;

    access_log /var/log/nginx/nexus_access.log;
    error_log /var/log/nginx/nexus_error.log;

    # Root Handler
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP Handler - THE FIX
    location ~ \.php$ {
        # Check if file exists
        try_files \$uri =404;

        # Split path info
        fastcgi_split_path_info ^(.+\.php)(/.+)$;

        # Connect to PHP-FPM
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_index index.php;

        # Standard Parameters
        include fastcgi_params;
        
        # CRITICAL: Tell PHP exactly where the script is
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }

    # Deny hidden files
    location ~ /\. { deny all; }
}
EOF

# Link site
rm -f /etc/nginx/sites-enabled/default
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/nexus-panel

# 7. STARTUP
echo -e "${BLUE}[5/5] Starting Services...${NC}"

# Port Cleanup
if command -v fuser &> /dev/null; then fuser -k 80/tcp 2>/dev/null; fi

# Restart PHP to ensure fresh config loading
systemctl restart php8.3-fpm

# Test and Start Nginx
if nginx -t; then
    systemctl restart nginx
    systemctl enable nginx
    systemctl enable php8.3-fpm
    
    echo -e "${GREEN}==============================================${NC}"
    echo -e "${GREEN}  INSTALLATION COMPLETE${NC}"
    echo -e "${GREEN}==============================================${NC}"
    echo -e "${YELLOW}IMPORTANT:${NC} Open your browser in ${YELLOW}INCOGNITO/PRIVATE MODE${NC}"
    echo -e "Access Panel at: http://${HOSTNAME:-localhost}"
    echo -e "${GREEN}==============================================${NC}"
else
    echo -e "${RED}Nginx Config Failed. Showing errors:${NC}"
    nginx -t
    exit 1
fi