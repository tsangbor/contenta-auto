<?php

namespace FlyingPress;

use WP_REST_Request;
use WP_REST_Response;

class TaskRunner
{
  private $table_name;
  private $wpdb;
  private $task_name;
  private $callback;
  private $batch_size;
  private $max_retries;

  // Constructor to initialize the TaskRunner.
  public function __construct(string $task_name, callable $callback, array $args = [])
  {
    global $wpdb;
    $this->wpdb = $wpdb;
    $this->task_name = $task_name;
    $this->table_name = $wpdb->prefix . 'tasks';
    $this->callback = $callback;
    $this->batch_size = $args['batch_size'] ?? 10;
    $this->max_retries = $args['max_retries'] ?? 3;

    $this->create_table();
    add_action('wp_ajax_task_runner_' . $this->task_name, [$this, 'ajax_process_batch']);
    add_action('wp_ajax_nopriv_task_runner_' . $this->task_name, [$this, 'ajax_process_batch']);
    add_filter('cron_schedules', [$this, 'add_cron_schedule']);
    add_action('init', [$this, 'schedule_cron']);
    add_action('task_runner_worker_cron_' . $this->task_name, [$this, 'resume_queue']);
    add_action('admin_menu', [$this, 'add_tasks_page']);
  }

  // Add custom cron schedule.
  public function add_cron_schedule($schedules)
  {
    $schedules['minute'] = [
      'interval' => 60,
      'display' => 'Every Minute',
    ];
    return $schedules;
  }

  // Schedule the cron event if not already scheduled.
  public function schedule_cron()
  {
    if (!wp_next_scheduled('task_runner_worker_cron_' . $this->task_name)) {
      wp_schedule_event(time(), 'minute', 'task_runner_worker_cron_' . $this->task_name);
    }
  }

  // Create the tasks table if it doesn't exist.
  private function create_table()
  {
    $charset_collate = $this->wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              task_name VARCHAR(100) NOT NULL,
              data LONGTEXT NOT NULL,
              status ENUM('pending', 'processing', 'failed') DEFAULT 'pending',
              priority INT DEFAULT 0,
              retries INT DEFAULT 0,
              retry_after TIMESTAMP NULL DEFAULT NULL,
              data_hash CHAR(32) NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY unique_task (task_name, data_hash)
            ) $charset_collate;";

