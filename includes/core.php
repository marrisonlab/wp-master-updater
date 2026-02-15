<?php

class Marrison_Master_Core {

    private $option_name = 'marrison_connected_clients';

    private function log_master($message) {
        $log_file = WP_CONTENT_DIR . '/wp-master-updater-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $log_file);
    }

    /**
     * Normalize URL to prevent duplicates
     * Removes trailing slash, converts to lowercase, ensures consistent protocol
     */
    private function normalize_url($url) {
        $url = trim($url);
        $url = untrailingslashit($url);
        // Parse URL to ensure consistency
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return $url; // Return as-is if invalid
        }
        
        // Rebuild URL with consistent format
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'http';
        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        
        $normalized = $scheme . '://' . $host . $port . $path;
        
        if ($normalized !== $url) {
            $this->log_master("URL normalized: '$url' -> '$normalized'");
        }
        
        return $normalized;
    }

    /**
     * Consolidate duplicate client entries (same site with different URL formats)
     */
    private function consolidate_duplicate_clients() {
        // Get clients directly to avoid recursion
        $clients = get_option($this->option_name, []);
        $normalized_map = [];
        $duplicates_found = false;
        
        foreach ($clients as $url => $data) {
            $normalized = $this->normalize_url($url);
            
            if (isset($normalized_map[$normalized])) {
                // Duplicate found - merge data, keeping most recent
                $duplicates_found = true;
                $existing = $normalized_map[$normalized];
                $existing_time = strtotime($clients[$existing]['last_sync'] ?? '2000-01-01');
                $current_time = strtotime($data['last_sync'] ?? '2000-01-01');
                
                if ($current_time > $existing_time) {
                    // Current entry is newer, replace
                    $this->log_master("Duplicate found: keeping '$url' over '$existing' (newer data)");
                    unset($clients[$existing]);
                    $normalized_map[$normalized] = $url;
                } else {
                    // Existing entry is newer, remove current
                    $this->log_master("Duplicate found: keeping '$existing' over '$url' (newer data)");
                    unset($clients[$url]);
                }
            } else {
                $normalized_map[$normalized] = $url;
            }
        }
        
        if ($duplicates_found) {
            update_option($this->option_name, $clients);
            $this->log_master("Consolidated duplicate clients");
        }
        
        return $duplicates_found;
    }

    public function get_clients() {
        static $consolidation_done = false;
        
        // Run consolidation once per request
        if (!$consolidation_done) {
            $this->consolidate_duplicate_clients();
            $consolidation_done = true;
        }
        
        return get_option($this->option_name, []);
    }

    public function update_client_data($data) {
        $clients = $this->get_clients();
        $site_url = $this->normalize_url($data['site_url']);
        
        // Preserve ignored plugins from any existing entry (normalized or not)
        $ignored_plugins = [];
        if (isset($clients[$site_url]['ignored_plugins'])) {
            $ignored_plugins = $clients[$site_url]['ignored_plugins'];
        } else {
            // Check if there's an entry with the original URL
            $original_url = $data['site_url'];
            if ($original_url !== $site_url && isset($clients[$original_url]['ignored_plugins'])) {
                $ignored_plugins = $clients[$original_url]['ignored_plugins'];
                // Remove old entry
                unset($clients[$original_url]);
                $this->log_master("Migrated ignored plugins from '$original_url' to '$site_url'");
            }
        }

        // Use normalized site_url as key
        $clients[$site_url] = array_merge($data, [
            'site_url' => $site_url, // Store normalized URL
            'last_sync' => current_time('mysql'),
            'status' => 'active', // Explicitly mark as active on successful update
            'ignored_plugins' => $ignored_plugins
        ]);
        update_option($this->option_name, $clients);
    }

    public function toggle_ignore_plugin($site_url, $plugin_path, $ignore) {
        $clients = $this->get_clients();
        
        // Use normalized URL
        $normalized_url = $this->normalize_url($site_url);
        
        if (!isset($clients[$normalized_url])) {
            $this->log_master("toggle_ignore_plugin: Client not found for '$normalized_url'");
            return false;
        }
        
        $found_key = $normalized_url;
        
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
        
        // Use normalized URL
        $normalized_url = $this->normalize_url($site_url);

        if (isset($clients[$normalized_url])) {
            $clients[$normalized_url]['status'] = 'unreachable';
            // Do NOT update last_sync so we know it's stale
            update_option($this->option_name, $clients);
            $this->log_master("Marked client unreachable: $normalized_url");
        } else {
            $this->log_master("mark_client_unreachable: Client not found for '$normalized_url'");
        }
    }

    public function delete_client($site_url) {
        $clients = $this->get_clients();
        $normalized_url = $this->normalize_url($site_url);
        
        if (isset($clients[$normalized_url])) {
            unset($clients[$normalized_url]);
            update_option($this->option_name, $clients);
            $this->log_master("Deleted client: $normalized_url");
            return true;
        }
        return false;
    }

    /**
     * Fetch private repository data (Plugins & Themes) with caching
     */
    public function get_private_repo_data() {
        // Check cache first (5 minute cache)
        $cache_key = 'marrison_private_repo_data';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            $this->log_master("Using cached repository data");
            return $cached;
        }
        
        $this->log_master("Fetching fresh repository data");
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

        // Cache for 5 minutes
        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
        
        return $data;
    }

    /**
     * Helper to fetch and parse repo JSON
     */
    private function fetch_repo_json($url) {
        $this->log_master("Fetching repository from: $url");
        
        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_master("Repository fetch failed: " . (is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response)));
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_master("JSON decode error: " . json_last_error_msg());
            $this->log_master("Raw response (first 500 chars): " . substr($body, 0, 500));
        }

        // Fallback for packages.json convention
        if (json_last_error() !== JSON_ERROR_NONE) {
            $url_fallback = untrailingslashit($url) . '/packages.json';
            $this->log_master("Trying fallback URL: $url_fallback");
            $response = wp_remote_get($url_fallback, ['timeout' => 15, 'sslverify' => false]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                 $body = wp_remote_retrieve_body($response);
                 $json = json_decode($body, true);
                 $this->log_master("Fallback successful, JSON decoded");
            } else {
                 $this->log_master("Fallback also failed");
            }
        }

        if (!$json || !is_array($json)) {
            $this->log_master("Invalid JSON structure or empty response");
            return [];
        }

        // Log structure and count
        $this->log_master("JSON structure keys: " . implode(', ', array_keys($json)));
        if (isset($json['plugins'])) {
            $this->log_master("Found " . count($json['plugins']) . " plugins in 'plugins' key");
        } elseif (isset($json['packages'])) {
            $this->log_master("Found " . count($json['packages']) . " plugins in 'packages' key");
        } else {
            $this->log_master("No 'plugins' or 'packages' key found, treating as direct array: " . count($json) . " items");
        }

        // Normalize structure
        if (isset($json['packages'])) return $json['packages'];
        if (isset($json['plugins'])) return $json['plugins'];
        
        // If it's a numeric array, convert to associative using slug as key
        if (is_array($json) && isset($json[0]) && isset($json[0]['slug'])) {
            $this->log_master("Converting numeric array to associative using slug as key");
            $associative = [];
            foreach ($json as $item) {
                if (isset($item['slug'])) {
                    $associative[$item['slug']] = $item;
                }
            }
            $this->log_master("Converted " . count($associative) . " items to associative array");
            return $associative;
        }
        
        return $json;
    }

    /**
     * Compare client installed items with private repo and inject updates
     */
    public function compare_and_inject_updates(&$client_data, $repo_data) {
        $this->log_master("Starting injection process for client: " . $client_data['site_url']);
        $this->log_master("Repository plugins available: " . count($repo_data['plugins'] ?? []));
        $this->log_master("Repository themes available: " . count($repo_data['themes'] ?? []));
        
        // Log all repository plugins for debugging
        if (!empty($repo_data['plugins'])) {
            $this->log_master("Repository plugin slugs: " . implode(', ', array_keys($repo_data['plugins'])));
        }
        
        // Log all client plugins for debugging  
        if (!empty($client_data['plugins'])) {
            $client_plugin_slugs = [];
            foreach ($client_data['plugins'] as $file => $plugin) {
                $slug = dirname($file);
                if ($slug === '.') $slug = basename($file, '.php');
                $client_plugin_slugs[] = $slug;
            }
            $this->log_master("Client plugin slugs: " . implode(', ', $client_plugin_slugs));
        }
        
        // Plugins
        if (!empty($repo_data['plugins']) && !empty($client_data['plugins'])) {
            foreach ($client_data['plugins'] as $file => $plugin) {
                $slug = dirname($file); // e.g., 'my-plugin' from 'my-plugin/my-plugin.php'
                if ($slug === '.') $slug = basename($file, '.php'); // Single file plugins

                $this->log_master("Processing plugin: $file -> slug: $slug");

                // Check for match in repo
                $repo_item = $repo_data['plugins'][$slug] ?? null;
                
                // Fallback: Check if key in repo matches the full filename? Rare but possible.
                if (!$repo_item && isset($repo_data['plugins'][$file])) {
                    $repo_item = $repo_data['plugins'][$file];
                    $this->log_master("Found match by full filename: $file");
                }

                if ($repo_item) {
                    $new_version = $repo_item['version'] ?? $repo_item['new_version'] ?? null;
                    $current_version = $plugin['Version'];
                    $package_url = $repo_item['package'] ?? $repo_item['download_url'] ?? '';

                    $this->log_master("Plugin injection check: $file | Current: $current_version | Available: $new_version | Package: " . ($package_url ?: 'MISSING'));

                    if ($new_version && version_compare($current_version, $new_version, '<')) {
                        // Validate package URL before injecting
                        if (empty($package_url)) {
                            $this->log_master("WARNING: Plugin $file has update to $new_version but NO PACKAGE URL - skipping injection");
                        } else {
                            // Create update entry
                            if (!isset($client_data['plugins_need_update'])) {
                                $client_data['plugins_need_update'] = [];
                            }
                            
                            // Inject update with validated package URL
                            $client_data['plugins_need_update'][$file] = [
                                'slug' => $slug,
                                'new_version' => $new_version,
                                'package' => $package_url,
                                'url' => $repo_item['url'] ?? '',
                                'Name' => $plugin['Name']
                            ];
                            
                            $this->log_master("✓ INJECTED plugin update: $file -> $new_version (Package: $package_url)");
                        }
                    } else {
                        $this->log_master("Plugin $file is up to date or version check failed");
                    }
                } else {
                    $this->log_master("No repo match for plugin: $file (slug: $slug)");
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
                    $package_url = $repo_item['package'] ?? $repo_item['download_url'] ?? '';

                    $this->log_master("Theme injection check: $slug | Current: $current_version | Available: $new_version | Package: " . ($package_url ?: 'MISSING'));

                    if ($new_version && version_compare($current_version, $new_version, '<')) {
                        // Validate package URL before injecting
                        if (empty($package_url)) {
                            $this->log_master("WARNING: Theme $slug has update to $new_version but NO PACKAGE URL - skipping injection");
                        } else {
                            if (!isset($client_data['themes_need_update'])) {
                                $client_data['themes_need_update'] = [];
                            }

                            $client_data['themes_need_update'][$slug] = [
                                'slug' => $slug,
                                'new_version' => $new_version,
                                'package' => $package_url,
                                'url' => $repo_item['url'] ?? '',
                                'Name' => $theme['Name']
                            ];
                            
                            $this->log_master("✓ INJECTED theme update: $slug -> $new_version (Package: $package_url)");
                        }
                    }
                } else {
                    $this->log_master("No repo item found for theme: $slug");
                }
            }
        }
        
        // Log summary of injected updates
        $plugin_updates = count($client_data['plugins_need_update'] ?? []);
        $theme_updates = count($client_data['themes_need_update'] ?? []);
        $this->log_master("Injection complete: $plugin_updates plugin updates, $theme_updates theme updates injected");
    }

    /**
     * Trigger remote update asynchronously on Agent
     */
    public function trigger_remote_update($client_url) {
        $primary_endpoint = trailingslashit($client_url) . 'wp-json/wp-agent-updater/v1/update-async';
        $args = [
            'body' => [
                'clear_cache' => true,
                'update_translations' => true,
            ],
            'timeout' => 15,
            'sslverify' => false,
        ];
        $response = wp_remote_post($primary_endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $is_json = stripos((string)$content_type, 'application/json') !== false;

        if ($code !== 200 || !$is_json) {
            $fallback_endpoint = trailingslashit($client_url) . '?rest_route=/wp-agent-updater/v1/update-async';
            $response = wp_remote_post($fallback_endpoint, $args);
            if (is_wp_error($response)) {
                return $response;
            }
            $code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $is_json = stripos((string)$content_type, 'application/json') !== false;

            if ($code !== 200 || !$is_json) {
                return new WP_Error('http_error', "Remote server returned code $code");
            }
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            return new WP_Error('update_failed', $body['message'] ?? 'Update start failed');
        }
        $job_id = isset($body['job_id']) ? $body['job_id'] : '';
        if (!$job_id) {
            return new WP_Error('update_failed', 'Missing job id from agent');
        }
        return [
            'job_id' => $job_id,
        ];
    }

    public function get_remote_update_status($client_url, $job_id) {
        $endpoint = trailingslashit($client_url) . 'wp-json/wp-agent-updater/v1/update-status';
        $endpoint = add_query_arg('job_id', $job_id, $endpoint);
        $response = wp_remote_get($endpoint, [
            'timeout' => 20,
            'sslverify' => false,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $is_json = stripos((string)$content_type, 'application/json') !== false;
        if ($code !== 200 || !$is_json) {
            return new WP_Error('http_error', "Remote server returned code $code");
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return new WP_Error('invalid_response', 'Invalid response from agent');
        }
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

        $response = wp_remote_get($endpoint, [
            'timeout' => 20,
            'sslverify' => false
        ]);

        $code = ! is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 0;
        $content_type = ! is_wp_error($response) ? wp_remote_retrieve_header($response, 'content-type') : '';
        $is_json = ! is_wp_error($response) && stripos((string)$content_type, 'application/json') !== false;

        // Fallback to non-pretty REST route if primary fails (e.g., servers without /wp-json support)
        if (is_wp_error($response) || $code !== 200 || ! $is_json) {
            $fallback_endpoint = trailingslashit($client_url) . '?rest_route=/wp-agent-updater/v1/status';
            $fallback_endpoint = add_query_arg('marrison_ts', time(), $fallback_endpoint);
            $response = wp_remote_get($fallback_endpoint, [
                'timeout' => 20,
                'sslverify' => false
            ]);

            if (is_wp_error($response)) {
                if ($mark_unreachable) {
                    $this->mark_client_unreachable($client_url);
                }
                return $response;
            }

            $code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $is_json = stripos((string)$content_type, 'application/json') !== false;

            if ($code !== 200 || ! $is_json) {
                if ($mark_unreachable) {
                    $this->mark_client_unreachable($client_url);
                }
                return new WP_Error('http_error', "Remote server returned code $code");
            }
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

        $this->log_master("Starting bulk sync for " . count($client_urls) . " clients");
        $start_time = microtime(true);

        // Fetch private repo data once for all clients (uses cache)
        $repo_data = $this->get_private_repo_data();

        $results = [];
        $requests = [];
        $request_map = [];

        // Build all requests for parallel execution
        foreach ($client_urls as $url) {
            $normalized_url = $this->normalize_url($url);
            $endpoint = trailingslashit($normalized_url) . 'wp-json/wp-agent-updater/v1/status';
            $endpoint = add_query_arg('marrison_ts', time(), $endpoint);
            
            $requests[] = [
                'url' => $endpoint,
                'type' => 'GET',
                'headers' => [],
                'data' => [],
                'cookies' => [],
                'options' => [
                    'timeout' => 20, // Increased timeout to reduce false negatives
                    'verify' => false
                ]
            ];
            
            $request_map[] = $normalized_url;
        }

        // Execute requests in TRUE parallel using Requests library
        try {
            // Use Requests library for true parallel execution
            if (class_exists('Requests')) {
                $this->log_master("Using Requests library for parallel execution");
                $responses = \Requests::request_multiple($requests, [
                    'timeout' => 20,
                    'verify' => false
                ]);
            } else {
                // Fallback to sequential if Requests not available
                $this->log_master("Requests library not available, using sequential fallback");
                $responses = [];
                foreach ($requests as $request) {
                    $responses[] = wp_remote_get($request['url'], $request['options']);
                }
            }
        } catch (Exception $e) {
            $this->log_master("Parallel request error: " . $e->getMessage());
            // Fallback to sequential
            $responses = [];
            foreach ($requests as $request) {
                $responses[] = wp_remote_get($request['url'], $request['options']);
            }
        }

        // Process responses
        foreach ($responses as $index => $response) {
            $url = $request_map[$index];
            
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $this->log_master("Client $url failed: $error_msg");
                
                // Retry once before marking unreachable
                $this->log_master("Retrying $url...");
                $retry_response = wp_remote_get($requests[$index]['url'], $requests[$index]['options']);
                
                if (!is_wp_error($retry_response) && wp_remote_retrieve_response_code($retry_response) === 200) {
                    $response = $retry_response;
                    $this->log_master("Retry successful for $url");
                } else {
                    $this->mark_client_unreachable($url);
                    $results[$url] = [
                        'success' => false,
                        'error' => $error_msg
                    ];
                    continue;
                }
            }

            $code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');

            if ($code !== 200) {
                $this->log_master("Client $url returned HTTP $code");
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => "HTTP $code"
                ];
                continue;
            }
            
            if (stripos($content_type, 'application/json') === false) {
                $this->log_master("Client $url returned non-JSON content: $content_type");
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid content type'
                ];
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data && isset($data['site_url'])) {
                // Inject private updates
                $this->compare_and_inject_updates($data, $repo_data);

                $this->update_client_data($data);
                $results[$url] = ['success' => true];
                $this->log_master("Client $url synced successfully");
            } else {
                $this->log_master("Client $url returned invalid data format");
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid data format'
                ];
            }
        }

        $elapsed = round(microtime(true) - $start_time, 2);
        $success_count = count(array_filter($results, function($r) { return $r['success'] ?? false; }));
        $this->log_master("Bulk sync completed: $success_count/" . count($client_urls) . " successful in {$elapsed}s");

        return [
            'success' => true,
            'results' => $results,
            'elapsed' => $elapsed
        ];
    }
}
