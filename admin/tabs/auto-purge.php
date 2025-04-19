<?php
/**
 * Auto Purge tab for Varnish Cache admin
 * 
 * @package Varnish Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>

<form method="post" action="">
    <input type="hidden" name="varnishcache_auto_purge_save" value="1" />
    <?php wp_nonce_field('varnishcache_auto_purge_settings', 'varnishcache_auto_purge_nonce'); ?>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e('Enable Auto Purge', 'varnishcache'); ?></th>
            <td>
                <input type="checkbox" name="auto_purge_enabled" value="1" <?php checked(get_option('varnishcache_auto_purge_enabled', false), true); ?> />
                <p class="description"><?php _e('Enable automatic cache purging.', 'varnishcache'); ?></p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Purge Frequency', 'varnishcache'); ?></th>
            <td>
                <select name="auto_purge_frequency">
                    <option value="thirtyminutes" <?php selected(get_option('varnishcache_auto_purge_frequency', 'daily'), 'thirtyminutes'); ?>><?php _e('Every 30 Minutes', 'varnishcache'); ?></option>
                    <option value="hourly" <?php selected(get_option('varnishcache_auto_purge_frequency', 'daily'), 'hourly'); ?>><?php _e('Hourly', 'varnishcache'); ?></option>
                    <option value="twicedaily" <?php selected(get_option('varnishcache_auto_purge_frequency', 'daily'), 'twicedaily'); ?>><?php _e('Twice Daily', 'varnishcache'); ?></option>
                    <option value="daily" <?php selected(get_option('varnishcache_auto_purge_frequency', 'daily'), 'daily'); ?>><?php _e('Daily', 'varnishcache'); ?></option>
                    <option value="weekly" <?php selected(get_option('varnishcache_auto_purge_frequency', 'daily'), 'weekly'); ?>><?php _e('Weekly', 'varnishcache'); ?></option>
                </select>
                <p class="description"><?php _e('How often to automatically purge the cache.', 'varnishcache'); ?></p>
            </td>
        </tr>
    </table>
    
    <?php submit_button(__('Save Auto Purge Settings', 'varnishcache')); ?>
</form> 