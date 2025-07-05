<?php

namespace FlyingPress\Integrations;

use FlyingPress\{Purge, Preload};

class PageBuilders
{
  protected static $post_types = [
    'ct_template', // Oxygen
    'bricks_template', // Bricks
    'elementor_library', // Elementor
    'elementor_snippet',
    'elementor_font',
    'elementor_icons',
    'elementor-hf',
    'wp_block', // Gutenberg
    'wp_navigation',
    'wp_template',
    'wp_template_part',
    'wp_global_styles',
    'fl-builder-template', // Beaver Builder
    'et_pb_layout', // Divi
    'et_header_layout',
    'et_footer_layout',
    'et_body_layout',
    'et_template',
    'et_code_snippet',
    'et_theme_options',
    'breakdance_template', // Breakdance
    'breakdance_header',
    'breakdance_footer',
    'breakdance_block',
    'brizy-layout', // Brizy
    'brizy-global-block',
  ];

  public static function init()
  {
    add_action('save_post', [__CLASS__, 'purge_on_template_update'], 10, 2);
  }

  public static function purge_on_template_update($post_id, $post)
  {
    // Check if post is published and post type is matched
    if (in_array($post->post_type, self::$post_types) && $post->post_status == 'publish') {
      Purge::purge_pages();
      Preload::preload_cache();
    }
  }
}
