#!/bin/bash

# Nexus Panel Production Auto-Installer & Startup Script for Ubuntu 24.04
# Fully automated installer that sets up Nginx + PHP-FPM + Docker
# Simply run ./startup.sh to install and start automatically

set -euo pipefail

# Ensure we're in the correct directory
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)

# If we're in a directory containing index.php, stay here
# Otherwise, try to find the project directory
if [ ! -f "index.php" ]; then
    # Check if we're in the nexus-panel directory
    if [[ "$SCRIPT_DIR" == */nexus-panel ]]; then
        cd "$SCRIPT_DIR"
    else
        # Look for the project directory
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
    else
        echo "No /etc/os-release file found - not running on a standard Linux distribution"
    fi
    echo ""
    echo "For Ubuntu 24.04 LTS, run this script directly."
    echo "For other systems, see the documentation for manual setup instructions."
    echo ""
    echo "On Windows, use WSL2 with Ubuntu 24.04 LTS:"
    echo "  1. Install WSL2: wsl --install -d Ubuntu-24.04"
    echo "  2. Move to WSL filesystem: cd /home/username/nexus-panel"
    echo "  3. Run: ./startup.sh"
    exit 1
fi

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================="
echo "    Nexus Panel Auto-Installer & Startup"
echo "         Ubuntu 24.04 LTS Optimized"
echo "========================================="
echo -e "${NC}"

# Function to display usage
show_usage() {
    echo "Usage: $0 [option]"
    echo "Automated Options:"
    echo "  (no args)     - Automatic install + nginx setup (default)"
    echo "  install       - Install dependencies and setup panel"
    echo "  nginx         - Configure and start with Nginx (recommended)"
    echo "  auto          - Automatic install + nginx setup (recommended)"
    echo "  help          - Show this help message"
    echo ""
    echo "Note: Development server mode has been removed for production focus"
    exit 1
}

# Function to check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        echo -e "${YELLOW}Warning: Running as root is not recommended for the web server${NC}"
        echo -e "${YELLOW}Consider running as a regular user for security${NC}"
    fi
}

# Function to check if git is installed
check_git() {
    if ! command -v git &> /dev/null; then
        echo -e "${RED}Git is not installed. Installing...${NC}"
        apt update
        apt install -y git
    fi
}

# Function to check if PHP is installed
check_php() {
    if ! command -v php &> /dev/null; then
        echo -e "${RED}PHP is not installed. Installing...${NC}"
        apt update
        apt install -y php8.3 php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml
    fi
}

# Function to check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        echo -e "${RED}Docker is not installed. Installing...${NC}"
        apt update
        apt install -y docker.io
        systemctl start docker
        systemctl enable docker
    fi
}

# Function to install dependencies
install_dependencies() {
    echo -e "${BLUE}Installing required dependencies for Ubuntu 24.04...${NC}"
    
    # Update package lists
    apt update
    
    # Install packages specific to Ubuntu 24.04
    # First, make sure Apache is not installed or remove it if it is
    if dpkg -l | grep -q apache2; then
        echo -e "${YELLOW}Removing Apache2 to avoid conflicts with Nginx...${NC}"
        systemctl stop apache2 2>/dev/null || true
        systemctl disable apache2 2>/dev/null || true
        apt remove -y apache2 apache2-utils apache2-bin apache2-data 2>/dev/null || true
    fi
    
    apt install -y \
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
        lsb-release
    
    # Add Docker's official GPG key if not already added
    if ! [ -e /usr/share/keyrings/docker-archive-keyring.gpg ]; then
        mkdir -p /etc/apt/keyrings
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker-archive-keyring.gpg
    fi
    
    # Add Docker repository
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    # Update package lists again
    apt update
    
    # Install Docker engine if not already installed
    if ! command -v docker &> /dev/null; then
        apt install -y docker-ce docker-ce-cli containerd.io
    fi
    
    # Start and enable services
    systemctl start docker
    systemctl enable docker
    systemctl start nginx
    systemctl enable nginx
    systemctl start php8.3-fpm
    systemctl enable php8.3-fpm
    
    # Add current user to docker group
    usermod -aG docker $(whoami) 2>/dev/null || true
    
    echo -e "${GREEN}✓ Dependencies installed successfully${NC}"
}

