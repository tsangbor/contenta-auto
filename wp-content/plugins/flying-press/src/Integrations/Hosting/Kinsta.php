<?php

namespace FlyingPress\Integrations\Hosting;

class Kinsta
{
  public static function init()
  {
    // Purge Kinsta Cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_entire_cache']);
    // Purge Kinsta Cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_entire_cache']);
  }

  public static function purge_entire_cache()
  {
    global $kinsta_cache;
    if (empty($kinsta_cache)) {
      return;
    }
    $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
  }
}
