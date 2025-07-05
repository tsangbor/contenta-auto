<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: Shortpixel Adaptive Images

class ShortPixelAI
{
  public static function init()
  {
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_settings']);
  }

  public static function disable_conflicting_settings()
  {
    if (!defined('SHORTPIXEL_AI_VERSION')) {
      return;
    }

    $options = \ShortPixel\AI\Options::_();

    // Disable lazy loading
    $options->set('img', 'eager_selectors', ['settings', 'exclusions']);

    // Disable altering of image dimensions
    $options->set(0, 'alter2wh', ['settings', 'behaviour']);
  }
}
