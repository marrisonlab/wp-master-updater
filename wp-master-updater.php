<?php
/**
 * Plugin Name: WP Master Updater
 * Plugin URI: https://github.com/marrisonlab/wp-master-updater
 * Description: Master controller for WP Master/Agent Updater System.
 * Version: 1.1.2
 * Author: Angelo Marra
 * Author URI: https://marrisonlab.com
 * License: GPL v2 or later
 * Text Domain: wp-master-updater
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_MASTER_UPDATER_PATH', plugin_dir_path(__FILE__));
define('WP_MASTER_UPDATER_URL', plugin_dir_url(__FILE__));

// Include files
require_once WP_MASTER_UPDATER_PATH . 'includes/core.php';
require_once WP_MASTER_UPDATER_PATH . 'includes/admin.php';
require_once WP_MASTER_UPDATER_PATH . 'includes/api.php';
require_once WP_MASTER_UPDATER_PATH . 'includes/github-updater.php';

// Initialize
function wp_master_updater_init() {
    $master = new Marrison_Master_Core();
    // $master->init(); // If needed
    
    // Initialize GitHub Updater
    new WP_Master_Updater_GitHub_Updater(
        __FILE__,
        'marrisonlab',
        'wp-master-updater'
    );
}
add_action('plugins_loaded', 'wp_master_updater_init');
