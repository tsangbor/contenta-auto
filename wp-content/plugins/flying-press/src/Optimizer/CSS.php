<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Caching, Config, CloudOptimizer, Utils};
use Wa72\Url\Url;
use MatthiasMullie\Minify;

class CSS
{
  public static function init()
  {
    add_filter(
      'flying_press_download_external_file:before',
      [__CLASS__, 'self_host_third_party_fonts'],
      10,
      3
    );
  }

  public static function minify($html)
  {
    if (!Config::$config['css_js_minify']) {
      return $html;
    }

    // run preg match all to grab all the tags
    preg_match_all("/<link[^>]*\srel=['\"]stylesheet['\"][^>]*>/", $html, $stylesheets);

    // excluded keywords from filter
    $exclude_keywords = apply_filters('flying_press_exclude_from_minify:css', []);
    try {
      foreach ($stylesheets[0] as $stylesheet_tag) {
        // check if any of the exclude keywords are in the tag
        if (Utils::any_keywords_match_string($exclude_keywords, $stylesheet_tag)) {
          continue;
        }

        $stylesheet = new HTML($stylesheet_tag);
        $href = $stylesheet->href;
        // Convert relative path to absolute path
        $file_path = Caching::get_file_path_from_url($href);
        if (!is_file($file_path)) {
          continue;
        }
        $css = file_get_contents($file_path);
        // Generate hash based on the css content and CDN url
        // If CDN URL changes, new hash will be generated
        $hash =
          Config::$config['cdn'] && Config::$config['cdn_type'] == 'custom'
            ? md5($css . Config::$config['cdn_url'])
            : md5($css);

        $file_name = substr($hash, 0, 12) . '.' . basename($file_path);
        $minified_path = FLYING_PRESS_CACHE_DIR . $file_name;
        $minified_url = FLYING_PRESS_CACHE_URL . $file_name;

        if (!is_file($minified_path)) {
          $minifier = new Minify\CSS($css);
          $minified_css = $minifier->minify();
          $minified_css = self::rewrite_absolute_urls($minified_css, $href);
          $minified_css = Font::inject_display_swap($minified_css);
          $minified_css = CDN::rewrite($minified_css);
          file_put_contents($minified_path, $minified_css);
        }

        $html = str_replace($href, $minified_url, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function load_used_css($html)
  {
    if (!Config::$config['css_rucss'] || \is_user_logged_in()) {
      return $html;
    }

    $used_css = CloudOptimizer::$optimizations->used_css ?? '';

    // If used_css is not available return early
    if (empty($used_css)) {
      return $html;
    }

    // Inject critical CSS in the head
    $used_css_tag = '<style id="flying-press-css">' . $used_css . '</style>';
    $html = Utils::str_replace_first('</title>', '</title>' . PHP_EOL . $used_css_tag, $html);

    // Find stylesheets in HTML
    preg_match_all("/<link[^>]*\srel=['\"]stylesheet['\"][^>]*>/", $html, $stylesheets);

    foreach ($stylesheets[0] as $stylesheet_tag) {
      $stylesheet = new HTML($stylesheet_tag);

      // Skip print stylesheets
      if ($stylesheet->media == 'print') {
        continue;
      }

      //  Defer loading non critical scripts on user interaction
      $stylesheet->{'data-href'} = $stylesheet->href;
      unset($stylesheet->href);
      $html = str_replace($stylesheet_tag, $stylesheet, $html);
    }

    return $html;
  }

  private static function rewrite_absolute_urls($content, $base_url)
  {
    $regex = '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)|@import\s+[\'"]([^\'"]+\.[^\s]+)[\'"]/';

    $content = preg_replace_callback(
      $regex,
      function ($match) use ($base_url) {
        // Remove empty values
        $match = array_values(array_filter($match));
        $url_string = $match[0];
        $relative_url = $match[1];
        // Check if url is an internal fragment identifier (e.g., #svg-gradient-primary)
        if (strpos($relative_url, '#') === 0) {
          // Return the original string without modification
          return $url_string;
        }
        $absolute_url = Url::parse($relative_url);
        $absolute_url->makeAbsolute(Url::parse($base_url));
        return str_replace($relative_url, $absolute_url, $url_string);
      },
      $content
    );

    return $content;
  }

  public static function lazy_render($html)
  {
    if (!Config::$config['lazy_render']) {
      return $html;
    }

    $elements = CloudOptimizer::$optimizations->content_visibility_targets ?? [];

    foreach ($elements as $element) {
      // Find the element with matching uid
      preg_match('/<[^>]+data-uid="' . $element->uid . '"[^>]*>/i', $html, $matches);

      // If element is excluded from lazy render, continue
      if (Utils::any_keywords_match_string(Config::$config['lazy_render_excludes'], $matches[0])) {
        continue;
      }

      $element_tag = new HTML($matches[0]);

      $element_tag->style .= "content-visibility: auto;contain-intrinsic-size: auto {$element->height}px;";

      $html = str_replace($element_tag->original_tag, $element_tag, $html);
    }
    return $html;
  }

  public static function self_host_third_party_css($html)
  {
    if (!Config::$config['css_js_self_host_third_party']) {
      return $html;
    }

    try {
      // Get all the link tags with rel stylesheet and href
      preg_match_all("/<link[^>]*\srel=['\"]stylesheet['\"][^>]*>/", $html, $stylesheets);

      foreach ($stylesheets[0] as $stylesheet_tag) {
        $stylesheet = new HTML($stylesheet_tag);

        // Download the external file if allowed
        $url = Utils::download_external_file($stylesheet->href);

        // If the file is not downloaded, continue
        if (!$url) {
          continue;
        }

        // Remove resource hints
        $html = Utils::remove_resource_hints($stylesheet->href, $html);

        // Save the original href
        $stylesheet->{'data-origin-href'} = $stylesheet->href;

        // Set the locally hosted file as the new href
        $stylesheet->href = $url;

        // Remove integrity and crossorigin attributes if exist
        unset($stylesheet->integrity);
        unset($stylesheet->crossorigin);

        // Replace the href with the locally hosted file
        $html = str_replace($stylesheet_tag, $stylesheet, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function self_host_third_party_fonts($content, $url, $extension)
  {
    // If the file is not a CSS file, return early
    if ($extension !== 'css') {
      return $content;
    }

    // Convert relative URLs to absolute URLs
    $content = self::rewrite_absolute_urls($content, $url);

    // Get a list of the font files
    $font_urls = Font::get_font_urls($content);

    if (empty($font_urls)) {
      return $content;
    }

    // Download the font files
    Font::download_fonts($font_urls, FLYING_PRESS_CACHE_DIR);

    // Replace the font URLs with the cached URLs
    foreach ($font_urls as $font_url) {
      if (filesize(FLYING_PRESS_CACHE_DIR . basename($font_url)) < 100) {
        continue;
      }
      $content = str_replace($font_url, FLYING_PRESS_CACHE_URL . basename($font_url), $content);
    }

    return $content;
  }
}
