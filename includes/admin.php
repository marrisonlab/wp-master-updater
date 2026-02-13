<?php

class Marrison_Master_Admin {

    private $core;

    public function __construct() {
        $this->core = new Marrison_Master_Core();
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_head', [$this, 'fix_menu_icon']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_marrison_client_action', [$this, 'handle_ajax_action']);
        add_action('wp_ajax_marrison_master_clear_cache', [$this, 'handle_clear_cache']);
        add_action('wp_ajax_marrison_fetch_repo_data', [$this, 'handle_fetch_repo_data']);
        add_action('admin_post_marrison_download_repo_template', [$this, 'handle_download_repo_template']);

        $plugin_basename = plugin_basename(WP_MASTER_UPDATER_PATH . 'wp-master-updater.php');
        add_filter('plugin_action_links_' . $plugin_basename, [$this, 'add_action_links']);
    }

    public function handle_download_repo_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        if (!in_array($type, ['plugins', 'themes'])) {
            wp_die('Invalid type');
        }

        $file_path = WP_MASTER_UPDATER_PATH . $type . '_repo/index.php';
        
        if (!file_exists($file_path)) {
            wp_die('Template file not found: ' . esc_html($file_path));
        }

        // Force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="index.php"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        
        readfile($file_path);
        exit;
    }

    private function render_header($title) {
        $logo_url = plugin_dir_url(__FILE__) . 'logo.svg';
        ?>
        <!-- Invisible H1 to catch WordPress notifications and prevent them from being injected into our custom header -->
        <h1 class="wp-heading-inline" style="display:none;"></h1>
        
        <div class="mmu-header">
            <div class="mmu-header-title">
                <div class="mmu-title-text"><?php echo esc_html($title); ?></div>
            </div>
            <div class="mmu-header-logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Marrison Logo">
            </div>
        </div>
        <style>
            .mmu-header {
                height: 120px;
                background: linear-gradient(to top right, #3f2154, #11111e);
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 40px;
                margin-bottom: 20px;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                color: #fff;
                box-sizing: border-box;
            }
            .mmu-header-title .mmu-title-text {
                color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 28px !important;
                font-weight: 600 !important;
                line-height: 1.2 !important;
            }
            .mmu-header-logo {
                display: flex;
                align-items: center;
            }
            .mmu-header-logo img {
                width: 180px;
                height: auto;
                display: block;
            }
        </style>
        <?php
    }

    public function add_action_links($links) {
        $settings_link = '<a href="admin.php?page=marrison-master-settings">Repositories</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_menu() {
        add_menu_page(
            'WP Master Updater',
            'WP Master',
            'manage_options',
            'wp-master-updater',
            [$this, 'render_dashboard'],
            plugin_dir_url(__FILE__) . 'menu-icon.svg?v=' . time()
        );
        add_submenu_page(
            'wp-master-updater',
            'Repositories',
            'Repositories',
            'manage_options',
            'marrison-master-settings',
            [$this, 'render_settings']
        );
        add_submenu_page(
            'wp-master-updater',
            'Guide',
            'Guide',
            'manage_options',
            'marrison-master-howto',
            [$this, 'render_howto']
        );
    }

    public function fix_menu_icon() {
        $icon_url = plugin_dir_url(__FILE__) . 'menu-icon.svg?v=' . time();
        ?>
        <style>
            #adminmenu .toplevel_page_wp-master-updater .wp-menu-image img {
                display: none !important;
            }
            #adminmenu .toplevel_page_wp-master-updater .wp-menu-image {
                background-color: #a7aaad;
                -webkit-mask: url('<?php echo esc_url($icon_url); ?>') no-repeat center center;
                mask: url('<?php echo esc_url($icon_url); ?>') no-repeat center center;
                -webkit-mask-size: 20px 20px;
                mask-size: 20px 20px;
            }
            #adminmenu .toplevel_page_wp-master-updater:hover .wp-menu-image {
                background-color: #72aee6;
            }
            #adminmenu .toplevel_page_wp-master-updater.wp-has-current-submenu .wp-menu-image {
                background-color: #fff;
            }
        </style>
        <?php
    }

    public function render_howto() {
        ?>
        <style>
            .mmu-guide-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            .mmu-guide-box {
                background: #fff;
                padding: 20px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
            }
            .mmu-guide-box h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f1;
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 16px;
            }
            .mmu-download-links {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f1;
            }
            .mmu-download-link {
                display: block;
                margin-bottom: 8px;
                text-decoration: none;
                color: #2271b1;
            }
            .mmu-download-link:hover {
                text-decoration: underline;
            }
            .mmu-download-link .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
                vertical-align: middle;
                margin-right: 5px;
                color: #2271b1;
            }
            @media (max-width: 960px) {
                .mmu-guide-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="wrap">
            <?php $this->render_header('Guide'); ?>
            
            <div class="mmu-guide-grid">
                <!-- 1. LED Legend -->
                <div class="mmu-guide-box">
                    <h2>LED Legend</h2>
                    <p>The status LEDs on the main dashboard indicate the current status of each connected client site:</p>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span class="mmu-led" style="color: #46b450; margin-right: 10px; background: #46b450; width: 12px; height: 12px; display: inline-block; border-radius: 50%; flex-shrink: 0;"></span>
                            <span><strong>Green:</strong> Everything updated. The site is fully updated and active.</span>
                        </li>
                        <li style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span class="mmu-led" style="color: #dc3232; margin-right: 10px; background: #dc3232; width: 12px; height: 12px; display: inline-block; border-radius: 50%; flex-shrink: 0;"></span>
                            <span><strong>Red:</strong> Updates available. There are plugins, themes, or translations to update.</span>
                        </li>
                        <li style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span class="mmu-led" style="color: #f0c330; margin-right: 10px; background: #f0c330; width: 12px; height: 12px; display: inline-block; border-radius: 50%; flex-shrink: 0;"></span>
                            <span><strong>Yellow:</strong> Inactive plugins found. The site has plugins installed but not activated.</span>
                        </li>
                        <li style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span class="mmu-led" style="color: #000000; margin-right: 10px; background: #000000; width: 12px; height: 12px; display: inline-block; border-radius: 50%; flex-shrink: 0;"></span>
                            <span><strong>Black:</strong> Agent unreachable. The Master Updater cannot connect to the client site. Check if the Agent plugin is active and the URL is correct.</span>
                        </li>
                    </ul>
                </div>

                <!-- 2. Configuration & Repositories -->
                <div class="mmu-guide-box">
                    <h2>Private Repositories</h2>
                    <p>You can host your own private repositories for plugins and themes. Follow these steps:</p>
                    <ol>
                        <li>Create a folder on your server (e.g. <code>/my-plugins-repo/</code>).</li>
						<li>Upload the <strong>Plugin Index</strong> file to this folder (rename it to <code>index.php</code> if necessary).</li>
                        <li>Upload your plugin ZIP files to this folder in the same format you would normally upload them to WordPress.</li>
                        <li>Go to the <strong>Repositories</strong> tab in WP Master Updater.</li>
                        <li>Enable "Private Plugin Repositories" and enter the URL of your folder (e.g. <code>https://example.com/my-plugins-repo/</code>).</li>
                        <li>Repeat the same process for themes if necessary.</li>
                    </ol>

                    <div class="mmu-download-links">
                        <p><strong>Download Template Files:</strong></p>
                        <a href="<?php echo admin_url('admin-post.php?action=marrison_download_repo_template&type=plugins'); ?>" class="mmu-download-link">
                            <span class="dashicons dashicons-download"></span> Download Plugin Index (index.php)
                        </a>
                        <a href="<?php echo admin_url('admin-post.php?action=marrison_download_repo_template&type=themes'); ?>" class="mmu-download-link">
                            <span class="dashicons dashicons-download"></span> Download Theme Index (index.php)
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('marrison_master_options', 'marrison_private_plugins_repo');
        register_setting('marrison_master_options', 'marrison_private_themes_repo');
        register_setting('marrison_master_options', 'marrison_enable_private_plugins');
        register_setting('marrison_master_options', 'marrison_enable_private_themes');
    }

    public function render_settings() {
        ?>
        <style>
            .mmu-settings-wrap {
                max-width: 100%;
            }

            .mmu-settings-header {
                margin-bottom: 20px;
            }

            .mmu-settings-title {
                margin-bottom: 5px;
            }

            .mmu-settings-subtitle {
                max-width: 720px;
                color: #646970;
                font-size: 13px;
            }

            .mmu-settings-row {
                display: grid;
                grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.3fr);
                gap: 24px;
                align-items: flex-start;
            }

            .mmu-settings-card {
                background: #ffffff;
                border-radius: 8px;
                border: 1px solid #dcdcde;
                padding: 20px 24px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .mmu-settings-card h2 {
                margin-top: 0;
                margin-bottom: 10px;
                font-size: 16px;
            }

            .mmu-settings-card p.description {
                margin-top: 0;
                margin-bottom: 16px;
            }

            .mmu-settings-card .form-table {
                margin-top: 0;
            }

            .mmu-settings-card .form-table th {
                padding-top: 0;
                display: none; /* hide legacy left labels */
            }

            .mmu-settings-card .form-table td {
                padding-top: 12px;
            }

            .mmu-switch {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
            }

            .mmu-switch input[type="checkbox"] {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
            }

            .mmu-switch-slider {
                position: relative;
                width: 42px;
                height: 22px;
                background-color: #dcdcde;
                border-radius: 999px;
                transition: background-color 0.2s ease;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
            }

            .mmu-switch-slider::before {
                content: "";
                position: absolute;
                left: 3px;
                top: 3px;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                background-color: #ffffff;
                box-shadow: 0 1px 2px rgba(0,0,0,0.15);
                transition: transform 0.2s ease;
            }

            .mmu-switch input[type="checkbox"]:checked + .mmu-switch-slider {
                background-color: #2271b1;
            }

            .mmu-switch input[type="checkbox"]:checked + .mmu-switch-slider::before {
                transform: translateX(18px);
            }

            .mmu-switch-label {
                font-weight: 500;
            }

            .mmu-url-field {
                margin-top: 10px;
            }

            .mmu-url-field .description {
                margin-top: 4px;
            }

            .mmu-url-field input[type="url"] {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .mmu-repo-preview {
                margin-top: 8px;
            }

            .mmu-settings-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .mmu-settings-save {
                margin-top: 10px;
                text-align: right;
            }

            .mmu-settings-aside {
                font-size: 13px;
                color: #646970;
            }

            .mmu-settings-aside h3 {
                margin-top: 0;
                margin-bottom: 8px;
                font-size: 14px;
            }

            .mmu-help-link {
                margin-top: 2px;
                font-size: 11px;
            }

            .mmu-help-link a {
                text-decoration: none;
            }

            .mmu-help-link a:hover {
                text-decoration: underline;
            }

            .mmu-switch-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
            }

            .mmu-help-inline {
                font-size: 11px;
                white-space: nowrap;
            }

            .mmu-help-inline a {
                text-decoration: none;
            }

            .mmu-help-inline a:hover {
                text-decoration: underline;
            }

            .mmu-settings-repo-list h3 {
                margin-top: 0;
                margin-bottom: 8px;
                font-size: 14px;
            }

            .mmu-settings-repo-list-section {
                margin-top: 8px;
            }

            @media (max-width: 960px) {
                .mmu-settings-row {
                    grid-template-columns: minmax(0, 1fr);
                }

                .mmu-settings-card .form-table th {
                    width: 160px;
                }
            }
        </style>

        <div class="wrap mmu-settings-wrap">
            <div class="mmu-settings-header">
                <?php $this->render_header('Repositories'); ?>
                         </div>

            <?php 
            $errors = get_settings_errors();
            if ( ! empty( $errors ) ) {
                foreach ( $errors as $error ) {
                    $message = $error['message'];
                    if ( $error['code'] === 'settings_updated' && $error['type'] === 'success' ) {
                        $message = 'Settings saved.';
                    }
                    echo '<div class="notice notice-' . esc_attr($error['type']) . ' settings-error is-dismissible">';
                    echo '<p><strong>' . $message . '</strong></p>';
                    echo '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
                    echo '</div>';
                }
            }
            ?>

            <form method="post" action="options.php">
                <?php settings_fields('marrison_master_options'); ?>
                <?php do_settings_sections('marrison_master_options'); ?>

                <div class="mmu-settings-row">
                    <div>
                        <div class="mmu-settings-card">
                            <h2>Private Repositories</h2>
                            <p class="description">
                                Enable custom repositories to distribute plugins and themes to your connected client sites.
                            </p>

                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"></th>
                                    <td>
                                        <div class="mmu-switch-row">
                                            <label class="mmu-switch" for="marrison_enable_private_plugins">
                                                <input type="checkbox"
                                                    name="marrison_enable_private_plugins"
                                                    id="marrison_enable_private_plugins"
                                                    value="1"
                                                    <?php checked(1, get_option('marrison_enable_private_plugins'), true); ?> />
                                                <span class="mmu-switch-slider" aria-hidden="true"></span>
                                                <span class="mmu-switch-label">Enable Private Plugin Repositories</span>
                                            </label>
                                            <span class="mmu-help-inline">
                                                <a href="<?php echo esc_url( admin_url('admin.php?page=marrison-master-howto') ); ?>">
                                                    How does it work?
                                                </a>
                                            </span>
                                        </div>

                                        <div class="mmu-url-field">
                                            <input type="url"
                                                name="marrison_private_plugins_repo"
                                                id="marrison_private_plugins_repo"
                                                value="<?php echo esc_attr(get_option('marrison_private_plugins_repo')); ?>"
                                                class="regular-text"
                                                placeholder="https://example.com/plugins-repo/" />
                                            <p class="description">
                                                URL of the folder containing your plugin repository.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"></th>
                                    <td>
                                        <div class="mmu-switch-row">
                                            <label class="mmu-switch" for="marrison_enable_private_themes">
                                                <input type="checkbox"
                                                    name="marrison_enable_private_themes"
                                                    id="marrison_enable_private_themes"
                                                    value="1"
                                                    <?php checked(1, get_option('marrison_enable_private_themes'), true); ?> />
                                                <span class="mmu-switch-slider" aria-hidden="true"></span>
                                                <span class="mmu-switch-label">Enable Private Theme Repositories</span>
                                            </label>
                                            <span class="mmu-help-inline">
                                                <a href="<?php echo esc_url( admin_url('admin.php?page=marrison-master-howto') ); ?>">
                                                    How does it work?
                                                </a>
                                            </span>
                                        </div>

                                        <div class="mmu-url-field">
                                            <input type="url"
                                                name="marrison_private_themes_repo"
                                                id="marrison_private_themes_repo"
                                                value="<?php echo esc_attr(get_option('marrison_private_themes_repo')); ?>"
                                                class="regular-text"
                                                placeholder="https://example.com/themes-repo/" />
                                            <p class="description">
                                                URL of the folder containing your theme repository.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <div class="mmu-settings-actions">
                                <button type="button" id="marrison_refresh_repo_btn" class="button button-secondary">
                                    Refresh Repository Data
                                </button>
                                <span id="marrison_refresh_spinner" class="spinner" style="float:none;"></span>
                                <span class="description">
                                    Refresh the preview of packages read from the configured repositories.
                                </span>
                            </div>

                            <div class="mmu-settings-save">
                                <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
                            </div>
                        </div>

                        <div class="mmu-settings-card">
                            <h2>Plugin Updates</h2>
                            <p>
                                Installed Version:
                                <strong><?php echo get_plugin_data(WP_MASTER_UPDATER_PATH . 'wp-master-updater.php')['Version']; ?></strong>
                            </p>
                            <p>
                                <a href="<?php echo wp_nonce_url(admin_url('plugins.php?force-check=1&plugin=wp-master-updater/wp-master-updater.php'), 'marrison-force-check-wp-master-updater/wp-master-updater.php'); ?>" class="button button-primary">
                                    Check Updates on GitHub
                                </a>
                            </p>
                            <p class="description">
                                Immediately check the GitHub repository to see if a newer version of WP Master Updater is available.
                            </p>
                        </div>
                    </div>

                    <div class="mmu-settings-aside">
                        <div class="mmu-settings-card mmu-settings-repo-list">
                            <h3>Repository Contents</h3>
                            <p class="description">
                                Real-time list of packages detected from your configured repositories.
                            </p>

                            <div class="mmu-settings-repo-list-section">
                                <h3>Plugins</h3>
                                <div id="marrison_plugins_repo_preview" class="marrison-repo-preview mmu-repo-preview"></div>
                            </div>

                            <div class="mmu-settings-repo-list-section">
                                <h3>Themes</h3>
                                <div id="marrison_themes_repo_preview" class="marrison-repo-preview mmu-repo-preview"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            jQuery(document).ready(function($) {
                // Handle switches
                $('#marrison_enable_private_plugins').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    $('#marrison_private_plugins_repo').prop('disabled', !isChecked);

                    if (!isChecked) $('#marrison_plugins_repo_preview').empty();
                    else if ($('#marrison_plugins_repo_preview').is(':empty')) loadRepoData(false);
                }).trigger('change');

                $('#marrison_enable_private_themes').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    $('#marrison_private_themes_repo').prop('disabled', !isChecked);

                    if (!isChecked) $('#marrison_themes_repo_preview').empty();
                    else if ($('#marrison_themes_repo_preview').is(':empty')) loadRepoData(false);
                }).trigger('change');

                // Auto-load on page load if values exist (cached or fresh)
                loadRepoData(false);

                $('#marrison_refresh_repo_btn').on('click', function() {
                    loadRepoData(true);
                });

                function loadRepoData(force) {
                    var pluginsEnabled = $('#marrison_enable_private_plugins').is(':checked');
                    var themesEnabled = $('#marrison_enable_private_themes').is(':checked');

                    var pluginsUrl = $('#marrison_private_plugins_repo').val();
                    var themesUrl = $('#marrison_private_themes_repo').val();
                    
                    if (!pluginsUrl && !themesUrl) return;

                    var spinner = $('#marrison_refresh_spinner');
                    var requests = [];

                    // Plugins
                    if (pluginsEnabled && pluginsUrl) {
                        var cacheKey = 'marrison_repo_plugins_v2_' + btoa(pluginsUrl);
                        var cached = localStorage.getItem(cacheKey);

                        if (!force && cached) {
                            $('#marrison_plugins_repo_preview').html(cached);
                        } else {
                            $('#marrison_plugins_repo_preview').html('<span style="opacity:0.5;">Loading...</span>');
                            requests.push(fetchRepo(pluginsUrl, '#marrison_plugins_repo_preview', cacheKey));
                        }
                    }

                    // Themes
                    if (themesEnabled && themesUrl) {
                        var cacheKey = 'marrison_repo_themes_v2_' + btoa(themesUrl);
                        var cached = localStorage.getItem(cacheKey);

                        if (!force && cached) {
                            $('#marrison_themes_repo_preview').html(cached);
                        } else {
                            $('#marrison_themes_repo_preview').html('<span style="opacity:0.5;">Loading...</span>');
                            requests.push(fetchRepo(themesUrl, '#marrison_themes_repo_preview', cacheKey));
                        }
                    }

                    if (requests.length > 0) {
                        spinner.addClass('is-active');
                        $.when.apply($, requests).always(function() {
                            spinner.removeClass('is-active');
                        });
                    }
                }

                function fetchRepo(url, targetId, cacheKey) {
                    return $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'marrison_fetch_repo_data',
                            nonce: '<?php echo wp_create_nonce('marrison_master_nonce'); ?>',
                            repo_url: url
                        },
                        success: function(res) {
                            if (res.success) {
                                $(targetId).html(res.data.html);
                                if (cacheKey) localStorage.setItem(cacheKey, res.data.html);
                            } else {
                                $(targetId).html('<span style="color:#dc3232;">Error: ' + res.data.message + '</span>');
                            }
                        },
                        error: function() {
                            $(targetId).html('<span style="color:#dc3232;">Connection error</span>');
                        }
                    });
                }
            });
            </script>
        </div>
        <?php
    }

    public function handle_fetch_repo_data() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'Invalid URL']);
        }

        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'HTTP Error: ' . $response->get_error_message()]);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error(['message' => 'HTTP Error: ' . $code]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: try appending packages.json if the URL ends in / or just looks like a base URL
            $url_fallback = untrailingslashit($url) . '/packages.json';
            $response = wp_remote_get($url_fallback, ['timeout' => 15, 'sslverify' => false]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                 $body = wp_remote_retrieve_body($response);
                 $data = json_decode($body, true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
             wp_send_json_error(['message' => 'Invalid response (not JSON).']);
        }
        
        $items = [];
        if (is_array($data)) {
            // Handle different structures
            $list = $data;
            if (isset($data['packages'])) $list = $data['packages'];
            elseif (isset($data['plugins'])) $list = $data['plugins'];
            elseif (isset($data['themes'])) $list = $data['themes'];
            
            foreach ($list as $key => $item) {
                if (!is_array($item)) continue;
                $name = $item['name'] ?? $item['title'] ?? '';
                $version = $item['version'] ?? $item['new_version'] ?? '';
                
                // Sometimes keys are slug => { name, version }
                if (empty($name) && is_string($key)) $name = ucfirst($key);
                
                if ($name && $version) {
                    $items[] = ['name' => $name, 'version' => $version];
                }
            }
        }
        
        if (empty($items)) {
             wp_send_json_success(['html' => '<p style="opacity:0.7; margin:5px 0;">No items found or format not recognized.</p>']);
        }
        
        ob_start();
        echo '<ul style="margin: 5px 0; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 4px; list-style:none; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
        foreach ($items as $item) {
            echo '<li style="margin:0; font-size:13px; padding: 5px; background: rgba(255,255,255,0.5); border-radius: 3px; display: flex; justify-content: space-between; align-items: center;"><strong>' . esc_html($item['name']) . '</strong> <span style="opacity:0.7; background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">v.' . esc_html($item['version']) . '</span></li>';
        }
        echo '</ul>';
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }

    public function handle_clear_cache() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $p_repo = get_option('marrison_private_plugins_repo');
        if ($p_repo) delete_transient('marrison_repo_' . md5($p_repo));
        
        $t_repo = get_option('marrison_private_themes_repo');
        if ($t_repo) delete_transient('marrison_repo_' . md5($t_repo));

        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');

        // Reset Client Data (Wipe plugin/theme info to force full re-sync)
        $clients = get_option('marrison_connected_clients', []);
        foreach ($clients as &$client) {
             // Keep identity, wipe data
             $client = [
                 'site_url' => $client['site_url'],
                 'site_name' => $client['site_name'] ?? 'Unknown',
                 'status' => 'active',
                 'last_sync' => '-' // Reset sync time
             ];
        }
        update_option('marrison_connected_clients', $clients);

        $html = $this->render_clients_table_body($clients);

        wp_send_json_success([
            'message' => 'WP Master Updater cache cleared and data reset. Start a Mass Sync to reload everything.',
            'html' => $html
        ]);
    }

    public function handle_ajax_action() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $client_url = isset($_POST['client_url']) ? sanitize_text_field($_POST['client_url']) : '';
        $action = isset($_POST['cmd']) ? sanitize_text_field($_POST['cmd']) : '';
        $backup_file = isset($_POST['backup_file']) ? sanitize_text_field($_POST['backup_file']) : '';
        $is_bulk = isset($_POST['bulk_mode']) && $_POST['bulk_mode'] === 'true';
        
        $msg = '';
        $success = true;

        if ($action === 'sync') {
            $res = $this->core->trigger_remote_sync($client_url);
            if (is_wp_error($res)) {
                $success = false;
                $msg = 'Sync Error: ' . $res->get_error_message();
            } else {
                $msg = 'Sync Started successfully';
            }
            if ($is_bulk) {
                if ($success) wp_send_json_success(['message' => $msg]);
                else wp_send_json_error(['message' => $msg]);
            }
        } elseif ($action === 'update') {
            $res = $this->core->trigger_remote_update($client_url);
            if (is_wp_error($res)) {
                $success = false;
                $msg = 'Update Error: ' . $res->get_error_message();
            } else {
                $msg = 'Update completed (plugins, themes, and translations)';
            }
        } elseif ($action === 'restore') {
            if (empty($backup_file)) {
                $success = false;
                $msg = 'Backup file missing';
            } else {
                $res = $this->core->trigger_restore_backup($client_url, $backup_file);
                if (is_wp_error($res)) {
                    $success = false;
                    $msg = 'Restore Error: ' . $res->get_error_message();
                } else {
                    $msg = 'Restore started successfully';
                }
            }
        } elseif ($action === 'delete') {
            $this->core->delete_client($client_url);
            $msg = 'Client removed';
        } elseif ($action === 'noop') {
            $msg = 'Table updated';
        }

        $clients = $this->core->get_clients();
        $html = $this->render_clients_table_body($clients);

        if ($success) {
            wp_send_json_success(['html' => $html, 'message' => $msg]);
        } else {
            wp_send_json_error(['html' => $html, 'message' => $msg]);
        }
    }

    private function render_clients_table_body($clients) {
        if (!empty($clients)) {
            uasort($clients, function($a, $b) {
                return strcasecmp($a['site_name'] ?? '', $b['site_name'] ?? '');
            });
        }
        ob_start();
        if (empty($clients)): ?>
            <tr><td colspan="7">No clients connected.</td></tr>
        <?php else: ?>
            <?php foreach ($clients as $url => $data): ?>
                <?php
                    $p_update_count = count($data['plugins_need_update'] ?? []);
                    $t_update_count = count($data['themes_need_update'] ?? []);
                    $trans_update = !empty($data['translations_need_update']);
                    $inactive_count = count($data['plugins_inactive'] ?? []);
                    $status = $data['status'] ?? 'active';
                    
                    // LED Logic
                    $led_color = '#46b450'; // Green
                    $led_title = 'Everything updated';
                    
                    if ($status === 'unreachable') {
                        $led_color = '#000000'; // Black
                        $led_title = 'Agent unreachable';
                    } elseif ($p_update_count > 0 || $t_update_count > 0 || $trans_update) {
                        $led_color = '#dc3232'; // Red
                        $led_title = 'Updates available';
                    } elseif ($inactive_count > 0) {
                        $led_color = '#f0c330'; // Yellow
                        $led_title = 'There are ' . $inactive_count . ' inactive plugins';
                    }
                    
                    $row_key = md5($url);
                    $is_green = ($led_color === '#46b450');
                    $is_yellow = ($led_color === '#f0c330');
                    $is_black = ($led_color === '#000000');
                ?>
                <tr class="mmu-main-row" data-key="<?php echo esc_attr($row_key); ?>" style="cursor: pointer;">
                    <td style="text-align: center;">
                        <span class="mmu-led" style="color: <?php echo $led_color; ?>;" title="<?php echo esc_attr($led_title); ?>"></span>
                    </td>
                    <td><strong style="display: block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($data['site_name']); ?>"><?php echo esc_html($data['site_name']); ?></strong></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></td>
                    <td>
                        <?php echo $p_update_count > 0 ? '<span style="color:#dc3232">Updates: ' . $p_update_count . '</span>' : '<span style="color:#46b450">Updated</span>'; ?>
                    </td>
                    <td>
                        <?php echo $t_update_count > 0 ? '<span style="color:#dc3232">Updates: ' . $t_update_count . '</span>' : '<span style="color:#46b450">Updated</span>'; ?>
                    </td>
                    <td><?php echo esc_html($data['last_sync'] ?? '-'); ?></td>
                    <td>
                        <form style="display:inline;" onsubmit="return false;">
                            <input type="hidden" name="client_url" value="<?php echo esc_attr($url); ?>">
                            <button type="button" value="sync" class="button button-secondary marrison-action-btn">Sync</button>
                            <button type="button" value="update" class="button button-primary marrison-action-btn" <?php echo ($is_green || $is_yellow || $is_black) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>Update</button>
                            <button type="button" value="delete" class="button button-link-delete marrison-action-btn" style="color: #dc3232;">Delete</button>
                        </form>
                    </td>
                </tr>
                
                <!-- Details Row -->
                <tr class="mmu-details-row" id="details-<?php echo esc_attr($row_key); ?>" style="display:none;">
                    <td colspan="7">
                        <div class="flex-container" style="display: flex; gap: 20px; margin-bottom: 25px;">
                            
                            <!-- Themes -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Installed Themes</h4>
                                <?php 
                                $themes = $data['themes_installed'] ?? [];
                                $themes_updates = $data['themes_need_update'] ?? [];
                                $themes_update_slugs = array_column($themes_updates, 'slug');
                                ?>
                                <?php if (!empty($themes)): ?>
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <?php foreach ($themes as $theme): ?>
                                            <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <strong><?php echo esc_html($theme['name']); ?></strong>
                                                <span style="opacity: 0.8;">v. <?php echo esc_html($theme['version']); ?></span>
                                                <?php 
                                                $update_key = array_search($theme['slug'], $themes_update_slugs);
                                                if ($update_key !== false) {
                                                    $new_ver = $themes_updates[$update_key]['new_version'];
                                                    echo '<div style="color: #ff8080; font-weight: bold; font-size: 0.9em;"><span class="dashicons dashicons-warning"></span> Update: v. ' . esc_html($new_ver) . '</div>';
                                                }
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No themes found.</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Translations -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Translations</h4>
                                <?php if ($trans_update): ?>
                                    <div style="color: #ff8080; font-weight: bold;">
                                        <span class="dashicons dashicons-translation"></span> Translations to update
                                    </div>
                                <?php else: ?>
                                    <div style="color: #46b450;">
                                        <span class="dashicons dashicons-yes"></span> Translations updated
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Backups -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Available Backups</h4>
                                <?php 
                                $backups = $data['backups'] ?? []; 
                                ?>
                                <?php if (!empty($backups)): ?>
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <?php foreach ($backups as $b): ?>
                                            <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <strong style="display:block;"><?php echo esc_html($b['slug']); ?></strong>
                                                    <small style="opacity: 0.7;"><?php echo esc_html($b['type']); ?> - <?php echo esc_html($b['date']); ?></small>
                                                </div>
                                                <div style="display:inline;">
                                                    <button type="button" value="restore" class="button button-small marrison-action-btn" 
                                                            data-client-url="<?php echo esc_attr($url); ?>" 
                                                            data-backup-file="<?php echo esc_attr($b['filename']); ?>" 
                                                            style="background: #fff; color: #2271b1; border: none;">Restore</button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="color: #fff;">No backups found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Plugins -->
                        <?php
                            $all_active = $data['plugins_active'] ?? [];
                            $all_inactive = $data['plugins_inactive'] ?? [];
                            $all_updates = $data['plugins_need_update'] ?? [];
                            
                            $update_paths = array_column($all_updates, 'path');
                            
                            $display_inactive = [];
                            foreach ($all_inactive as $p) {
                                if (!in_array($p['path'], $update_paths)) {
                                    $display_inactive[] = $p;
                                }
                            }
                            
                            $display_active_updated = [];
                            foreach ($all_active as $p) {
                                if (!in_array($p['path'], $update_paths)) {
                                    $display_active_updated[] = $p;
                                }
                            }
                        ?>
                        
                        <div class="mmu-details-section">
                            <h4>Plugins</h4>
                            
                            <!-- Updates -->
                            <?php if (!empty($all_updates)): ?>
                                <h5 style="color: #ff8080;"><span class="dashicons dashicons-warning"></span> Plugins to update</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                    <?php foreach ($all_updates as $p): ?>
                                        <div style="padding: 12px; background: rgba(255,128,128,0.1); border-radius: 6px; border-left: 4px solid #ff8080;">
                                            <strong style="color: #fff; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #ccc; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?> → v. <?php echo esc_html($p['new_version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Inactive -->
                            <?php if (!empty($display_inactive)): ?>
                                <h5 style="color: #f0c330;"><span class="dashicons dashicons-admin-plugins"></span> Inactive plugins</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                    <?php foreach ($display_inactive as $p): ?>
                                        <div style="padding: 12px; background: rgba(240,195,48,0.1); border-radius: 6px; border-left: 4px solid #f0c330;">
                                            <strong style="color: #ccc; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #999; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Active & Updated -->
                            <?php if (!empty($display_active_updated)): ?>
                                <h5 style="color: #46b450;"><span class="dashicons dashicons-yes"></span> Active and updated plugins</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                                    <?php foreach ($display_active_updated as $p): ?>
                                        <div style="padding: 12px; background: rgba(70,180,80,0.1); border-radius: 6px; border-left: 4px solid #46b450;">
                                            <strong style="color: #fff; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #ccc; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif;
        return ob_get_clean();
    }

    public function render_dashboard() {
        $clients = $this->core->get_clients();
        ?>
        <style>
            /* Modern Color Scheme */
            :root {
                --primary-color: #2271b1;
                --primary-hover: #135e96;
                --success-color: #46b450;
                --warning-color: #f0c330;
                --error-color: #dc3232;
                --text-primary: #1d2327;
                --text-secondary: #646970;
                --bg-light: #f6f7f7;
                --bg-lighter: #fafafa;
                --border-color: #c3c4c7;
                --shadow-light: 0 1px 3px rgba(0,0,0,0.1);
                --shadow-medium: 0 2px 6px rgba(0,0,0,0.15);
            }
            
            .wp-list-table.widefat {
                border: 1px solid var(--border-color);
                border-radius: 8px;
                overflow: hidden;
                box-shadow: var(--shadow-light);
            }
            
            .wp-list-table thead th {
                background: linear-gradient(180deg, #f6f7f7 0%, #f0f0f1 100%);
                border-bottom: 2px solid var(--border-color);
                font-weight: 600;
                color: var(--text-primary);
                padding: 12px 10px;
            }
            
            .mmu-main-row {
                transition: all 0.2s ease;
                border-left: 3px solid transparent;
            }
            
            .mmu-main-row:hover { 
                background-color: var(--bg-lighter) !important;
                transform: translateX(2px);
                border-left-color: var(--primary-color);
            }
            
            .mmu-main-row td {
                padding: 16px 10px;
                vertical-align: middle;
                border-bottom: 1px solid var(--border-color);
            }
            
            .mmu-details-row td { 
                background: #12111f;
                padding: 25px;
                border-bottom: 25px solid var(--bg-lighter) !important;
                box-shadow: inset 0 4px 12px rgba(0,0,0,0.2);
            }

            .mmu-details-section {
                background: rgba(255,255,255,0.08);
                padding: 20px;
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);
                color: #fff !important;
            }

            .mmu-details-section h4 {
                color: #fff;
                font-weight: 600;
                margin: 0 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid rgba(255,255,255,0.2);
            }

            .mmu-details-section ul li, 
            .mmu-details-section ul li strong,
            .mmu-details-section ul li span {
                color: #f0f0f1 !important;
            }
            
            .mmu-details-section h5 {
                margin: 0 0 15px 0;
                font-weight: 600;
            }

            .mmu-led {
                display: inline-block;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                box-shadow: 0 0 8px currentColor;
                transition: all 0.3s ease;
                position: relative;
            }
            
            .mmu-led::after {
                content: '';
                position: absolute;
                top: 3px;
                left: 3px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: currentColor;
                opacity: 0.8;
            }
            
            .button.loading {
                opacity: 0.7;
                cursor: wait;
            }
        </style>

        <div class="wrap">
            <?php $this->render_header('Connected Clients'); ?>
            <div id="marrison-notices"></div>
            
            <div class="tablenav top" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <div class="actions">
                    <button id="marrison-bulk-sync" class="button button-primary">Mass Sync</button>
                    <button id="marrison-bulk-update" class="button button-secondary" style="margin: 0 5px;">Mass Update</button>
                    <button id="marrison-clear-cache" class="button button-secondary">Clear Master Cache</button>
                </div>
                <div id="marrison-progress-wrap" style="display:none; flex: 1; max-width: 400px; border: 1px solid #c3c4c7; height: 24px; background: #fff; position: relative; border-radius: 4px; overflow: hidden;">
                     <div id="marrison-progress-bar" style="width: 0%; height: 100%; background: #46b450; transition: width 0.3s ease;"></div>
                     <div id="marrison-progress-text" style="position: absolute; top: 0; left: 0; width: 100%; text-align: center; line-height: 24px; font-size: 12px; font-weight: 600; color: #1d2327; text-shadow: 0 0 2px #fff;">0%</div>
                </div>
                <div id="marrison-bulk-status" style="font-weight: 600;"></div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Status</th>
                        <th>Site</th>
                        <th>URL</th>
                        <th>Plugins</th>
                        <th>Themes</th>
                        <th>Last Sync</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="marrison-clients-body">
                    <?php echo $this->render_clients_table_body($clients); ?>
                </tbody>
            </table>
        </div>
        
        <script>
        var marrison_vars = { 
            nonce: '<?php echo wp_create_nonce('marrison_master_nonce'); ?>' 
        };
        
            function updateProgress(current, total, message, callback) {
                var $ = jQuery;
                var wrap = $('#marrison-progress-wrap');
                var bar = $('#marrison-progress-bar');
                var text = $('#marrison-progress-text');
                var status = $('#marrison-bulk-status');

                if (wrap.length === 0 || bar.length === 0 || text.length === 0 || status.length === 0) {
                    console.error('Progress bar elements not found');
                    return;
                }

                if (total <= 0) {
                    wrap.hide();
                    bar.css('width', '0%');
                    text.text('0%');
                    status.text('');
                    return;
                }

                if (current > total) current = total;
                if (current < 0) current = 0;

                var percent = Math.round((current / total) * 100);
                
                wrap.show();
                bar.css('width', percent + '%');
                text.text(percent + '%');
                if (message) status.text(message);
                
                if (percent >= 100) {
                    setTimeout(function() {
                        wrap.fadeOut(400, function() {
                            if (callback && typeof callback === 'function') {
                                callback();
                            }
                            status.text('');
                            bar.css('width', '0%');
                            text.text('0%');
                        });
                    }, 3000);
                }
            }
            window.marrisonUpdateProgress = updateProgress;

            function performClientAction(btn) {
                var $ = jQuery;
                btn = $(btn);
                var clientUrl = btn.data('client-url') || btn.closest('form').find('input[name="client_url"]').val();
                var cmd = btn.val();
                var backupFile = btn.data('backup-file') || btn.closest('form').find('input[name="backup_file"]').val() || '';

                if (cmd === 'delete' && !confirm('Are you sure you want to delete this client?')) {
            return;
        }
        if (cmd === 'restore' && !confirm('Restore this backup?')) {
            return;
        }

        console.log('performClientAction called:', cmd, 'for client:', clientUrl); // Debug log

                var row = btn.closest('tr');
                var actionButtons = row.find('.marrison-action-btn');
                var allActionButtons = $('.marrison-action-btn');
                actionButtons.prop('disabled', true);
                allActionButtons.prop('disabled', true);
                
                var originalButtonText = btn.text();
                var clientName = 'Client'; 

                try {
                    if (row.hasClass('mmu-main-row')) {
                        clientName = row.find('td:nth-child(2)').text();
                    } else {
                        var mainRow = row.closest('.mmu-details-row').prev('.mmu-main-row');
                        if (mainRow.length) {
                            clientName = mainRow.find('td:nth-child(2)').text();
                        }
                    }
                } catch(e) { console.error('Name extraction failed', e); }

                var progressIntervalId = null;
                var progressMessage = cmd + ' on ' + clientName + ': In progress...';
                if(cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                    btn.text('In progress...');
                    window.marrisonUpdateProgress(10, 100, progressMessage);
                }

                $.post(ajaxurl, {
                    action: 'marrison_client_action',
                    nonce: marrison_vars.nonce,
                    client_url: clientUrl,
                    cmd: cmd,
                    backup_file: backupFile
                }).done(function(response) {
                    try {
                        if (response && response.success && response.data && response.data.html) {
                            $('#marrison-clients-body').html(response.data.html);
                        }
                        if (response && response.success && response.data && response.data.message) {
                            $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        } else if (response && !response.success) {
                            $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>' + (response.data ? response.data.message : 'Error') + '</p></div>');
                        }
                    } catch (e) {
                        console.error('UI update failed', e);
                    } finally {
                        try { bindEvents(); } catch (e) {}
                    }
                }).fail(function() {
                    $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Network error.</p></div>');
                }).always(function(data, textStatus, errorThrown) {
                    if (progressIntervalId) {
                        try { clearInterval(progressIntervalId); } catch(e) {}
                        progressIntervalId = null;
                    }

                    if (cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                        var isSuccess = (textStatus === 'success');
                        if (isSuccess && data && typeof data === 'object' && data.success === false) isSuccess = false;

                        var statusMsg = isSuccess ? 'Completed' : 'Failed';
                        
                        var onCompletionCallback = null;
                        if (cmd === 'restore' && isSuccess) {
                            onCompletionCallback = function() {
                                try {
                                    $('#marrison-notices').append('<div class="notice notice-info is-dismissible"><p>Restore completed. Starting auto-sync...</p></div>');
                                    
                                    var newSyncBtn = $('.mmu-main-row input[name="client_url"][value="' + clientUrl.replace(/"/g, '\\"') + '"]')
                                        .closest('form')
                                        .find('.marrison-action-btn[value="sync"]');

                                    if (newSyncBtn.length) {
                                        performClientAction(newSyncBtn);
                                    } else {
                                        console.log('Sync button not found after restore');
                                    }
                                } catch(e) { 
                                    console.error('Auto-sync error:', e); 
                                }
                            };
                        }
                        
                        window.marrisonUpdateProgress(100, 100, cmd + ' su ' + clientName + ': ' + statusMsg, onCompletionCallback);

                    } else {
                        window.marrisonUpdateProgress(0, 0);
                    }

                    try {
                        if (window.marrisonProgressInterval) {
                            clearInterval(window.marrisonProgressInterval);
                            window.marrisonProgressInterval = null;
                        }
                    } catch(e) { console.error('Progress interval clear failed', e); }

                    try {
                        if (actionButtons && actionButtons.length) actionButtons.prop('disabled', false);
                    } catch(e) { console.error('Button enable failed', e); }
                    try {
                        if (!window.isBulkRunning && allActionButtons && allActionButtons.length) allActionButtons.prop('disabled', false);
                    } catch(e) { console.error('Button enable failed', e); }

                    try {
                        if (btn && btn.length) btn.text(originalButtonText);
                    } catch(e) { console.error('Button text reset failed', e); }
                });
            }
            window.performClientAction = performClientAction;

            function bindEvents() {
                var $ = jQuery;
                $('.mmu-main-row').off('click').on('click', function(e) {
                    if ($(e.target).closest('a, button, input').length) return;
                    if ($(e.target).closest('td').is(':last-child')) return;
                    var key = $(this).data('key');
                    $('#details-' + key).toggle();
                });
                
                $('.marrison-action-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    console.log('Button clicked:', $(this).val(), 'isBulkRunning:', window.isBulkRunning); // Debug log
                    if (window.isBulkRunning) return; 
                    performClientAction(this);
                });
            }

            jQuery(function($) {
                bindEvents();
                
                $('#marrison-bulk-sync').on('click', function(e) {
                    e.preventDefault();
                    if (window.isBulkRunning) return;

                    var clients = [];
                    $('#marrison-clients-body .marrison-action-btn[value="sync"]').each(function() {
                        var clientUrl = $(this).closest('form').find('input[name="client_url"]').val();
                        if (clientUrl && clients.indexOf(clientUrl) === -1) {
                            clients.push(clientUrl);
                        }
                    });

                    if (clients.length === 0) {
                        alert('No clients available for synchronization.');
                        return;
                    }

                    if (!confirm('Start sync on all ' + clients.length + ' clients?')) return;

                    window.isBulkRunning = true;
                    var bulkSyncBtn = $(this);
                    var originalText = bulkSyncBtn.text();
                    bulkSyncBtn.prop('disabled', true);
                    $('.marrison-action-btn').prop('disabled', true);
                    $('#marrison-notices').empty();

                    var total = clients.length;
                    var current = 0;
                    var successCount = 0;
                    var errorCount = 0;
                    
                    updateProgress(0, total, 'Starting mass sync...');
                    
                    function syncNext() {
                        if (current >= total) {
                            $.post(ajaxurl, {
                                action: 'marrison_client_action',
                                cmd: 'noop',
                                nonce: marrison_vars.nonce
                            }, function(response) {
                                if (response.success && response.data.html) {
                                    $('#marrison-clients-body').html(response.data.html);
                                    bindEvents();
                                }
                                $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>Mass sync completed. Success: ' + successCount + ', Errors: ' + errorCount + '</p></div>');
                                updateProgress(total, total, 'Completed!');
                            }).always(function() {
                                window.isBulkRunning = false;
                                bulkSyncBtn.prop('disabled', false).text(originalText);
                            });
                            return;
                        }

                        var clientUrl = clients[current];
                        updateProgress(current, total, 'Sync in progress: ' + (current + 1) + '/' + total);
                        
                        $.post(ajaxurl, {
                            action: 'marrison_client_action',
                            cmd: 'sync',
                            client_url: clientUrl,
                            bulk_mode: 'true',
                            nonce: marrison_vars.nonce
                        }, function(response) {
                            if (response.success) successCount++;
                            else errorCount++;
                        }).fail(function() {
                            errorCount++;
                        }).always(function() {
                            current++;
                            syncNext();
                        });
                    }
                    
                    syncNext();
                });

                $('#marrison-bulk-update').on('click', function(e) {
                    e.preventDefault();
                    if (window.isBulkRunning) return;

                    var clients = [];
                    // Only select enabled update buttons (sites that need updates)
                    $('#marrison-clients-body .marrison-action-btn[value="update"]:not(:disabled)').each(function() {
                        var clientUrl = $(this).closest('form').find('input[name="client_url"]').val();
                        if (clientUrl && clients.indexOf(clientUrl) === -1) {
                            clients.push(clientUrl);
                        }
                    });

                    if (clients.length === 0) {
                        alert('No clients available for update (or all are already updated).');
                        return;
                    }

                    if (!confirm('Start update on ' + clients.length + ' clients?')) return;

                    window.isBulkRunning = true;
                    var bulkUpdateBtn = $(this);
                    var originalText = bulkUpdateBtn.text();
                    bulkUpdateBtn.prop('disabled', true);
                    $('.marrison-action-btn').prop('disabled', true);
                    $('#marrison-notices').empty();

                    var total = clients.length;
                    var current = 0;
                    var successCount = 0;
                    var errorCount = 0;
                    
                    updateProgress(0, total, 'Starting mass update...');
                    
                    function updateNext() {
                        if (current >= total) {
                            $.post(ajaxurl, {
                                action: 'marrison_client_action',
                                cmd: 'noop',
                                nonce: marrison_vars.nonce
                            }, function(response) {
                                if (response.success && response.data.html) {
                                    $('#marrison-clients-body').html(response.data.html);
                                    bindEvents();
                                }
                                $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>Mass update completed. Success: ' + successCount + ', Errors: ' + errorCount + '</p></div>');
                                updateProgress(total, total, 'Completed!');
                            }).always(function() {
                                window.isBulkRunning = false;
                                bulkUpdateBtn.prop('disabled', false).text(originalText);
                            });
                            return;
                        }

                        var clientUrl = clients[current];
                        updateProgress(current, total, 'Update in progress: ' + (current + 1) + '/' + total);
                        
                        $.post(ajaxurl, {
                            action: 'marrison_client_action',
                            cmd: 'update',
                            client_url: clientUrl,
                            bulk_mode: 'true',
                            nonce: marrison_vars.nonce
                        }, function(response) {
                            if (response.success) successCount++;
                            else errorCount++;
                        }).fail(function() {
                            errorCount++;
                        }).always(function() {
                            current++;
                            updateNext();
                        });
                    }
                    
                    updateNext();
                });
                
                $('#marrison-clear-cache').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    btn.prop('disabled', true).text('Cleaning...');
                    
                    $.post(ajaxurl, {
                        action: 'marrison_master_clear_cache',
                        nonce: marrison_vars.nonce
                    }, function(response) {
                        if (response.success) {
                            $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                            if (response.data.html) {
                                $('#marrison-clients-body').html(response.data.html);
                                bindEvents();
                            }
                        } else {
                            $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Cache clearing error</p></div>');
                        }
                    }).always(function() {
                        btn.prop('disabled', false).text('Clear Master Cache');
                    });
                });
            });
        </script>
        <?php
    }
}

new Marrison_Master_Admin();
