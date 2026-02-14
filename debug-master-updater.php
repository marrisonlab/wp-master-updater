<?php
/**
 * Debug script for WP Master Updater
 * Test repository data and injection process
 */

// Include WordPress
require_once('../../../wp-config.php');

echo "=== WP Master Updater Debug ===\n\n";

// Load master core
require_once('wp-master-updater/includes/core.php');
$master = new Marrison_Master_Core();

echo "=== Repository Configuration ===\n";
$plugins_repo = get_option('marrison_private_plugins_repo');
$themes_repo = get_option('marrison_private_themes_repo');

echo "Plugins Repo: " . ($plugins_repo ?: 'NOT SET') . "\n";
echo "Themes Repo: " . ($themes_repo ?: 'NOT SET') . "\n";

echo "\n=== Fetching Repository Data ===\n";
$repo_data = $master->get_private_repo_data();

echo "Plugins in repo: " . count($repo_data['plugins']) . "\n";
if (!empty($repo_data['plugins'])) {
    foreach ($repo_data['plugins'] as $slug => $plugin) {
        $package = $plugin['package'] ?? $plugin['download_url'] ?? 'MISSING';
        echo "- $slug: " . ($plugin['version'] ?? 'NO VERSION') . " (Package: " . ($package !== 'MISSING' ? 'YES' : 'NO') . ")\n";
        if ($package !== 'MISSING') {
            echo "  Package URL: $package\n";
        }
    }
}

echo "Themes in repo: " . count($repo_data['themes']) . "\n";
if (!empty($repo_data['themes'])) {
    foreach ($repo_data['themes'] as $slug => $theme) {
        $package = $theme['package'] ?? $theme['download_url'] ?? 'MISSING';
        echo "- $slug: " . ($theme['version'] ?? 'NO VERSION') . " (Package: " . ($package !== 'MISSING' ? 'YES' : 'NO') . ")\n";
        if ($package !== 'MISSING') {
            echo "  Package URL: $package\n";
        }
    }
}

echo "\n=== Connected Clients ===\n";
$clients = $master->get_clients();
echo "Connected clients: " . count($clients) . "\n";

foreach ($clients as $url => $client_data) {
    echo "\nClient: $url\n";
    echo "- Status: " . ($client_data['status'] ?? 'UNKNOWN') . "\n";
    echo "- Last sync: " . ($client_data['last_sync'] ?? 'NEVER') . "\n";
    
    if (!empty($client_data['plugins_need_update'])) {
        echo "- Plugin updates needed: " . count($client_data['plugins_need_update']) . "\n";
        foreach ($client_data['plugins_need_update'] as $file => $update) {
            $has_package = !empty($update['package']);
            echo "  * $file: " . $update['new_version'] . " (Package: " . ($has_package ? 'YES' : 'NO') . ")\n";
            if ($has_package) {
                echo "    Package: " . $update['package'] . "\n";
            }
        }
    }
    
    if (!empty($client_data['themes_need_update'])) {
        echo "- Theme updates needed: " . count($client_data['themes_need_update']) . "\n";
        foreach ($client_data['themes_need_update'] as $slug => $update) {
            $has_package = !empty($update['package']);
            echo "  * $slug: " . $update['new_version'] . " (Package: " . ($has_package ? 'YES' : 'NO') . ")\n";
            if ($has_package) {
                echo "    Package: " . $update['package'] . "\n";
            }
        }
    }
}

echo "\n=== Master Log File ===\n";
$log_file = WP_CONTENT_DIR . '/wp-master-updater-debug.log';
if (file_exists($log_file)) {
    echo "Last 10 lines of master log:\n";
    $lines = file($log_file);
    $last_lines = array_slice($lines, -10);
    echo implode('', $last_lines);
} else {
    echo "No master log file found at: $log_file\n";
}

echo "\n=== Debug Complete ===\n";
