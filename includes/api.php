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
        register_rest_route('wp-master-updater/v1', '/push-request', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_push_request_check'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('wp-master-updater/v1', '/update-request', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_update_request_check'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('wp-master-updater/v1', '/poll', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_poll'],
            'permission_callback' => '__return_true'
        ]);
    }

    private function is_authorized($request) {
        $token_required = get_option('marrison_master_api_token');
        if (empty($token_required)) {
            return true;
        }
        $ts = $request->get_header('x-marrison-timestamp');
        $sig = $request->get_header('x-marrison-signature');
        if ($ts && $sig) {
            $now = time();
            if (abs($now - (int)$ts) > 600) {
                return false;
            }
            $message = $request->get_method() === 'POST' ? (string)$request->get_body() : (string)$request->get_param('site_url');
            $expected = hash_hmac('sha256', $message . '|' . $ts, $token_required);
            return hash_equals($expected, $sig);
        }
        $provided = $request->get_header('x-marrison-token');
        return is_string($provided) && hash_equals($token_required, $provided);
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
        if (!$this->is_authorized($request)) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
        }
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

        // Return current repo configuration
        $config = [
            'plugins_repo' => get_option('marrison_private_plugins_repo'),
            'themes_repo' => get_option('marrison_private_themes_repo')
        ];

        $this->finish_guard('[Marrison Sync Output] ');
        return rest_ensure_response([
            'success' => true,
            'message' => 'Data synchronized successfully',
            'config' => $config
        ]);
    }

    public function handle_push_request_check($request) {
        if (!$this->is_authorized($request)) {
            return rest_ensure_response(['requested' => false, 'message' => 'Unauthorized']);
        }
        $site_url = isset($_GET['site_url']) ? sanitize_text_field($_GET['site_url']) : '';
        if (empty($site_url)) {
            return rest_ensure_response(['requested' => false, 'message' => 'Missing site_url']);
        }
        $requested = $this->core->consume_agent_push_request($site_url);
        return rest_ensure_response(['requested' => $requested]);
    }

    public function handle_update_request_check($request) {
        if (!$this->is_authorized($request)) {
            return rest_ensure_response(['requested' => false, 'message' => 'Unauthorized']);
        }
        $site_url = isset($_GET['site_url']) ? sanitize_text_field($_GET['site_url']) : '';
        if (empty($site_url)) {
            return rest_ensure_response(['requested' => false, 'message' => 'Missing site_url']);
        }
        $opts = $this->core->consume_agent_update_request($site_url);
        if ($opts === null) {
            return rest_ensure_response(['requested' => false]);
        }
        return rest_ensure_response(['requested' => true, 'options' => $opts]);
    }

    public function handle_poll($request) {
        if (!$this->is_authorized($request)) {
            return rest_ensure_response([
                'push_requested' => false,
                'update_requested' => false,
                'message' => 'Unauthorized'
            ]);
        }
        $site_url = isset($_GET['site_url']) ? sanitize_text_field($_GET['site_url']) : '';
        if (empty($site_url)) {
            return rest_ensure_response([
                'push_requested' => false,
                'update_requested' => false,
                'message' => 'Missing site_url'
            ]);
        }
        $this->core->touch_client_last_sync($site_url);
        $push = $this->core->consume_agent_push_request($site_url);
        $opts = $this->core->consume_agent_update_request($site_url);
        return rest_ensure_response([
            'push_requested' => $push,
            'update_requested' => $opts !== null,
            'update_options' => $opts
        ]);
    }
}

new Marrison_Master_API();
