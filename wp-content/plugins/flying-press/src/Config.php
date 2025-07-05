<?php

namespace FlyingPress;

class Config
{
  // Variable to store the configuration
  public static $config;

  // Default configuration
  protected static $initial_config = [
    // License
    'license_key' => '',
    'license_active' => false,
    'license_status' => '',

    // CSS & JavaScript Optimization
    'css_js_minify' => true,
    'css_rucss' => true,
    'css_rucss_include_selectors' => [],
    'js_delay' => true,
    'js_delay_method' => 'background',
    'js_delay_excludes' => [],
    'js_delay_third_party' => true,
    'js_delay_third_party_excludes' => [],
    'css_js_self_host_third_party' => true,

    // Image, Video & iFrame Optimization
    'lazy_load' => true,
    'lazy_load_exclusions' => [],
    'properly_size_images' => true,
    'youtube_placeholder' => true,
    'self_host_gravatars' => true,

    // Fonts Optimization
    'fonts_preload' => true,
    'fonts_optimize_google' => true,
    'fonts_display_swap' => true,

    // Rendering Optimization
    'lazy_render' => true,
    'lazy_render_excludes' => [],

    // Basic Caching
    'cache_link_prefetch' => true,
    'cache_mobile' => false,
    'cache_logged_in' => false,
    'cache_refresh' => false,
    'cache_refresh_interval' => '2hours',

    // Advanced Caching
    'cache_bypass_urls' => [],
    'cache_include_queries' => [],
    'cache_bypass_cookies' => [],

    // CDN
    'cdn' => false,
    'cdn_type' => 'custom',
    'cdn_url' => '',
    'cdn_file_types' => 'all',
    'flying_cdn_api_key' => '',

    // Automatic Cleaning
    'db_auto_clean' => false,
    'db_auto_clean_interval' => 'daily',

    // Post Cleanup
    'db_post_revisions' => false,
    'db_post_auto_drafts' => false,
    'db_post_trashed' => false,

    // Comment Cleanup
    'db_comments_spam' => false,
    'db_comments_trashed' => false,

    // Table Optimization
    'db_transients_expired' => false,
    'db_optimize_tables' => false,

    // Remove Unnecessary Assets
    'bloat_disable_block_css' => false,
    'bloat_disable_dashicons' => false,
    'bloat_disable_emojis' => false,
    'bloat_disable_jquery_migrate' => false,

    // Disable Features
    'bloat_disable_xml_rpc' => false,
    'bloat_disable_rss_feed' => false,
    'bloat_disable_oembeds' => false,
    'bloat_disable_cron' => false,

    // Database & Activity
    'bloat_post_revisions_control' => false,
    'bloat_heartbeat_control' => false,
  ];

  public static function init()
  {
    // Get the saved configuration from the database
    self::$config = get_option('FLYING_PRESS_CONFIG', []);

    // If the saved version is different from the current version, run the upgrade action
    $saved_version = get_option('FLYING_PRESS_VERSION');
    $current_version = FLYING_PRESS_VERSION;

    if ($saved_version !== $current_version || empty(self::$config)) {
      Preload::$worker->destroy();
      update_option('FLYING_PRESS_VERSION', $current_version);
      self::migrate_config();
    }

    // Remove the configuration when the plugin is deleted
    register_uninstall_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'on_uninstall']);
  }

  public static function migrate_config()
  {
    // Remove keys that don't exist in the initial config
    self::$config = array_intersect_key(self::$config, self::$initial_config);

    // Add new fields from the default configuration if they don't exist in the saved configuration
    self::$config = array_merge(self::$initial_config, self::$config);

    update_option('FLYING_PRESS_CONFIG', self::$config);
    do_action('flying_press_update_config:after', self::$config);
    do_action('flying_press_upgraded');
  }

  // Function to update the configuration
  public static function update_config($new_config = [])
  {
    self::$config = array_merge(self::$config, $new_config);

    update_option('FLYING_PRESS_CONFIG', self::$config);
    do_action('flying_press_update_config:after', self::$config);
  }

  public static function on_uninstall()
  {
    delete_option('FLYING_PRESS_CONFIG');
    delete_option('FLYING_PRESS_VERSION');

    // Remove the tasks table
    Preload::$worker->destroy();
  }
}
