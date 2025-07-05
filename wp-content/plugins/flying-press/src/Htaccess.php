<?php

namespace FlyingPress;

class Htaccess
{
  public static function init()
  {
    register_activation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'add_htaccess_rules']);
    register_deactivation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'remove_htaccess_rules']);
    add_action('flying_press_update_config:after', [__CLASS__, 'add_htaccess_rules']);
  }

  public static function add_htaccess_rules()
  {
    $htaccess_file = ABSPATH . '.htaccess';

    if (!file_exists($htaccess_file) || !is_writeable($htaccess_file)) {
      return;
    }

    // Get the contents of the .htaccess file
    $htaccess_contents = file_get_contents($htaccess_file);

    // Get the rules we want to add
    $flying_press_rules = file_get_contents(FLYING_PRESS_PLUGIN_DIR . 'assets/htaccess.txt');

    // If server is OpenLiteSpeed, remove gzip related rules
    if (preg_match('/openlitespeed/i', $_SERVER['LSWS_EDITION'] ?? '')) {
      $flying_press_rules = preg_replace(
        '/# GZIP compression for text files: HTML, CSS, JS, Text, XML, fonts.*# End rewrite requests to cache\n*/s',
        '',
        $flying_press_rules
      );
    }

    // If separate mobile caching is enabled, replace MOBILE_CACHING_FLAG:0 with MOBILE_CACHING_FLAG:1
    if (Config::$config['cache_mobile']) {
      $flying_press_rules = str_replace(
        'MOBILE_CACHING_FLAG:0',
        'MOBILE_CACHING_FLAG:1',
        $flying_press_rules
      );
    }

    // Get the site's hostname
    $hostname = parse_url(site_url(), PHP_URL_HOST);

    // Replace HOSTNAME with the current site's hostname
    $flying_press_rules = str_replace('HOSTNAME', $hostname, $flying_press_rules);

    $marker_regex = '/# BEGIN FlyingPress.*# END FlyingPress/s';

    // If the rules is already in the file, replace it
    if (preg_match($marker_regex, $htaccess_contents)) {
      $htaccess_contents = preg_replace($marker_regex, $flying_press_rules, $htaccess_contents);
    }
    // If WordPress rules are present, add it before that
    elseif (strpos($htaccess_contents, '# BEGIN WordPress') !== false) {
      $htaccess_contents = str_replace(
        '# BEGIN WordPress',
        "$flying_press_rules\n\n# BEGIN WordPress",
        $htaccess_contents
      );
    }
    // Otherwise, add it to the top of the file
    else {
      $htaccess_contents = "$flying_press_rules\n$htaccess_contents";
    }

    file_put_contents($htaccess_file, $htaccess_contents);
  }

  public static function remove_htaccess_rules()
  {
    $htaccess_file = ABSPATH . '.htaccess';

    if (!file_exists($htaccess_file) || !is_writeable($htaccess_file)) {
      return;
    }

    $htaccess = file_get_contents($htaccess_file);

    // Remove our rules
    $htaccess = preg_replace('/# BEGIN FlyingPress.*# END FlyingPress\n*/s', '', $htaccess);

    // Write back to htaccess
    file_put_contents($htaccess_file, $htaccess);
  }
}
