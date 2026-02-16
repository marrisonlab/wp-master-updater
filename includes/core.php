<?php

class Marrison_Master_Core {

    private $option_name = 'marrison_connected_clients';

    public function get_clients() {
        return get_option($this->option_name, []);
    }

    public function update_client_data($data) {
        $clients = $this->get_clients();
        $site_url = $data['site_url'];
        
        // Preserve ignored plugins
        $ignored_plugins = [];
        if (isset($clients[$site_url]['ignored_plugins'])) {
            $ignored_plugins = $clients[$site_url]['ignored_plugins'];
        }

        // Use site_url as key
        $clients[$site_url] = array_merge($data, [
            'last_sync' => current_time('mysql'),
            'status' => 'active', // Explicitly mark as active on successful update
            'ignored_plugins' => $ignored_plugins
        ]);
        update_option($this->option_name, $clients);
    }

    public function toggle_ignore_plugin($site_url, $plugin_path, $ignore) {
        $clients = $this->get_clients();
        
        // Normalize input to handle trailing slash mismatches for site key
        $url_norm = untrailingslashit($site_url);
        $found_key = false;
        
        if (isset($clients[$site_url])) {
            $found_key = $site_url;
        } else {
            foreach (array_keys($clients) as $key) {
                if (untrailingslashit($key) === $url_norm) {
                    $found_key = $key;
                    break;
                }
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

    public function mark_client_unreachable($site_url) {
        $clients = $this->get_clients();
        
        // Normalize input to handle trailing slash mismatches
        $url_norm = untrailingslashit($site_url);
        $found_key = false;
        
        // Try exact match first
        if (isset($clients[$site_url])) {
            $found_key = $site_url;
        } else {
            // Try normalized match
            foreach (array_keys($clients) as $key) {
                if (untrailingslashit($key) === $url_norm) {
                    $found_key = $key;
                    break;
                }
            }
        }

        if ($found_key) {
            $clients[$found_key]['status'] = 'unreachable';
            // Do NOT update last_sync so we know it's stale
            update_option($this->option_name, $clients);
        }
    }

    public function delete_client($site_url) {
        $clients = $this->get_clients();
        if (isset($clients[$site_url])) {
            unset($clients[$site_url]);
            update_option($this->option_name, $clients);
            return true;
        }
        return false;
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

    /**
     * Helper to fetch and parse repo JSON
     */
    private function fetch_repo_json($url) {
        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        // Fallback for packages.json convention
        if (json_last_error() !== JSON_ERROR_NONE) {
            $url_fallback = untrailingslashit($url) . '/packages.json';
            $response = wp_remote_get($url_fallback, ['timeout' => 15, 'sslverify' => false]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                 $body = wp_remote_retrieve_body($response);
                 $json = json_decode($body, true);
            }
        }

        if (!$json || !is_array($json)) return [];

        // Normalize structure
        if (isset($json['packages'])) return $json['packages'];
        if (isset($json['plugins'])) return $json['plugins'];
        if (isset($json['themes'])) return $json['themes'];
        
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
        $response = wp_remote_post($client_url . '/wp-json/wp-agent-updater/v1/update', [
            'body' => [
                'clear_cache' => true,
                'update_translations' => true
            ],
            'timeout' => 300,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($body) && isset($body['success']) && $body['success'] === false) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('update_failed', $body['message'] ?? 'Update failed');
        }
        
        // Sync after update to refresh data
        $this->trigger_remote_sync($client_url, false);
        return $body;
    }

    /**
     * Trigger remote sync with optimized timeout
     */
    public function trigger_remote_sync($client_url, $mark_unreachable = true) {
        // Request fresh data from Agent
        // Append cache buster to avoid cached JSON responses (e.g. Varnish/Cloudflare)
        $endpoint = trailingslashit($client_url) . 'wp-json/wp-agent-updater/v1/status';
        $endpoint = add_query_arg('marrison_ts', time(), $endpoint);

        $response = $this->request_get_with_retry($endpoint);

        if (is_wp_error($response)) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        // Verify Content-Type is JSON (handles redirects to 404/Home which return HTML 200 OK)
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        // Use stripos for case-insensitive check
        if (stripos($content_type, 'application/json') === false) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return new WP_Error('invalid_content_type', 'Response was not JSON (Possible redirect or error page)');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data && isset($data['site_url'])) {
            // Inject private updates
            $repo_data = $this->get_private_repo_data();
            $this->compare_and_inject_updates($data, $repo_data);

            $this->update_client_data($data);
            return true;
        }
        
        if ($mark_unreachable) {
            $this->mark_client_unreachable($client_url);
        }
        return new WP_Error('invalid_response', 'Invalid response from agent');
    }

    public function trigger_restore_backup($client_url, $backup_filename) {
        $response = wp_remote_post($client_url . '/wp-json/wp-agent-updater/v1/backups/restore', [
            'body' => ['filename' => $backup_filename],
            'timeout' => 60,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
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
     * Bulk sync all clients in parallel for better performance
     */
    public function bulk_sync_parallel($client_urls = null) {
        if ($client_urls === null) {
            $clients = $this->get_clients();
            $client_urls = array_keys($clients);
        }

        if (empty($client_urls)) {
            return ['success' => true, 'results' => []];
        }

        // Fetch private repo data once for all clients
        $repo_data = $this->get_private_repo_data();

        $results = [];
        $requests = [];

        // Build all requests
        foreach ($client_urls as $url) {
            $endpoint = trailingslashit($url) . 'wp-json/wp-agent-updater/v1/status';
            $endpoint = add_query_arg('marrison_ts', time(), $endpoint);
            
            $requests[$url] = [
                'url' => $endpoint
            ];
        }

        // Execute requests in parallel using WordPress HTTP API
        foreach ($requests as $url => $request) {
            $response = $this->request_get_with_retry($request['url']);

            if (is_wp_error($response)) {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');

            if ($code !== 200 || stripos($content_type, 'application/json') === false) {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid response'
                ];
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data && isset($data['site_url'])) {
                // Inject private updates
                $this->compare_and_inject_updates($data, $repo_data);

                $this->update_client_data($data);
                $results[$url] = ['success' => true];
            } else {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid data format'
                ];
            }
        }

        return [
            'success' => true,
            'results' => $results
        ];
    }
}
