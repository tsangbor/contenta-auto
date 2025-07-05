<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Config, CloudOptimizer, Utils};

class IFrame
{
  public static function add_youtube_placeholder($html)
  {
    if (!Config::$config['youtube_placeholder']) {
      return $html;
    }

    // Get all iframes
    preg_match_all(
      '/<iframe[^>]+\bsrc=["\'](?:https?:)?\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com)\/[^"\']+\b[^>]*><\/iframe>/',
      $html,
      $iframes
    );

    // No iframes found
    if (empty($iframes[0])) {
      return $html;
    }

    try {
      foreach ($iframes[0] as $iframe_tag) {
        $iframe = new HTML($iframe_tag);
        $title = $iframe->title;
        $src = $iframe->src;
        $src .= preg_match('/\?/', $src) ? '&autoplay=1' : '?autoplay=1';
        $placeholder_url = self::get_self_hosted_placeholder($src) ?? false;

        if (!$placeholder_url) {
          return $html;
        }

        $placeholder_tag = "<span class='flying-press-youtube' data-src='$src' onclick='load_flying_press_youtube_video(this)'>
        <img src='$placeholder_url' width='1280' height='720' alt='$title'/>
        <svg xmlns='http://www.w3.org/2000/svg' width=68 height=48><path fill=red d='M67 8c-1-3-3-6-6-6-5-2-27-2-27-2S12 0 7 2C4 2 2 5 1 8L0 24l1 16c1 3 3 6 6 6 5 2 27 2 27 2s22 0 27-2c3 0 5-3 6-6l1-16-1-16z'/><path d='M45 24L27 14v20' fill=#fff /></svg>
        </span>";

        $html = str_replace($iframe_tag, $placeholder_tag, $html);
      }
      $html = self::inject_css_js($html);
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function lazy_load($html)
  {
    if (!Config::$config['lazy_load']) {
      return $html;
    }

    $lazy_load_exclusions = Config::$config['lazy_load_exclusions'];
    $excluded_iframes = CloudOptimizer::$optimizations->critical_iframes ?? [];
    // Merge default and user excluded keywords
    $exclude_keywords = array_merge($lazy_load_exclusions, $excluded_iframes);

    // get all iframes
    preg_match_all('/<iframe\s+[^>]*src=(["\']).*?\1[^>]*>/', $html, $iframes);

    try {
      foreach ($iframes[0] as $iframe_tag) {
        // Exclude critical iframes
        if (Utils::any_keywords_match_string($exclude_keywords, $iframe_tag)) {
          continue;
        }

        $iframe = new HTML($iframe_tag);

        // Get src or data-src (data-src is used by some other lazy loading plugins)
        $src = $iframe->src ?? $iframe->{'data-src'};

        // Remove src and data-src
        unset($iframe->src);
        unset($iframe->{'data-src'});

        // Set src to data-lazy-src
        $iframe->{'data-lazy-src'} = $src;

        $html = str_replace($iframe_tag, $iframe, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  private static function inject_css_js($html)
  {
    // JavaScript for YouTube placeholder
    $script_tag =
      '<script>function load_flying_press_youtube_video(t){let e=document.createElement("iframe");e.setAttribute("src",t.getAttribute("data-src")),e.setAttribute("frameborder","0"),e.setAttribute("allowfullscreen","1"),e.setAttribute("allow","autoplay; encrypted-media; gyroscope;"),t.innerHTML="",t.appendChild(e)}</script>';
    $html = preg_replace('/<\/body>(?!.*<\/body>)/is', "$script_tag</body>", $html);

    // CSS for YouTube placeholder
    $css = ".flying-press-youtube{display:inline-block;position:relative;width:100%;padding-bottom:56.23%;overflow:hidden;cursor:pointer}
      .flying-press-youtube:hover{filter:brightness(.9)}
      .flying-press-youtube img{position:absolute;inset:0;width:100%;height:auto;margin:auto}
      .flying-press-youtube svg{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)}
      .flying-press-youtube iframe{position:absolute;inset:0;width:100%;height:100%}";

    // Remove "wp-has-aspect-ratio" class from the html
    // Otherwise, the padding-bottom will be added to the iframe
    $html = str_replace('wp-has-aspect-ratio', '', $html);

    return str_replace('</head>', "<style>$css</style></head>", $html);
  }

  private static function get_self_hosted_placeholder($src)
  {
    $placeholder = md5($src) . '.jpg';
    $placeholder_file = FLYING_PRESS_CACHE_DIR . $placeholder;
    $placeholder_url = FLYING_PRESS_CACHE_URL . $placeholder;

    // If placeholder already exists , return the placeholder url
    if (file_exists($placeholder_file)) {
      return $placeholder_url;
    }

    // Get the appropriate placeholder url
    $response = wp_remote_get("https://video-thumbnails.flyingpress.com/?url=$src");

    // If there's any error, return
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return false;
    }

    //  Download the placeholder
    ['url' => $url] = json_decode(wp_remote_retrieve_body($response), true);
    $image_request = wp_remote_get($url);
    if (is_wp_error($image_request) || wp_remote_retrieve_response_code($image_request) !== 200) {
      return false;
    }

    // If everything goes well save the downloaded image
    $image = wp_remote_retrieve_body($image_request);
    file_put_contents($placeholder_file, $image);
    return $placeholder_url;
  }
}
