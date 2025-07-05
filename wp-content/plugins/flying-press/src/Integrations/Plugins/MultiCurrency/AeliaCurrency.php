<?php

namespace FlyingPress\Integrations\Plugins\MultiCurrency;

class AeliaCurrency
{
  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_integration']);
  }

  public static function setup_integration()
  {
    if (!class_exists('WC_Aelia_CurrencySwitcher')) {
      return;
    }

    add_filter('flying_press_cache_include_cookies', [__CLASS__, 'include_cookies']);
  }

  public static function include_cookies($cookies)
  {
    return [...$cookies, 'aelia_cs_selected_currency'];
  }
}
