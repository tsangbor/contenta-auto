<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: SiteGround Optimizer

class SiteGround
{
  public static function init()
  {
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_options']);
  }

  public static function disable_conflicting_options()
  {
    if (!class_exists('SiteGround_Optimizer\Options\Options')) {
      return;
    }

    $options = [
      'default_enable_cache',
      'default_autoflush_cache',
      'supercacher_permissions',
      'enable_cache',
      'logged_in_cache',
      'autoflush_cache',
      'optimize_html',
      'optimize_javascript',
      'optimize_javascript_async',
      'combine_javascript',
      'optimize_css',
      'combine_css',
      'preload_combined_css',
      'file_caching',
      'optimize_web_fonts',
      'combine_google_fonts',
      'disable_emojis',
      'remove_query_strings',
      'lazyload_images',
    ];

    $sgoptions = new \SiteGround_Optimizer\Options\Options();

    foreach ($options as $option) {
      $sgoptions::disable_option('siteground_optimizer_' . $option);
    }
  }
}
