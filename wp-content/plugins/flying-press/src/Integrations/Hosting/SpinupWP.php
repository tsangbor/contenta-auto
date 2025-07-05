<?php

namespace FlyingPress\Integrations\Hosting;

class SpinupWP
{
  public static function init()
  {
    // Purge SpinupWP cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);
    // Purge SpinupWP cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!class_exists('SpinupWp\Cache')) {
      return;
    }

    if (function_exists('spinupwp_purge_site')) {
      \spinupwp_purge_site();
    }
  }
}
