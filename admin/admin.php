<?php
/**
 * Varnish Cache admin functionality
 * 
 * @package Varnish Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class that handles all admin functionality
 */
class VarnishCache_Admin {
    /**
     * Initialize the admin functionality
     */
    public function __construct() {
        // Add admin menus via admin_menu action
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add a link to the settings in the plugins list
        add_filter('plugin_action_links_' . plugin_basename(VARNISHCACHE_PLUGIN_FILE), [$this, 'add_settings_link']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Show admin notices
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Add admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);

        // Handle settings save
        add_action('admin_init', [$this, 'varnishcache_handle_settings_save']);

        // Handle auto purge settings save
        add_action('admin_init', [$this, 'handle_auto_purge_settings_save']);
    }
    
    /**
     * Add an admin menu for the plugin
     */
    public function add_admin_menu() {
        add_options_page(
            __('Varnish Cache', 'varnishcache'),
            __('Varnish Cache', 'varnishcache'), 
            'manage_options', 
            'varnishcache-settings', 
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Add items to the admin bar menu
     *
     * @param WP_Admin_Bar $admin_bar The WP_Admin_Bar instance
     */
    public function add_admin_bar_menu($admin_bar) {
        // N'afficher que dans l'interface d'administration
        if (!is_admin()) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $admin_bar_nodes = [
            [
                'id'     => 'varnishcache',
                'title'  => __('Cache', 'varnishcache'),
                'meta'   => ['class' => 'varnishcache'],
            ],
            [
                'parent' => 'varnishcache',
                'id'     => 'varnishcache-purge',
                'title'  => __('Purge All Cache', 'varnishcache'),
                'href'   => wp_nonce_url(add_query_arg('varnishcache', 'purge-entire-cache'), 'purge-entire-cache'),
                'meta'   => [
                    'title' => __('Purge All Cache', 'varnishcache'),
                ],
            ],
            [
                'parent' => 'varnishcache',
                'id'     => 'varnishcache-settings',
                'title'  => __('Settings', 'varnishcache'),
                'href'   => admin_url('options-general.php?page=varnishcache-settings'),
                'meta'   => ['tabindex' => '0'],
            ],
        ];
        
        foreach ($admin_bar_nodes as $node) {
            $admin_bar->add_node($node);
        }
    }
    
    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_varnishcache-settings') {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * Add a link to the settings in the plugins list
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=varnishcache-settings">' . __('Settings', 'varnishcache') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Display the plugin settings page
     */
    public function render_settings_page() {
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you do not have permission to access this page.', 'varnishcache'));
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=varnishcache-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'varnishcache'); ?>
                </a>
                <a href="?page=varnishcache-settings&tab=auto-purge" class="nav-tab <?php echo $active_tab === 'auto-purge' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Auto Purge', 'varnishcache'); ?>
                </a>
                <a href="?page=varnishcache-settings&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tools', 'varnishcache'); ?>
                </a>
            </h2>
            
            <?php 
            // Include the appropriate tab file based on the active tab
            switch ($active_tab) {
                case 'auto-purge':
                    require_once plugin_dir_path(__FILE__) . 'tabs/auto-purge.php';
                    break;
                case 'tools':
                    require_once plugin_dir_path(__FILE__) . 'tabs/tools.php';
                    break;
                case 'settings':
                default:
                    require_once plugin_dir_path(__FILE__) . 'tabs/settings.php';
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Handle settings form submission
     */
    public function varnishcache_handle_settings_save() {
        global $varnishcache_admin;
        
        if (!isset($_POST['varnishcache_save'])) {
            return;
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you do not have permission to access this page.', 'varnishcache'));
        }
        
        $new_settings = [
            'cache_devmode' => isset($_POST['cache_devmode']) && $_POST['cache_devmode'] === '1',
            'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1',
            'server' => sanitize_text_field($_POST['server']),
            'cacheLifetime' => sanitize_text_field($_POST['cache_lifetime']),
            'cacheTagPrefix' => sanitize_text_field($_POST['cache_tag_prefix']),
            'excludedParams' => array_map('trim', explode(',', sanitize_text_field($_POST['excluded_params']))),
            'excludes' => array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['excludes']))))
        ];
        
        $varnishcache_admin->write_cache_settings($new_settings);
        
        // Set success message
        set_transient('varnishcache_admin_notices', [
            [
                'type' => 'success',
                'message' => __('Settings saved successfully.', 'varnishcache')
            ]
        ], 30);
        
