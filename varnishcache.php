<?php
/**
 * Plugin Name: Varnish Cache
 * Description: Manage the cache settings and perform purge operations
 * Version: 1.0.0
 * Author: ACARY
 * Author URI: https://acary.ca
 * Text Domain: varnishcache
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('VARNISHCACHE_PLUGIN_FILE', __FILE__);

// Translations
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain('varnishcache', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Include Updater if not already included
require_once plugin_dir_path(__FILE__) . '/includes/plugin-update-checker.php';

// Include admin functionality
require_once plugin_dir_path(__FILE__) . '/admin/admin.php';

/**
 * Add custom cron schedules
 *
 * @param array $schedules Existing schedules
 * @return array Modified schedules
 */
function varnishcache_add_cron_schedules($schedules) {
    // Add a '30 minutes' schedule to the existing ones
    $schedules['thirtyminutes'] = array(
        'interval' => 1800, // 30 minutes in seconds
        'display'  => __('Every 30 Minutes', 'varnishcache')
    );
    return $schedules;
}
add_filter('cron_schedules', 'varnishcache_add_cron_schedules');

/**
 * Class to handle service functionality like automatic cache purging
 */
class VarnishCache_Service {
    /**
     * Initialize the service
     */
    public function __construct() {
        // Purge cache when a post is saved/updated
        add_action('save_post', [$this, 'purge_on_post_save'], 10, 3);
        
        // Purge cache when a post is deleted
        add_action('delete_post', [$this, 'purge_on_post_delete'], 10);
        
        // Purge cache when a comment is added/updated/deleted
        add_action('comment_post', [$this, 'purge_on_comment_change'], 10);
        add_action('edit_comment', [$this, 'purge_on_comment_change'], 10);
        add_action('delete_comment', [$this, 'purge_on_comment_change'], 10);
        
        // Purge cache when a term is updated
        add_action('edit_terms', [$this, 'purge_on_term_change'], 10);
        add_action('create_term', [$this, 'purge_on_term_change'], 10);
        add_action('delete_term', [$this, 'purge_on_term_change'], 10);
        
        // Register cron hook for auto purge
        add_action('varnishcache_auto_purge_hook', [$this, 'run_auto_purge']);
    }
    
    /**
     * Purge cache when a post is saved
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public function purge_on_post_save($post_id, $post, $update) {
        // Skip if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Skip if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if this is not a public post type
        $post_type = get_post_type($post_id);
        if (!get_post_type_object($post_type)->public) {
            return;
        }
        
        // Purge the cache
        $this->purge_site_cache();
    }
    
    /**
     * Purge cache when a post is deleted
     * 
     * @param int $post_id The post ID
     */
    public function purge_on_post_delete($post_id) {
        // Skip if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Purge the cache
        $this->purge_site_cache();
    }
    
    /**
     * Purge cache when a comment is changed
     * 
     * @param int $comment_id The comment ID
     */
    public function purge_on_comment_change($comment_id) {
        $this->purge_site_cache();
    }
    
    /**
     * Purge cache when a term is changed
     * 
     * @param int $term_id The term ID
     */
    public function purge_on_term_change($term_id) {
        $this->purge_site_cache();
    }
    
    /**
     * Purge the entire site cache
     */
    private function purge_site_cache() {
        global $varnishcache_admin;
        
        // Check if admin is initialized
        if (!$varnishcache_admin) {
            return;
        }
        
        // Get the settings
        $settings = $varnishcache_admin->get_cache_settings();
        
        // Check if cache is enabled
        if (empty($settings['enabled']) || $settings['cache_devmode']) {
            return;
        }
        
        // Get the host
        $host = (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) 
            ? sanitize_text_field($_SERVER['HTTP_HOST']) 
            : '';
            
        if (empty($host)) {
            return;
        }
        
        // Purge the cache
        $varnishcache_admin->purge_host($host);
    }
    
    /**
     * Run the automatic cache purge
     */
    public function run_auto_purge() {
        // Check if auto purge is enabled
        if (!get_option('varnishcache_auto_purge_enabled', false)) {
            return;
        }
        
        // Get the host
        $host = (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) 
            ? sanitize_text_field($_SERVER['HTTP_HOST']) 
            : parse_url(get_site_url(), PHP_URL_HOST);
            
        if (empty($host)) {
            return;
        }
        
        // Purge the cache
        $this->purge_site_cache();
        
        // Log the auto purge event
        error_log(sprintf('Varnish Cache auto purge executed at %s', date('Y-m-d H:i:s')));
    }
}

/**
 * Initialize service functionality
 */
function varnishcache_service_init() {
    global $varnishcache_service;
    $varnishcache_service = new VarnishCache_Service();
}
// Initialize service functionality when plugins are loaded
add_action('plugins_loaded', 'varnishcache_service_init');

/**
 * Activation and deactivation functions for the plugin
 */
function varnishcache_activate() {
    // Schedule auto purge if enabled
    if (get_option('varnishcache_auto_purge_enabled', false)) {
        $frequency = get_option('varnishcache_auto_purge_frequency', 'daily');
        
        // Clear any existing scheduled hook
        wp_clear_scheduled_hook('varnishcache_auto_purge_hook');
        
        // Schedule new hook with selected frequency
        if (!wp_next_scheduled('varnishcache_auto_purge_hook')) {
            wp_schedule_event(time(), $frequency, 'varnishcache_auto_purge_hook');
        }
    }
}
register_activation_hook(__FILE__, 'varnishcache_activate');

function varnishcache_deactivate() {
    // Clear scheduled hook on plugin deactivation
    wp_clear_scheduled_hook('varnishcache_auto_purge_hook');
}
register_deactivation_hook(__FILE__, 'varnishcache_deactivate');
