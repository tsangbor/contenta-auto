<?php

namespace FlyingPress\Integrations\Themes;

class BuddyBoss
{
  public static function init()
  {
    add_filter('bb_exclude_endpoints_from_restriction', [__CLASS__, 'excluded_endpoints']);
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_options']);
  }

  public static function excluded_endpoints($endpoints)
  {
    if (!class_exists('buddyboss_theme_Redux_Framework_config')) {
      return $endpoints;
    }

    $fp_endpoints = [
      '/flying-press/config',
      '/flying-press/cached-pages-count',
      '/flying-press/purge-current-page',
      '/flying-press/preload-cache',
      '/flying-press/purge-pages',
      '/flying-press/purge-pages-and-preload',
      '/flying-press/purge-everything-and-preload',
      '/flying-press/activate-license',
    ];
    $endpoints = array_merge($endpoints, $fp_endpoints);
    return $endpoints;
  }

  public static function disable_conflicting_options()
  {
    if (!class_exists('buddyboss_theme_Redux_Framework_config')) {
      return;
    }

    $options = get_option('buddyboss_theme_options');
    if ($options['boss_minified_css'] || $options['boss_minified_js']) {
      $options['boss_minified_css'] = 0;
      $options['boss_minified_js'] = 0;
      update_option('buddyboss_theme_options', $options);
    }
  }
}
