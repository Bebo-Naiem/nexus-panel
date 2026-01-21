@echo off
REM Nexus Panel Initialization Batch Script for Windows
REM This script helps verify the installation on Windows
REM Project Repository: https://github.com/Bebo-Naiem/nexus-panel

echo =========================================
echo   Nexus Panel Initialization Script (Windows)
echo =========================================
echo.

echo Checking prerequisites...
echo.

REM Check if PHP is available
echo Checking for PHP...
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP 8.3+ and add it to your system PATH
    pause
    exit /b 1
) else (
    echo ✓ PHP is available: %PHP%
    php --version
)

echo.

REM Check if Docker is available
echo Checking for Docker...
where docker >nul 2>nul
if %errorlevel% neq 0 (
    echo WARNING: Docker is not installed or not in PATH
    echo Please install Docker Desktop for Windows
) else (
    echo ✓ Docker is available
    docker --version
)

echo.

REM Initialize database
echo Initializing database...
php test_db.php
if %errorlevel% equ 0 (
    echo ✓ Database initialized successfully
) else (
    echo ⚠ Error initializing database
)

echo.

echo =========================================
echo   NEXUS PANEL READY FOR DEVELOPMENT!
echo =========================================
echo.
echo On Windows, you can use WSL2 with Ubuntu 24.04 to run the full installation,
echo or use a local PHP development server for testing.
echo.
echo To start a local PHP server for testing:
echo   1. Open WSL2 Ubuntu terminal
echo   2. Navigate to this directory
echo   3. Run: php -S localhost:8000
echo.
echo Then visit http://localhost:8000 in your browser
echo.
pause