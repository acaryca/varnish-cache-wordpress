<?php
/**
 * Tools tab for Varnish Cache admin
 * 
 * @package Varnish Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

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