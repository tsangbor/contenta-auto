<?php

namespace FlyingPress;

class Auth
{
  public static function is_allowed()
  {
    $current_user = wp_get_current_user();
    $allowed_roles = apply_filters('flying_press_allowed_roles', ['administrator', 'editor']);

    // Allow access if the user is a super admin or has one of the allowed roles
    return \is_super_admin($current_user->ID) ||
      array_intersect($current_user->roles, $allowed_roles);
  }
}
