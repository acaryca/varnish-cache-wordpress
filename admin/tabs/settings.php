<?php
/**
 * Settings tab for Varnish Cache admin
 * 
 * @package Varnish Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
global $varnishcache_admin;
$settings = $varnishcache_admin->get_cache_settings();
?>

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