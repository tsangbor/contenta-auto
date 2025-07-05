<?php

namespace FlyingPress;

class Cron
{
  public static function init()
  {
    add_action('cron_schedules', [__CLASS__, 'add_custom_schedules']);
  }

  public static function add_custom_schedules($schedules)
  {
    $schedules['2hours'] = [
      'interval' => 7200,
      'display' => 'Once every 2 hours',
    ];

    $schedules['6hours'] = [
      'interval' => 21600,
      'display' => 'Once every 6 hours',
    ];

    $schedules['monthly'] = [
      'display' => 'Once monthly',
      'interval' => 2635200,
    ];

    return $schedules;
  }
}
