<?php

namespace FlyingPress;

class License
{
  private static $surecart_key = 'pt_ZFDsoFWUW6hPMLqpQskUYDcz';
  private static $client;

  public static function init()
  {
    // Load licensing SDK
    add_action('init', [__CLASS__, 'load_sdk']);

    // Check if license key is set
    add_action('admin_notices', [__CLASS__, 'license_notice']);

    // License check every week
    add_action('flying_press_license_reactivation', [__CLASS__, 'update_license_status']);
    if (!wp_next_scheduled('flying_press_license_reactivation')) {
      wp_schedule_event(time(), 'weekly', 'flying_press_license_reactivation');
    }

    // Activate license on plugin activation
    register_activation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'activate_license']);

    // Check activation after upgrade
    add_action('flying_press_upgraded', [__CLASS__, 'check_activation']);
  }

  public static function load_sdk()
  {
    // Initialize the SureCart client
    if (!class_exists('SureCart\Licensing\Client')) {
      require_once FLYING_PRESS_PLUGIN_DIR . 'licensing/src/Client.php';
    }

    self::$client = new \SureCart\Licensing\Client(
      'FlyingPress',
      self::$surecart_key,
      FLYING_PRESS_FILE
    );
  }

  public static function activate_license($license_key)
  {
    if (!$license_key) {
      return;
    }

    $activated = self::$client->license()->activate($license_key);

    if (is_wp_error($activated)) {
      throw new \Exception($activated->get_error_message());
    }

    Config::update_config([
      'license_key' => $license_key,
      'license_active' => true,
      'license_status' => 'active',
    ]);

    return true;
  }

  public static function check_activation()
  {
    $config = Config::$config;

    if (!$config['license_key']) {
      return;
    }

    if ($config['license_active']) {
      return;
    }

    self::activate_license($config['license_key']);
  }

  public static function update_license_status()
  {
    $license_key = Config::$config['license_key'];

    if (!$license_key) {
      return;
    }

    $response = wp_remote_get("https://api.surecart.com/v1/public/licenses/$license_key", [
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . self::$surecart_key,
      ],
    ]);

    $body = wp_remote_retrieve_body($response);
    $license = json_decode($body, true);

    if (!isset($license['status'])) {
      return;
    }

    Config::update_config([
      'license_status' => $license['status'],
    ]);
  }

  public static function license_notice()
  {
    // Don't show notice on FlyingPress page
    if (isset($_GET['page']) && $_GET['page'] === 'flying-press') {
      return;
    }

    $config = Config::$config;

    $license_page = admin_url('admin.php?page=flying-press');

    // Add notice if the license is not activated
    if (!$config['license_active']) {
      echo "<div class='notice notice-error'>
              <p><b>FlyingPress</b>: Your license key is not activated. <a href='" .
        esc_url($license_page) .
        "'>Activate</a></p>
            </div>";
      return;
    }

    // Add notice if the license is invalid
    $status = $config['license_status'];
    if (!in_array($status, ['valid', 'active'])) {
      echo "<div class='notice notice-error'>
        <p><b>FlyingPress</b>: 
          Your license key is $status. Please <a href='" .
        esc_url($license_page) .
        "'>activate</a> your license key.
        </p>
      </div>";
    }
  }
}
