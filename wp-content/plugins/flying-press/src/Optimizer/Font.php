<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Config, CloudOptimizer, Utils};

class Font
{
  public static function add_display_swap_to_google_fonts($html)
  {
    if (!Config::$config['fonts_display_swap']) {
      return $html;
    }

    // get all link tags with google fonts
    preg_match_all(
      '/<link[^>]*\s+href=[\'"](https:\/\/|\/\/)fonts\.googleapis\.com\/css[^\'"]+[\'"][^>]*>/i',
      $html,
      $googlefonts
    );

    try {
      foreach ($googlefonts[0] as $google_font_tag) {
        $google_font = new HTML($google_font_tag);

        $google_font->href = preg_match('/display=\w+/', $google_font->href)
          ? preg_replace('/display=\w+/', 'display=swap', $google_font->href)
          : $google_font->href . '&display=swap';

        $html = str_replace($google_font_tag, $google_font, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function add_display_swap_to_internal_styles($html)
  {
    if (!Config::$config['fonts_display_swap']) {
      return $html;
    }

    // get all style tags
    preg_match_all('/<style[^>]*>([^<]*)<\/style>/i', $html, $styles);
    try {
      foreach ($styles[0] as $style_tag) {
        $style = new HTML($style_tag);
        $css = $style->getContent();
        $css = self::inject_display_swap($css);
        $style->setContent($css);
        $html = str_replace($style_tag, $style, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function optimize_google_fonts($html)
  {
    if (!Config::$config['fonts_optimize_google']) {
      return $html;
    }

    // Remove all preconnect, preload, prefech, and dns-prefetch tags
    $html = preg_replace(
      '/<link[^>]*(?:preload|preconnect|prefetch)[^>]*(?:fonts\.gstatic\.com|fonts\.googleapis\.com)[^>]*>/i',
      '',
      $html
    );

    // Find all Google Fonts
    preg_match_all(
      '/<link[^>]*\s+href=[\'"](https:\/\/|\/\/)fonts\.googleapis\.com\/css[^\'"]+[\'"][^>]*>/i',
      $html,
      $googlefonts
    );
    try {
      foreach ($googlefonts[0] as $google_font_tag) {
        $google_font = new HTML($google_font_tag);
        $href = $google_font->href;
        $hash = substr(md5($href), 0, 12);
        $file_name = "$hash.google-font.css";
        $file_path = FLYING_PRESS_CACHE_DIR . $file_name;
        $file_url = FLYING_PRESS_CACHE_URL . $file_name;
        if (!is_file($file_path)) {
          self::self_host_style_sheet($href, $file_path);
        }
        $google_font->href = $file_url;
        $html = str_replace($google_font_tag, $google_font, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function preload_fonts($html)
  {
    if (!Config::$config['fonts_preload']) {
      return $html;
    }

    $font_urls = CloudOptimizer::$optimizations->critical_fonts ?? [];

    $preload_tags = '';
    try {
      foreach ($font_urls as $font_url) {
        // get file extension from url and create type attribute
        $type = 'font/' . pathinfo(parse_url($font_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $preload_tags .= "<link rel='preload' href='$font_url' as='font' type='$type' fetchpriority='high' crossorigin='anonymous'>";
      }

      $html = Utils::str_replace_first('</title>', '</title>' . PHP_EOL . $preload_tags, $html);
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function inject_display_swap($content)
  {
    if (!Config::$config['fonts_display_swap']) {
      return $content;
    }

    // Remove existing font-display: xxx
    $content = preg_replace('/font-display:\s*(swap|block|fallback|optional);?/', '', $content);

    // Add font-display: swap
    return preg_replace('/@font-face\s*{/', '@font-face{font-display:swap;', $content);
  }

  public static function download_fonts($urls, $save_path)
  {
    // Initialize a single cURL handle for reuse
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_USERAGENT, Utils::$user_agent);
    curl_setopt($curl_handle, CURLOPT_HEADER, 0);
    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($curl_handle, CURLOPT_ENCODING, ''); // Accept encoding responses (gzip, deflate)

    // Loop through each URL
    foreach ($urls as $url) {
      $file = $save_path . '/' . basename($url);

      // Open file pointer in binary write mode for better performance and compatibility
      $file_pointer = fopen($file, 'wb');

      // Set cURL options specific to this request
      curl_setopt($curl_handle, CURLOPT_URL, $url);
      curl_setopt($curl_handle, CURLOPT_FILE, $file_pointer);

      // Execute the cURL session (download the file)
      curl_exec($curl_handle);

      // Close the file pointer
      fclose($file_pointer);
    }

    // Close the cURL session
    curl_close($curl_handle);
  }

  private static function self_host_style_sheet($url, $file_path)
  {
    // If URL starts with "//", add https
    if (substr($url, 0, 2) === '//') {
      $url = 'https:' . $url;
    }

    // Decode URL (e.g. https://fonts.googleapis.com/css?display=swap&#038;family=Nunito%3A400%2C700&#038;ver=c63b)
    $url = html_entity_decode($url);

    // Download style sheet
    $css_file_response = wp_remote_get($url, [
      'user-agent' => Utils::$user_agent,
      'httpversion' => '2.0',
    ]);

    // Check Google Fonts returned response
    if (
      is_wp_error($css_file_response) ||
      wp_remote_retrieve_response_code($css_file_response) !== 200
    ) {
      throw new \Exception('Failed to download Google Fonts CSS: ' . $url);
    }

    // Extract body (CSS)
    $css = $css_file_response['body'];

    // Get list of fonts (woff2 files) inside the CSS
    $font_urls = self::get_font_urls($css);

    self::download_fonts($font_urls, FLYING_PRESS_CACHE_DIR);

    foreach ($font_urls as $font_url) {
      $cached_font_url = FLYING_PRESS_CACHE_URL . basename($font_url);
      // Skip if file is too small
      if (filesize(FLYING_PRESS_CACHE_DIR . basename($font_url)) < 100) {
        continue;
      }
      $css = str_replace($font_url, $cached_font_url, $css);
    }

    file_put_contents($file_path, $css);
  }

  public static function get_font_urls($css)
  {
    // Extract font urls like: https://fonts.gstatic.com/s/roboto/v20/KFOmCnqEu92Fr1Mu4mxKKTU1Kg.woff2
    $regex = '/url\([\'"]?(https?:\/\/[^\'"\)]+\.(woff2|woff|ttf|svg|otf|eot))[^\'"]*[\'"]?\)/i';
    preg_match_all($regex, $css, $matches);
    return array_unique($matches[1]);
  }

  public static function optimize_inline_google_fonts($html)
  {
    if (!Config::$config['fonts_optimize_google']) {
      return $html;
    }

    // Get all inline google fonts
    preg_match_all(
      '/@import[\s]*url\([\'"](https:\/\/fonts\.googleapis\.com\/.*)[\'"]\)/i',
      $html,
      $matches
    );

    foreach ($matches[1] as $match) {
      $href = $match;
      $hash = substr(md5($href), 0, 12);
      $file_name = "$hash.google-font.css";
      $file_path = FLYING_PRESS_CACHE_DIR . $file_name;
      $file_url = FLYING_PRESS_CACHE_URL . $file_name;
      if (!is_file($file_path)) {
        self::self_host_style_sheet($href, $file_path);
      }

      $html = str_replace($match, $file_url, $html);
    }

    return $html;
  }
}
