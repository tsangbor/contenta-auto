<?php

namespace FlyingPress;

class AdminBar
{
  public static function init()
  {
    // Add the admin bar menu
    add_action('admin_bar_menu', [__CLASS__, 'add_menu_items'], 10000);

    // Inject WP Rest API URL in the head (both frontend and backend)
    add_action('wp_head', [__CLASS__, 'inject_rest_api_url']);
    add_action('admin_head', [__CLASS__, 'inject_rest_api_url']);

    // Inject the JavaScript
    add_action('wp_loaded', [__CLASS__, 'enqueue_scripts']);
  }

  public static function inject_rest_api_url()
  {
    if (!is_admin_bar_showing()) {
      return;
    }

    $rest_url = get_rest_url(null, 'flying-press');
    echo "<script id='flying-press-rest'>var flying_press_rest_url='$rest_url'</script>";
  }

  public static function enqueue_scripts()
  {
    if (!is_admin_bar_showing()) {
      return;
    }

    wp_enqueue_script(
      'flying-press-admin-bar',
      FLYING_PRESS_PLUGIN_URL . 'assets/admin.js',
      [],
      FLYING_PRESS_VERSION,
      true
    );
  }

  public static function add_menu_items($admin_bar)
  {
    if (!Auth::is_allowed()) {
      return;
    }

    $admin_bar->add_menu([
      'id' => 'flying-press',
      'title' => 'FlyingPress',
      'href' => admin_url('admin.php?page=flying-press'),
    ]);

    if (!is_admin()) {
      $admin_bar->add_menu([
        'id' => 'purge-current-page',
        'parent' => 'flying-press',
        'title' => 'Purge current page',
        'href' => '#',
        'meta' => [
          'onclick' => 'purge_current_page()',
        ],
      ]);
    }

    $admin_bar->add_menu([
      'id' => 'preload-cache',
      'parent' => 'flying-press',
      'title' => 'Preload cache',
      'href' => '#',
      'meta' => [
        'onclick' => 'preload_cache()',
      ],
    ]);

    $admin_bar->add_menu([
      'id' => 'purge-pages-and-preload',
      'parent' => 'flying-press',
      'title' => 'Purge pages and preload',
      'href' => '#',
      'meta' => [
        'onclick' => 'purge_pages_and_preload()',
      ],
    ]);
  }
}
