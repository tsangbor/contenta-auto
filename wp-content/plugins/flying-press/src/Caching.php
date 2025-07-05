<?php

namespace FlyingPress;
use FlyingPress\Utils;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Caching
{
  public static $default_ignore_queries = [
    'adgroupid',
    'adid',
    'age-verified',
    'ao_noptimize',
    'campaignid',
    'cn-reloaded',
    'dm_i',
    'ef_id',
    'epik',
    'fb_action_ids',
    'fb_action_types',
    'fb_source',
    'fbclid',
    'gad_source',
    'gbraid',
    'gclid',
    'gclsrc',
    'gdfms',
    'gdftrk',
    'gdffi',
    '_ga',
    '_gl',
    'kboard_id',
    'mkwid',
    'mc_cid',
    'mc_eid',
    'msclkid',
    'mtm_campaign',
    'mtm_cid',
    'mtm_content',
    'mtm_keyword',
    'mtm_medium',
    'mtm_source',
    'pcrid',
    'pk_campaign',
    'pk_cid',
    'pk_content',
    'pk_keyword',
    'pk_medium',
    'pk_source',
    'pp',
    'ref',
    'redirect_log_mongo_id',
    'redirect_mongo_id',
    'sb_referer_host',
    's_kwcid',
    'srsltid',
    'sscid',
    'trk_contact',
    'trk_msg',
    'trk_module',
    'trk_sid',
    'ttclid',
    'utm_campaign',
    'utm_content',
    'utm_expid',
    'utm_id',
    'utm_medium',
    'utm_source',
    'utm_term',
  ];

  // Default include query strings
  public static $default_include_queries = [
    'lang',
    'currency',
    'orderby',
    'max_price',
    'min_price',
    'rating_filter',
  ];

  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_cache_refresh']);
    add_action('flying_press_cache_refresh', [__CLASS__, 'refresh_cache']);
    add_action('set_logged_in_cookie', [__CLASS__, 'add_logged_in_roles'], 10, 4);
    add_action('clear_auth_cookie', [__CLASS__, 'remove_logged_in_roles']);
  }

  public static function refresh_cache()
  {
    Purge::purge_pages();
    Preload::preload_cache();
  }

  public static function setup_cache_refresh()
  {
    $lifespan = Config::$config['cache_refresh_interval'];
    $action_name = 'flying_press_cache_refresh';

    if (!Config::$config['cache_refresh']) {
      wp_clear_scheduled_hook($action_name);
      return;
    }

    if (!wp_next_scheduled($action_name) || wp_get_schedule($action_name) != $lifespan) {
      wp_clear_scheduled_hook($action_name);
      wp_schedule_event(time(), $lifespan, $action_name);
    }
  }

  public static function cache_page($html)
  {
    // Get the cache file name
    $cache_file_name = self::get_cache_file_name();

    // Get the cache file path
    $cache_file_path = self::get_cache_path();

    // Add footprint to the HTML
    $footprint =
      'Powered by FlyingPress for lightning-fast performance. Learn more: https://flyingpress.com. Cached at ' .
      time();
    $html .= apply_filters('flying_press_footprint', "<!-- $footprint -->");

    // Save the HTML to the cache file
    file_put_contents($cache_file_path . $cache_file_name, gzencode($html));

    // Add cache headers
    header('Cache-Tag: ' . $_SERVER['HTTP_HOST']);
    header('CDN-Cache-Control: max-age=2592000');
    header('Cache-Control: no-cache, must-revalidate');
  }

  public static function get_cache_path()
  {
    // Allow translation plugins to translate the request URI
    $request_uri = apply_filters('flying_press_request_uri', $_SERVER['REQUEST_URI']);

    // Get the host and path
    $host = $_SERVER['HTTP_HOST'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path = urldecode($path);

    // Allow developers to modify the cache path
    $path = apply_filters('flying_press_cache_file_path', $path);

    $path = FLYING_PRESS_CACHE_DIR . "$host/$path/";

    // Create the cache directory if it does not exist
    !is_dir($path) && mkdir($path, 0755, true);

    return $path;
  }

  public static function get_cache_file_name($type = 'html')
  {
    $config = Config::$config;
    $file_name = 'index';

    // Append "-logged-in" and user roles to the file name if the user is logged in
    $file_name .=
      $config['cache_logged_in'] && is_user_logged_in()
        ? '-logged-in-' . implode('-', wp_get_current_user()->roles)
        : '';

    // Include Cookies
    $cookies_to_include = apply_filters('flying_press_cache_include_cookies', []);

    // If any of the include cookies exist append it to the file name
    foreach ($cookies_to_include as $cookie_name) {
      if (isset($_COOKIE[$cookie_name])) {
        $file_name .= '-' . $_COOKIE[$cookie_name];
      }
    }

    // Append the '-mobile' if mobile caching is enabled and the current device is mobile
    $file_name .= $config['cache_mobile'] && wp_is_mobile() ? '-mobile' : '';

    // Remove ignored query string parameters and generate hash of the remaining
    $ignore_queries = apply_filters('flying_press_ignore_queries', self::$default_ignore_queries);
    $query_strings = array_diff_key($_GET, array_flip($ignore_queries));
    $file_name .= !empty($query_strings) ? '-' . md5(serialize($query_strings)) : '';

    // Allow developers to modify the cache file name
    $file_name = apply_filters('flying_press_cache_file_name', $file_name);

    // Append the '.html.gz' extension
    $file_name .= ".$type.gz";

    return $file_name;
  }

  public static function is_cacheable($content)
  {
    // Get the configuration
    $config = Config::$config;

    // Check for ?no_optimize in URL
    if (isset($_GET['no_optimize'])) {
      return false;
    }

    $ignore_queries = apply_filters('flying_press_ignore_queries', self::$default_ignore_queries);

    // Merge the ignored and included query parameters
    $known_queries = [
      ...$ignore_queries,
      ...self::$default_include_queries,
      ...$config['cache_include_queries'],
    ];

    // Check if the URL contains any unknown query parameters in $_GET
    foreach ($_GET as $key => $value) {
      if (!in_array($key, $known_queries)) {
        return false;
      }
    }

    // Get the current full URL
    $current_url = site_url($_SERVER['REQUEST_URI']);

    // Check if current page is a WordPress page that should not be cached
    $regex = '/wp-(admin|login|register|comments-post|cron|json|sitemap)|\.(txt|xml)/';
    if (preg_match($regex, $current_url)) {
      return false;
    }

    // Check if request method is GET
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'GET') {
      return false;
    }

    // Check if current page is an AJAX request
    if (wp_doing_ajax()) {
      return false;
    }

    // Check if user is logged in and if the 'cache_logged_in' option is enabled
    if (is_user_logged_in() && !$config['cache_logged_in']) {
      return false;
    }

    // Check if user role is excluded from caching
    $excluded_roles = apply_filters('flying_press_cache_excluded_roles', []);
    $user_role = $_COOKIE['fp_logged_in_roles'] ?? false;

    if (Utils::any_keywords_match_string($excluded_roles, $user_role)) {
      return false;
    }

    // Check if current page is on the excluded pages list
    if (Utils::any_keywords_match_string($config['cache_bypass_urls'], $current_url)) {
      return false;
    }

    // Check if current page has any cookies set that should not be cached
    foreach ($config['cache_bypass_cookies'] as $cookie) {
      if (preg_grep("/$cookie/i", array_keys($_COOKIE))) {
        return false;
      }
    }

    // Check if current user is an admin or if current page does not respond with status code 200
    if (is_admin() || http_response_code() !== 200) {
      return false;
    }

    // Check if content is HTML
    if (!preg_match('/<!DOCTYPE\s*html\b[^>]*>/i', $content)) {
      return false;
    }

    // Check if AMP endpoint is enabled and if current page is an AMP page
    if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
      return false;
    }

    // Check if the post is password protected
    if (is_singular() && post_password_required()) {
      return false;
    }

    return apply_filters('flying_press_is_cacheable', true);
  }

  public static function count_pages($path)
  {
    try {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
      );

      $dirs = [];
      foreach ($iterator as $file) {
        if ($file->isFile() && substr($file->getFilename(), -8) === '.html.gz') {
          $dirs[$file->getPath()] = true;
        }
      }

      return count($dirs);
    } catch (\Exception $e) {
      error_log('FlyingPress: Error counting pages - ' . $e->getMessage());
      return 0;
    }
  }

  public static function get_file_path_from_url($url)
  {
    $file_relative_path = parse_url($url, PHP_URL_PATH);
    $site_path = parse_url(site_url(), PHP_URL_PATH);
    $file_path = file_exists(ABSPATH . preg_replace("$^$site_path$", '', $file_relative_path))
      ? ABSPATH . preg_replace("$^$site_path$", '', $file_relative_path)
      : $_SERVER['DOCUMENT_ROOT'] . preg_replace("$^$site_path$", '', $file_relative_path);
    return $file_path;
  }

  public static function add_logged_in_roles($logged_in_cookie, $expire, $expiration, $user_id)
  {
    // Get the user
    $user = get_user_by('ID', $user_id);

    if (!$user) {
      return;
    }

    if (!isset($_COOKIE['fp_logged_in_roles'])) {
      $user_role = implode('-', $user->roles);
      $expiry = time() + 14 * DAY_IN_SECONDS;
      setcookie('fp_logged_in_roles', $user_role, $expiry, COOKIEPATH, COOKIE_DOMAIN, false);
    }
  }

  public static function remove_logged_in_roles()
  {
    if (isset($_COOKIE['fp_logged_in_roles'])) {
      // Unset the cookie
      unset($_COOKIE['fp_logged_in_roles']);
      // Set the cookie to expire in the past so it will be deleted by the browser
      setcookie('fp_logged_in_roles', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false);
    }
  }
}
