#!/bin/bash

# Nexus Panel Startup Script
# Initializes the application and runs setup tasks

echo "========================================="
echo "  Nexus Panel Initialization Script"
echo "========================================="
echo ""

# Check if running as root (optional for initialization)
if [[ $EUID -eq 0 ]]; then
    echo "✓ Running with elevated privileges"
fi

# Set the script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "✓ Working directory: $SCRIPT_DIR"
echo ""

# Initialize SQLite database
echo "Initializing database..."
php "$SCRIPT_DIR/test_db.php"
echo "✓ Database initialized"
echo ""

# Check if Docker is running
echo "Checking Docker status..."
if command -v docker &> /dev/null; then
    if docker info &> /dev/null; then
        echo "✓ Docker is running and accessible"
    else
        echo "⚠ Docker is installed but not running or accessible"
        echo "  You may need to start Docker service and ensure permissions are set correctly"
    fi
else
    echo "✗ Docker is not installed"
    echo "  Please install Docker before running Nexus Panel"
    exit 1
fi
echo ""

# Check PHP extensions
echo "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("pdo_sqlite" "curl" "mbstring" "json")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo "✗ Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    echo "  Please install the required PHP extensions"
    exit 1
else
    echo "✓ All required PHP extensions are installed"
fi
echo ""

# Set proper file permissions
echo "Setting file permissions..."
find "$SCRIPT_DIR" -type f -name "*.php" -exec chmod 644 {} \;
find "$SCRIPT_DIR" -type d -exec chmod 755 {} \;
chmod 664 "$SCRIPT_DIR/nexus.sqlite" 2>/dev/null || touch "$SCRIPT_DIR/nexus.sqlite" && chmod 664 "$SCRIPT_DIR/nexus.sqlite"
echo "✓ File permissions set"
echo ""

# Create necessary directories if they don't exist
echo "Creating necessary directories..."
mkdir -p "$SCRIPT_DIR/logs"
mkdir -p "$SCRIPT_DIR/storage"
chmod 755 "$SCRIPT_DIR/logs" "$SCRIPT_DIR/storage"
echo "✓ Directories created"
echo ""

echo "========================================="
echo "  NEXUS PANEL READY!"
echo "========================================="
echo ""
echo "Nexus Panel has been initialized successfully!"
echo ""
echo "To access the panel:"
echo "1. Ensure your web server (Apache/Nginx) is configured to serve this directory"
echo "2. Visit your domain/IP in a web browser"
echo "3. Login with default credentials:"
echo "   - Username: admin"
echo "   - Password: admin123"
echo ""
echo "For Ubuntu 24.04 with Nginx, run: sudo bash install.sh"
echo ""