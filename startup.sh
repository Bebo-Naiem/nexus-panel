#!/bin/bash

# ==========================================================
# NEXUS PANEL REPAIR & INSTALLER (Ubuntu 24.04)
# Target: /var/www/nexus-panel
# Fixes: Firewall, Permissions, Port 80 Conflicts, PHP Socket
# ==========================================================

set -u

# --- CONFIGURATION ---
TARGET_DIR="/var/www/nexus-panel"
REPO_URL="https://github.com/Bebo-Naiem/nexus-panel.git"
NGINX_CONF="/etc/nginx/sites-available/nexus-panel"
PHP_SOCKET="/run/php/php8.3-fpm.sock"

# --- COLORS ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# --- 1. ROOT CHECK ---
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: Run as root (sudo ./startup.sh)${NC}"
   exit 1
fi

echo -e "${BLUE}>>> Starting Nexus Panel Installation & Repair...${NC}"

# --- 2. STOP EVERYTHING ---
echo -e "${YELLOW}Stopping services to clear conflicts...${NC}"
systemctl stop nginx 2>/dev/null
systemctl stop apache2 2>/dev/null
systemctl stop php8.3-fpm 2>/dev/null

# Kill ANY process on Port 80
if command -v fuser &> /dev/null; then fuser -k 80/tcp 2>/dev/null; fi
if command -v lsof &> /dev/null; then
    PID=$(lsof -t -i:80)
    if [ ! -z "$PID" ]; then kill -9 $PID; fi
fi

# --- 3. INSTALL DEPENDENCIES ---
echo -e "${BLUE}Installing Dependencies...${NC}"
apt-get update -qq
apt-get install -y -qq git curl nginx php8.3-fpm php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml docker.io net-tools ufw

# --- 4. SETUP DIRECTORY ---
echo -e "${BLUE}Setting up /var/www/nexus-panel...${NC}"
mkdir -p /var/www

if [ -d "$TARGET_DIR" ]; then
    # Backup existing config if exists
    if [ -f "$TARGET_DIR/.env" ]; then cp "$TARGET_DIR/.env" /tmp/nexus_env_backup; fi
    
    # Reset git to force clean state
    cd "$TARGET_DIR"
    git fetch --all
    git reset --hard origin/main
    git pull
else
    git clone "$REPO_URL" "$TARGET_DIR"
fi

cd "$TARGET_DIR"

# Database Init
if [ -f "test_db.php" ]; then php test_db.php; fi

# --- 5. PERMISSIONS (CRITICAL) ---
echo -e "${BLUE}Applying Permissions...${NC}"
chown -R www-data:www-data "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
# Ensure index.php is readable
chmod 644 "$TARGET_DIR/index.php"

# Storage dirs
mkdir -p logs storage
chown -R www-data:www-data logs storage
chmod -R 775 logs storage

# --- 6. PHP & NGINX CONFIG ---
echo -e "${BLUE}Configuring Web Server...${NC}"

# Restart PHP to ensure socket exists
systemctl restart php8.3-fpm

# Check for socket
if [ ! -S "$PHP_SOCKET" ]; then
    echo -e "${RED}Error: PHP Socket $PHP_SOCKET not found!${NC}"
    echo "Attempting to find socket..."
    PHP_SOCKET=$(find /run/php -name "php*-fpm.sock" | head -n 1)
    if [ -z "$PHP_SOCKET" ]; then
        echo -e "${RED}PHP is not running properly. Check 'systemctl status php8.3-fpm'${NC}"
        exit 1
    fi
    echo -e "${YELLOW}Found socket at: $PHP_SOCKET${NC}"
fi

cat > "$NGINX_CONF" << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root $TARGET_DIR;
    index index.php index.html;

    access_log /var/log/nginx/nexus_access.log;
    error_log /var/log/nginx/nexus_error.log;

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
}
EOF

# Link Config
rm -f /etc/nginx/sites-enabled/default
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/nexus-panel

# --- 7. FIREWALL FIX ---
echo -e "${BLUE}Configuring Firewall (UFW)...${NC}"
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp
    ufw allow 22/tcp
    # Don't enable if not already enabled to avoid locking user out
    if ufw status | grep -q "Status: active"; then
        ufw reload
    fi
fi

# --- 8. START & VERIFY ---
echo -e "${BLUE}Starting Nginx...${NC}"
if nginx -t; then
    systemctl start nginx
    systemctl enable nginx
else
    echo -e "${RED}Nginx Config Error${NC}"
    nginx -t
    exit 1
fi

sleep 2

# INTERNAL CONNECTION TEST
echo -e "${BLUE}Testing connection...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)

if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "302" ]; then
    echo -e "${GREEN}==============================================${NC}"
    echo -e "${GREEN} SUCCESS! Server is running.${NC}"
    echo -e "${GREEN} URL: http://${HOSTNAME:-localhost}${NC}"
    echo -e "${GREEN} Location: $TARGET_DIR${NC}"
    echo -e "${GREEN}==============================================${NC}"
else
    echo -e "${RED}==============================================${NC}"
    echo -e "${RED} WARNING: Server started, but Localhost returned Code: $HTTP_CODE${NC}"
    echo -e "${RED}==============================================${NC}"
    echo -e "${YELLOW}Troubleshooting info:${NC}"
    echo "1. Nginx Status: $(systemctl is-active nginx)"
    echo "2. PHP Status: $(systemctl is-active php8.3-fpm)"
    echo "3. Last Nginx Error Log:"
    tail -n 5 /var/log/nginx/nexus_error.log 2>/dev/null || echo "No error log found."
fi