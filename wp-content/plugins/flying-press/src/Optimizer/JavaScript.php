<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Caching, Config, CloudOptimizer, Utils};
use MatthiasMullie\Minify;

class JavaScript
{
  public static function init()
  {
    add_action('init', [__CLASS__, 'disable_native_speculationrules']);
  }

  public static function minify($html)
  {
    if (!Config::$config['css_js_minify']) {
      return $html;
    }

    // get all the scripts with src attribute
    preg_match_all('/<script[^>]*src=[\'"][^\'"]+[\'"][^>]*><\/script>/i', $html, $scripts);

    // Get excluded keywords from filter
    $exclude_keywords = apply_filters('flying_press_exclude_from_minify:js', []);

    try {
      // loop through all the scripts
      foreach ($scripts[0] as $script) {
        // skip if script is excluded
        if (Utils::any_keywords_match_string($exclude_keywords, $script)) {
          continue;
        }

        $script = new HTML($script);
        $src = $script->src;
        $file_path = Caching::get_file_path_from_url($src);

        // Skip if file doesn't exist or empty
        if (!is_file($file_path) || !filesize($file_path)) {
          continue;
        }

        // Generate hash
        $hash = substr(hash_file('md5', $file_path), 0, 12);

        // If already minified, add hash to the query string and skip minification
        if (preg_match('/\.min\.js/', $src)) {
          $html = str_replace($src, strtok($src, '?') . "?ver=$hash", $html);
          continue;
        }

        // Generate minified file path and URL
        $file_name = $hash . '.' . basename($file_path);
        $minified_path = FLYING_PRESS_CACHE_DIR . $file_name;
        $minified_url = FLYING_PRESS_CACHE_URL . $file_name;

        // Create minified version if it doesn't exist
        if (!is_file($minified_path)) {
          $minifier = new Minify\JS($file_path);
          $minifier->minify($minified_path);
        }

        // Check if minified version is smaller than original
        $original_file_size = filesize($file_path);
        $minified_file_size = filesize($minified_path);
        $wasted_bytes = $original_file_size - $minified_file_size;
        $wasted_percent = ($wasted_bytes / $original_file_size) * 100;

        if ($wasted_bytes < 2048 || $wasted_percent < 10) {
          $minified_url = strtok($src, '?') . "?ver=$hash";
        }

        $html = str_replace($src, $minified_url, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function move_module_scripts($html)
  {
    // Regex to capture both self-closing and standard script tags with type="module"
    $module_scripts_regex = '/<script[^>]*type=["\']module["\'][^>]*?(?:><\/script>|\/>)/i';
    preg_match_all($module_scripts_regex, $html, $matches);

    // If no module scripts found, return the original HTML
    if (empty($matches[0])) {
      return $html;
    }

    // Extract the found module scripts
    $module_scripts = implode("\n", $matches[0]);

    // Remove the module scripts from the original HTML
    $html = preg_replace($module_scripts_regex, '', $html);

    // Place the module scripts before the closing body tag
    return preg_replace('/<\/body>(?!.*<\/body>)/is', "$module_scripts</body>", $html);
  }

  public static function delay_scripts($html)
  {
    try {
      if (!Config::$config['js_delay']) {
        return $html;
      }

      // Get all the scripts
      preg_match_all('/<script[^>]*>([\s\S]*?)<\/script>/i', $html, $scripts);

      $external_domains = CloudOptimizer::$optimizations->external_domains ?? [];

      $exclude_keywords = [
        ...Config::$config['js_delay_excludes'],
        ...Config::$config['js_delay_third_party_excludes'],
      ];

      // loop through all the scripts
      foreach ($scripts[0] as $script_tag) {
        // If the script tag is excluded, skip
        if (Utils::any_keywords_match_string($exclude_keywords, $script_tag)) {
          continue;
        }

        $script = new HTML($script_tag);

        // Skip non-standard scripts
        if ($script->type && $script->type !== 'text/javascript') {
          continue;
        }

        // Skip Rest API script injected by FlyingPress
        if ($script->id === 'flying-press-rest') {
          continue;
        }

        // Skip empty inline scripts
        if (!$script->src && empty(trim($script->getContent()))) {
          continue;
        }

        $script->{"data-loading-method"} =
          Config::$config['js_delay_method'] === 'user-interaction'
            ? 'user-interaction'
            : (Config::$config['js_delay_third_party'] &&
            Utils::any_keywords_match_string($external_domains, $script_tag)
              ? 'user-interaction'
              : 'idle');

        // Convert script to data URI if it's inline
        $src = $script->src ?? 'data:text/javascript,' . rawurlencode($script->getContent());

        $script->{'data-src'} = $src;
        unset($script->src);
        $script->setContent('');

        // Replace original script tag in HTML
        $html = str_replace($script_tag, $script, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function inject_core_lib($html)
  {
    $js_code = file_get_contents(FLYING_PRESS_PLUGIN_DIR . 'assets/core.min.js');

    // create script tag and  add append it to the  body tag
    $script_tag = PHP_EOL . "<script>$js_code</script>" . PHP_EOL;

    return preg_replace('/<\/body>(?!.*<\/body>)/is', "$script_tag</body>", $html);
  }

  public static function inject_speculationrules($html)
  {
    if (!Config::$config['cache_link_prefetch']) {
      return $html;
    }

    // Skip if logged in
    if (is_user_logged_in()) {
      return $html;
    }

    // Prepare speculationrules
    $speculationrules = [
      'prefetch' => [
        [
          'source' => 'document',
          'where' => [
            'and' => [
              ['href_matches' => '/*'],
              [
                'not' => [
                  'href_matches' => [
                    '*.php',
                    '\/wp-(admin|includes|content|login|signup|json)(.*)?',
                    '\/?.*',
                    '\/*(cart|checkout|logout)\/*',
                  ],
                ],
              ],
            ],
          ],
          'eagerness' => 'moderate',
        ],
      ],
    ];

    $script_tag = new HTML('<script></script>');
    $script_tag->type = 'speculationrules';
    $script_tag->setContent(json_encode($speculationrules, true));

    $html = str_replace('</head>', $script_tag . '</head>', $html);

    return $html;
  }

  public static function disable_native_speculationrules()
  {
    if (!Config::$config['cache_link_prefetch']) {
      return;
    }

    add_filter('wp_speculation_rules_configuration', '__return_null');
  }

  public static function self_host_third_party_js($html)
  {
    if (!Config::$config['css_js_self_host_third_party']) {
      return $html;
    }

    try {
      // Find all the script tags with src attribute
      preg_match_all('/<script[^>]*src=[\'"][^\'"]+[\'"][^>]*><\/script>/i', $html, $scripts);

      foreach ($scripts[0] as $script_tag) {
        $script = new HTML($script_tag);

        // Download the external file if allowed
        $url = Utils::download_external_file($script->src);

        if (!$url) {
          continue;
        }

        // Remove resource hints
        $html = Utils::remove_resource_hints($script->src, $html);

        // Save the original src
        $script->{'data-origin-src'} = $script->src;

        // Set the locally hosted file as the new src
        $script->src = $url;

        // Remove integrity and crossorigin attributes if exist
        unset($script->integrity);
        unset($script->crossorigin);

        // Replace the source with the new file
        $html = str_replace($script_tag, $script, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }
}
