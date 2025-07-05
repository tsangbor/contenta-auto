<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: Perfmatters

class Perfmatters
{
  public static function init()
  {
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_settings']);
  }

  public static function disable_conflicting_settings($config)
  {
    if (!defined('PERFMATTERS_VERSION')) {
      return;
    }

    $options = get_option('perfmatters_options');

    $config['js_delay'] && ($options['assets']['delay_js'] = false);
    $config['css_rucss'] && ($options['assets']['remove_unused_css'] = false);
    $config['lazy_load'] && ($options['lazyload']['lazy_loading'] = false);
    $config['lazy_load'] && ($options['lazyload']['css_background_images'] = false);
    $config['properly_size_images'] && ($options['lazyload']['image_dimensions'] = false);
    $options['lazy_load']['critical_images'] = false;
    $config['lazy_load'] && ($options['lazyload']['lazy_loading_iframes'] = false);
    $config['cache_link_prefetch'] && ($options['preload']['instant_page'] = false);
    $config['fonts_optimize_google'] && ($options['fonts']['local_google_fonts'] = false);
    $config['fonts_display_swap'] && ($options['fonts']['display_swap'] = false);
    $config['fonts_display_swap'] && ($options['fonts']['disable_google_fonts'] = false);
    $config['cdn'] && ($options['cdn']['enable_cdn'] = false);

    update_option('perfmatters_options', $options);
  }
}
