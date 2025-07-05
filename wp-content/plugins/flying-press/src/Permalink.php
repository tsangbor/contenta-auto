<?php
namespace FlyingPress;

class Permalink
{
  public static function init()
  {
    add_action('admin_notices', [__CLASS__, 'check_permalink_structure']);
  }

  public static function check_permalink_structure()
  {
    $permalink_structure = get_option('permalink_structure');

    // Skip if a permalink structure is set
    if (!empty($permalink_structure)) {
      return;
    }

    $configure = admin_url('options-permalink.php');

    echo '<div class="notice notice-error"><p><b>FlyingPress:</b> A Permalink structure is required. <a href="' .
      $configure .
      '">Configure now</a></p></div>';
    '</p></div>';
  }
}
