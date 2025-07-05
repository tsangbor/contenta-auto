<?php

namespace FlyingPress;

class WPConfig
{
  public static function get_wp_config_path()
  {
    $wp_config_path = ABSPATH . 'wp-config.php';

    // If wp-config.php is not found in the current directory,
    // look for it in the parent directory
    if (!file_exists($wp_config_path)) {
      $wp_config_path = dirname(ABSPATH) . '/wp-config.php';
    }

    // File is not readable or writable
    if (!is_readable($wp_config_path) || !is_writable($wp_config_path)) {
      return false;
    }

    return $wp_config_path;
  }

  public static function add_constant($name, $value, $comment = 'Added by FlyingPress')
  {
    $wp_config_path = self::get_wp_config_path();

    // Return early if wp-config.php file path is not found or not writable/readable
    if (!$wp_config_path) {
      return;
    }

    $content = file_get_contents($wp_config_path);

    // Remove existing define for the constant
    $escaped_name = preg_quote($name, '/');
    $regex = '/\s*define\(\s*[\'"]' . $escaped_name . '[\'"].*/';
    $content = preg_replace($regex, '', $content);

    // Format value
    $formatted_value = var_export($value, true);

    // Insert new define
    $define = "\ndefine( '$name', $formatted_value ); // $comment";

    $content = str_replace('<?php', '<?php' . $define, $content);
    file_put_contents($wp_config_path, $content);
  }

  public static function remove_constant($constant_name)
  {
    $wp_config_path = self::get_wp_config_path();

    // Return early if wp-config.php file path is not found or not writable/readable
    if (!$wp_config_path) {
      return;
    }

    $content = file_get_contents($wp_config_path);

    // Escape constant name for regex
    $escaped_constant = preg_quote($constant_name, '/');

    // Remove the define line for the given constant
    $regex = '/\s*define\(\s*[\'"]' . $escaped_constant . '[\'"].*/';
    $content = preg_replace($regex, '', $content);

    file_put_contents($wp_config_path, $content);
  }
}
