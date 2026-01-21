#!/bin/bash

# Nexus Panel Production Auto-Installer & Startup Script for Ubuntu 24.04
# Fully automated installer that sets up Nginx + PHP-FPM + Docker
# Simply run ./startup.sh to install and start automatically

set -euo pipefail

# Ensure we're in the correct directory
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)

if [ ! -f "index.php" ]; then
    if [[ "$SCRIPT_DIR" == */nexus-panel ]]; then
        cd "$SCRIPT_DIR"
    else
        if [ -d "$HOME/nexus-panel" ]; then
            cd "$HOME/nexus-panel"
        elif [ -d "$PWD/nexus-panel" ]; then
            cd "$PWD/nexus-panel"
        else
            echo "Error: Cannot find nexus-panel directory"
            echo "Please run this script from the nexus-panel directory"
            exit 1
        fi
    fi
fi

# Check if running on Ubuntu 24.04
if [ ! -f /etc/os-release ] || ! grep -q "Ubuntu" /etc/os-release || ! grep -q "24.04" /etc/os-release; then
    echo "Error: This script is designed specifically for Ubuntu 24.04 LTS"
    if [ -f /etc/os-release ]; then
        echo "Current OS detected:"
        cat /etc/os-release 2>/dev/null | grep -E "^(PRETTY_NAME|=)" | head -2
    fi
    exit 1
fi

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}========================================="
echo "    Nexus Panel Auto-Installer & Startup"
echo "         Ubuntu 24.04 LTS Optimized"
echo "========================================="
echo -e "${NC}"

show_usage() {
    echo "Usage: $0 [option]"
    echo "  (no args)     - Automatic install + nginx setup (default)"
    echo "  nginx         - Configure and start with Nginx"
    exit 1
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        echo -e "${YELLOW}Notice: You are not root. You will likely be prompted for sudo password during installation.${NC}"
    fi
}

install_dependencies() {
    echo -e "${BLUE}Installing required dependencies for Ubuntu 24.04...${NC}"
    
    # Aggressively stop and disable Apache if present
    if command -v apache2 &> /dev/null || dpkg -l | grep -q apache2; then
        echo -e "${YELLOW}Stopping and removing Apache2 to prevent Port 80 conflicts...${NC}"
        sudo systemctl stop apache2 2>/dev/null || true
        sudo systemctl disable apache2 2>/dev/null || true
        sudo apt remove -y apache2 apache2-utils apache2-bin apache2-data 2>/dev/null || true
        sudo apt autoremove -y 2>/dev/null || true
    fi
    
    sudo apt update
    
    # Added net-tools (for netstat), psmisc (for fuser), and lsof to prevent startup errors
    sudo apt install -y \
        git \
        php8.3 \
        php8.3-sqlite3 \
        php8.3-curl \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-fpm \
        nginx \
        docker.io \
        docker-compose-v2 \
        ca-certificates \
        curl \
        gnupg \
        lsb-release \
        net-tools \
        psmisc \
        lsof
    
    # Add Docker's official GPG key
    if ! [ -e /usr/share/keyrings/docker-archive-keyring.gpg ]; then
        sudo mkdir -p /etc/apt/keyrings
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker-archive-keyring.gpg
    fi
    
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    sudo apt update
    
    if ! command -v docker &> /dev/null; then
        sudo apt install -y docker-ce docker-ce-cli containerd.io
    fi
    
    sudo systemctl start docker
    sudo systemctl enable docker
    sudo systemctl start php8.3-fpm
    sudo systemctl enable php8.3-fpm
    
    # Ensure current user is in docker group
    sudo usermod -aG docker $(whoami) 2>/dev/null || true
    
    echo -e "${GREEN}✓ Dependencies installed successfully${NC}"
}

clone_repository() {
    if [ -d "nexus-panel" ]; then
        echo -e "${YELLOW}Using existing directory${NC}"
        cd nexus-panel
        return 0
    fi
    
    echo -e "${BLUE}Cloning Nexus Panel repository...${NC}"
    git clone https://github.com/Bebo-Naiem/nexus-panel.git
    cd nexus-panel
    echo -e "${GREEN}✓ Repository cloned successfully${NC}"
}

setup_database() {
    echo -e "${BLUE}Setting up database...${NC}"
    if [ ! -f "test_db.php" ]; then
        echo -e "${YELLOW}Warning: test_db.php not found, skipping specific DB init step.${NC}"
        return
    fi
    php test_db.php
    echo -e "${GREEN}✓ Database setup completed${NC}"
}

