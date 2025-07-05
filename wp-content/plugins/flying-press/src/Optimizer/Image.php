<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Caching, Config, CloudOptimizer, Utils};

class Image
{
  private static $images = [];
  private static $data_images = [];

  public static function parse_images($html)
  {
    // Remove tags that are not needed for image parsing
    $html_without_scripts = preg_replace(
      '/<script.*?<\/script>|<noscript.*?<\/noscript>|<template.*?<\/template>/is',
      '',
      $html
    );

    // Find all images with src attribute
    preg_match_all('/<img[^>]+src=["\'][^"\'>]+["\'][^>]*>/', $html_without_scripts, $images);
    $images = $images[0];

    // Filter out base64 images
    $data_images = array_filter($images, function ($image) {
      return strpos($image, 'data:image') !== false;
    });

    // Get diff between images and data_images
    $images = array_diff($images, $data_images);

    // Parse data images using HTML class
    $data_images = array_map(function ($image) {
      return new HTML($image);
    }, $data_images);

    // Parse image using HTML class
    $images = array_map(function ($image) {
      return new HTML($image);
    }, $images);

    // Store images and base64 images in the static variables respectively
    self::$images = $images;
    self::$data_images = $data_images;
  }

  public static function add_width_height($html)
  {
    if (!Config::$config['properly_size_images']) {
      return $html;
    }

    try {
      foreach (self::$images as $image) {
        // get src attribute
        $src = $image->src;

        // Skip if both width and height are already set
        if (is_numeric($image->width) && is_numeric($image->height)) {
          continue;
        }

        // Get width and height
        $dimensions = self::get_dimensions($src);

        // Skip if no dimensions found
        if (!$dimensions) {
          continue;
        }

        // Add missing width and height attributes
        $ratio = $dimensions['width'] / $dimensions['height'];

        if (!is_numeric($image->width) && !is_numeric($image->height)) {
          $image->width = $dimensions['width'];
          $image->height = $dimensions['height'];
        } elseif (!is_numeric($image->width)) {
          $image->width = $image->height * $ratio;
        } elseif (!is_numeric($image->height)) {
          $image->height = $image->width / $ratio;
        }
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    }
  }

  public static function exclude_above_fold($html)
  {
    $excluded_image_urls = CloudOptimizer::$optimizations->critical_images ?? [];

    foreach (self::$images as $key => $image) {
      if (in_array($image->{'data-uid'}, $excluded_image_urls)) {
        $image->loading = 'eager';
      }
    }
  }

  public static function lazy_load($html)
  {
    if (!Config::$config['lazy_load']) {
      return $html;
    }

    $default_exclude_keywords = ['eager', 'skip-lazy'];
    $user_exclude_keywords = Config::$config['lazy_load_exclusions'];

    // Merge default and user excluded keywords
    $exclude_keywords = array_merge($default_exclude_keywords, $user_exclude_keywords);

    try {
      foreach (self::$images as $image) {
        // Image is excluded from lazy loading
        if (Utils::any_keywords_match_string($exclude_keywords, $image)) {
          $image->loading = 'eager';
          $image->fetchpriority = 'high';
          $image->decoding = 'async';
        } else {
          $image->loading = 'lazy';
          $image->fetchpriority = 'low';
        }
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    }
  }

  public static function responsive_images($html)
  {
    if (!Config::$config['properly_size_images']) {
      return $html;
    }

    // Get all images from the page
    $images = array_filter(self::$images, function ($image) {
      return strpos($image->src, site_url()) !== false;
    });

    try {
      foreach ($images as $image) {
        // Skip images with loading="eager" attribute
        if ($image->loading === 'eager') {
          continue;
        }

        // Skip SVG images
        if (strpos($image->src, '.svg') !== false) {
          continue;
        }

        // Skip if width and height are not set
        if (!is_numeric($image->width) || !is_numeric($image->height)) {
          continue;
        }

        if ($image->srcset || ($image->srcset = self::generate_srcset($image))) {
          // Set sizes="auto" if srcset is present or successfully generated
          $image->sizes = 'auto';
        } else {
          // Remove srcset and sizes if srcset is unavailable
          unset($image->srcset, $image->sizes);
        }
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    }

    return $html;
  }

  private static function generate_srcset($image)
  {
    // Extract the attachment ID from the image URL
    $attachment_id = attachment_url_to_postid(preg_replace('/-\d+x\d+/', '', $image->src));

    // Use wp_get_attachment_image_srcset to generate the srcset
    return wp_get_attachment_image_srcset($attachment_id, [$image->width, $image->height]);
  }

  public static function localhost_gravatars($html)
  {
    if (!Config::$config['self_host_gravatars']) {
      return $html;
    }

    try {
      foreach (self::$images as $image) {
        if (strpos($image->src, 'gravatar.com/avatar/') === false) {
          continue;
        }

        // Get the self-hosted Gravatar URL for src
        $self_hosted_url = self::get_self_hosted_gravatar($image->src);

        // Change src to self hosted url
        $image->src = $self_hosted_url;

        // Skip if image does not have srcset
        if (!$image->srcset) {
          continue;
        }

        foreach (explode(',', $image->srcset) as $descriptor) {
          // Extract the URL before the first space
          // Use the entire descriptor if no space is found.
          $url = strstr(trim($descriptor), ' ', true) ?: trim($descriptor);

          // Get the self-hosted Gravatar URL for srcset
          $self_hosted_url = self::get_self_hosted_gravatar($url);

          // Change srcset to self hosted urls
          $image->srcset = str_replace($url, $self_hosted_url, $image->srcset);
        }
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    }

    return $html;
  }

  private static function get_self_hosted_gravatar($url)
  {
    $file_name = 'gravatar-' . substr(md5($url), 0, 12) . '.png';

    if (!file_exists(FLYING_PRESS_CACHE_DIR . $file_name)) {
      $gravatar_request = wp_remote_get($url);
      $gravatar = wp_remote_retrieve_body($gravatar_request);
      file_put_contents(FLYING_PRESS_CACHE_DIR . $file_name, $gravatar);
    }

    return FLYING_PRESS_CACHE_URL . $file_name;
  }

  public static function write_images($html)
  {
    foreach (self::$images as $image) {
      $html = str_replace($image->original_tag, $image, $html);
    }
    return $html;
  }

  public static function lazy_load_bg_elements($html)
  {
    if (!Config::$config['lazy_load']) {
      return $html;
    }

    // Add styles that will be used to hide the background image
    $html = Utils::str_replace_first(
      '</title>',
      '</title>' .
        PHP_EOL .
        '<style>.flying-press-lazy-bg{background-image:none!important;}</style>',
      $html
    );

    // Get offscreen elements with background images
    $element_uids = CloudOptimizer::$optimizations->non_critical_bg_elems ?? [];

    // Exclude keywords
    $exclusions = Config::$config['lazy_load_exclusions'];

    try {
      foreach ($element_uids as $element_uid) {
        // Find the element with matching uid
        preg_match('/<[^>]+data-uid="' . $element_uid . '"[^>]*>/i', $html, $matches);

        $element = new HTML($matches[0]);

        // Skip if element is in exclusions
        if (Utils::any_keywords_match_string($exclusions, $element->original_tag)) {
          continue;
        }

        $element->class .= ' flying-press-lazy-bg';

        $html = str_replace($element->original_tag, $element, $html);
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function preload($html)
  {
    // Filter self::$images to get only images with loading="eager"
    $images = array_filter(self::$images, function ($image) {
      return $image->loading === 'eager';
    });

    $preload_images = [];

    $critical_bg_images = CloudOptimizer::$optimizations->critical_bg_images ?? [];
    $critical_video_posters = CloudOptimizer::$optimizations->critical_video_posters ?? [];

    foreach ([...$critical_bg_images, ...$critical_video_posters] as $critical_image_url) {
      $preload_images[] = "<link rel='preload' href='$critical_image_url' as='image' fetchpriority='high' />";
    }

    try {
      foreach ($images as $image) {
        $src = $image->src;
        $srcset = $image->srcset;
        $sizes = $image->sizes;
        $preload_images[] = "<link rel='preload' href='$src' as='image' imagesrcset='$srcset' imagesizes='$sizes'/>";
      }

      // Get unique preload tags
      $preload_images = array_unique($preload_images);

      // Convert array to string
      $preload_image_tags = implode(PHP_EOL, $preload_images);

      // Add preload tags after head tag opening
      $html = Utils::str_replace_first(
        '</title>',
        '</title>' . PHP_EOL . $preload_image_tags,
        $html
      );
    } catch (\Exception $e) {
      error_log($e->getMessage());
    } finally {
      return $html;
    }
  }

  public static function clean_data_images($html)
  {
    foreach (self::$data_images as $image) {
      unset($image->loading, $image->fetchpriority, $image->decoding);
      $html = str_replace($image->original_tag, $image, $html);
    }
    return $html;
  }

  private static function get_dimensions($url)
  {
    try {
      // Extract width if found the the url. For example something-100x100.jpg
      if (preg_match('/(?:.+)-([0-9]+)x([0-9]+)\.(jpg|jpeg|png|gif|svg)$/', $url, $matches)) {
        list($_, $width, $height) = $matches;
        return ['width' => $width, 'height' => $height];
      }

      // Get width and height for Gravatar images
      if (strpos($url, 'gravatar.com/avatar/') !== false) {
        $query_string = parse_url($url, PHP_URL_QUERY);
        parse_str($query_string ?? '', $query_vars);
        $size = $query_vars['s'] ?? 80;
        return ['width' => $size, 'height' => $size];
      }

      $file_path = Caching::get_file_path_from_url($url);

      if (!is_file($file_path)) {
        return false;
      }

      // Get width and height from svg
      if (
        file_exists($file_path) &&
        is_readable($file_path) &&
        pathinfo($file_path, PATHINFO_EXTENSION) === 'svg' &&
        filesize($file_path) > 0
      ) {
        $xml = simplexml_load_file($file_path);
        $attr = $xml->attributes();
        $viewbox = explode(' ', $attr->viewBox);
        $width =
          isset($attr->width) && preg_match('/\d+/', $attr->width, $value)
            ? (int) $value[0]
            : (count($viewbox) == 4
              ? (int) $viewbox[2]
              : null);
        $height =
          isset($attr->height) && preg_match('/\d+/', $attr->height, $value)
            ? (int) $value[0]
            : (count($viewbox) == 4
              ? (int) $viewbox[3]
              : null);
        if ($width && $height) {
          return ['width' => $width, 'height' => $height];
        }
      }

      // Get image size by checking the file
      list($width, $height) = getimagesize($file_path);
      if ($width && $height) {
        return ['width' => $width, 'height' => $height];
      }
    } catch (\Exception $e) {
      return false;
    }
  }
}