    $this->wpdb->query($sql);
  }

  // Insert a single task into the database.
  public function insert_task($task, $priority = 0)
  {
    $data = maybe_serialize($task);

    $query = $this->wpdb->prepare(
      "INSERT INTO {$this->table_name} (task_name, data, data_hash, priority)
        VALUES (%s, %s, %s, %d)
        ON DUPLICATE KEY UPDATE priority = VALUES(priority)",
      $this->task_name,
      $data,
      md5($data),
      $priority
    );

    return $this->wpdb->query($query);
  }

  // Insert multiple tasks into the database with batching.
  public function insert_tasks($tasks, $priority = 0)
  {
    $batch_size = 10000;
    $rows_inserted = 0;
    $placeholders_template = '(%s, %s, %s, %d)';
    $task_name = $this->task_name; // Cache for performance

    foreach (array_chunk($tasks, $batch_size) as $batch) {
      $placeholders = [];
      $values = [];

      foreach ($batch as $task) {
        $serialized_data = maybe_serialize($task);

        $placeholders[] = $placeholders_template;
        $values[] = $task_name;
        $values[] = $serialized_data;
        $values[] = md5($serialized_data);
        $values[] = $priority;
      }

      $query = sprintf(
        'INSERT INTO %s (task_name, data, data_hash, priority) VALUES %s ON DUPLICATE KEY UPDATE priority = VALUES(priority)',
        $this->table_name,
        implode(', ', $placeholders)
      );

      // Prepare and execute the query
      $prepared_query = $this->wpdb->prepare($query, ...$values);
      $result = $this->wpdb->query($prepared_query);
      $rows_inserted += $result !== false ? $result : 0;
    }

    return $rows_inserted;
  }

  // Retry the task with exponential backoff until max retries are reached.
  public function retry_task($id)
  {
    $task = $this->wpdb->get_row(
      $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
    );

    if ($task->retries < $this->max_retries) {
      // Calculate backoff time - exponential backoff with base of 2 minutes
      // 1st retry: 2 min, 2nd: 4 min, 3rd: 8 min, etc.
      $backoff_seconds = pow(2, $task->retries) * 120;

      // Use MySQL's DATE_ADD function to ensure consistent timezone handling
      $this->wpdb->query(
        $this->wpdb->prepare(
          "UPDATE {$this->table_name} 
           SET status = 'pending', 
               retries = retries + 1, 
               retry_after = DATE_ADD(NOW(), INTERVAL %d SECOND) 
           WHERE id = %d",
          $backoff_seconds,
          $id
        )
      );
    } else {
      // Remove the task if max retries are reached
      $this->wpdb->query(
        $this->wpdb->prepare("DELETE FROM {$this->table_name} WHERE id = %d", $id)
      );
    }
  }

  // Delete a specific task from the database.
  public function remove_task($id)
  {
    return $this->wpdb->delete($this->table_name, ['id' => $id], ['%d']);
  }

  // Clear all tasks for the current task name.
  public function clear_tasks()
  {
    $this->wpdb->delete($this->table_name, ['task_name' => $this->task_name], ['%s']);
  }

  // Get count of remaining tasks for current task name
  public function get_remaining_tasks_count()
  {
    return (int) $this->wpdb->get_var(
      $this->wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->table_name} 
         WHERE task_name = %s 
         AND status IN ('pending', 'processing')",
        $this->task_name
      )
    );
  }

  // Start processing the task queue by triggering REST API calls.
  public function start_queue()
  {
    $url = admin_url('admin-ajax.php?action=task_runner_' . $this->task_name);
    wp_remote_post($url, [
      'timeout' => 0.01,
      'blocking' => false,
      'cookies' => $_COOKIE,
      'sslverify' => false,
    ]);
  }

  // Process a batch of tasks.
  public function process_batch()
  {
    if ($this->has_processing_tasks()) {
      return;
    }

    $tasks = $this->fetch_tasks();

    if (empty($tasks)) {
      return;
    }

    $this->process_tasks($tasks);
    $this->start_queue();
    return true;
  }

  // Reset tasks that have been processing for too long.
  private function reset_stale_tasks()
  {
    $this->wpdb->query(
      "UPDATE {$this->table_name} 
         SET status = 'pending' 
         WHERE status = 'processing' 
         AND updated_at < NOW() - INTERVAL 2 MINUTE"
    );
  }

  // Fetch a batch of pending tasks from the database.
  private function fetch_tasks()
  {
    $this->wpdb->query('START TRANSACTION');

    $tasks = $this->wpdb->get_results(
      $this->wpdb->prepare(
        "SELECT id, data
         FROM {$this->table_name}
         WHERE task_name = %s
         AND status = 'pending'
         AND (retry_after IS NULL OR retry_after <= NOW())
         ORDER BY priority DESC, id ASC
         LIMIT %d
         FOR UPDATE",
        $this->task_name,
        $this->batch_size
      )
    );

    if (!empty($tasks)) {
      $ids = wp_list_pluck($tasks, 'id');
      $placeholders = implode(',', array_fill(0, count($ids), '%d'));

      // Mark fetched tasks as processing
      $this->wpdb->query(
        $this->wpdb->prepare(
          "UPDATE {$this->table_name}
           SET status = 'processing'
           WHERE id IN ($placeholders)",
          $ids
        )
      );

      foreach ($tasks as $task) {
        $task->data = maybe_unserialize($task->data);
      }
    }

    $this->wpdb->query('COMMIT');

    return $tasks;
  }

  // Process an individual task using the callback.
  private function process_tasks($tasks)
  {
    if (is_callable($this->callback)) {
      return call_user_func($this->callback, $tasks);
    }

    error_log('No callback defined for task: ' . $this->task_name);
    return new \WP_Error('no_callback', 'No callback defined for task: ' . $this->task_name);
  }

  // Update the status of a task.
  private function update_status($id, $status)
  {
    $this->wpdb->update($this->table_name, ['status' => $status], ['id' => $id], ['%s'], ['%d']);
  }

  // Resume processing the task queue.
  public function resume_queue()
  {
    $this->reset_stale_tasks();

    if ($this->has_processing_tasks()) {
      return;
    }

    $this->start_queue();
  }

  public function ajax_process_batch()
  {
    $this->process_batch();
    echo 'done';
    wp_die(); // required for admin-ajax
  }

  // Check if the task runner has processing tasks
  private function has_processing_tasks(): bool
  {
    return (bool) $this->wpdb->get_var(
      $this->wpdb->prepare(
        "SELECT 1
             FROM {$this->table_name}
             WHERE task_name = %s
               AND status = 'processing'
             LIMIT 1",
        $this->task_name
      )
    );
  }

  // Add tasks page to admin menu
  public function add_tasks_page()
  {
    if (!Auth::is_allowed()) {
      return;
    }

    add_submenu_page('', 'Tasks', '', 'edit_posts', 'wp-tasks', [$this, 'render_tasks_page']);
  }

  // Render the tasks page
  public function render_tasks_page()
  {
    // Get pagination parameters
    $per_page = 100;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Single query to get both count and data
    $query = $this->wpdb->prepare(
      "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table_name} ORDER BY priority DESC, id ASC LIMIT %d OFFSET %d",
      $per_page,
      $offset
    );

    $tasks = $this->wpdb->get_results($query);
    $total_items = $this->wpdb->get_var('SELECT FOUND_ROWS()');
    $total_pages = ceil($total_items / $per_page);

    // Format dates for display
    $date_format = get_option('date_format') . ' ' . get_option('time_format');
    ?>
    <div class="wrap">
      <h1>Tasks</h1>
      
      <?php if (empty($tasks)): ?>
        <div class="notice notice-warning">
          <p>No tasks found in the database.</p>
        </div>
      <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
          <colgroup>
            <col style="width: 5%">
            <col style="width: 10%">
            <col style="width: 35%">
            <col style="width: 8%">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 12%">
            <col style="width: 10%">
            <col style="width: 10%">
          </colgroup>
          <thead>
            <tr>
              <th>ID</th>
              <th>Task Name</th>
              <th>Data</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Retries</th>
              <th>Retry After</th>
              <th>Created At</th>
              <th>Updated At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $task):

              $data = maybe_unserialize($task->data);
              $created_at = strtotime($task->created_at);
              $updated_at = strtotime($task->updated_at);
              $retry_after = $task->retry_after ? strtotime($task->retry_after) : null;
              ?>
              <tr>
                <td><?php echo esc_html($task->id); ?></td>
                <td><?php echo esc_html($task->task_name); ?></td>
                <td class="task-data"><pre><?php echo esc_html(print_r($data, true)); ?></pre></td>
                <td>
                  <span class="status-<?php echo esc_attr($task->status); ?>">
                    <?php echo esc_html($task->status); ?>
                  </span>
                </td>
                <td><?php echo esc_html($task->priority); ?></td>
                <td><?php echo esc_html($task->retries); ?></td>
                <td><?php echo $retry_after
                  ? esc_html(date_i18n($date_format, $retry_after))
                  : '-'; ?></td>
                <td><?php echo esc_html(date_i18n($date_format, $created_at)); ?></td>
                <td><?php echo esc_html(date_i18n($date_format, $updated_at)); ?></td>
              </tr>
            <?php
            endforeach; ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
          <div class="tablenav bottom">
            <div class="tablenav-pages">
              <span class="displaying-num"><?php echo number_format_i18n(
                $total_items
              ); ?> items</span>
              <span class="pagination-links">
                <?php echo paginate_links([
                  'base' => add_query_arg('paged', '%#%'),
                  'format' => '',
                  'prev_text' => '&laquo;',
                  'next_text' => '&raquo;',
                  'total' => $total_pages,
                  'current' => $current_page,
                ]); ?>
              </span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <style>
      .status-pending { color: #f0b849; }
      .status-processing { color: #0073aa; }
      .status-failed { color: #dc3232; }
      .task-data pre { 
        margin: 0; 
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
        font-size: 12px;
        line-height: 1.4;
        padding: 5px;
        background: #f8f9fa;
        border: 1px solid #e2e4e7;
        border-radius: 3px;
      }
      .task-data {
        max-width: 0;
        word-wrap: break-word;
      }
    </style>
    <?php
  }

  public function destroy()
  {
    $this->wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
  }
}
