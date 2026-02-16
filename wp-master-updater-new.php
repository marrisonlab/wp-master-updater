<?php
/**
 * Plugin Name: WP Master Updater
 * Description: Master controller for managing WordPress updates across multiple sites
 * Version: 2.0.0
 * Author: Marrison Lab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('MARRISON_MASTER_VERSION', '2.0.0');
define('MARRISON_MASTER_DIR', plugin_dir_path(__FILE__));

// Include core files
require_once MARRISON_MASTER_DIR . 'includes/core-new.php';
require_once MARRISON_MASTER_DIR . 'includes/api-new.php';

// Initialize the plugin
function marrison_master_init() {
    // Core is auto-initialized via API class
}
add_action('plugins_loaded', 'marrison_master_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create default options
    if (!get_option('marrison_master_config')) {
        update_option('marrison_master_config', [
            'plugins_repo' => '',
            'themes_repo' => '',
            'enable_private_plugins' => false,
            'enable_private_themes' => false
        ]);
    }
    
    if (!get_option('marrison_master_clients')) {
        update_option('marrison_master_clients', []);
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
    wp_clear_scheduled_hook('marrison_master_sync_cron');
});
