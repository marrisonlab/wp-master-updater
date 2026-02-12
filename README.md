# WP Master Updater

WP Master Updater is the main component of the remote WordPress management system that allows you to control and update multiple WordPress installations from a single centralized interface.

## Features

- **Multi-Site Management**: Control and manage multiple WordPress installations from a single dashboard
- **Centralized Updates**: Update plugins, themes, and translations on all connected clients
- **Backup System**: Complete backup management with remote restore capability
- **Status Monitoring**: View the status of all connected clients in real-time
- **Private Repositories**: Supports private plugin and theme repositories
- **Intuitive Interface**: User-friendly dashboard with LED status indicators

## Installation

1. Download the latest version from the [GitHub repository](https://github.com/marrisonlab/wp-master-updater)
2. Upload the plugin to the `/wp-content/plugins/` directory of your WordPress site
3. Activate the plugin via the WordPress admin panel
4. Configure settings in the "WP Master Updater" → "Settings" page

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Internet connection to communicate with clients

## Configuration

### Private Repositories

To configure private plugin and theme repositories:

1. Go to "WP Master Updater" → "Settings"
2. Enter the URL of your private plugin repository
3. Enter the URL of your private theme repository
4. Save changes

### Clients

To connect a client to the master:

1. Install the [WP Agent Updater](https://github.com/marrisonlab/wp-agent-updater) plugin on the client
2. Configure the client to communicate with the master URL
3. The client will automatically appear in the master dashboard

## Usage

### Main Dashboard

The main dashboard shows:
- List of all connected clients
- Update status (plugins, themes, translations)
- LED status indicators (green, yellow, red, black)
- Last synchronization
- Quick actions (Sync, Update, Restore, Delete)

### Client Details

Click on a client row to expand details:
- Installed plugins and their status
- Installed themes
- Available backups
- Translations

### Bulk Operations

Select multiple clients to perform group operations:
- Bulk sync
- Bulk update
- Backup operations

## Status Indicators

- **Green** ✅: All up to date
- **Yellow** ⚠️: Deactivated plugins present
- **Red** ❌: Updates available
- **Black** ⚫: Client unreachable

## Backup and Restore

### Create Backup
Backups are automatically created by the client before every update.

### Restore Backup
1. Expand client details
2. Select a backup from the list
3. Click "Restore"
4. Confirm the operation

## Security

- All communications between master and client are secure
- Authentication via WordPress nonces
- Access control based on WordPress roles

## Support

For support and additional documentation:
- [GitHub Repository](https://github.com/marrisonlab/wp-master-updater)
- [Issue Tracker](https://github.com/marrisonlab/wp-master-updater/issues)
