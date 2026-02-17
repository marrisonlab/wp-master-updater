<?php

class Marrison_Master_Core {

    private $option_name = 'marrison_connected_clients';

    public function get_clients() {
        return get_option($this->option_name, []);
    }

    private function normalize_site_url($url) {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        $url = untrailingslashit($url);
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $host = strtolower($parts['host']);
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $normalized = $scheme . '://' . $host;
        if ($path !== '') {
            $normalized .= $path;
        }
        return $normalized;
    }

    private function get_site_identity($url) {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        $url = untrailingslashit($url);
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $host = strtolower($parts['host']);
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        return $host . $path;
    }

    public function update_client_data($data) {
        $clients = $this->get_clients();
        $raw_url = isset($data['site_url']) ? $data['site_url'] : '';
        $normalized_url = $this->normalize_site_url($raw_url);
        $identity = $this->get_site_identity($raw_url);

        $ignored_plugins = [];
        $ignored_themes = [];

        foreach ($clients as $key => $client) {
            if ($this->get_site_identity($key) === $identity) {
                if (isset($client['ignored_plugins']) && is_array($client['ignored_plugins'])) {
                    $ignored_plugins = array_values(array_unique(array_merge($ignored_plugins, $client['ignored_plugins'])));
                }
                if (isset($client['ignored_themes']) && is_array($client['ignored_themes'])) {
                    $ignored_themes = array_values(array_unique(array_merge($ignored_themes, $client['ignored_themes'])));
                }
                unset($clients[$key]);
            }
        }

        $client = array_merge($data, [
            'site_url' => $normalized_url,
            'last_sync' => current_time('mysql'),
            'status' => 'active',
            'ignored_plugins' => $ignored_plugins,
            'ignored_themes' => $ignored_themes
        ]);

        $repo_data = $this->get_private_repo_data();
        $client['plugins_upstream_newer'] = $this->detect_plugins_upstream_newer($client, $repo_data);

        $clients[$normalized_url] = $client;
        update_option($this->option_name, $clients);
    }

    private function detect_plugins_upstream_newer($client_data, $repo_data) {
        $result = [];
        if (empty($repo_data['plugins']) || empty($client_data['plugins_need_update'])) {
            return $result;
        }

        $updates_by_path = [];
        foreach ($client_data['plugins_need_update'] as $item) {
            if (!isset($item['path'])) {
                continue;
            }
            $updates_by_path[$item['path']] = $item;
        }

        if (empty($updates_by_path)) {
            return $result;
        }

        $installed = [];
        foreach ($client_data['plugins_active'] as $p) {
            if (isset($p['path'])) {
                $installed[$p['path']] = $p;
            }
        }
        foreach ($client_data['plugins_inactive'] as $p) {
            if (isset($p['path']) && !isset($installed[$p['path']])) {
                $installed[$p['path']] = $p;
            }
        }

        if (empty($installed)) {
            return $result;
        }

        $repo_plugins = $repo_data['plugins'];

        foreach ($installed as $path => $plugin) {
            if (!isset($updates_by_path[$path])) {
                continue;
            }

            $update = $updates_by_path[$path];
            if (empty($update['new_version'])) {
                continue;
            }
            $latest_version = $update['new_version'];

            $slug = dirname($path);
            if ($slug === '.') {
                $slug = basename($path, '.php');
            }

            if (!isset($repo_plugins[$slug])) {
                continue;
            }

            $repo_item = $repo_plugins[$slug];
            $private_version = $repo_item['version'] ?? ($repo_item['new_version'] ?? null);
            if (!$private_version) {
                continue;
            }

            if (version_compare($private_version, $latest_version, '>=')) {
                continue;
            }

            $result[] = [
                'path' => $path,
                'name' => $plugin['name'] ?? '',
                'installed_version' => $plugin['version'] ?? '',
                'private_version' => $private_version,
                'latest_version' => $latest_version
            ];
        }

        return $result;
    }

    public function toggle_ignore_plugin($site_url, $plugin_path, $ignore) {
        $clients = $this->get_clients();
        $identity = $this->get_site_identity($site_url);
        $found_key = false;
        foreach (array_keys($clients) as $key) {
            if ($this->get_site_identity($key) === $identity) {
                $found_key = $key;
                break;
            }
        }

        if (!$found_key) {
            return false;
        }
        
        $ignored = $clients[$found_key]['ignored_plugins'] ?? [];
        
        if ($ignore === 'true' || $ignore === true) {
            if (!in_array($plugin_path, $ignored)) {
                $ignored[] = $plugin_path;
            }
        } else {
            $ignored = array_diff($ignored, [$plugin_path]);
        }
        
        $clients[$found_key]['ignored_plugins'] = array_values($ignored);
        update_option($this->option_name, $clients);
        return true;
    }

