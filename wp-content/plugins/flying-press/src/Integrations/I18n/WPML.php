<?php

namespace FlyingPress\Integrations\I18n;

class WPML
{
  public static function init()
  {
    // Check if WPML is active
    if (!defined('ICL_SITEPRESS_VERSION')) {
      return;
    }

    // Filter URLs on preloading all URLs
    add_filter('flying_press_preload_urls', [__CLASS__, 'add_translated_urls'], 10, 1);

    // Filter URLs on auto purging URLs
    add_filter('flying_press_auto_purge_urls', [__CLASS__, 'add_translated_urls'], 10, 1);
  }

  public static function add_translated_urls($urls)
  {
    // Get active WPML languages
    $languages = apply_filters('wpml_active_languages', null, []);

    $translated_urls = [];

    // Get home URLs for each language
    foreach ($languages as $language) {
      $translated_urls[] = apply_filters('wpml_permalink', home_url(), $language['code']);
    }

    // Get the subsequent translated URLs of the original URLs
    foreach ($urls as $url) {
      foreach ($languages as $language) {
        $translated_urls[] = apply_filters('wpml_permalink', $url, $language['code'], true);
      }
    }

    $urls = array_unique([...$urls, ...$translated_urls]);

    return $urls;
  }
}
