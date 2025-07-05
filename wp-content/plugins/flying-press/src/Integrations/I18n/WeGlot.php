<?php

namespace FlyingPress\Integrations\I18n;

class WeGlot
{
  public static function init()
  {
    add_filter('flying_press_cache_file_path', [__CLASS__, 'add_language_code']);

    // Preload Translated Pages
    add_filter('flying_press_preload_urls', [__CLASS__, 'add_translated_urls']);

    // Autopurge Translated Pages
    add_filter('flying_press_auto_purge_urls', [__CLASS__, 'add_translated_urls']);
  }

  public static function add_language_code($path)
  {
    // Check if WeGlot is active
    if (!defined('WEGLOT_VERSION')) {
      return $path;
    }

    $url = weglot_get_current_full_url();
    $path = parse_url($url, PHP_URL_PATH);

    return $path;
  }

  public static function add_translated_urls($urls)
  {
    // Check if WeGlot is active
    if (!defined('WEGLOT_VERSION')) {
      return $urls;
    }

    // Get Destination languages
    $languages = \weglot_get_service('Language_Service_Weglot')->get_destination_languages();

    // Activate the Replace Link Service
    $replace_link_service = \weglot_get_service('Replace_Link_Service_Weglot');

    // Append the translated URLs to the preload urls list
    foreach ($urls as $url) {
      foreach ($languages as $lang) {
        $urls[] = $replace_link_service->replace_url($url, $lang);
      }
    }

    return array_unique($urls);
  }
}
