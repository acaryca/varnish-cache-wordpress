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
        
        // Handle cache purge requests
        $this->handle_cache_purge();
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
        
        // Handle form submission
        if (isset($_POST['varnishcache_save'])) {
            $this->save_settings();
        }
        
        $settings = $this->get_cache_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p><?php echo __('This page allows you to configure Varnish Cache settings.', 'varnishcache'); ?></p>
            
            <div class="notice notice-info">
                <p>
                    <a href="<?php echo esc_url(add_query_arg('varnishcache', 'purge-entire-cache', admin_url())); ?>" class="button">
                        <?php _e('Purge Entire Cache', 'varnishcache'); ?>
                    </a>
                    <?php _e('Use this button to purge the entire Varnish Cache.', 'varnishcache'); ?>
                </p>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="varnishcache_save" value="1" />
                
                <div class="card">
                    <h2><?php echo __('Settings', 'varnishcache'); ?></h2>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo __('Enable Varnish Cache', 'varnishcache'); ?></th>
                            <td>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled'], true); ?> />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Varnish Server', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="server" value="<?php echo esc_attr($settings['server']); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Cache Lifetime', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="cache_lifetime" value="<?php echo esc_attr($settings['cacheLifetime']); ?>" class="regular-text" required />
                                <p class="description"><?php echo __('Cache Lifetime in seconds.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Cache Tag Prefix', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="cache_tag_prefix" value="<?php echo esc_attr($settings['cacheTagPrefix']); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Excluded Params', 'varnishcache'); ?></th>
                            <td>
                                <input type="text" name="excluded_params" value="<?php echo esc_attr(implode(',', $settings['excludedParams'])); ?>" class="regular-text" />
                                <p class="description"><?php echo __('List of GET parameters to disable caching. Separate with commas.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Excludes', 'varnishcache'); ?></th>
                            <td>
                                <textarea name="excludes" rows="6" class="large-text"><?php echo esc_textarea(implode(PHP_EOL, $settings['excludes'])); ?></textarea>
                                <p class="description"><?php echo __('URLs that Varnish Cache shouldn\'t cache. One URL per line.', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Development Mode', 'varnishcache'); ?></th>
                            <td>
                                <input type="checkbox" name="cache_devmode" value="1" <?php checked($settings['cache_devmode'], true); ?> />
                                <p class="description"><?php echo __('Enable development mode (disables cache).', 'varnishcache'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get cache settings from the settings file
     * 
     * @return array The cache settings
     */
    public function get_cache_settings() {
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
     * Write settings to the JSON file
     * 
     * @param array $settings The settings to write
     * @return bool Whether the write operation was successful
     */
    public function write_cache_settings(array $settings) {
        $settings_dir = sprintf('%s/.varnish-cache', rtrim(getenv('HOME'), '/'));
        $settings_file = $settings_dir . '/settings.json';
        
        // Create directory if it doesn't exist
        if (!file_exists($settings_dir)) {
            if (!mkdir($settings_dir, 0755, true)) {
                add_settings_error(
                    'varnishcache_settings',
                    'varnishcache_write_error',
                    __('Failed to create settings directory. Please check file permissions.', 'varnishcache'),
                    'error'
                );
                return false;
            }
        }
        
        $settings_json = json_encode($settings, JSON_PRETTY_PRINT);
        $result = file_put_contents($settings_file, $settings_json);
        
        if ($result === false) {
            add_settings_error(
                'varnishcache_settings',
                'varnishcache_write_error',
                __('Failed to write settings file. Please check file permissions.', 'varnishcache'),
                'error'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Save form settings
     */
    public function save_settings() {
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
            'excludes' => array_map('trim', explode(PHP_EOL, sanitize_textarea_field($_POST['excludes'])))
        ];
        
        if ($this->write_cache_settings($new_settings)) {
            add_settings_error(
                'varnishcache_settings',
                'varnishcache_settings_saved',
                __('Settings saved successfully.', 'varnishcache'),
                'success'
            );
        }
    }
    
    /**
     * Get the Varnish server from settings
     * 
     * @return string The server address
     */
    public function get_server() {
        $settings = $this->get_cache_settings();
        return $settings['server'];
    }
    
    /**
     * Get the cache tag prefix from settings
     * 
     * @return string The cache tag prefix
     */
    public function get_tag_prefix() {
        $settings = $this->get_cache_settings();
        return $settings['cacheTagPrefix'];
    }
    
    /**
     * Purge cache for a specific host
     * 
     * @param string $host The host to purge cache for
     */
    public function purge_host($host) {
        $headers = [
            'Host' => $host
        ];
        $request_url = $this->get_server();

        $this->purge_cache($headers, $request_url);
    }
    
    /**
     * Send a PURGE request to the Varnish server
     * 
     * @param array $headers Headers to send with the request
     * @param string|null $request_url The server URL to send the request to (optional)
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
            add_settings_error(
                'varnishcache_settings',
                'varnishcache_purge_failed',
                sprintf(__('Varnish Cache Purge Failed: %s', 'varnishcache'), $error_message),
                'error'
            );
            return false;
        }
    }
    
    /**
     * Handle cache purge requests
     */
    public function handle_cache_purge() {
        if (!isset($_GET['varnishcache']) || 'purge-entire-cache' != sanitize_text_field($_GET['varnishcache'])) {
            return;
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you do not have permission to access this page.', 'varnishcache'));
        }
        
        $host = (isset($_SERVER['HTTP_HOST']) && !empty(sanitize_text_field($_SERVER['HTTP_HOST']))) 
            ? sanitize_text_field($_SERVER['HTTP_HOST']) 
            : '';
            
        if (empty($host)) {
            add_settings_error(
                'varnishcache_settings',
                'varnishcache_purge_failed',
                __('Failed to determine current host.', 'varnishcache'),
                'error'
            );
            return;
        }
        
        $this->purge_host($host);
        
        add_settings_error(
            'varnishcache_settings',
            'varnishcache_purge_success',
            __('Cache has been purged successfully.', 'varnishcache'),
            'success'
        );
    }
}

/**
 * Initialize admin functionality
 */
function varnishcache_admin_init() {
    global $varnishcache_admin;
    $varnishcache_admin = new VarnishCache_Admin();
}

// Initialize admin earlier to ensure menus are added correctly
add_action('plugins_loaded', 'varnishcache_admin_init'); 