<?php

namespace FlyingPress;

class Integrations
{
  public static function init()
  {
    Integrations\WooCommerce::init();
    Integrations\ACF::init();
    Integrations\APO::init();
    Integrations\Varnish::init();
    Integrations\Hosting::init();
    Integrations\I18n::init();
    Integrations\Plugins::init();
    Integrations\Themes::init();
    Integrations\PageBuilders::init();
  }
}
