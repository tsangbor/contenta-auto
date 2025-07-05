<?php

namespace FlyingPress\Integrations\I18n;

class Polylang
{
  public static function init()
  {
    // Check if Polylang is active
    if (!defined('POLYLANG_VERSION')) {
      return;
    }

    // Filter URLs on preloading all URLs
    add_filter('flying_press_preload_urls', [__CLASS__, 'add_translated_urls'], 10, 1);

    // Filter URLs on auto purging URLs
    add_filter('flying_press_auto_purge_urls', [__CLASS__, 'add_translated_urls'], 10, 1);
  }

  public static function add_translated_urls($urls)
  {
    $translated_urls = [];

    // Get home URLs for each language
    foreach (\pll_languages_list() as $language) {
      $translated_urls[] = \pll_home_url($language);
    }

    // Get translated URLs for each post and each taxonomy term
    foreach ($urls as $url) {
      $postid = \url_to_postid($url);

      // Add post translations
      foreach (\pll_get_post_translations($postid) as $translation) {
        $translated_urls[] = \get_permalink($translation);
      }

      // Add term translations
      foreach (get_object_taxonomies(get_post_type($postid)) as $taxonomy) {
        $terms = get_the_terms($postid, $taxonomy);
        if (is_array($terms) && !\is_wp_error($terms)) {
          foreach ($terms as $term) {
            foreach (\pll_get_term_translations($term->term_id) as $translation) {
              $term_link = \get_term_link($translation);
              if (!\is_wp_error($term_link)) {
                $translated_urls[] = $term_link;
              }
            }
          }
        }
      }
    }

    return array_unique($translated_urls);
  }
}
