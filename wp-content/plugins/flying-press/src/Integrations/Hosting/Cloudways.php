<?php

namespace FlyingPress\Integrations\Hosting;

class CloudWays
{
  public static function init()
  {
    if (!class_exists('Breeze_CloudFlare_Helper')) {
      return;
    }

    // Purge Cloudways CF cache before purging urls by FlyingPress
    add_action('flying_press_purge_urls:before', [__CLASS__, 'purge_urls_cache'], 10, 1);
    // Purge Cloudways CF cache before purging pages by FlyingPress
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cache']);
    // Purge Cloudways CF cache before purging entire FlyingPress cache
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cache']);
  }

  public static function purge_cache()
  {
    if (!\Breeze_CloudFlare_Helper::is_cloudflare_enabled()) {
      return;
    }

    \Breeze_CloudFlare_Helper::reset_all_cache();
  }

  public static function purge_urls_cache($urls)
  {
    if (!\Breeze_CloudFlare_Helper::is_cloudflare_enabled()) {
      return;
    }

    \Breeze_CloudFlare_Helper::purge_cloudflare_cache_urls($urls);
  }
}
