<?php

namespace FlyingPress;

class Compatibility
{
  public static $active_incompatible_plugins = [];

  public static function init()
  {
    add_action('admin_init', [__CLASS__, 'find_incompatible_plugins']);
    add_action('admin_notices', [__CLASS__, 'add_notice']);
  }

  public static function find_incompatible_plugins()
  {
    $incompatible_plugins = [
      [
        'name' => 'Autoptimize',
        'file' => 'autoptimize/autoptimize.php',
      ],
      [
        'name' => 'WP Rocket',
        'file' => 'wp-rocket/wp-rocket.php',
      ],
      [
        'name' => 'LiteSpeed Cache',
        'file' => 'litespeed-cache/litespeed-cache.php',
      ],
      [
        'name' => 'Swift Performance',
        'file' => 'swift-performance/performance.php',
      ],
      [
        'name' => 'Swift Performance Lite',
        'file' => 'swift-performance-lite/performance.php',
      ],
      [
        'name' => 'W3 Total Cache',
        'file' => 'w3-total-cache/w3-total-cache.php',
      ],
      [
        'name' => 'WP Fastest Cache',
        'file' => 'wp-fastest-cache/wpFastestCache.php',
      ],
      [
        'name' => 'WP Super Cache',
        'file' => 'wp-super-cache/wp-cache.php',
      ],
      [
        'name' => 'Hummingbird',
        'file' => 'hummingbird-performance/wp-hummingbird.php',
      ],
      [
        'name' => 'Cache Enabler',
        'file' => 'cache-enabler/cache-enabler.php',
      ],
      [
        'name' => 'Fast Velocity Minify',
        'file' => 'fast-velocity-minify/fvm.php',
      ],
      [
        'name' => 'WP Optimize',
        'file' => 'wp-optimize/wp-optimize.php',
      ],
      [
        'name' => 'instant.page',
        'file' => 'instant-page/instantpage.php',
      ],
      [
        'name' => 'WP Meteor',
        'file' => 'wp-meteor/wp-meteor.php',
      ],
    ];

    // Generate a list of incompatible active plugin
    foreach ($incompatible_plugins as $plugin) {
      if (\is_plugin_active($plugin['file'])) {
        array_push(self::$active_incompatible_plugins, $plugin['name']);
      }
    }
  }

  public static function add_notice()
  {
    // Return if there are no plugins
    if (!count(self::$active_incompatible_plugins)) {
      return;
    }

    // Add notice to remove incompatible plugins
    echo '<div class="notice notice-error is-dismissible"><p><b>FlyingPress</b> is incompatible with the following plugin(s):' .
      '<br/><ul>';

    foreach (self::$active_incompatible_plugins as $plugin) {
      echo '<li><b>• ' . $plugin . '</b></li>';
    }

    echo '</ul></p></div>';
  }
}
