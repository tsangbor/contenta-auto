<?php

namespace FlyingPress;

class WPCache
{
  public static function init()
  {
    self::add_constant();
    register_deactivation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'remove_constant']);
  }

  public static function add_constant()
  {
    // Skip if WP_CACHE is already defined and true
    if (defined('WP_CACHE') && WP_CACHE) {
      return;
    }

    WPConfig::add_constant('WP_CACHE', true);
  }

  public static function remove_constant()
  {
    WPConfig::remove_constant('WP_CACHE');
  }
}
