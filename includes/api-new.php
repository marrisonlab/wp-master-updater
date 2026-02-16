<?php

/**
 * WP Master Updater API - Complete Rewrite
 * Simple, reliable REST endpoints
 */

class Marrison_Master_API {
    
    private $core;
    
    public function __construct() {
        $this->core = new Marrison_Master_Core();
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // Client sync endpoint
        register_rest_route('marrison-master/v1', '/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sync'],
            'permission_callback' => '__return_true'
        ]);
        
        // Update client endpoint
        register_rest_route('marrison-master/v1', '/update/(?P<site_url>.+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_update'],
            'permission_callback' => '__return_true',
            'args' => [
                'site_url' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return filter_var($param, FILTER_VALIDATE_URL);
                    }
                ]
            ]
        ]);
        
        // Get clients endpoint
        register_rest_route('marrison-master/v1', '/clients', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_clients'],
            'permission_callback' => '__return_true'
        ]);
        
        // Toggle ignore endpoint
        register_rest_route('marrison-master/v1', '/ignore', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_toggle_ignore'],
            'permission_callback' => '__return_true'
        ]);
        
        // Config endpoint
        register_rest_route('marrison-master/v1', '/config', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_config'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Handle client sync
     */
    public function handle_sync($request) {
        try {
            $data = $request->get_json_params();
            
            if (empty($data['site_url'])) {
                return new WP_Error('missing_data', 'Missing site URL', ['status' => 400]);
            }
            
            // Inject private updates
            $data = $this->core->inject_updates($data);
            
            // Update client data
            $success = $this->core->update_client($data);
            
            if (!$success) {
                return new WP_Error('update_failed', 'Failed to update client data', ['status' => 500]);
            }
            
            // Return current config
            $config = $this->core->get_config();
            
            return rest_ensure_response([
                'success' => true,
                'message' => 'Sync successful',
                'config' => $config,
                'injected_updates' => [
                    'plugins' => count($data['plugins_need_update'] ?? []),
                    'themes' => count($data['themes_need_update'] ?? [])
                ]
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('sync_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Handle client update
     */
    public function handle_update($request) {
        $site_url = urldecode($request->get_param('site_url'));
        $options = $request->get_json_params() ?? [];
        
        $result = $this->core->trigger_client_update($site_url, $options);
        
        if (!$result['success']) {
            return new WP_Error('update_failed', $result['message'], ['status' => 500]);
        }
        
        // Sync after update
        $this->core->sync_client($site_url);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Handle get clients
     */
    public function handle_get_clients($request) {
        $clients = $this->core->get_clients();
        
        return rest_ensure_response([
            'success' => true,
            'clients' => $clients,
            'count' => count($clients)
        ]);
    }
    
    /**
     * Handle toggle ignore
     */
    public function handle_toggle_ignore($request) {
        $params = $request->get_json_params();
        
        $required = ['site_url', 'type', 'slug', 'ignore'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', "Missing field: $field", ['status' => 400]);
            }
        }
        
        $site_url = $params['site_url'];
        $type = $params['type'];
        $slug = $params['slug'];
        $ignore = $params['ignore'] === true || $params['ignore'] === 'true';
        
        if (!in_array($type, ['plugin', 'theme'])) {
            return new WP_Error('invalid_type', 'Type must be plugin or theme', ['status' => 400]);
        }
        
        $success = $this->core->toggle_ignore($site_url, $type, $slug, $ignore);
        
        if (!$success) {
            return new WP_Error('toggle_failed', 'Failed to toggle ignore status', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => "Ignore status updated for $type $slug"
        ]);
    }
    
    /**
     * Handle config
     */
    public function handle_config($request) {
        $method = $request->get_method();
        
        if ($method === 'GET') {
            $config = $this->core->get_config();
            return rest_ensure_response([
                'success' => true,
                'config' => $config
            ]);
        }
        
        if ($method === 'POST') {
            $config = $request->get_json_params() ?? [];
            
            // Validate config
            $allowed_fields = [
                'plugins_repo',
                'themes_repo', 
                'enable_private_plugins',
                'enable_private_themes'
            ];
            
            $filtered_config = array_intersect_key($config, array_flip($allowed_fields));
            
            $success = $this->core->update_config($filtered_config);
            
            if (!$success) {
                return new WP_Error('config_failed', 'Failed to update config', ['status' => 500]);
            }
            
            return rest_ensure_response([
                'success' => true,
                'message' => 'Configuration updated',
                'config' => $this->core->get_config()
            ]);
        }
    }
}

// Initialize API
new Marrison_Master_API();
