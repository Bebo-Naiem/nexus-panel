@echo off
REM Nexus Panel Packaging Script for Windows
REM This script creates a deployment package for Linux servers

echo =========================================
echo   Nexus Panel Packaging Script
echo =========================================
echo.

echo Creating deployment package...
echo.

REM Create deployment directory
set PACKAGE_DIR=nexus-panel-deployment
if exist "%PACKAGE_DIR%" rmdir /s /q "%PACKAGE_DIR%"
mkdir "%PACKAGE_DIR%"

echo Copying files to package directory...
xcopy "*.php" "%PACKAGE_DIR%\" /Y
xcopy "*.sh" "%PACKAGE_DIR%\" /Y
xcopy "views\" "%PACKAGE_DIR%\views\" /E /I /Y
xcopy "docs\" "%PACKAGE_DIR%\docs\" /E /I /Y

echo.
echo Package created in: %PACKAGE_DIR%
echo.
echo To deploy to Linux:
echo 1. Transfer the %PACKAGE_DIR% folder to your Linux server
echo 2. Extract it to your desired location
echo 3. Run deploy_to_linux.sh as root
echo.
echo Deployment files included:
dir "%PACKAGE_DIR%"
echo.
echo Ready for Linux deployment!
pause