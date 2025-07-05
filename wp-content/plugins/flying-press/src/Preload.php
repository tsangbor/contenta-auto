<?php

namespace FlyingPress;

use FlyingPress\{Config, TaskRunner};
use WpOrg\Requests\Requests;

class Preload
{
  public static $worker;
  private static $concurrency = 1;
  private static $batch_size = 5;

  public static function init()
  {
    self::$worker = new TaskRunner(
      'preload-urls',
      [__CLASS__, 'process_urls'],
      [
        'batch_size' => self::$concurrency * self::$batch_size,
      ]
    );
  }

  public static function process_urls($tasks)
  {
    foreach (array_chunk($tasks, self::$concurrency) as $task_batch) {
      $requests = [];

      // Build an array of WP HTTP requests
      foreach ($task_batch as $task) {
        $url = $task->data['url'];
        $user_agent =
          $task->data['device'] === 'mobile' ? Utils::$mobile_user_agent : Utils::$user_agent;

        $requests[$task->id] = [
          'url' => $url,
          'headers' => [
            'Range' => 'bytes=0-0',
            'x-flying-press-preload' => '1',
            'Cookie' => 'wordpress_logged_in_1=1;' . $task->data['cookies'],
            'User-Agent' => $user_agent,
          ],
          'timeout' => 60,
          'sslverify' => false,
        ];
      }

      // Fire off concurrent requests
      $responses = Requests::request_multiple($requests);

      // Handle each response
      foreach ($responses as $task_id => $response) {
        // If WP_HTTP_Error or no status_code, consider it a server error (>=500)
        $status =
          is_wp_error($response) || !isset($response->status_code) ? 500 : $response->status_code;

        if ($status === 429 || $status >= 500) {
          // retry on rate-limit or server errors
          self::$worker->retry_task($task_id);
        } else {
          // everything else → done
          self::$worker->remove_task($task_id);
        }
      }

      // Small pause between batches
      $delay = apply_filters('flying_press_preload_delay', 0.2);
      usleep($delay * 1000000);
    }
  }

  public static function preload_urls($urls, $priority = 0, $cookies = '')
  {
    self::queue_urls($urls, $priority, $cookies);
    self::$worker->start_queue();
  }

  public static function preload_cache()
  {
    // Clear existing tasks
    self::$worker->clear_tasks();

    // Add home URL
    self::queue_urls([home_url()]);

    // Suspend cache addition to prevent cache writes (memory usage)
    wp_suspend_cache_addition(true);

    // Fetch post type URLs in batches
    $post_types = get_post_types(['public' => true, 'exclude_from_search' => false]);
    $paged = 1;

    do {
      $query = new \WP_Query([
        'post_status' => 'publish',
        'has_password' => false,
        'ignore_sticky_posts' => true,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'order' => 'DESC',
        'orderby' => 'date',
        'post_type' => $post_types,
        'posts_per_page' => 10000, // Fetch 10k posts at a time
        'paged' => $paged,
        'fields' => 'ids', // Only get post IDs
      ]);

      $post_urls = [];
      foreach ($query->posts as $post_id) {
        $post_urls[] = get_permalink($post_id);
      }
      self::queue_urls($post_urls);

      $paged++;
    } while ($query->have_posts());

    // Fetch taxonomy URLs
    $taxonomies = get_taxonomies(['public' => true, 'rewrite' => true]);
    foreach ($taxonomies as $taxonomy) {
      $query_args = [
        'hide_empty' => true,
        'hierarchical' => false,
        'update_term_meta_cache' => false,
        'taxonomy' => $taxonomy,
      ];
      $terms = get_terms($query_args);
      $taxonomy_urls = [];
      foreach ($terms as $term) {
        $taxonomy_urls[] = get_term_link($term, $taxonomy);
      }
      self::queue_urls($taxonomy_urls);
    }

    // Fetch author URLs
    $user_ids = get_users([
      'role' => 'author',
      'count_total' => false,
      'fields' => 'ID',
    ]);

    $user_urls = [];
    foreach ($user_ids as $user_id) {
      $user_urls[] = get_author_posts_url($user_id);
    }
    self::queue_urls($user_urls);

    // Resume cache addition
    wp_suspend_cache_addition(false);

    self::$worker->start_queue();
  }

  private static function queue_urls($urls, $priority = 0, $cookies = '')
  {
    $urls = apply_filters('flying_press_preload_urls', $urls);
    $tasks = [];

    foreach ($urls as $url) {
      if ('' === trim($url)) {
        continue;
      }

      $tasks[] = [
        'url' => $url,
        'device' => 'desktop',
        'cookies' => $cookies,
      ];

      if (Config::$config['cache_mobile']) {
        $tasks[] = [
          'url' => $url,
          'device' => 'mobile',
          'cookies' => $cookies,
        ];
      }
    }

    self::$worker->insert_tasks($tasks, $priority);
  }
}
