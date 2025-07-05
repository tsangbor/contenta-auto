<?php

namespace FlyingPress;

class WpCLI
{
  public static function init()
  {
    if (defined('WP_CLI') && WP_CLI) {
      \WP_CLI::add_command('flying-press', [__CLASS__, 'sub_commands']);
    }
  }

  public static function sub_commands($args, $assoc_args)
  {
    if (empty($args)) {
      \WP_CLI::error(
        'Please specify a subcommand: preload-cache, purge-pages-and-preload, purge-everything, or activate-license.'
      );
    }

    $subcommand = $args[0];

    if ($subcommand !== 'activate-license' && !is_writable(FLYING_PRESS_CACHE_DIR)) {
      \WP_CLI::error(
        'Error: Unable to write to the wp-content/cache folder. Verify folder permissions and try again.'
      );
    }

    switch ($subcommand) {
      case 'preload-cache': // usage: wp flying-press preload-cache
        try {
          Preload::preload_cache();
          \WP_CLI::success('Cache preloaded successfully.');
        } catch (\Exception $e) {
          \WP_CLI::error('Error preloading cache: ' . $e->getMessage());
        }
        break;

      case 'purge-pages-and-preload': // usage: wp flying-press purge-pages-and-preload
        try {
          Purge::purge_pages();
          Preload::preload_cache();
          \WP_CLI::success('Pages purged and Cache preloaded successfully.');
        } catch (\Exception $e) {
          \WP_CLI::error('Error purging pages: ' . $e->getMessage());
        }
        break;

      case 'purge-everything': // usage: wp flying-press purge-everything
        try {
          Purge::purge_everything();
          \WP_CLI::success('Everything purged successfully.');
        } catch (\Exception $e) {
          \WP_CLI::error('Error: ' . $e->getMessage());
        }
        break;

      case 'activate-license': // usage: wp flying-press activate-license LICENSE-KEY
        if (empty($args[1])) {
          \WP_CLI::error('Please provide a license key.');
        }
        $license_key = $args[1];
        try {
          License::activate_license($license_key);
          \WP_CLI::success('FlyingPress License activated successfully.');
        } catch (\Exception $e) {
          \WP_CLI::error('Error activating license: ' . $e->getMessage());
        }
        break;

      default:
        \WP_CLI::error('Invalid command: ' . $subcommand);
    }
  }
}
