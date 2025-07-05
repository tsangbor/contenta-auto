<?php

namespace FlyingPress\Integrations;

class I18n
{
  public static function init()
  {
    I18n\WeGlot::init();
    I18n\WPML::init();
    I18n\Polylang::init();
    I18n\TranslatePress::init();
  }
}
