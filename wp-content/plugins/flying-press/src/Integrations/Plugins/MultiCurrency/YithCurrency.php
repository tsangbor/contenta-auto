<?php

namespace FlyingPress\Integrations\Plugins\MultiCurrency;

class YithCurrency
{
  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_integration']);
  }

  public static function setup_integration()
  {
    // Return early if YITH WCMCS Plugin is not active
    if (!defined('YITH_WCMCS_VERSION')) {
      return;
    }

    add_filter('flying_press_cache_include_cookies', [__CLASS__, 'include_cookies']);
  }

  public static function include_cookies($cookies)
  {
    return [...$cookies, 'yith_wcmcs_currency'];
  }
}
