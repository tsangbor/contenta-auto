<?php

namespace FlyingPress\Integrations\I18n;

class TranslatePress
{
  private static $url_converter;
  private static $trp_language;

  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_integration']);
  }

  public static function setup_integration()
  {
    if (!class_exists('TRP_Translate_Press')) {
      return;
    }

    global $TRP_LANGUAGE;

    // Get the URL converter and current language
    self::$url_converter = \TRP_Translate_Press::get_trp_instance()->get_component('url_converter');
    self::$trp_language = $TRP_LANGUAGE;

    // Add translated URLs to the list of preload URLs
    add_filter('flying_press_preload_urls', [__CLASS__, 'add_translated_urls']);

    // Add translated URLs to the list of URLs to purge
    add_filter('flying_press_auto_purge_urls', [__CLASS__, 'add_translated_urls']);

    // Translate the request URI
    add_filter('flying_press_request_uri', [__CLASS__, 'convert_request_uri']);
  }

  public static function add_translated_urls($urls)
  {
    $languages = array_diff(array_keys(\trp_get_languages()), [self::$trp_language]);

    foreach ($urls as $url) {
      foreach ($languages as $language) {
        $urls[] = self::$url_converter->get_url_for_language($language, $url, '');
      }
    }

    return array_unique($urls);
  }

  public static function convert_request_uri($request_uri)
  {
    return self::$url_converter->get_url_for_language(self::$trp_language, $request_uri, '');
  }
}
