<?php
    // Prevent direct access
    if (!defined('ABSPATH')) {
        exit;
    }
    
    require 'plugin-update-checker/plugin-update-checker.php';
    use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
    
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/acaryca/varnish-cache-wordpress/',
        VARNISHCACHE_PLUGIN_FILE,
        'varnishcache'
    );
    
    //Set the branch that contains the stable release.
    $myUpdateChecker->setBranch('master');