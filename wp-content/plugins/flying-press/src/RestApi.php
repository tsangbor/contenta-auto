<?php

namespace FlyingPress;

class RestApi
{
  public static function init()
  {
    add_action('rest_api_init', [__CLASS__, 'register_rest_apis']);
  }

  public static function register_rest_apis()
  {
    // Only allow access to the REST API for users with the specified roles
    if (!Auth::is_allowed()) {
      return;
    }

    register_rest_route('flying-press', '/cache_status/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'get_cache_status'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/config/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'update_config'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-current-page/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_current_page'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/preload-cache/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'preload_cache'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-pages-and-preload/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_pages_and_preload'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-everything/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_everything'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/activate-license/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'activate_license'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function get_cache_status()
  {
    return [
      'pages_cached' => Caching::count_pages(FLYING_PRESS_CACHE_DIR),
      'pages_in_queue' => Preload::$worker->get_remaining_tasks_count(),
    ];
  }

  public static function update_config($request)
  {
    $config = $request->get_json_params();

    if (empty($config)) {
      return new \WP_Error('flying-press/invalid-config', 'Invalid config');
    }

    Config::update_config($config);
    return Config::$config;
  }

  public static function preload_cache()
  {
    Preload::preload_cache();
    return ['success' => true];
  }

  public static function purge_current_page($request)
  {
    function_exists('fastcgi_finish_request') && fastcgi_finish_request();
    $url = $request->get_param('url');

    if (empty($url)) {
      return new \WP_Error('flying-press/invalid-url', 'Invalid URL');
    }

    Purge::purge_urls([$url]);
    Preload::preload_urls([$url]);
    return ['success' => true];
  }

  public static function purge_pages_and_preload()
  {
    Purge::purge_pages();
    Preload::preload_cache();
    return ['success' => true];
  }

  public static function purge_everything()
  {
    Purge::purge_everything();
    return ['success' => true];
  }

  public static function activate_license($request)
  {
    $license_key = $request->get_param('license_key');

    if (empty($license_key)) {
      return new \WP_Error('flying-press/invalid-license-key', 'Invalid License Key');
    }

    try {
      License::activate_license($license_key);
      return Config::$config;
    } catch (\Exception $e) {
      return new \WP_Error('flying-press/invalid-license-key', $e->getMessage());
    }
  }
}
