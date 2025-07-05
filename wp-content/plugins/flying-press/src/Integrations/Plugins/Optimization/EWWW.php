<?php

namespace FlyingPress\Integrations\Plugins\Optimization;

// Plugin: EWWW Image Optimizer

class EWWW
{
  public static function init()
  {
    add_action('flying_press_update_config:after', [__CLASS__, 'disable_conflicting_settings']);
  }

  public static function disable_conflicting_settings()
  {
    if (!defined('EWWW_IMAGE_OPTIMIZER_VERSION')) {
      return;
    }

    update_option('ewww_image_optimizer_lazy_load', false);
    update_site_option('ewww_image_optimizer_lazy_load', false);
  }
}
