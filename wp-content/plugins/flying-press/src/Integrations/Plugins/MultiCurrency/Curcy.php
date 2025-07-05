<?php

// Plugin: https://wordpress.org/plugins/woo-multi-currency/

namespace FlyingPress\Integrations\Plugins\MultiCurrency;

class Curcy
{
  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_integration']);
  }

  public static function setup_integration()
  {
    // Check for free plugin or pro plugin
    if (!class_exists('WOOMULTI_CURRENCY_F') && !class_exists('WOOMULTI_CURRENCY')) {
      return;
    }

    add_filter('flying_press_cache_include_cookies', [__CLASS__, 'include_cookies']);
  }

  public static function include_cookies($cookies)
  {
    return [...$cookies, 'wmc_current_currency'];
  }
}
