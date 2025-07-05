<?php

namespace FlyingPress\Integrations\Hosting;

class RunCloud
{
  public static function init()
  {
    // Purge Runcloud cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);
    // Purge Runcloud cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!class_exists('RunCloud_Hub')) {
      return;
    }
    if (is_multisite()) {
      \RunCloud_Hub::purge_cache_all_sites();
    } else {
      \RunCloud_Hub::purge_cache_all();
    }
  }
}
