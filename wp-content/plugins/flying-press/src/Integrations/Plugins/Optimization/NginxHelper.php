<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: Nginx Helper

class NginxHelper
{
  public static function init()
  {
    // Purge Nginx Helper cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);

    // Purge Nginx Helper cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!class_exists('Nginx_Helper')) {
      return;
    }

    global $nginx_purger;

    $nginx_purger->purge_all();
  }
}
