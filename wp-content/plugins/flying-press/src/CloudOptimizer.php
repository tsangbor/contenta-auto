<?php

namespace FlyingPress;

class CloudOptimizer
{
  const API_URL = 'https://page-optimizer.flyingpress.com/optimizer/';

  public static $optimizations;

  // Fetches optimizations from cache or API
  public static function fetch_optimizations($html)
  {
    $hash = self::get_hash($html);
    $cache_file = Caching::get_cache_path() . Caching::get_cache_file_name('json');

    // Try reading from cache
    if (is_readable($cache_file)) {
      $json = json_decode(gzdecode(file_get_contents($cache_file)));
      if (($json->structure_hash ?? '') === $hash) {
        return self::$optimizations = $json;
      }
    }

    // Fetch fresh data
    $json = self::fetch_from_api($html, $hash);
    if (!$json) {
      return null;
    }

    $json->structure_hash = $hash;
    file_put_contents($cache_file, gzencode(json_encode($json)));

    return self::$optimizations = $json;
  }

  // Sends request to the optimizer API
  private static function fetch_from_api($html, $hash)
  {
    $response = wp_remote_post(self::API_URL, [
      'body' => [
        'html' => $html,
        'url' => site_url($_SERVER['REQUEST_URI']),
        'device' => wp_is_mobile() && Config::$config['cache_mobile'] ? 'mobile' : 'desktop',
        'config' => Config::$config,
        'version' => FLYING_PRESS_VERSION,
        'cache_file_name' => Caching::get_cache_file_name(),
        'hash' => $hash,
      ],
      'timeout' => 60,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return null;
    }

    return json_decode(wp_remote_retrieve_body($response));
  }

  // Creates a structure hash based on HTML tag structure, IDs, classes, etc.
  private static function get_hash($html)
  {
    $html = html_entity_decode($html);

    preg_match_all('/<\s*([a-zA-Z][\w:-]*)\b[^>]*>/i', $html, $tags);
    preg_match_all('/\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $ids);
    preg_match_all('/\bclass\s*=\s*["\']([^"\']+)["\']/i', $html, $classes);
    preg_match_all(
      '/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']/i',
      $html,
      $stylesheets
    );
    preg_match_all('/<script[^>]*src=["\']([^"\']+)["\']/i', $html, $scripts);

    // Add rucss include selectors
    $include_selectors = Config::$config['css_rucss_include_selectors'] ?? [];

    // Flatten and clean class list
    $class_list = [];
    foreach ($classes[1] as $class_string) {
      foreach (preg_split('/\s+/', trim($class_string)) as $class) {
        $class = preg_replace('/\d.*$/', '', trim($class));
        if ($class !== '') {
          $class_list[] = $class;
        }
      }
    }

    // Clean IDs
    $id_list = array_map(fn($id) => preg_replace('/\d.*$/', '', $id), $ids[1] ?? []);

    // Clean tag names
    $tag_list = array_map(fn($tag) => '<' . strtolower($tag) . '>', $tags[1] ?? []);

    // Combine and hash
    $all = array_merge(
      $id_list,
      $class_list,
      $stylesheets[1] ?? [],
      $scripts[1] ?? [],
      $tag_list,
      $include_selectors
    );
    $all = array_unique(array_filter($all));
    sort($all);

    return md5(implode(' ', $all));
  }
}
