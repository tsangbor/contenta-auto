<?php

namespace FlyingPress;

class Dashboard
{
  public static $menu_icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjYiIGhlaWdodD0iMTciIHZpZXdCb3g9IjAgMCAyNiAxNyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTExLjA1OTQgMEM5LjYzODc0IDAgOC4zMTQ5NiAwLjcxODcwNyA3LjU0MDQ4IDEuOTEwMDRMNS41NTUxNSA0Ljk2NTQxTDAgMTMuNTI5OUgxLjY2ODE1QzIuNjA4NTMgMTMuNTI5OSAzLjQ4NDc4IDEzLjA1MzEgMy45OTU3NSAxMi4yNjMzTDcuMzEzMjMgNy4xNjE5NkM4LjE5OTI0IDUuNzkyMTcgOS43MTg5MSA0Ljk2NTQxIDExLjM0OTQgNC45NjU0MUgyMC4yNzk5QzIxLjcwMDYgNC45NjU0MSAyMy4wMjQ0IDQuMjQ2NzEgMjMuNzk4OCAzLjA1NTM3TDI1Ljc4NDIgMEgxMS4wNTk0WiIgZmlsbD0iIzRGNDZFNSIvPgo8cGF0aCBkPSJNMTIuMDY0NiA2LjU3ODEyQzEwLjY0MzkgNi41NzgxMiA5LjMxOTQ1IDcuMjk2ODMgOC41NDQ5NyA4LjQ4ODE2TDguNTIxMjcgOC41MjQ0MUw4LjA5NzQ0IDkuMTc2OUw2LjU1OTY1IDExLjU0MzVINi41NjAzNEwzLjQyOTY5IDE2LjM2NEg1LjExMTc4QzYuMDQzOCAxNi4zNjQgNi45MTIzOCAxNS44OTI3IDcuNDE5ODYgMTUuMTExM0w5LjA2NTcxIDEyLjU3OEM5LjQ4NDY2IDExLjkzMjUgMTAuMjAyIDExLjU0MzUgMTAuOTcxNiAxMS41NDM1SDE3LjA1NjVDMTguNDc3MiAxMS41NDM1IDE5LjgwMSAxMC44MjQ4IDIwLjU3NTUgOS42MzM1TDIyLjU2MDggNi41NzgxMkgxMi4wNjQ2WiIgZmlsbD0iIzRGNDZFNSIvPgo8L3N2Zz4K';

  public static function init()
  {
    add_action('admin_menu', [__CLASS__, 'add_menu']);
  }

  public static function add_menu()
  {
    if (!Auth::is_allowed()) {
      return;
    }

    $menu = add_menu_page(
      'FlyingPress',
      'FlyingPress',
      'edit_posts',
      'flying-press',
      [__CLASS__, 'render'],
      self::$menu_icon,
      '81'
    );

    // A kind of hack to inject JS only in the page we need
    add_action('admin_print_scripts-' . $menu, [__CLASS__, 'add_js']);
  }

  public static function add_js()
  {
    wp_enqueue_script(
      'flying_press_dashboard',
      FLYING_PRESS_PLUGIN_URL . 'assets/app.js',
      [],
      filemtime(FLYING_PRESS_PLUGIN_DIR . 'assets/app.js'),
      true
    );
  }

  public static function render()
  {
    $config = json_encode(Config::$config);
    $version = FLYING_PRESS_VERSION;
    echo "<script>window.flying_press={config:$config,version:'$version'}</script>";
    echo '<div id="app"></div>';
  }
}