    public function touch_client_last_sync($site_url) {
        $clients = $this->get_clients();
        $identity = $this->get_site_identity($site_url);
        $found_key = false;
        foreach (array_keys($clients) as $key) {
            if ($this->get_site_identity($key) === $identity) {
                $found_key = $key;
                break;
            }
        }

        if (!$found_key) {
            return;
        }

        $clients[$found_key]['last_sync'] = current_time('mysql');
        update_option($this->option_name, $clients);
    }

    public function mark_client_pending_sync($site_url) {
        $clients = $this->get_clients();
        $identity = $this->get_site_identity($site_url);
        $found_key = false;
        foreach (array_keys($clients) as $key) {
            if ($this->get_site_identity($key) === $identity) {
                $found_key = $key;
                break;
            }
        }

        if (!$found_key) {
            return;
        }

        $clients[$found_key]['status'] = 'pending';
        update_option($this->option_name, $clients);
    }

    public function mark_client_unreachable($site_url) {
        $clients = $this->get_clients();
        $identity = $this->get_site_identity($site_url);
        $found_key = false;
        foreach (array_keys($clients) as $key) {
            if ($this->get_site_identity($key) === $identity) {
                $found_key = $key;
                break;
            }
        }

        if ($found_key !== false) {
            $clients[$found_key]['status'] = 'unreachable';
            // Do NOT update last_sync so we know it's stale
            update_option($this->option_name, $clients);
        }
    }

    public function delete_client($site_url) {
        $clients = $this->get_clients();
        $identity = $this->get_site_identity($site_url);
        $deleted = false;
        foreach ($clients as $key => $client) {
            if ($this->get_site_identity($key) === $identity) {
                unset($clients[$key]);
                $deleted = true;
            }
        }
        if ($deleted) {
            update_option($this->option_name, $clients);
        }
        return $deleted;
    }

    public function request_agent_push($site_url) {
        $requests = get_option('marrison_push_requests', []);
        $requests[untrailingslashit($site_url)] = true;
        update_option('marrison_push_requests', $requests);
        return true;
    }

    public function consume_agent_push_request($site_url) {
        $key = untrailingslashit($site_url);
        $requests = get_option('marrison_push_requests', []);
        $requested = !empty($requests[$key]);
        if ($requested) {
            unset($requests[$key]);
            update_option('marrison_push_requests', $requests);
        }
        return $requested;
    }

    public function request_agent_update($site_url, $options = []) {
        $requests = get_option('marrison_update_requests', []);
        $requests[untrailingslashit($site_url)] = [
            'clear_cache' => isset($options['clear_cache']) ? (bool)$options['clear_cache'] : true,
            'update_translations' => isset($options['update_translations']) ? (bool)$options['update_translations'] : true
        ];
        update_option('marrison_update_requests', $requests);
        $this->trigger_remote_cron($site_url);
        return true;
    }

    public function consume_agent_update_request($site_url) {
        $key = untrailingslashit($site_url);
        $requests = get_option('marrison_update_requests', []);
        if (!empty($requests[$key])) {
            $opts = $requests[$key];
            unset($requests[$key]);
            update_option('marrison_update_requests', $requests);
            return $opts;
        }
        return null;
    }

    public function request_agent_restore($site_url, $filename) {
        $requests = get_option('marrison_restore_requests', []);
        $requests[untrailingslashit($site_url)] = [
            'filename' => $filename
        ];
        update_option('marrison_restore_requests', $requests);
        $this->trigger_remote_cron($site_url);
        return true;
    }

    public function consume_agent_restore_request($site_url) {
        $key = untrailingslashit($site_url);
        $requests = get_option('marrison_restore_requests', []);
        if (!empty($requests[$key])) {
            $data = $requests[$key];
            unset($requests[$key]);
            update_option('marrison_restore_requests', $requests);
            return $data;
        }
        return null;
    }

    public function trigger_remote_cron($site_url) {
        $url = untrailingslashit($site_url) . '/wp-cron.php?doing_wp_cron=1';
        $headers = [];
        $token = get_option('marrison_master_api_token');
        if (!empty($token)) {
            $headers['x-marrison-token'] = $token;
        }
        wp_remote_get($url, [
            'timeout' => 3,
            'sslverify' => false,
            'headers' => $headers
        ]);
    }

