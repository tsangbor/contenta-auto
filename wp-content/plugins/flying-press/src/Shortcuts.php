<?php
namespace FlyingPress;

class Shortcuts
{
  public static function init()
  {
    add_filter('plugin_action_links_' . FLYING_PRESS_FILE_NAME, [__CLASS__, 'add_shortcuts']);
  }

  public static function add_shortcuts($links)
  {
    $settings_url = admin_url('admin.php?page=flying-press');

    // Use array_unshift to prepend the links
    array_unshift($links, '<a target="_blank" href="https://docs.flyingpress.com/">Docs</a>');

    array_unshift($links, '<a href="' . $settings_url . '">Settings</a>');

    return $links;
  }
}
