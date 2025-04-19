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
add_action('admin_init', function() {
    if (file_exists(sprintf('%s/.varnish-cache/settings.json', rtrim(getenv('HOME'), '/')))) {
        require_once plugin_dir_path(__FILE__) . '/admin/admin.php';
    }
});


// add admin top menu
add_action('admin_bar_menu', function ($adminbar) {
    if( !current_user_can('manage_options') ) return;
    
    $admin_bar_nodes = [
        [
            'id'     => 'varnishcache',
            'title'  => __('Cache', 'varnishcache'),
            'meta'   => ['class' => 'varnishcache'],
        ],
        [
            'parent' => 'varnishcache',
            'id'     => 'varnishcache-purge',
            'title'  => __('Purge all', 'varnishcache'),
            'href'   => wp_nonce_url(add_query_arg('varnishcache', 'purge-entire-cache'), 'purge-entire-cache'),
            'meta'   => [
                'title' => __( 'Purge all', 'varnishcache' ),
            ],
        ],
        [
            'parent' => 'varnishcache',
            'id'     => 'varnishcache-settings',
            'title'  => __('Settings', 'varnishcache'),
            'href'   => admin_url('options-general.php?page=varnishcache-settings'),
            'meta'   => [ 'tabindex' => '0' ],
        ],
    ];
    foreach ($admin_bar_nodes as $node) {
        $adminbar->add_node($node);
    }
}, 100);

// Handle cache purge from admin bar
add_action('admin_init', function() {
    if (isset($_GET['varnishcache']) && 'purge-entire-cache' == sanitize_text_field($_GET['varnishcache'])) {
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'purge-entire-cache')) {
            wp_die(__('Security check failed.', 'varnishcache'));
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to purge the cache.', 'varnishcache'));
        }
        
        $host = (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) 
            ? sanitize_text_field($_SERVER['HTTP_HOST']) 
            : '';
            
        if (!empty($host)) {
            global $varnishcache_admin;
            if (isset($varnishcache_admin)) {
                $varnishcache_admin->purge_host($host);
                
                // Add admin notice
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' 
                        . __('Cache has been purged successfully.', 'varnishcache') 
                        . '</p></div>';
                });
            }
        }
    }
});


/**
 * Activation and deactivation functions for the plugin
 */
function varnishcache_activate() {
    // Nothing to do for now
}
register_activation_hook(__FILE__, 'varnishcache_activate');

function varnishcache_deactivate() {
    // Nothing to do for now
}
register_deactivation_hook(__FILE__, 'varnishcache_deactivate');