    public function trigger_agent_update_now($site_url, $options = []) {
        $headers = [];
        $token = get_option('marrison_master_api_token');
        if (!empty($token)) {
            $headers['x-marrison-token'] = $token;
            $ts = time();
            $payload = json_encode([
                'clear_cache' => isset($options['clear_cache']) ? (bool)$options['clear_cache'] : true,
                'update_translations' => isset($options['update_translations']) ? (bool)$options['update_translations'] : true
            ]);
            $headers['x-marrison-timestamp'] = (string)$ts;
            $headers['x-marrison-signature'] = hash_hmac('sha256', $payload . '|' . $ts, $token);
            $headers['content-type'] = 'application/json';
        }
        $resp = wp_remote_post(untrailingslashit($site_url) . '/wp-json/wp-agent-updater/v1/update', [
            'body' => isset($headers['content-type']) ? $payload : [
                'clear_cache' => isset($options['clear_cache']) ? (bool)$options['clear_cache'] : true,
                'update_translations' => isset($options['update_translations']) ? (bool)$options['update_translations'] : true
            ],
            'timeout' => 60,
            'sslverify' => true,
            'headers' => $headers
        ]);
        return $resp;
    }

    public function notify_agent_poll_now($site_url) {
        $headers = [];
        $token = get_option('marrison_master_api_token');
        if (!empty($token)) {
            $headers['x-marrison-token'] = $token;
        }
        return wp_remote_post(untrailingslashit($site_url) . '/wp-json/wp-agent-updater/v1/poll-now', [
            'timeout' => 15,
            'sslverify' => true,
            'headers' => $headers
        ]);
    }

    public function trigger_agent_restore_now($site_url, $filename) {
        $headers = [];
        $token = get_option('marrison_master_api_token');
        if (!empty($token)) {
            $headers['x-marrison-token'] = $token;
            $ts = time();
            $payload = json_encode(['filename' => $filename]);
            $headers['x-marrison-timestamp'] = (string)$ts;
            $headers['x-marrison-signature'] = hash_hmac('sha256', $payload . '|' . $ts, $token);
            $headers['content-type'] = 'application/json';
        }
        $resp = wp_remote_post(untrailingslashit($site_url) . '/wp-json/wp-agent-updater/v1/backups/restore', [
            'body' => isset($headers['content-type']) ? $payload : ['filename' => $filename],
            'timeout' => 60,
            'sslverify' => true,
            'headers' => $headers
        ]);
        return $resp;
    }
    /**
     * Fetch private repository data (Plugins & Themes)
     */
    public function get_private_repo_data() {
        $data = ['plugins' => [], 'themes' => []];

        // Plugins
        if (get_option('marrison_enable_private_plugins')) {
            $url = get_option('marrison_private_plugins_repo');
            if ($url) {
                $data['plugins'] = $this->fetch_repo_json($url);
            }
        }

        // Themes
        if (get_option('marrison_enable_private_themes')) {
            $url = get_option('marrison_private_themes_repo');
            if ($url) {
                $data['themes'] = $this->fetch_repo_json($url);
            }
        }

        return $data;
    }

    private function fetch_repo_json($url) {
        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $url_fallback = untrailingslashit($url) . '/packages.json';
            $response = wp_remote_get($url_fallback, ['timeout' => 15, 'sslverify' => false]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                 $body = wp_remote_retrieve_body($response);
                 $json = json_decode($body, true);
            }
        }

        if (!$json || !is_array($json)) {
            return [];
        }

        if (isset($json['packages'])) {
            $json = $json['packages'];
        } elseif (isset($json['plugins'])) {
            $json = $json['plugins'];
        } elseif (isset($json['themes'])) {
            $json = $json['themes'];
        }

        if (is_array($json) && isset($json[0]) && is_array($json[0]) && isset($json[0]['slug'])) {
            $assoc = [];
            foreach ($json as $item) {
                if (!empty($item['slug'])) {
                    $assoc[$item['slug']] = $item;
                }
            }
            $json = $assoc;
        }

