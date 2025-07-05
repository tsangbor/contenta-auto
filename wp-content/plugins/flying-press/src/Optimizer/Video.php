<?php

namespace FlyingPress\Optimizer;

use FlyingPress\{Config, CloudOptimizer, Utils};

class Video
{
  public static function lazy_load($html)
  {
    if (!Config::$config['lazy_load']) {
      return $html;
    }

    // Add critical video posters to the exclusion list
    $lazy_load_exclusions = [
      ...Config::$config['lazy_load_exclusions'],
      ...CloudOptimizer::$optimizations->critical_video_posters ?? [],
    ];

    // Get all videos
    preg_match_all('/<video\s+[^>]*src=(["\']).*?\1[^>]*>/', $html, $videos);

    foreach ($videos[0] as $video_tag) {
      // Skip if the video is excluded
      if (Utils::any_keywords_match_string($lazy_load_exclusions, $video_tag)) {
        continue;
      }

      $video = new HTML($video_tag);

      // Set video src to data-lazy-src and remove it
      $video->{'data-lazy-src'} = $video->src;
      unset($video->src);

      // Replace the original video tag
      $html = str_replace($video_tag, $video, $html);
    }

    return $html;
  }
}