        // Redirect to prevent form resubmission
        wp_redirect(admin_url('options-general.php?page=varnishcache-settings'));
        exit;
    }


    /**
     * Handle auto purge settings form submission
     */
    public function handle_auto_purge_settings_save() {
        if (!isset($_POST['varnishcache_auto_purge_save'])) {
            return;
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you do not have permission to access this page.', 'varnishcache'));
        }
        
        // Verify nonce
        if (!isset($_POST['varnishcache_auto_purge_nonce']) || !wp_verify_nonce($_POST['varnishcache_auto_purge_nonce'], 'varnishcache_auto_purge_settings')) {
            wp_die(__('Security check failed.', 'varnishcache'));
        }
        
        // Get current settings for comparison
        $current_enabled = get_option('varnishcache_auto_purge_enabled', false);
        $current_frequency = get_option('varnishcache_auto_purge_frequency', 'daily');
        
        // Get new settings
        $new_enabled = isset($_POST['auto_purge_enabled']) && $_POST['auto_purge_enabled'] === '1';
        $new_frequency = sanitize_text_field($_POST['auto_purge_frequency']);
        
        // Update options
        update_option('varnishcache_auto_purge_enabled', $new_enabled);
        update_option('varnishcache_auto_purge_frequency', $new_frequency);
        
        // Handle cron job scheduling
        if ($new_enabled) {
            // Clear existing scheduled hook
            wp_clear_scheduled_hook('varnishcache_auto_purge_hook');
            
            // Schedule new hook with selected frequency
            if (!wp_next_scheduled('varnishcache_auto_purge_hook')) {
                wp_schedule_event(time(), $new_frequency, 'varnishcache_auto_purge_hook');
            }
        } else {
            // If auto purge is disabled, clear the scheduled hook
            wp_clear_scheduled_hook('varnishcache_auto_purge_hook');
        }
        
        // Set success message
        set_transient('varnishcache_admin_notices', [
            [
                'type' => 'success',
                'message' => __('Auto Purge settings saved successfully.', 'varnishcache')
            ]
        ], 30);
        
        // Redirect to prevent form resubmission
        wp_redirect(admin_url('options-general.php?page=varnishcache-settings&tab=auto-purge'));
        exit;
    }
    
    /**
     * Get cache settings from the settings file
     * 
     * @return array The cache settings
     */
    public function get_cache_settings_from_json() {
        $settings_file = sprintf('%s/.varnish-cache/settings.json', rtrim(getenv('HOME'), '/'));
        $default_settings = [
            'cache_devmode' => false,
            'enabled' => false,
            'server' => '',
            'cacheLifetime' => '3600',
            'cacheTagPrefix' => '',
            'excludedParams' => [],
            'excludes' => []
        ];

        if (file_exists($settings_file)) {
            $cache_settings = json_decode(file_get_contents($settings_file), true);
            
            // Merge with default settings and ensure values are of the correct type
            return array_merge($default_settings, $cache_settings ?: []);
        }
        
        return $default_settings;
    }
    
    /**
     * Get cache settings
     * 
     * @return array The cache settings
     */
    public function get_cache_settings() {
        return $this->get_cache_settings_from_json();
    }
    
    /**
     * Write cache settings to the settings file
     * 
     * @param array $settings The settings to write
     * @return bool True on success, false on failure
     */
    public function write_cache_settings(array $settings) {
        $settings_file = sprintf('%s/.varnish-cache/settings.json', rtrim(getenv('HOME'), '/'));
        
        // Create directory if it doesn't exist
        $settings_dir = dirname($settings_file);
        if (!file_exists($settings_dir)) {
            if (!mkdir($settings_dir, 0755, true)) {
                return false;
            }
        }
        
        $settings_json = json_encode($settings, JSON_PRETTY_PRINT);
        return (false !== file_put_contents($settings_file, $settings_json));
    }
    
    /**
     * Get the Varnish server from settings
     * 
     * @return string The server address
     */
    public function get_server() {
        $settings = $this->get_cache_settings();
        return $settings['server'] ?? '';
    }
    
    /**
     * Get the cache tag prefix from settings
     * 
     * @return string The cache tag prefix
     */
    public function get_tag_prefix() {
        $settings = $this->get_cache_settings();
        return $settings['cacheTagPrefix'] ?? '';
    }
    
    /**
     * Purge cache for a specific host
     * 
     * @param string $host The host to purge cache for
     * @return bool True if purge was successful, false otherwise
     */
    public function purge_host($host) {
        $headers = [
            'Host' => $host
        ];
        $request_url = $this->get_server();

        return $this->purge_cache($headers, $request_url);
    }
    
    /**
     * Send a PURGE request to the Varnish server
     * 
     * @param array $headers Headers to send with the request
     * @param string|null $request_url The server URL to send the request to (optional)
     * @return bool True if purge was successful, false otherwise
     */
    public function purge_cache(array $headers, $request_url = null) {
        try {
            if (true === is_null($request_url)) {
                $request_url = $this->get_server();
            }
            
            if (empty($request_url)) {
                throw new \Exception(__('No Varnish server configured.', 'varnishcache'));
            }
            
            $request_url = sprintf('http://%s', $request_url);
            $response = wp_remote_request(
                $request_url,
                [
                    'sslverify' => false,
                    'method'    => 'PURGE',
                    'headers'   => $headers,
                    'timeout'   => 10,
                ]
            );
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $http_status_code = wp_remote_retrieve_response_code($response);
            
            if (200 != $http_status_code) {
                throw new \Exception(sprintf('HTTP Status Code: %s', $http_status_code));
            }
            
            return true;
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            // Store error message in transient
            set_transient('varnishcache_admin_notices', [
                [
                    'type' => 'error',
                    'message' => sprintf(__('Varnish Cache Purge Failed: %s', 'varnishcache'), $error_message)
                ]
            ], 30);
            return false;
        }
    }
    
    /**
     * Show admin notices stored in transients
     */
    public function show_admin_notices() {
        // Check if there are stored notices
        $notices = get_transient('varnishcache_admin_notices');
        if ($notices) {
            foreach ($notices as $notice) {
                $class = ($notice['type'] === 'error') ? 'notice-error' : 'notice-success';
                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
            }
            // Clear the transient
            delete_transient('varnishcache_admin_notices');
        }
    }
}

/**
 * Initialize admin functionality
 */
function varnishcache_admin_init() {
    global $varnishcache_admin;
    $varnishcache_admin = new VarnishCache_Admin();
}
// Initialize admin functionality when plugins are loaded
add_action('plugins_loaded', 'varnishcache_admin_init'); 