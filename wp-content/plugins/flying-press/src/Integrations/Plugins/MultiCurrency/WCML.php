<?php
namespace FlyingPress\Integrations\Plugins\MultiCurrency;

class WCML
{
  public static function init()
  {
    add_action('init', [__CLASS__, 'setup_integration']);
  }

  public static function setup_integration()
  {
    if (!function_exists('\WCML\functions\getClientCurrency')) {
      return;
    }

    self::set_currency_cookie();

    add_action('wcml_switch_currency', [__CLASS__, 'set_currency_cookie'], 2, 1);
    add_action('deactivate_plugin', [__CLASS__, 'remove_currency_cookie'], 5, 1);
    add_filter('flying_press_cache_include_cookies', [__CLASS__, 'include_cookies']);
  }

  // Set  cookie for current currency
  public static function set_currency_cookie($currency = '')
  {
    if (!wcml_is_multi_currency_on()) {
      return;
    }

    $expiry = time() + 14 * DAY_IN_SECONDS;
    if (!isset($_COOKIE['wcml_currency']) && empty($currency)) {
      $currency = \WCML\functions\getClientCurrency();
      setcookie('wcml_currency', $currency, $expiry, COOKIEPATH, COOKIE_DOMAIN, false);
    }

    if (
      !empty($currency) &&
      isset($_COOKIE['wcml_currency']) &&
      $currency !== $_COOKIE['wcml_currency']
    ) {
      setcookie('wcml_currency', $currency, $expiry, COOKIEPATH, COOKIE_DOMAIN, false);
    }
  }

  // Remove wcml_currency cookie
  public static function remove_currency_cookie($plugin)
  {
    if (
      isset($_COOKIE['wcml_currency']) &&
      $plugin === 'woocommerce-multilingual/wpml-woocommerce.php'
    ) {
      // Unset the cookie
      unset($_COOKIE['wcml_currency']);
      setcookie('wcml_currency', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false);
    }
  }

  public static function include_cookies($cookies)
  {
    return [...$cookies, 'wcml_currency'];
  }
}
