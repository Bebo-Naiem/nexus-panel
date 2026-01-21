# Nexus Panel - Advanced Game Server Management System

A complete game server management system built with PHP, similar to Pterodactyl, designed for Ubuntu 24.04 LTS. Features advanced server provisioning, real-time monitoring, and comprehensive user management.

## Features

- **User Management**: Register, login, profile management, and role-based access
- **Server Control**: Start, stop, restart Docker containers with granular permissions
- **Real-time Logs**: View container logs in terminal-style interface
- **Advanced Provisioning**: Create, configure, and deploy new containers with resource limits
- **Resource Management**: CPU, memory, and disk allocation controls
- **Monitoring**: Real-time server statistics and performance metrics
- **Admin Panel**: Comprehensive user and server management interface
- **Environment Variables**: Configure containers with custom environment variables
- **Port Management**: Map container ports to host ports
- **Activity Logging**: Track user actions and server events
- **Modern UI**: Dark-themed interface with Tailwind CSS
- **SQLite Database**: Zero-config file-based database
- **Docker Integration**: Full container lifecycle management

## Tech Stack

- **Web Server**: Nginx
- **Backend**: PHP 8.3 with PHP-FPM
- **Database**: SQLite
- **Container Engine**: Docker
- **Frontend**: Tailwind CSS + Vanilla JavaScript
- **OS**: Ubuntu 24.04 LTS

## Installation

### Prerequisites

- Ubuntu 24.04 LTS
- Root or sudo access
- Internet connection

### Automatic Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/yourusername/nexus-panel.git
   cd nexus-panel
   ```

2. Run the installation script:
   ```bash
   sudo bash install.sh
   ```

3. Access the panel:
   - Visit `http://YOUR_SERVER_IP` in your browser
   - Login with default credentials:
     - Username: `admin`
     - Password: `admin123`

## Default Credentials

- **Username**: `admin`
- **Password**: `admin123`

**Important**: Change the default password immediately after first login!

## Architecture

### Database Schema

The system uses SQLite with multiple tables for comprehensive management:

#### `users`
- `id`: Primary key
- `username`: Unique username
- `email`: Unique email
- `password_hash`: BCrypt hashed password
- `role`: 'admin' or 'user'
- `created_at`: Timestamp

#### `servers`
- `id`: Primary key
- `name`: Server name
- `container_id`: Docker container ID
- `user_id`: Foreign key to users table
- `status`: Current status ('running', 'stopped', etc.)
- `description`: Server description
- `memory_limit`: Memory allocation limit
- `cpu_limit`: CPU percentage limit
- `disk_limit`: Disk space limit
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

#### `server_allocations`
- `id`: Primary key
- `server_id`: Foreign key to servers table
- `ip_address`: IP address for allocation
- `port`: Port number
- `assigned_at`: Assignment timestamp

#### `server_variables`
- `id`: Primary key
- `server_id`: Foreign key to servers table
- `variable_key`: Environment variable name
- `variable_value`: Environment variable value
- `created_at`: Creation timestamp

#### `activity_logs`
- `id`: Primary key
- `user_id`: Foreign key to users table
- `server_id`: Foreign key to servers table
- `action`: Action performed
- `description`: Description of the action
- `ip_address`: User IP address
- `user_agent`: User agent string
- `created_at`: Timestamp

### API Endpoints

The system handles various actions via POST requests to `api.php`:

- `login` - Authenticate user
- `register` - Create new user
- `update_profile` - Change password
- `get_my_servers` - Get user's assigned servers
- `list_users` - List all users (admin only)
- `delete_user` - Delete user (admin only)
- `list_all_containers` - List Docker containers (admin only)
- `assign_server` - Assign container to user (admin only)
- `start/stop/restart` - Control server state
- `get_logs` - Get container logs
- `create_server` - Create new server/container (admin only)
- `delete_server` - Delete server/container (admin only)
- `get_images` - Get available Docker images (admin only)
- `pull_image` - Pull new Docker image (admin only)
- `get_server_stats` - Get real-time server statistics

## Security Features

- Session-based authentication
- Password hashing with BCrypt
- Input validation and sanitization
- Role-based access control
- CSRF protection
- SQL injection prevention

## Usage

1. **As Admin**:
   - Create and manage user accounts
   - Assign Docker containers to users
   - Monitor system status
   - Create new server instances with resource limits
   - Pull and manage Docker images
   - View comprehensive activity logs
   - Access all server statistics

2. **As Regular User**:
   - Login to dashboard
   - View assigned servers
   - Start/stop/restart containers
   - View real-time logs and server statistics
   - Access server details and resource usage
   - Update profile

3. **Advanced Features**:
   - Create servers with custom resource allocation (CPU, memory, disk)
   - Configure environment variables for containers
   - Map host ports to container ports
   - Monitor real-time server performance
   - Track all user activities in the system

## Docker Integration

The system integrates with Docker to manage containers:

- Uses `docker ps` to list containers
- Controls containers with `docker start/stop/restart`
- Retrieves logs with `docker logs --tail 50`
- Requires `www-data` to have Docker permissions

## Troubleshooting

### Common Issues

1. **Permission Errors**:
   - Ensure `www-data` is in the `docker` group
   - Check file permissions on the installation directory

2. **Service Not Starting**:
   - Check logs in `/var/log/nginx/`
   - Verify PHP-FPM is running: `sudo systemctl status php8.3-fpm`

3. **Database Issues**:
   - Check permissions on `nexus.sqlite`
   - Verify SQLite extension is installed

### Logs

- Nginx: `/var/log/nginx/nexus_access.log` and `/var/log/nginx/nexus_error.log`
- PHP: `/var/log/php8.3-fpm.log`
- System: `/var/log/syslog`

## Development

The system is organized as follows:

```
├── config.php               # Application configuration
├── db.php                   # Database connection and migration
├── DockerManager.php        # Docker container management
├── ServerManager.php        # Server creation and management
├── api.php                  # Backend API controller
├── index.php                # Frontend router and layout
├── views/                   # Individual page views
│   ├── login.php
│   ├── dashboard.php
│   ├── servers.php
│   ├── server-details.php   # Server details with stats
│   ├── profile.php
│   ├── admin-users.php
│   ├── admin-servers.php
│   └── create-server.php    # Server creation form
├── install.sh               # Ubuntu installation script
├── startup.sh               # Initialization script
├── startup.bat              # Windows initialization script
├── test_db.php              # Database test script
├── README.md                # This file
└── LICENSE                  # License file
```

## License

MIT License - See LICENSE file for details.