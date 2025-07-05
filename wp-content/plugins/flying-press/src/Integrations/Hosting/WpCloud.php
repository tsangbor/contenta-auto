<?php

namespace FlyingPress\Integrations\Hosting;

use Edge_Cache_Atomic;

class WpCloud
{
  public static function init()
  {
    if (!class_exists('Atomic_Persistent_Data')) {
      return;
    }

    // Create and remove MU plugin on plugin activation and deactivation
    register_activation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'create_mu_plugin']);
    register_deactivation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'remove_mu_plugin']);

    // Purge Edge Cache
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_edge_cache']);
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_edge_cache']);
  }

  public static function create_mu_plugin()
  {
    // Ensure WPMU_PLUGIN_DIR exists
    if (!is_dir(WPMU_PLUGIN_DIR)) {
      mkdir(WPMU_PLUGIN_DIR, 0755, true);
    }

    // Write the MU plugin file
    file_put_contents(
      WPMU_PLUGIN_DIR . '/flying-press-advanced-cache-loader.php',
      "<?php
/*
Plugin Name: FlyingPress Advanced Cache Loader
Description: MU plugin to load FlyingPress advanced cache if it exists.
Author: FlyingPress
Version: 1.0
*/

// Cancel batcache to ensure no conflicts with our caching system
if (function_exists('batcache_cancel')) {
 batcache_cancel();
}

// Include FlyingPress custom advanced cache file if it exists
if (file_exists(WP_CONTENT_DIR . '/flying-press-advanced-cache.php')) {
    require_once WP_CONTENT_DIR . '/flying-press-advanced-cache.php';
}"
    );
  }

  public static function remove_mu_plugin()
  {
    // Path to the MU plugin file
    $mu_plugin_file = WPMU_PLUGIN_DIR . '/flying-press-advanced-cache-loader.php';

    // Remove the MU plugin file if it exists
    if (file_exists($mu_plugin_file)) {
      unlink($mu_plugin_file);
    }
  }

  public static function purge_edge_cache()
  {
    if (!class_exists('Edge_Cache_Atomic')) {
      return;
    }

    $edge_cache = new Edge_Cache_Atomic();

    if (!$edge_cache) {
      return;
    }

    $edge_cache->purge_domain($edge_cache->get_domain_name(), [
      'wp_actions' => 'purge_cache',
      'wp_action' => 'edge_cache_purge',
      'batcache' => 'yes',
    ]);
  }
}
