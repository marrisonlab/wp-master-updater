<?php

/**
 * WP Master Updater Core - Complete Rewrite
 * Simplified, robust, focused architecture
 */

class Marrison_Master_Core {
    
    private $clients_option = 'marrison_master_clients';
    private $config_option = 'marrison_master_config';
    private $log_file;
    
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/marrison-master-updater.log';
    }
    
    /**
     * Get all registered clients
     */
    public function get_clients() {
        return get_option($this->clients_option, []);
    }
    
    /**
     * Update client data with validation
     */
    public function update_client($data) {
        if (empty($data['site_url']) || !filter_var($data['site_url'], FILTER_VALIDATE_URL)) {
            $this->log('Invalid site URL in client data');
            return false;
        }
        
        $clients = $this->get_clients();
        $site_url = untrailingslashit($data['site_url']);
        
        // Preserve existing ignored items
        $existing = $clients[$site_url] ?? [];
        $ignored_plugins = $existing['ignored_plugins'] ?? [];
        $ignored_themes = $existing['ignored_themes'] ?? [];
        
        $clients[$site_url] = array_merge($data, [
            'last_sync' => current_time('mysql'),
            'status' => 'active',
            'ignored_plugins' => $ignored_plugins,
            'ignored_themes' => $ignored_themes
        ]);
        
        update_option($this->clients_option, $clients);
        $this->log("Updated client: $site_url");
        return true;
    }
    
    public function mark_client_pending_sync($site_url) {
        $clients = $this->get_clients();
        $url_norm = untrailingslashit($site_url);
        
        $found_key = false;
        foreach (array_keys($clients) as $key) {
            if (untrailingslashit($key) === $url_norm) {
                $found_key = $key;
                break;
            }
        }
        
        if ($found_key === false) {
            return;
        }
        
        $clients[$found_key]['status'] = 'pending';
        update_option($this->clients_option, $clients);
    }
    
    /**
     * Get master configuration
     */
    public function get_config() {
        return get_option($this->config_option, [
            'plugins_repo' => '',
            'themes_repo' => '',
            'enable_private_plugins' => false,
            'enable_private_themes' => false
        ]);
    }
    
    /**
     * Update master configuration
     */
    public function update_config($config) {
        $current = $this->get_config();
        $updated = array_merge($current, $config);
        update_option($this->config_option, $updated);
        $this->log('Configuration updated');
        return true;
    }
    
    /**
     * Fetch and parse repository data - Robust parsing
     */
    public function fetch_repository($url) {
        if (empty($url)) {
            return [];
        }
        
        $this->log("Fetching repository: $url");
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'headers' => ['User-Agent' => 'Marrison-Master-Updater/1.0']
        ]);
        
        if (is_wp_error($response)) {
            $this->log('Repository fetch error: ' . $response->get_error_message());
            return [];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log("Repository HTTP error: $code");
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Repository JSON error: ' . json_last_error_msg());
            return [];
        }
        
        // Convert numeric array to associative using slug as key
        if (is_array($data) && isset($data[0]) && isset($data[0]['slug'])) {
            $this->log('Converting numeric array to associative');
            $associative = [];
            foreach ($data as $item) {
                if (!empty($item['slug'])) {
                    $slug = $item['slug'];
                    $associative[$slug] = $item;
                }
            }
            $data = $associative;
            $this->log('Converted ' . count($associative) . ' items');
        }
        
        $this->log('Repository fetched successfully: ' . count($data) . ' items');
        return $data;
    }
    
    /**
     * Inject private updates into client data
     */
    public function inject_updates(&$client_data) {
        $config = $this->get_config();
        
        // Plugins
        if ($config['enable_private_plugins'] && !empty($config['plugins_repo'])) {
            $repo_plugins = $this->fetch_repository($config['plugins_repo']);
            $client_data = $this->inject_plugin_updates($client_data, $repo_plugins);
        }
        
        // Themes
        if ($config['enable_private_themes'] && !empty($config['themes_repo'])) {
            $repo_themes = $this->fetch_repository($config['themes_repo']);
            $client_data = $this->inject_theme_updates($client_data, $repo_themes);
        }
        
        return $client_data;
    }
    
    /**
     * Inject plugin updates
     */
    private function inject_plugin_updates($client_data, $repo_plugins) {
        if (empty($client_data['plugins']) || empty($repo_plugins)) {
            return $client_data;
        }
        
        $client_data['plugins_need_update'] = $client_data['plugins_need_update'] ?? [];
        
        foreach ($client_data['plugins'] as $file => $plugin) {
            $slug = $this->extract_plugin_slug($file);
            
            if (isset($repo_plugins[$slug])) {
                $repo_item = $repo_plugins[$slug];
                $current_version = $plugin['version'] ?? '0.0.0';
                $new_version = $repo_item['version'] ?? '0.0.0';
                
                if (version_compare($current_version, $new_version, '<')) {
                    $package_url = $repo_item['download_url'] ?? $repo_item['package'] ?? '';
                    
                    $client_data['plugins_need_update'][$file] = [
                        'slug' => $slug,
                        'name' => $plugin['name'] ?? $slug,
                        'current_version' => $current_version,
                        'new_version' => $new_version,
                        'package' => $package_url,
                        'type' => 'private'
                    ];
                    
                    $this->log("Injected plugin update: $slug $current_version -> $new_version");
                }
            }
        }
        
        return $client_data;
    }
    
    /**
     * Inject theme updates
     */
    private function inject_theme_updates($client_data, $repo_themes) {
        if (empty($client_data['themes']) || empty($repo_themes)) {
            return $client_data;
        }
        
        $client_data['themes_need_update'] = $client_data['themes_need_update'] ?? [];
        
        foreach ($client_data['themes'] as $slug => $theme) {
            if (isset($repo_themes[$slug])) {
                $repo_item = $repo_themes[$slug];
                $current_version = $theme['version'] ?? '0.0.0';
                $new_version = $repo_item['version'] ?? '0.0.0';
                
                if (version_compare($current_version, $new_version, '<')) {
                    $package_url = $repo_item['download_url'] ?? $repo_item['package'] ?? '';
                    
                    $client_data['themes_need_update'][$slug] = [
                        'slug' => $slug,
                        'name' => $theme['name'] ?? $slug,
                        'current_version' => $current_version,
                        'new_version' => $new_version,
                        'package' => $package_url,
                        'type' => 'private'
                    ];
                    
                    $this->log("Injected theme update: $slug $current_version -> $new_version");
                }
            }
        }
        
        return $client_data;
    }
    
    /**
     * Extract plugin slug from file path
     */
    private function extract_plugin_slug($file) {
        $slug = dirname($file);
        return ($slug === '.') ? basename($file, '.php') : $slug;
    }
    
    /**
     * Trigger remote update on client
     */
    public function trigger_client_update($site_url, $options = []) {
        $endpoint = untrailingslashit($site_url) . '/wp-json/marrison-agent/v1/update';
        
        $headers = ['User-Agent' => 'Marrison-Master-Updater/1.0'];
        $token = get_option('marrison_master_api_token');
        $ts = time();
        if (!empty($token)) {
            $payload = wp_json_encode(array_merge([
                'clear_cache' => true,
                'update_plugins' => true,
                'update_themes' => true,
                'update_translations' => true
            ], $options));
            $headers['X-Marrison-Token'] = $token;
            $headers['X-Marrison-Timestamp'] = (string)$ts;
            $headers['X-Marrison-Signature'] = hash_hmac('sha256', $payload . '|' . $ts, $token);
        }
        $response = wp_remote_post($endpoint, [
            'body' => array_merge([
                'clear_cache' => true,
                'update_plugins' => true,
                'update_themes' => true,
                'update_translations' => true
            ], $options),
            'timeout' => 300,
            'sslverify' => true,
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            $this->log('Update trigger error: ' . $response->get_error_message());
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log("Update trigger HTTP error: $code");
            return ['success' => false, 'message' => "HTTP $code"];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log('Update triggered successfully');
        return $body;
    }
    
    /**
     * Sync with client
     */
    public function sync_client($site_url) {
        $endpoint = untrailingslashit($site_url) . '/wp-json/marrison-agent/v1/status';
        
        $headers = ['User-Agent' => 'Marrison-Master-Updater/1.0'];
        $token = get_option('marrison_master_api_token');
        $ts = time();
        if (!empty($token)) {
            $headers['X-Marrison-Token'] = $token;
            $headers['X-Marrison-Timestamp'] = (string)$ts;
            $headers['X-Marrison-Signature'] = hash_hmac('sha256', $site_url . '|' . $ts, $token);
        }
        $response = wp_remote_get($endpoint, [
            'timeout' => 30,
            'sslverify' => true,
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            $this->log('Sync error: ' . $response->get_error_message());
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log("Sync HTTP error: $code");
            return ['success' => false, 'message' => "HTTP $code"];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['site_url'])) {
            $this->log('Invalid sync response');
            return ['success' => false, 'message' => 'Invalid response'];
        }
        
        // Inject private updates
        $data = $this->inject_updates($data);
        
        // Update client data
        $this->update_client($data);
        
        $this->log("Sync completed: $site_url");
        return ['success' => true, 'data' => $data];
    }
    
    /**
     * Toggle ignore status for plugin/theme
     */
    public function toggle_ignore($site_url, $type, $slug, $ignore) {
        $clients = $this->get_clients();
        $site_url = untrailingslashit($site_url);
        
        if (!isset($clients[$site_url])) {
            return false;
        }
        
        $key = $type === 'plugin' ? 'ignored_plugins' : 'ignored_themes';
        $ignored = $clients[$site_url][$key] ?? [];
        
        if ($ignore) {
            if (!in_array($slug, $ignored)) {
                $ignored[] = $slug;
            }
        } else {
            $ignored = array_diff($ignored, [$slug]);
        }
        
        $clients[$site_url][$key] = array_values($ignored);
        update_option($this->clients_option, $clients);
        
        $this->log("Toggled ignore $type $slug on $site_url: " . ($ignore ? 'IGNORE' : 'UNIGNORE'));
        return true;
    }
    
    /**
     * Simple logging
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $this->log_file);
    }
}
