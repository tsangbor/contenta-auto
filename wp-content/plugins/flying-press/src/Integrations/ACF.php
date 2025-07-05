<?php

namespace FlyingPress\Integrations;

use FlyingPress\{Purge, Preload};

class ACF
{
  public static function init()
  {
    // Purge post type URL when an ACF field is updated in any post type or custom option pages
    add_action('acf/save_post', [__CLASS__, 'auto_purge']);
    add_action('acf/options_page/save', [__CLASS__, 'auto_purge']);
  }

  public static function auto_purge($post_id)
  {
    // If it's an options page, purge all pages and preload cache
    if ($post_id === 'options') {
      Purge::purge_pages();
      Preload::preload_cache();
      return;
    }

    // Get the post type for the current post ID
    $post_type = get_post_type($post_id);

    // If the post type is a nav_menu_item, return early
    if ($post_type === 'nav_menu_item') {
      return;
    }

    // Get the permalink for the post and then purge it
    $url = get_permalink($post_id);
    Purge::purge_urls([$url]);
    Preload::preload_urls([$url], time());
  }
}
