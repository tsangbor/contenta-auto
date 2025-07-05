<?php

namespace FlyingPress\Integrations\Hosting;

class SiteGround
{
  public static function init()
  {
    // Purge SiteGround cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);
    // Purge SiteGround cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!class_exists('SiteGround_Optimizer\File_Cacher\Cache')) {
      return;
    }

    if (function_exists('sg_cachepress_purge_everything')) {
      \sg_cachepress_purge_everything();
    }
  }
}
