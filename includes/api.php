<?php

class Marrison_Master_API {

    private $core;
    private $response_sent = false;

    public function __construct() {
        $this->core = new Marrison_Master_Core();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wp-master-updater/v1', '/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sync_request'],
            'permission_callback' => '__return_true'
        ]);
    }

    private function start_guard($context) {
        $this->response_sent = false;
        ob_start();
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () use ($context) {
            if ($this->response_sent) {
                return;
            }
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                http_response_code(200);
                header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                echo wp_json_encode([
                    'success' => false,
                    'message' => $context . ': ' . $error['message']
                ]);
                exit;
            }
        });
    }

    private function finish_guard($log_prefix) {
        $output = ob_get_clean();
        restore_error_handler();
        $this->response_sent = true;

        if (!empty($output)) {
            error_log($log_prefix . substr($output, 0, 500));
        }
    }

    public function handle_sync_request($request) {
        $this->start_guard('Sync failed');
        $data = $request->get_json_params();

        if (empty($data) || !isset($data['site_url'])) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => 'Missing site URL'
            ]);
        }

        try {
            $this->core->update_client_data($data);
        } catch (Throwable $e) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        // Return current repo configuration and injected updates
        $config = [
            'plugins_repo' => get_option('marrison_private_plugins_repo'),
            'themes_repo' => get_option('marrison_private_themes_repo')
        ];

        // Get client data to extract injected updates
        // IMPORTANT: Use normalized URL to match the key used in update_client_data()
        $clients = $this->core->get_clients();
        $injected_updates = [];

        // The site_url in $data might not be normalized yet, but update_client_data() normalizes it
        // So we need to check both the original and what would be the normalized version
        $original_url = $data['site_url'];
        
        // Try to find the client data - it should be stored with normalized URL
        $client_data = null;
        if (isset($clients[$original_url])) {
            $client_data = $clients[$original_url];
        } else {
            // The URL was normalized during update_client_data(), so look for it
            foreach ($clients as $url => $data_entry) {
                if (isset($data_entry['site_url']) && $data_entry['site_url'] === $original_url) {
                    $client_data = $data_entry;
                    break;
                }
            }
        }

        if ($client_data) {
            // Extract injected plugin updates
            if (!empty($client_data['plugins_need_update'])) {
                $injected_updates['plugins'] = $client_data['plugins_need_update'];
            }
            
            // Extract injected theme updates  
            if (!empty($client_data['themes_need_update'])) {
                $injected_updates['themes'] = $client_data['themes_need_update'];
            }
        }

        $this->finish_guard('[Marrison Sync Output] ');
        
        // Log injected updates for debugging
        if (!empty($injected_updates)) {
            error_log('[Marrison API] Sending injected updates to ' . $original_url . ': ' . json_encode($injected_updates));
        } else {
            error_log('[Marrison API] No injected updates found for ' . $original_url);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Data synchronized successfully',
            'config' => $config,
            'injected_updates' => $injected_updates
        ]);
    }
}

new Marrison_Master_API();
