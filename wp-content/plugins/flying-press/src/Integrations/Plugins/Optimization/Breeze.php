<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: Breeze

class Breeze
{
  public static function init()
  {
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_settings']);
  }

  public static function disable_conflicting_settings()
  {
    if (!class_exists('Breeze_Configuration') && !defined('BREEZE_VERSION')) {
      return;
    }

    // Options

    $settings = [
      // Varnish Cache settings
      'varnish_cache' => [
        'auto-purge-varnish' => '0',
        'breeze-varnish-server-ip' => '127.0.0.1',
      ],

      // Basic Settings
      'basic_settings' => [
        'breeze-active' => '0',
        'breeze-cross-origin' => '0',
        'breeze-disable-admin' => [],
        'breeze-gzip-compression' => '0',
        'breeze-browser-cache' => '0',
        'breeze-lazy-load' => '0',
        'breeze-lazy-load-native' => '0',
        'breeze-lazy-load-iframes' => '0',
        'breeze-desktop-cache' => '0',
        'breeze-mobile-cache' => '0',
        'breeze-display-clean' => '0',
        'breeze-ttl' => 3600, // load from default
      ],

      // File Settings
      'file_settings' => [
        'breeze-minify-html' => '0',
        'breeze-minify-css' => '0',
        'breeze-font-display-swap' => '0',
        'breeze-group-css' => '0',
        'breeze-exclude-css' => [],
        'breeze-include-inline-css' => '0',
        'breeze-minify-js' => '0',
        'breeze-group-js' => '0',
        'breeze-include-inline-js' => '0',
        'breeze-exclude-js' => [],
        'breeze-move-to-footer-js' => [],
        'breeze-defer-js' => [],
        'breeze-enable-js-delay' => '0',
        'breeze-delay-js-scripts' => [],
        'no-breeze-no-delay-js' => [],
        'breeze-delay-all-js' => '0',
      ],

      // Preload Settings
      'preload_settings' => [
        'breeze-preload-fonts' => [],
        'breeze-preload-links' => '0',
        'breeze-prefetch-urls' => [],
      ],

      // Advanced Settings
      'advanced_settings' => [
        'breeze-exclude-urls' => [],
        'cached-query-strings' => [],
        'breeze-wp-emoji' => '0',
        'breeze-store-googlefonts-locally' => '0',
        'breeze-store-googleanalytics-locally' => '0',
        'breeze-store-facebookpixel-locally' => '0',
        'breeze-store-gravatars-locally' => '0',
      ],

      // Heartbeat settings
      'heartbeat_settings' => [
        'breeze-control-heartbeat' => '0',
        'breeze-heartbeat-front' => '',
        'breeze-heartbeat-postedit' => '',
        'breeze-heartbeat-backend' => '',
      ],

      // CDN Integration
      'cdn_integration' => [
        'cdn-active' => '0',
        'cdn-url' => '',
        'cdn-content' => [],
        'cdn-exclude-content' => [],
        'cdn-relative-path' => '0',
      ],
    ];

    foreach ($settings as $setting => $value) {
      \breeze_update_option($setting, $value);
    }
  }
}
