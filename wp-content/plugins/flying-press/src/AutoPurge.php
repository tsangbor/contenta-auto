<?php

namespace FlyingPress;

class AutoPurge
{
  public static function init()
  {
    // Purge when scheduled post is published
    add_action('future_to_publish', function ($post) {
      self::post_updated($post->ID, $post, $post);
    });

    // Purge cache on updating post
    add_action('post_updated', [__CLASS__, 'post_updated'], 10, 3);

    // Preload post URL when comment count is updated
    add_action('wp_update_comment_count', [__CLASS__, 'preload_on_comment']);
  }

  public static function post_updated($post_id, $post_after, $post_before)
  {
    // Get the status of the post after and before the update
    $post_after_status = get_post_status($post_after);
    $post_before_status = get_post_status($post_before);

    // If both post statuses are not 'publish', return early
    if (!in_array('publish', [$post_after_status, $post_before_status])) {
      return;
    }

    $post_type = get_post_type($post_id);

    // If post type is nav_menu_item, return early
    if ($post_type === 'nav_menu_item') {
      return;
    }

    $urls = [];

    // Add URLs of post before and after the update
    if ($post_before_status == 'publish') {
      $urls[] = get_permalink($post_before);
    }
    if ($post_after_status == 'publish') {
      $urls[] = get_permalink($post_after);
    }

    // Add home URL
    $urls[] = home_url();

    // Posts page (blog archive)
    $posts_page = get_option('page_for_posts');
    if ($posts_page) {
      $urls[] = get_permalink($posts_page);
    }

    // Get the post type archive( especially for custom post types )
    $urls[] = get_post_type_archive_link($post_type);

    // Add author profile URL
    $author_id = get_post_field('post_author', $post_id);
    $urls[] = get_author_posts_url($author_id);

    // Urls of the post taxonomies
    $urls = [...$urls, ...self::get_post_taxonomy_urls($post_id)];

    // Add URLs from filter
    $urls = apply_filters('flying_press_auto_purge_urls', $urls, $post_id);

    // Get unique URLs
    $urls = array_unique($urls);

    Purge::purge_urls($urls);
    Preload::preload_urls($urls, time());
  }

  public static function preload_on_comment($post_id)
  {
    // If post type is not viewable, return early
    if (!is_post_type_viewable(get_post_type($post_id))) {
      return;
    }

    $url = get_permalink($post_id);
    Purge::purge_urls([$url]);
    Preload::preload_urls([$url], time());
  }

  public static function get_post_taxonomy_urls($post_id)
  {
    $urls = [];

    $taxonomies = get_object_taxonomies(get_post_type($post_id), 'objects');

    // Include taxonomies with public archive
    $taxonomies = array_filter($taxonomies, function ($taxonomy) {
      return $taxonomy->publicly_queryable;
    });

    foreach ($taxonomies as $taxonomy) {
      // Get the terms of the taxonomy
      $terms = get_the_terms($post_id, $taxonomy->name);

      // if terms is not an array, continue
      if (!is_array($terms) || is_wp_error($terms) || empty($terms)) {
        continue;
      }

      foreach ($terms as $term) {
        if (!is_object($term)) {
          continue;
        }

        // Add taxonomy archive URL
        $urls[] = get_term_link($term);

        // If no parent term, continue
        if ($term->parent == 0) {
          continue;
        }

        // Add taxonomy term parent URLs
        $parent_terms = get_ancestors($term->term_id, $taxonomy->name);

        foreach ($parent_terms as $parent_term) {
          $urls[] = get_term_link($parent_term);
        }
      }
    }
    return $urls;
  }
}
