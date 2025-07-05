<?php
namespace FlyingPress;

class Database
{
  public static function init()
  {
    add_action('flying_press_clean_database', [__CLASS__, 'clean']);
    add_action('init', [__CLASS__, 'setup_scheduled_clean']);
  }

  public static function setup_scheduled_clean()
  {
    $schedule = Config::$config['db_auto_clean_interval'];
    $action_name = 'flying_press_clean_database';

    if (!Config::$config['db_auto_clean']) {
      wp_clear_scheduled_hook($action_name);
      return;
    }

    if (!wp_next_scheduled($action_name) || wp_get_schedule($action_name) != $schedule) {
      wp_clear_scheduled_hook($action_name);
      wp_schedule_event(time(), $schedule, $action_name);
    }
  }

  public static function clean()
  {
    global $wpdb;

    $config = Config::$config;

    if ($config['db_post_revisions']) {
      $query = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'revision'");
      if ($query) {
        foreach ($query as $id) {
          wp_delete_post_revision(intval($id)) instanceof \WP_Post ? 1 : 0;
        }
      }
    }

    if ($config['db_post_auto_drafts']) {
      $query = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft'");
      if ($query) {
        foreach ($query as $id) {
          wp_delete_post(intval($id), true) instanceof \WP_Post ? 1 : 0;
        }
      }
    }

    if ($config['db_post_trashed']) {
      $query = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status = 'trash'");
      if ($query) {
        foreach ($query as $id) {
          wp_delete_post($id, true) instanceof \WP_Post ? 1 : 0;
        }
      }
    }

    if ($config['db_comments_spam']) {
      $query = $wpdb->get_col(
        "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = 'spam'"
      );
      if ($query) {
        foreach ($query as $id) {
          wp_delete_comment(intval($id), true);
        }
      }
    }

    if ($config['db_comments_trashed']) {
      $query = $wpdb->get_col(
        "SELECT comment_ID FROM $wpdb->comments WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')"
      );
      if ($query) {
        foreach ($query as $id) {
          wp_delete_comment(intval($id), true);
        }
      }
    }

    if ($config['db_transients_expired']) {
      $time = isset($_SERVER['REQUEST_TIME']) ? (int) $_SERVER['REQUEST_TIME'] : time();
      $query = $wpdb->get_col(
        $wpdb->prepare(
          "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d",
          $wpdb->esc_like('_transient_timeout') . '%',
          $time
        )
      );
      if ($query) {
        foreach ($query as $transient) {
          $key = str_replace('_transient_timeout_', '', $transient);
          delete_transient($key);
        }
      }
    }

    if ($config['db_optimize_tables']) {
      $query = $wpdb->get_results(
        "SELECT table_name, data_free FROM information_schema.tables WHERE table_schema = '" .
          DB_NAME .
          "' and Engine <> 'InnoDB' and data_free > 0"
      );
      if ($query) {
        foreach ($query as $table) {
          $wpdb->query("OPTIMIZE TABLE $table->table_name");
        }
      }
    }
  }
}
