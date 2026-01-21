#!/bin/bash

# Nexus Panel Deployment Script for Linux
# This script prepares and deploys Nexus Panel to /var/www/nexus-panel
# Run this on your Linux server after transferring files from Windows

set -euo pipefail

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================="
echo "  Nexus Panel Linux Deployment Script"
echo "========================================="
echo -e "${NC}"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root (use sudo)${NC}" 
   exit 1
fi

echo -e "${GREEN}✓ Running with root privileges${NC}"
echo ""

# Set installation directory
INSTALL_DIR="/var/www/nexus-panel"
echo -e "${GREEN}✓ Target installation directory: $INSTALL_DIR${NC}"
echo ""

# Check if directory exists
if [ ! -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}Directory $INSTALL_DIR does not exist. Creating...${NC}"
    mkdir -p "$INSTALL_DIR"
fi

# Check if files are present
if [ ! -f "./index.php" ]; then
    echo -e "${RED}Error: Nexus Panel files not found in current directory${NC}"
    echo -e "${YELLOW}Please ensure you're in the directory containing the Nexus Panel files${NC}"
    exit 1
fi

echo -e "${BLUE}Deploying Nexus Panel files...${NC}"

# Copy all files to installation directory
echo -e "${BLUE}Copying files to $INSTALL_DIR...${NC}"
cp -r ./* "$INSTALL_DIR/" 2>/dev/null || {
    echo -e "${RED}Failed to copy files${NC}"
    exit 1
}

# Set proper ownership and permissions
echo -e "${BLUE}Setting file permissions...${NC}"
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"

# Handle database file specifically
DB_FILE="$INSTALL_DIR/nexus.sqlite"
if [ ! -f "$DB_FILE" ]; then
    echo -e "${BLUE}Creating database file...${NC}"
    touch "$DB_FILE"
fi
chown www-data:www-data "$DB_FILE"
chmod 664 "$DB_FILE"

# Create logs directory
LOGS_DIR="$INSTALL_DIR/logs"
if [ ! -d "$LOGS_DIR" ]; then
    mkdir -p "$LOGS_DIR"
    chown www-data:www-data "$LOGS_DIR"
    chmod 755 "$LOGS_DIR"
fi

echo -e "${GREEN}✓ Files deployed successfully${NC}"
echo ""

# Test PHP configuration
echo -e "${BLUE}Testing PHP configuration...${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo -e "${GREEN}✓ PHP $PHP_VERSION detected${NC}"
else
    echo -e "${RED}✗ PHP not found${NC}"
    exit 1
fi

# Test required PHP extensions
echo -e "${BLUE}Checking required PHP extensions...${NC}"
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

echo ""

# Test database connectivity
echo -e "${BLUE}Testing database connectivity...${NC}"
if php "$INSTALL_DIR/test_db.php"; then
    echo -e "${GREEN}✓ Database test passed${NC}"
else
    echo -e "${RED}✗ Database test failed${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}========================================="
echo "  DEPLOYMENT COMPLETE!"
echo "========================================="
echo -e "${NC}"

echo -e "${GREEN}Nexus Panel has been successfully deployed to $INSTALL_DIR${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "1. Configure your web server (Nginx/Apache) to serve $INSTALL_DIR"
echo "2. Ensure www-data has Docker permissions: usermod -aG docker www-data"
echo "3. Restart your web server"
echo "4. Access the panel at your server's IP address"
echo ""
echo -e "${YELLOW}Default credentials:${NC}"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo -e "${BLUE}Remember to change the default password after first login!${NC}"
echo ""