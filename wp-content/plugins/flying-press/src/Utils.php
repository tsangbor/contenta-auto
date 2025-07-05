<?php

namespace FlyingPress;

class Utils
{
  const EXTERNAL_DOMAINS = [
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'unpkg.com',
    'code.jquery.com',
    'ajax.googleapis.com',
    'use.fontawesome.com',
    'bootstrapcdn.com',
    'cdn.rawgit.com',
  ];

  public static $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
  public static $mobile_user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1';

  public static function any_keywords_match_string($keywords, $string)
  {
    // Filter out empty elements
    $keywords = array_filter($keywords);

    foreach ($keywords as $keyword) {
      if (stripos($string, $keyword) !== false) {
        return true;
      }
    }

    return false;
  }

  public static function str_replace_first($search, $replace, $subject)
  {
    $pos = strpos($subject, $search);
    if ($pos !== false) {
      return substr_replace($subject, $replace, $pos, strlen($search));
    }
    return $subject;
  }

  public static function download_external_file($url)
  {
    $external_domains = apply_filters(
      'flying_press_selfhost_external_domains',
      self::EXTERNAL_DOMAINS
    );

    // Check if the base domain is present in the external domains array
    if (!self::any_keywords_match_string($external_domains, $url)) {
      return null;
    }

    // Check if src has protocol if not prepend https
    $url_new = preg_match('/^https?:\/\//', $url) ? $url : 'https:' . $url;

    // Get the file name
    $file_name = strtok(basename($url_new), '?');

    if (is_file(FLYING_PRESS_CACHE_DIR . $file_name)) {
      return FLYING_PRESS_CACHE_URL . $file_name;
    }

    // Get the content from the file and it into the cache directory

    $response = wp_remote_get($url_new, [
      'user-agent' => self::$user_agent,
      'httpversion' => '2.0',
    ]);

    // Return if the file response is invalid
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return null;
    }

    // Content type of the response
    $content_type = wp_remote_retrieve_header($response, 'content-type');

    // Determine the extension based on the content type
    $extension = strpos($content_type, 'text/css') !== false ? 'css' : 'js';

    // If the file name does not have an extension then append the type as extension
    if (!preg_match('/\.(css|js)$/', $file_name)) {
      $file_name = md5($url_new) . '.' . $extension;
    }

    $content = wp_remote_retrieve_body($response);

    // Filter hook to modify file contents before saving
    $content = apply_filters(
      'flying_press_download_external_file:before',
      $content,
      $url_new,
      $extension
    );

    // Check if the file already exists
    file_put_contents(FLYING_PRESS_CACHE_DIR . $file_name, $content);

    return FLYING_PRESS_CACHE_URL . $file_name;
  }

  public static function remove_resource_hints($url, $html)
  {
    $url_host = parse_url($url, PHP_URL_HOST);

    if (!$url_host) {
      return $html;
    }

    $html = preg_replace(
      '/<link[^>]*(?:prefetch|preconnect|preload)[^>]*' . preg_quote($url_host) . '[^>]*>/i',
      '',
      $html
    );
    return $html;
  }

  public static function get_include_cookies()
  {
    $include_cookies = apply_filters('flying_press_cache_include_cookies', []);

    if (empty($include_cookies) || empty($_COOKIE)) {
      return '';
    }

    // Extract only the include
    $cookies = array_intersect_key($_COOKIE, array_flip($include_cookies));

    // Build cookie string
    return implode(
      '; ',
      array_map(fn($name, $value) => "$name=$value", array_keys($cookies), array_values($cookies))
    );
  }
}
