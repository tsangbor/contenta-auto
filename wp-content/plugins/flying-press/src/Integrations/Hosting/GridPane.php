<?php

namespace FlyingPress\Integrations\Hosting;

class GridPane
{
  public static function init()
  {
    // Purge GridPane cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);
    // Purge GridPane cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!class_exists('Nginx_Cache_Purger_Admin')) {
      return;
    }

    $gppurge = new \Nginx_Cache_Purger_Admin();
    $gppurge->register_purge();
  }
}
