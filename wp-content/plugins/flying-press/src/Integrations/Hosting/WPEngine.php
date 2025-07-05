<?php

namespace FlyingPress\Integrations\Hosting;

use WPeCommon;

class WPEngine
{
  public static function init()
  {
    // Purge WPEngine Cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_wpe_cache']);
    // Purge WPEngine Cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_wpe_cache']);
  }

  public static function purge_wpe_cache()
  {
    if (class_exists('WPeCommon')) {
      // Purge memcached
      if (method_exists('WPeCommon', 'purge_memcached')) {
        WPeCommon::purge_memcached();
      }
      //Purge CDN cache
      if (method_exists('WPeCommon', 'clear_maxcdn_cache')) {
        WPeCommon::clear_maxcdn_cache();
      }
      // Purge object cache
      if (method_exists('WPeCommon', 'purge_varnish_cache')) {
        WPeCommon::purge_varnish_cache();
      }
    }
  }
}
