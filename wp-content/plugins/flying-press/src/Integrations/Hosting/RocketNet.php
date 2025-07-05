<?php

namespace FlyingPress\Integrations\Hosting;

class RocketNet
{
  public static function init()
  {
    // Purge Rocket.net cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cdn_cache']);
    // Purge Rocket.net cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cdn_cache']);
  }

  public static function purge_cdn_cache()
  {
    if (!class_exists('CDN_Clear_Cache_Hooks')) {
      return;
    }
    \CDN_Clear_Cache_Hooks::purge_cache();
  }
}
