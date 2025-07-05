<?php

namespace FlyingPress;
use FlyingPress\Optimizer\{Bloat, CDN, CSS, Font, HTML, IFrame, Image, JavaScript, Video};

class Optimizer
{
  public static function init()
  {
    ob_start([__CLASS__, 'process_output']);
    JavaScript::init();
    Bloat::init();
    CSS::init();
  }

  private static function process_output($content)
  {
    if (!Caching::is_cacheable($content)) {
      return $content;
    }

    header('Cache-Control: no-store, s-maxage=0');
    header('Cloudflare-CDN-Cache-Control: no-store');

    if (is_user_logged_in()) {
      Caching::cache_page($content);
      return $content;
    }

    // Add url to queue if user visits an uncached page
    if (!isset($_SERVER['HTTP_X_FLYING_PRESS_PRELOAD'])) {
      Preload::preload_urls(
        [site_url($_SERVER['REQUEST_URI'])],
        time(),
        Utils::get_include_cookies()
      );
      return $content;
    }

    $unoptimized_content = $content;

    // Apply early optimizations
    $content = new HTML($content);
    $content = $content->setUid();
    $content = Font::add_display_swap_to_internal_styles($content);
    $content = Font::add_display_swap_to_google_fonts($content);
    $content = Font::optimize_google_fonts($content);
    $content = Font::optimize_inline_google_fonts($content);
    $content = CSS::minify($content);
    $content = CSS::self_host_third_party_css($content);
    $content = IFrame::add_youtube_placeholder($content);

    Image::parse_images($content);
    Image::add_width_height($content);
    Image::localhost_gravatars($content);

    $content = JavaScript::minify($content);
    $content = JavaScript::self_host_third_party_js($content);

    // Fetch optimizations from Cloud Optimizer or local cache
    if (!CloudOptimizer::fetch_optimizations($content)) {
      // Return 503 error if Cloud Optimizer fails
      status_header(503);
      wp_die('Error optimizing page.', 'FlyingPress Error', ['response' => 503]);
    }

    // Apply remaining optimizations requiring SSO
    $content = CSS::load_used_css($content);
    $content = CSS::lazy_render($content);
    $content = IFrame::lazy_load($content);
    $content = Video::lazy_load($content);
    $content = Font::preload_fonts($content);

    Image::exclude_above_fold($content);
    Image::lazy_load($content);
    Image::responsive_images($content);
    $content = Image::write_images($content);
    $content = Image::clean_data_images($content);
    $content = Image::preload($content);
    $content = Image::lazy_load_bg_elements($content);

    $content = JavaScript::inject_speculationrules($content);
    $content = JavaScript::move_module_scripts($content);

    // Clean up the content removing all the data-uid attributes
    $content = preg_replace('/\sdata-uid="\d*"/', '', $content);

    $content = JavaScript::delay_scripts($content);

    $content = JavaScript::inject_core_lib($content);

    $content = CDN::add_preconnect($content);
    $content = CDN::rewrite($content);

    $content = apply_filters('flying_press_optimization:after', $content);

    Caching::cache_page($content);

    return $content;
  }
}
