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
        
        // Handle cache purge requests
        add_action('admin_init', [$this, 'handle_cache_purge']);
        
        // Handle settings save
        add_action('admin_init', [$this, 'handle_settings_save']);
        
        // Add admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
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
        
        // Get current settings
        $settings = $this->get_cache_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=varnishcache-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'varnishcache'); ?>
                </a>
                <a href="?page=varnishcache-settings&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tools', 'varnishcache'); ?>
                </a>
            </h2>
            
            <?php if ($active_tab === 'settings') : ?>
                <form method="post" action="">
                    <input type="hidden" name="varnishcache_save" value="1" />
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Enable Varnish Cache', 'varnishcache'); ?></th>
                            <td>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled'], true); ?> />
                                <p class="description"><?php _e('Enable or disable Varnish Cache integration.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Varnish Server', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="server" value="<?php echo esc_attr($settings['server']); ?>" class="regular-text" required />
                                <p class="description"><?php _e('Varnish server address (e.g. localhost:6081).', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Cache Lifetime', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="cache_lifetime" value="<?php echo esc_attr($settings['cacheLifetime']); ?>" class="regular-text" required />
                                <p class="description"><?php _e('Cache lifetime in seconds.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Cache Tag Prefix', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="cache_tag_prefix" value="<?php echo esc_attr($settings['cacheTagPrefix']); ?>" class="regular-text" />
                                <p class="description"><?php _e('Prefix for cache tags.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Excluded Params', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="excluded_params" value="<?php echo esc_attr(implode(',', $settings['excludedParams'])); ?>" class="regular-text" />
                                <p class="description"><?php _e('List of GET parameters to disable caching. Separate with commas.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Excludes', 'varnishcache'); ?></th>
                            <td>
                                <textarea name="excludes" rows="6" class="large-text"><?php echo esc_textarea(implode("\n", $settings['excludes'])); ?></textarea>
                                <p class="description"><?php _e('URLs that Varnish Cache shouldn\'t cache. One URL per line.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Development Mode', 'varnishcache'); ?></th>
                            <td>
                                <input type="checkbox" name="cache_devmode" value="1" <?php checked($settings['cache_devmode'], true); ?> />
                                <p class="description"><?php _e('Enable development mode (disables cache).', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            <?php elseif ($active_tab === 'tools') : ?>
                <div class="card">
                    <h2><?php _e('Cache Management', 'varnishcache'); ?></h2>
                    <p><?php _e('Use these tools to manage your Varnish Cache.', 'varnishcache'); ?></p>
                    
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('varnishcache', 'purge-entire-cache'), 'purge-entire-cache')); ?>" class="button button-primary">
                            <?php _e('Purge Entire Cache', 'varnishcache'); ?>
                        </a>
                        <span class="description"><?php _e('Purges all cached content from Varnish.', 'varnishcache'); ?></span>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle settings form submission
     */
    public function handle_settings_save() {
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
        
        $this->write_cache_settings($new_settings);
        
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
     * Handle cache purge requests
     */
    public function handle_cache_purge() {
        // Check if we have a purge request
        if (!isset($_GET['varnishcache']) || 'purge-entire-cache' != sanitize_text_field($_GET['varnishcache'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'purge-entire-cache')) {
            wp_die(__('Security check failed.', 'varnishcache'));
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you do not have permission to access this page.', 'varnishcache'));
        }
        
        $host = (isset($_SERVER['HTTP_HOST']) && !empty(sanitize_text_field($_SERVER['HTTP_HOST']))) 
            ? sanitize_text_field($_SERVER['HTTP_HOST']) 
            : '';
            
        if (empty($host)) {
            wp_die(__('Failed to determine current host.', 'varnishcache'));
        }
        
        $result = $this->purge_host($host);
        
        // Store message in transient
        set_transient('varnishcache_admin_notices', [
            [
                'type' => $result ? 'success' : 'error',
                'message' => $result 
                    ? __('Cache has been purged successfully.', 'varnishcache')
                    : __('Failed to purge cache. Please check your settings.', 'varnishcache')
            ]
        ], 30);
        
        // Check if the request is coming from the admin bar or elsewhere
        $referer = wp_get_referer();
        
        if ($referer) {
            // Extract tab from referer if it exists
            if (strpos($referer, 'page=varnishcache-settings') !== false) {
                // This is from our settings page, preserve the current tab
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
                if (empty($tab)) {
                    // Try to extract tab from referer
                    $tab_pos = strpos($referer, 'tab=');
                    if ($tab_pos !== false) {
                        $tab_part = substr($referer, $tab_pos + 4);
                        $tab_end = strpos($tab_part, '&');
                        $tab = $tab_end !== false ? substr($tab_part, 0, $tab_end) : $tab_part;
                    } else {
                        $tab = 'settings'; // Default tab
                    }
                }
                wp_redirect(admin_url('options-general.php?page=varnishcache-settings&tab=' . $tab));
            } else {
                // Not from settings page, redirect back to referer
                wp_safe_redirect($referer);
            }
        } else {
            // No referer, default to settings page
            wp_redirect(admin_url('options-general.php?page=varnishcache-settings'));
        }
        exit;
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