        return $json;
    }
    
    private function request_get_with_retry($url) {
        $timeouts = [20, 30];
        foreach ($timeouts as $t) {
            $response = wp_remote_get($url, [
                'timeout' => $t,
                'sslverify' => false
            ]);
            if (!is_wp_error($response)) {
                return $response;
            }
            $code = $response->get_error_code();
            $msg = $response->get_error_message();
            if ($code !== 'http_request_failed' && stripos($msg, 'cURL error 28') === false) {
                return $response;
            }
        }
        return $response;
    }

    /**
     * Compare client installed items with private repo and inject updates
     */
    public function compare_and_inject_updates(&$client_data, $repo_data) {
        // Plugins
        if (!empty($repo_data['plugins']) && !empty($client_data['plugins'])) {
            foreach ($client_data['plugins'] as $file => $plugin) {
                $slug = dirname($file); // e.g., 'my-plugin' from 'my-plugin/my-plugin.php'
                if ($slug === '.') $slug = basename($file, '.php'); // Single file plugins

                // Check for match in repo
                $repo_item = $repo_data['plugins'][$slug] ?? null;
                
                // Fallback: Check if key in repo matches the full filename? Rare but possible.
                if (!$repo_item && isset($repo_data['plugins'][$file])) {
                    $repo_item = $repo_data['plugins'][$file];
                }

                if ($repo_item) {
                    $new_version = $repo_item['version'] ?? $repo_item['new_version'] ?? null;
                    $current_version = $plugin['Version'];

                    if ($new_version && version_compare($current_version, $new_version, '<')) {
                        // Create update entry
                        if (!isset($client_data['plugins_need_update'])) {
                            $client_data['plugins_need_update'] = [];
                        }
                        
                        // Only add if not already present (prefer WP repo? or override? usually private overrides)
                        // Let's overwrite or add.
                        $client_data['plugins_need_update'][$file] = [
                            'slug' => $slug,
                            'new_version' => $new_version,
                            'package' => $repo_item['package'] ?? $repo_item['download_url'] ?? '',
                            'url' => $repo_item['url'] ?? '',
                            'Name' => $plugin['Name']
                        ];
                    }
                }
            }
        }

        // Themes
        if (!empty($repo_data['themes']) && !empty($client_data['themes'])) {
            foreach ($client_data['themes'] as $slug => $theme) {
                // Themes are usually keyed by folder name (slug) in WP
                $repo_item = $repo_data['themes'][$slug] ?? null;

                if ($repo_item) {
                    $new_version = $repo_item['version'] ?? $repo_item['new_version'] ?? null;
                    $current_version = $theme['Version'];

                    if ($new_version && version_compare($current_version, $new_version, '<')) {
                        if (!isset($client_data['themes_need_update'])) {
                            $client_data['themes_need_update'] = [];
                        }

                        $client_data['themes_need_update'][$slug] = [
                            'slug' => $slug,
                            'new_version' => $new_version,
                            'package' => $repo_item['package'] ?? $repo_item['download_url'] ?? '',
                            'url' => $repo_item['url'] ?? '',
                            'Name' => $theme['Name']
                        ];
                    }
                }
            }
        }
    }

    /**
     * Trigger remote update with cache clearing and translation support
     */
    public function trigger_remote_update($client_url) {
        return new WP_Error('update_disabled', 'Update pull disabilitato: l’Agent esegue su richiesta push');
    }

    /**
     * Trigger remote sync disabled (push-only architecture)
     */
    public function trigger_remote_sync($client_url, $mark_unreachable = true) {
        return new WP_Error('sync_disabled', 'Sync pull disabilitato: l’Agent aggiorna il Master via push');
    }

    public function trigger_restore_backup($client_url, $backup_filename) {
        $headers = [];
        $token = get_option('marrison_master_api_token');
        if (!empty($token)) {
            $headers['x-marrison-token'] = $token;
        }

        $response = wp_remote_post(untrailingslashit($client_url) . '/wp-json/wp-agent-updater/v1/backups/restore', [
            'body' => ['filename' => $backup_filename],
            'timeout' => 600,
            'sslverify' => false,
            'headers' => $headers
        ]);

        if (is_wp_error($response)) {
            $code = $response->get_error_code();
            $msg = $response->get_error_message();
            if ($code === 'http_request_failed' && stripos($msg, 'cURL error 28') !== false) {
                return true;
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['success']) && $body['success']) {
            $this->trigger_remote_sync($client_url, false);
            return true;
        }

        return new WP_Error('restore_failed', $body['message'] ?? 'Restore failed, unknown error');
    }

    /**
     * Bulk sync disabled (push-only architecture)
     */
    public function bulk_sync_parallel($client_urls = null) {
        return new WP_Error('sync_disabled', 'Bulk sync disabilitato: push-only');
    }
}
