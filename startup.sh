#!/bin/bash

# Nexus Panel Startup & Git Operations Script
# This script handles git operations and starts the Nexus Panel development server
# Usage: ./startup.sh [git-clone|start-dev|full-setup]

set -euo pipefail

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================="
echo "  Nexus Panel Startup & Git Operations"
echo "========================================="
echo -e "${NC}"

# Function to display usage
show_usage() {
    echo "Usage: $0 [option]"
    echo "Options:"
    echo "  git-clone     - Clone the Nexus Panel repository from GitHub"
    echo "  start-dev     - Start development server with PHP built-in server"
    echo "  full-setup    - Clone repo and start dev server (complete setup)"
    echo "  help          - Show this help message"
    exit 1
}

# Function to clone the repository
git_clone() {
    echo -e "${BLUE}Cloning Nexus Panel repository...${NC}"
    
    if [ -d "nexus-panel" ]; then
        echo -e "${YELLOW}Directory 'nexus-panel' already exists${NC}"
        read -p "Remove existing directory and clone fresh? (y/N): " confirm
        if [[ $confirm =~ ^[Yy]$ ]]; then
            rm -rf nexus-panel
        else
            echo -e "${YELLOW}Changing to existing nexus-panel directory${NC}"
            cd nexus-panel
            echo -e "${GREEN}✓ Changed to nexus-panel directory${NC}"
            return 0
        fi
    fi
    
    git clone https://github.com/Bebo-Naiem/nexus-panel.git || {
        echo -e "${RED}Failed to clone repository${NC}"
        exit 1
    }
    
    cd nexus-panel
    echo -e "${GREEN}✓ Repository cloned successfully${NC}"
    echo -e "${GREEN}✓ Changed to nexus-panel directory${NC}"
}

# Function to start development server
start_dev_server() {
    echo -e "${BLUE}Starting development server...${NC}"
    
    if [ ! -f "index.php" ]; then
        echo -e "${RED}Error: index.php not found in current directory${NC}"
        echo -e "${YELLOW}Please run this in the Nexus Panel root directory or use git-clone first${NC}"
        exit 1
    fi
    
    # Check if PHP is available
    if ! command -v php &> /dev/null; then
        echo -e "${RED}PHP is not installed or not in PATH${NC}"
        exit 1
    fi
    
    # Get available port (try common ports)
    PORT=${PORT:-8080}
    
    echo -e "${GREEN}✓ Starting development server on port $PORT${NC}"
    echo -e "${YELLOW}Access the panel at: http://localhost:$PORT${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the server${NC}"
    
    php -S localhost:$PORT
}

# Function for full setup (clone + start dev server)
full_setup() {
    git_clone
    start_dev_server
}

# Main script logic
case "${1:-help}" in
    "git-clone")
        git_clone
        ;;
    "start-dev")
        start_dev_server
        ;;
    "full-setup")
        full_setup
        ;;
    "help"|"-h"|"--help")
        show_usage
        ;;
    *)
        echo -e "${RED}Invalid option: $1${NC}"
        show_usage
        ;;
esac