# Function to clone the repository
clone_repository() {
    if [ -d "nexus-panel" ]; then
        echo -e "${YELLOW}Nexus Panel directory already exists${NC}"
        read -p "Remove existing directory and clone fresh? (y/N): " confirm
        if [[ $confirm =~ ^[Yy]$ ]]; then
            rm -rf nexus-panel
        else
            echo -e "${YELLOW}Using existing directory${NC}"
            cd nexus-panel
            return 0
        fi
    fi
    
    echo -e "${BLUE}Cloning Nexus Panel repository...${NC}"
    git clone https://github.com/Bebo-Naiem/nexus-panel.git
    cd nexus-panel
    
    echo -e "${GREEN}✓ Repository cloned successfully${NC}"
}

# Function to setup database
setup_database() {
    echo -e "${BLUE}Setting up database...${NC}"
    
    # Make sure we're in the right directory
    if [ ! -f "test_db.php" ]; then
        echo -e "${RED}Error: Not in Nexus Panel directory${NC}"
        exit 1
    fi
    
    # Run database setup
    php test_db.php
    
    echo -e "${GREEN}✓ Database setup completed${NC}"
}

# Function to set permissions
set_permissions() {
    echo -e "${BLUE}Setting file permissions for Ubuntu 24.04...${NC}"
    
    # Set proper ownership (adjust as needed)
    chown -R $(whoami):$(whoami) .
    
    # Ensure www-data can access necessary files for web operation
    chown -R www-data:www-data . 2>/dev/null || true
    
    # Set proper file permissions
    find . -type f -name "*.php" -exec chmod 644 {} \;
    find . -type d -exec chmod 755 {} \;
    chmod 664 nexus.sqlite 2>/dev/null || touch nexus.sqlite && chmod 664 nexus.sqlite
    
    # Create necessary directories
    mkdir -p logs
    mkdir -p storage
    chmod 755 logs storage
    chown -R www-data:www-data logs storage 2>/dev/null || true
    
    # Fix Docker socket permissions for Ubuntu 24.04
    chmod 666 /var/run/docker.sock 2>/dev/null || true
    
    echo -e "${GREEN}✓ Permissions set${NC}"
}

