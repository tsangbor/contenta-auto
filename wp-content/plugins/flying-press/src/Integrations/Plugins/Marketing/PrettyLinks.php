<?php

namespace FlyingPress\Integrations\Plugins\Marketing;

// Plugin : Pretty Links

class PrettyLinks
{
  public static function init()
  {
    add_filter('flying_press_is_cacheable', [__CLASS__, 'is_cacheable']);
  }

  public static function is_cacheable($is_cacheable)
  {
    if (!class_exists('PrliLink')) {
      return $is_cacheable;
    }

    $pretty_link = new \PrliLink();

    if ($pretty_link->is_pretty_link(site_url($_SERVER['REQUEST_URI']))) {
      return false;
    }

    return $is_cacheable;
  }
}
