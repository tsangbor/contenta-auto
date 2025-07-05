<?php
// FlyingPress

$config = CONFIG_TO_REPLACE;

if (!headers_sent()) {
  // Set response cache headers
  header('x-flying-press-cache: MISS');
  header('x-flying-press-source: PHP');
}

// Skip WP CLI requests
if (defined('WP_CLI') && WP_CLI) {
  return false;
}

// Check if its preload request
if (isset($_SERVER['HTTP_X_FLYING_PRESS_PRELOAD'])) {
  unset($_COOKIE['wordpress_logged_in_1']);
  return false;
}

// Check if the request method is HEAD or GET
if (!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], ['HEAD', 'GET'])) {
  return false;
}

// Check if current page has any cookies set that should not be cached
foreach ($config['cache_bypass_cookies'] as $cookie) {
  if (preg_grep("/$cookie/i", array_keys($_COOKIE))) {
    return false;
  }
}

// Default file name is "index.php"
$file_name = 'index';

// Check if the user is logged in
$is_user_logged_in = preg_grep('/^wordpress_logged_in_/i', array_keys($_COOKIE));
if ($is_user_logged_in && !$config['cache_logged_in']) {
  return false;
}

// Append "-logged-in" to the file name if the user is logged in
$file_name .= $is_user_logged_in ? '-logged-in' : '';

// Add user role to cache file name
$file_name .= isset($_COOKIE['fp_logged_in_roles']) ? '-' . $_COOKIE['fp_logged_in_roles'] : '';

// Include Cookies
$cookies_to_include = $config['cache_include_cookies'] ?? [];

// If any of the include cookies exist append it to the file name
foreach ($cookies_to_include as $cookie_name) {
  if (isset($_COOKIE[$cookie_name])) {
    $file_name .= '-' . $_COOKIE[$cookie_name];
  }
}

// Check if user agent is mobile and append "mobile" to the file name
$is_mobile =
  isset($_SERVER['HTTP_USER_AGENT']) &&
  preg_match(
    '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera (Mini|Mobi)/i',
    $_SERVER['HTTP_USER_AGENT']
  );
$file_name .= $config['cache_mobile'] && $is_mobile ? '-mobile' : '';

// Remove ignored query string parameters and generate hash of the remaining
$query_strings = array_diff_key($_GET, array_flip($config['cache_ignore_queries']));
$file_name .= !empty($query_strings) ? '-' . md5(serialize($query_strings)) : '';

// File paths for cache files
$host = $_SERVER['HTTP_HOST'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($path);
$cache_file_path = WP_CONTENT_DIR . "/cache/flying-press/$host/$path/$file_name.html.gz";

// Check if the gzipped cache file exists
if (!file_exists($cache_file_path)) {
  return false;
}

// Set the necessary headers
ini_set('zlib.output_compression', 0);
header('Content-Encoding: gzip');

// CDN cache headers
header("Cache-Tag: $host");
header('CDN-Cache-Control: max-age=2592000');
header('Cache-Control: no-cache, must-revalidate');

// Set cache HIT response header
header('x-flying-press-cache: HIT');

// Add Last modified response header
$cache_last_modified = filemtime($cache_file_path);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $cache_last_modified) . ' GMT');

// Get last modified since from request header
$http_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
  ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])
  : 0;

// If file is not modified during this time, send 304
if ($http_modified_since >= $cache_last_modified) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304);
  exit();
}

header('Content-Type: text/html; charset=UTF-8');

readfile($cache_file_path);
exit();