# Function to configure and start Nginx
setup_nginx() {
    echo -e "${BLUE}Configuring Nginx for Ubuntu 24.04...${NC}"
    
    if [ ! -f "index.php" ]; then
        echo -e "${RED}Error: index.php not found in current directory${NC}"
        exit 1
    fi
    
    # Get current directory
    CURRENT_DIR=$(pwd)
    
    # Create Nginx configuration
    NGINX_CONFIG="/etc/nginx/sites-available/nexus-panel"
    NGINX_LINK="/etc/nginx/sites-enabled/nexus-panel"
    
    # Backup existing config if it exists
    if [ -f "$NGINX_CONFIG" ]; then
        cp "$NGINX_CONFIG" "$NGINX_CONFIG.backup.$(date +%s)"
    fi
    
    # Create Nginx server block optimized for Ubuntu 24.04
    cat > "$NGINX_CONFIG" << EOF
server {
    listen 80;
    server_name _;
    root $CURRENT_DIR;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Ensure the root location serves index.php by default
    location = / {
        try_files /index.php /index.php;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 128k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    location ~ /\. {
        deny all;
    }
    
    # Deny access to sensitive files
    location ~ \\\.(env|sqlite|log)$ {
        deny all;
    }
}
EOF
    
    # Disable default Nginx site to avoid conflicts
    if [ -f /etc/nginx/sites-enabled/default ]; then
        rm -f /etc/nginx/sites-enabled/default
        echo -e "${YELLOW}Disabled default Nginx site to avoid conflicts${NC}"
    fi
    
    # Enable the site
    ln -sf "$NGINX_CONFIG" "$NGINX_LINK"
    
    # Check if port 80 is available
    if netstat -tuln | grep -q ':80 '; then
        echo -e "${YELLOW}Warning: Port 80 is already in use${NC}"
        netstat -tuln | grep ':80 '
        echo -e "${YELLOW}Stopping any service on port 80...${NC}"
        # Try to identify what's using port 80
        lsof -i :80 2>/dev/null | head -n 10 || true
        # Kill any process on port 80 if it's not nginx
        if ! pgrep nginx >/dev/null 2>&1; then
            sudo fuser -k 80/tcp 2>/dev/null || true
            sleep 2
        fi
    fi
    
    # Test Nginx configuration
    if nginx -t; then
        # Stop nginx if running before enabling new site
        systemctl stop nginx 2>/dev/null || true
        sleep 2  # Give time for nginx to fully stop
        
        # Reload systemd to pick up any changes
        systemctl daemon-reload
        
        # Start Nginx
        if systemctl start nginx; then
            echo -e "${GREEN}✓ Nginx configured and started${NC}"
            echo -e "${GREEN}✓ Nexus Panel is now accessible at: http://localhost${NC}"
            echo -e "${YELLOW}Default credentials: admin / admin123${NC}"
        else
            echo -e "${RED}✗ Nginx failed to start${NC}"
            echo -e "${YELLOW}Check nginx status with: systemctl status nginx${NC}"
            journalctl -xeu nginx --no-pager | head -n 20
            exit 1
        fi
    else
        echo -e "${RED}✗ Nginx configuration test failed${NC}"
        echo -e "${YELLOW}Configuration saved to: $NGINX_CONFIG${NC}"
        nginx -t 2>&1
        exit 1
    fi
}

# Function to start production server
start_production_server() {
    echo -e "${RED}Development server mode is disabled${NC}"
    echo -e "${YELLOW}Use 'nginx' option to start production server${NC}"
    echo -e "${BLUE}Example: ./startup.sh nginx${NC}"
    exit 1
}

# Function to start with Nginx
start_with_nginx() {
    setup_nginx
    
    # Start PHP-FPM if not running
    if ! systemctl is-active --quiet php8.3-fpm; then
        systemctl start php8.3-fpm
        systemctl enable php8.3-fpm
        echo -e "${GREEN}✓ PHP-FPM started and enabled${NC}"
    else
        # Restart PHP-FPM to ensure it's working properly
        systemctl restart php8.3-fpm
        echo -e "${GREEN}✓ PHP-FPM restarted${NC}"
    fi
    
    # Ensure Nginx is not running before starting
    systemctl stop nginx 2>/dev/null || true
    sleep 2
    
    # Start Nginx
    if systemctl start nginx; then
        systemctl enable nginx
        echo -e "${GREEN}✓ Nginx started and enabled${NC}"
    else
        echo -e "${RED}✗ Nginx failed to start${NC}"
        echo -e "${YELLOW}Check configuration and logs:${NC}"
        nginx -t
        systemctl status nginx --no-pager
        journalctl -xeu nginx --no-pager | head -n 20
        exit 1
    fi
    
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  NEXUS PANEL IS NOW RUNNING WITH NGINX${NC}"
    echo -e "${GREEN}  Ubuntu 24.04 LTS Optimized Version${NC}"
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${YELLOW}Access the panel at: http://${HOSTNAME:-localhost}${NC}"
    echo -e "${YELLOW}Default credentials: admin / admin123${NC}"
    echo -e "${GREEN}Panel configured to run automatically with Nginx${NC}"
    echo -e "${GREEN}=========================================${NC}"
    
    # Final status check for Ubuntu 24.04
    echo -e "${BLUE}Performing final status check...${NC}"
    systemctl status nginx php8.3-fpm docker --no-pager
}

# Function for automatic installation and start
automatic_install() {
    echo -e "${BLUE}Starting automatic installation and setup for Ubuntu 24.04...${NC}"
    
    check_root
    check_git
    check_php
    check_docker
    
    install_dependencies
    clone_repository
    setup_database
    set_permissions
    
    # Ensure Docker daemon is running
    systemctl start docker
    systemctl enable docker
    
    # Add www-data user to docker group for web-based Docker control
    usermod -aG docker www-data 2>/dev/null || true
    
    start_with_nginx
}

# Main script logic
if [ $# -eq 0 ]; then
    # No arguments provided, run automatic installation by default
    echo -e "${BLUE}No arguments provided. Running automatic installation and setup...${NC}"
    automatic_install
else
    case "${1:-help}" in
        "install")
            check_root
            check_git
            check_php
            check_docker
            install_dependencies
            clone_repository
            setup_database
            set_permissions
            echo -e "${GREEN}Installation completed! Run './startup.sh nginx' to start with Nginx.${NC}"
            ;;
        "start")
            echo -e "${RED}Development server mode has been removed${NC}"
            echo -e "${YELLOW}Use 'nginx' option instead for production setup${NC}"
            exit 1
            ;;
        "nginx")
            if [ ! -f "index.php" ]; then
                if [ -d "nexus-panel" ]; then
                    cd nexus-panel
                else
                    echo -e "${RED}Nexus Panel directory not found${NC}"
                    exit 1
                fi
            fi
            start_with_nginx
            ;;
        "auto")
            automatic_install
            ;;
        "help"|"-h"|"--help")
            show_usage
            ;;
        *)
            echo -e "${RED}Invalid option: $1${NC}"
            show_usage
            ;;
    esac
fi