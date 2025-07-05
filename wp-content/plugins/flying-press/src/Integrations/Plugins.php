<?php

namespace FlyingPress\Integrations;

class Plugins
{
  public static function init()
  {
    Plugins\MultiCurrency\WCML::init();
    Plugins\MultiCurrency\AeliaCurrency::init();
    Plugins\MultiCurrency\YithCurrency::init();
    Plugins\MultiCurrency\Curcy::init();
    Plugins\Marketing\PrettyLinks::init();
    Plugins\Optimization\SiteGround::init();
    Plugins\Optimization\Breeze::init();
    Plugins\Optimization\EWWW::init();
    Plugins\Optimization\ShortPixelAI::init();
    Plugins\Optimization\Perfmatters::init();
    Plugins\Optimization\NginxHelper::init();
  }
}
