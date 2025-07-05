<?php

namespace FlyingPress\Integrations;

class Varnish
{
  public static function init()
  {
    if (self::is_varnish_enabled()) {
      // Purge Varnish cache before FP cache is purged by url
      add_action('flying_press_purge_url:before', [__CLASS__, 'purge_varnish_by_url'], 10, 1);

      // Purge Varnish cache before FP cache is purged
      add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_varnish'], 10, 0);

      // Purge Varnish cache before entire FP cache is purged
      add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_varnish'], 10, 0);
    }
  }

  public static function purge_varnish_by_url($url)
  {
    $path = parse_url($url, PHP_URL_PATH);
    $request_args = [
      'method' => 'URLPURGE',
      'headers' => [
        'Host' => $_SERVER['SERVER_NAME'],
        'User-Agent' =>
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
      ],
      'sslverify' => false,
    ];
    wp_remote_request('https://127.0.0.1' . $path, $request_args);
  }

  public static function purge_varnish()
  {
    $request_args = [
      'method' => 'PURGE',
      'headers' => [
        'Host' => $_SERVER['SERVER_NAME'],
        'User-Agent' =>
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
      ],
      'sslverify' => false,
    ];
    wp_remote_request('https://127.0.0.1/', $request_args);
  }

  private static function is_varnish_enabled()
  {
    if (!isset($_SERVER['HTTP_X_VARNISH'])) {
      return false;
    }

    if (!isset($_SERVER['HTTP_X_APPLICATION'])) {
      return false;
    }

    if ($_SERVER['HTTP_X_APPLICATION'] === 'varnishpass') {
      return false;
    }

    return true;
  }
}
