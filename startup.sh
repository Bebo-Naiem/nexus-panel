#!/bin/bash

# Nexus Panel Auto-Installer & Startup Script
# Automatically installs dependencies and starts the panel
# Usage: ./startup.sh [install|start|auto]

set -euo pipefail

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================="
echo "    Nexus Panel Auto-Installer & Startup"
echo "========================================="
echo -e "${NC}"

# Function to display usage
show_usage() {
    echo "Usage: $0 [option]"
    echo "Options:"
    echo "  install       - Install dependencies and setup panel"
    echo "  start         - Start development server only"
    echo "  nginx         - Configure and start with Nginx (recommended)"
    echo "  auto          - Automatic install + start (legacy)"
    echo "  help          - Show this help message"
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
    echo -e "${BLUE}Installing required dependencies...${NC}"
    
    # Update package lists
    apt update
    
    # Install packages
    apt install -y \
        git \
        php8.3 \
        php8.3-sqlite3 \
        php8.3-curl \
        php8.3-mbstring \
        php8.3-xml \
        docker.io \
        nginx
    
    # Start and enable services
    systemctl start docker
    systemctl enable docker
    
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
    echo -e "${BLUE}Setting file permissions...${NC}"
    
    # Set proper ownership (adjust as needed)
    chown -R $(whoami):$(whoami) .
    
    # Set proper file permissions
    find . -type f -name "*.php" -exec chmod 644 {} \;
    find . -type d -exec chmod 755 {} \;
    chmod 664 nexus.sqlite 2>/dev/null || touch nexus.sqlite && chmod 664 nexus.sqlite
    
    # Create necessary directories
    mkdir -p logs
    mkdir -p storage
    chmod 755 logs storage
    
    echo -e "${GREEN}✓ Permissions set${NC}"
}

# Function to configure and start Nginx
setup_nginx() {
    echo -e "${BLUE}Configuring Nginx...${NC}"
    
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
    
    # Create Nginx server block
    cat > "$NGINX_CONFIG" << EOF
server {
    listen 80;
    server_name _;
    root $CURRENT_DIR;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
    
    # Enable the site
    ln -sf "$NGINX_CONFIG" "$NGINX_LINK"
    
    # Test Nginx configuration
    if nginx -t 2>/dev/null; then
        # Reload Nginx
        systemctl reload nginx
        echo -e "${GREEN}✓ Nginx configured and reloaded${NC}"
        echo -e "${GREEN}✓ Nexus Panel is now accessible at: http://localhost${NC}"
        echo -e "${YELLOW}Default credentials: admin / admin123${NC}"
    else
        echo -e "${RED}✗ Nginx configuration test failed${NC}"
        echo -e "${YELLOW}Configuration saved to: $NGINX_CONFIG${NC}"
        exit 1
    fi
}

# Function to start development server
start_dev_server() {
    echo -e "${BLUE}Starting development server...${NC}"
    
    if [ ! -f "index.php" ]; then
        echo -e "${RED}Error: index.php not found in current directory${NC}"
        exit 1
    fi
    
    # Check if PHP is available
    if ! command -v php &> /dev/null; then
        echo -e "${RED}PHP is not installed${NC}"
        exit 1
    fi
    
    # Check required PHP extensions
    echo -e "${BLUE}Checking PHP extensions...${NC}"
    REQUIRED_EXTENSIONS=("pdo_sqlite" "curl" "mbstring" "json")
    MISSING_EXTENSIONS=()
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            MISSING_EXTENSIONS+=("$ext")
        fi
    done
    
    if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
        echo -e "${RED}✗ Missing PHP extensions: ${MISSING_EXTENSIONS[*]}${NC}"
        echo -e "${YELLOW}Please install the missing extensions${NC}"
        exit 1
    else
        echo -e "${GREEN}✓ All required PHP extensions are installed${NC}"
    fi
    
    # Get available port (try common ports)
    PORT=${PORT:-8080}
    
    echo -e "${GREEN}✓ Starting development server on port $PORT${NC}"
    echo -e "${YELLOW}Access the panel at: http://localhost:$PORT${NC}"
    echo -e "${YELLOW}Default credentials: admin / admin123${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the server${NC}"
    
    # Fix session permissions if running as root
    if [[ $EUID -eq 0 ]]; then
        mkdir -p /var/lib/php/sessions
        chmod 777 /var/lib/php/sessions 2>/dev/null || true
    fi
    
    php -S localhost:$PORT
}

# Function to start with Nginx
start_with_nginx() {
    setup_nginx
    
    # Start PHP-FPM if not running
    if ! systemctl is-active --quiet php8.3-fpm; then
        systemctl start php8.3-fpm
        systemctl enable php8.3-fpm
        echo -e "${GREEN}✓ PHP-FPM started and enabled${NC}"
    fi
    
    # Start Nginx if not running
    if ! systemctl is-active --quiet nginx; then
        systemctl start nginx
        systemctl enable nginx
        echo -e "${GREEN}✓ Nginx started and enabled${NC}"
    fi
    
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  NEXUS PANEL IS NOW RUNNING WITH NGINX${NC}"
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${YELLOW}Access the panel at: http://${HOSTNAME:-localhost}${NC}"
    echo -e "${YELLOW}Default credentials: admin / admin123${NC}"
    echo -e "${GREEN}Panel configured to run automatically with Nginx${NC}"
    echo -e "${GREEN}=========================================${NC}"
}

# Function for automatic installation and start
automatic_install() {
    echo -e "${BLUE}Starting automatic installation and setup...${NC}"
    
    check_root
    check_git
    check_php
    check_docker
    
    install_dependencies
    clone_repository
    setup_database
    set_permissions
    start_dev_server
}

# Main script logic
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
        if [ ! -f "index.php" ]; then
            if [ -d "nexus-panel" ]; then
                cd nexus-panel
            else
                echo -e "${RED}Nexus Panel directory not found${NC}"
                exit 1
            fi
        fi
        start_dev_server
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