set_permissions() {
    echo -e "${BLUE}Setting file permissions...${NC}"
    
    sudo chown -R $(whoami):$(whoami) .
    sudo chown -R www-data:www-data . 2>/dev/null || true
    
    find . -type f -name "*.php" -exec chmod 644 {} \;
    find . -type d -exec chmod 755 {} \;
    
    # Handle DB file creation if missing
    if [ ! -f nexus.sqlite ]; then
        touch nexus.sqlite
    fi
    chmod 664 nexus.sqlite
    sudo chown www-data:www-data nexus.sqlite
    
    mkdir -p logs storage
    chmod 755 logs storage
    sudo chown -R www-data:www-data logs storage
    
    # Fix Docker socket
    sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
    
    echo -e "${GREEN}✓ Permissions set${NC}"
}

setup_nginx() {
    echo -e "${BLUE}Configuring Nginx...${NC}"
    
    if [ ! -f "index.php" ]; then
        echo -e "${RED}Error: index.php not found in current directory${NC}"
        exit 1
    fi
    
    CURRENT_DIR=$(pwd)
    NGINX_CONFIG="/etc/nginx/sites-available/nexus-panel"
    NGINX_LINK="/etc/nginx/sites-enabled/nexus-panel"
    
    # Verify PHP Socket path for 24.04
    PHP_SOCKET="/run/php/php8.3-fpm.sock"
    if [ ! -S "$PHP_SOCKET" ]; then
        # Try fallback location
        if [ -S "/var/run/php/php8.3-fpm.sock" ]; then
            PHP_SOCKET="/var/run/php/php8.3-fpm.sock"
        else
            echo -e "${YELLOW}Warning: PHP 8.3 socket not found. Restarting PHP-FPM...${NC}"
            sudo systemctl restart php8.3-fpm
            sleep 2
        fi
    fi

    sudo bash -c "cat > $NGINX_CONFIG" << EOF
server {
    listen 80;
    server_name _;
    root $CURRENT_DIR;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

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
        fastcgi_buffer_size 128k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    location ~ /\. {
        deny all;
    }
    
    location ~ \\\.(env|sqlite|log)$ {
        deny all;
    }
}
EOF
    
    # Remove default configuration
    if [ -f /etc/nginx/sites-enabled/default ]; then
        sudo rm -f /etc/nginx/sites-enabled/default
    fi
    
    sudo ln -sf "$NGINX_CONFIG" "$NGINX_LINK"
    
    # AGGRESSIVE PORT 80 CLEANUP
    echo -e "${BLUE}Ensuring Port 80 is free...${NC}"
    
    # 1. Stop Nginx gracefully first
    sudo systemctl stop nginx 2>/dev/null || true
    
    # 2. Check for Apache specifically
    if pgrep apache2 >/dev/null 2>&1; then
        echo -e "${YELLOW}Killing lingering Apache processes...${NC}"
        sudo pkill -f apache2
    fi
    
    # 3. Force kill anything else on port 80
    if command -v fuser &> /dev/null; then
        sudo fuser -k 80/tcp 2>/dev/null || true
    elif command -v lsof &> /dev/null; then
        # Fallback if fuser fails but lsof exists
        PID=$(sudo lsof -t -i:80)
        if [ ! -z "$PID" ]; then
            sudo kill -9 $PID 2>/dev/null || true
        fi
    fi
    
    # Wait for socket release
    sleep 3
    
    echo -e "${BLUE}Testing Nginx configuration...${NC}"
    if sudo nginx -t; then
        echo -e "${GREEN}✓ Configuration test passed${NC}"
        sudo systemctl start nginx
        
        # Verify it actually started
        if systemctl is-active --quiet nginx; then
             echo -e "${GREEN}✓ Nginx started successfully${NC}"
             echo -e "${GREEN}=========================================${NC}"
             echo -e "${GREEN}  NEXUS PANEL IS RUNNING${NC}"
             echo -e "${YELLOW}  http://${HOSTNAME:-localhost}${NC}"
             echo -e "${GREEN}=========================================${NC}"
        else
             echo -e "${RED}✗ Nginx service failed to start despite valid config.${NC}"
             echo "Checking logs..."
             sudo journalctl -xeu nginx --no-pager | tail -n 20
             exit 1
        fi
    else
        echo -e "${RED}✗ Nginx configuration failed${NC}"
        sudo nginx -t
        exit 1
    fi
}

start_with_nginx() {
    setup_nginx
}

automatic_install() {
    check_root
    install_dependencies
    clone_repository
    setup_database
    set_permissions
    
    # Ensure www-data user is in docker group
    sudo usermod -aG docker www-data 2>/dev/null || true
    
    start_with_nginx
}

# Main script logic
if [ $# -eq 0 ]; then
    automatic_install
else
    case "${1:-help}" in
        "install")
            check_root
            install_dependencies
            clone_repository
            setup_database
            set_permissions
            echo -e "${GREEN}Installation completed! Run './startup.sh nginx' to start.${NC}"
            ;;
        "nginx")
            if [ ! -f "index.php" ] && [ -d "nexus-panel" ]; then
                cd nexus-panel
            fi
            start_with_nginx
            ;;
        "auto")
            automatic_install
            ;;
        *)
            show_usage
            ;;
    esac
fi