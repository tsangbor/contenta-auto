<?php

namespace FlyingPress\Integrations;

class APO
{
  public static function init()
  {
    if (!class_exists('CF\WordPress\Hooks') || !self::is_apo_enabled()) {
      return;
    }
    // Purge APO cache for an URL when it's purged from FlyingPress
    add_action('flying_press_purge_urls:before', [__CLASS__, 'purge_cloudflare_cache_by_urls']);

    // When Cloudflare plugin is active Purge Cloudflare APO cache before purging FP cache
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cloudflare_cache']);

    // Purge Cloudflare cache before when entire FP cache is purged
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cloudflare_cache']);
  }

  public static function purge_cloudflare_cache()
  {
    $cfapi = new \CF\WordPress\Hooks();
    $cfapi->purgeCacheEverything();
  }

  public static function purge_cloudflare_cache_by_urls($urls)
  {
    if (!is_array($urls) || empty($urls)) {
      return;
    }

    $postids = [];
    foreach ($urls as $url) {
      $postids[] = url_to_postid($url);
    }
    $postids = array_unique($postids);
    $cfapi = new \CF\WordPress\Hooks();
    $cfapi->purgeCacheByRelevantURLs($postids);
  }

  private static function is_apo_enabled()
  {
    if (!class_exists('CF\API\Plugin')) {
      return false;
    }

    $datastore = new \CF\WordPress\DataStore(new \CF\Integration\DefaultLogger(false));
    $apo_config = $datastore->get('automatic_platform_optimization');
    if (!is_array($apo_config)) {
      return false;
    }
    if (
      array_key_exists('id', $apo_config) &&
      $apo_config['id'] === 'automatic_platform_optimization' &&
      $apo_config['value'] == '1'
    ) {
      return true;
    }
    return false;
  }
}
