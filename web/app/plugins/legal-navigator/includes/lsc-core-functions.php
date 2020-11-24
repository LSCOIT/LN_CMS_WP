<?php

if (!defined('ABSPATH')) {
  die('-1');
}

// Include core functions (available in both admin and frontend).
include('lsc-formatting-functions.php');

/**
 * Load assets
 *
 * @param $filename
 *
 * @return string
 */
function lsc_asset_path($filename)
{
  $dist_path = lsc_strip_protocol(lsc()->plugin_url() . '/assets/');
  $directory = dirname($filename) . '/';
  $file      = basename($filename);

  return $dist_path . $directory . $file;
}

/**
 * Queue some JavaScript code to be output in the footer.
 *
 * @param string $code
 */
function lsc_enqueue_js($code)
{
  global $lsc_queued_js;

  if (empty($lsc_queued_js)) {
    $lsc_queued_js = '';
  }

  $lsc_queued_js .= "\n" . $code . "\n";
}

/**
 * Output any queued javascript code in the footer.
 */
function lsc_print_js()
{
  global $lsc_queued_js;

  if (!empty($lsc_queued_js)) {
    // Sanitize.
    $lsc_queued_js = wp_check_invalid_utf8($lsc_queued_js);
    $lsc_queued_js = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", $lsc_queued_js);
    $lsc_queued_js = str_replace("\r", '', $lsc_queued_js);

    $js = "<!-- lsc JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) { $lsc_queued_js });\n</script>\n";

    /**
     * game_portal_queued_js filter.
     *
     * @param string $js JavaScript code.
     */
    echo $js;

    unset($lsc_queued_js);
  }
}


function get_resource_types()
{
  return [
    'Organizations',
    'Related Readings',
    'Articles',
    'Videos',
    'Forms',
  ];
}


function get_organizational_unit()
{
  return [
    'HI' => 'Hawaii',
    'AK' => 'Alaska'
  ];
}

function get_topic_id_by_uid($topic_uid) {
	global $wpdb;
	$query = $wpdb->prepare(
		"SELECT term_id FROM $wpdb->termmeta WHERE meta_key = %s AND meta_value = %s",
		'_topic_id',
		$topic_uid
	);

	return $wpdb->get_var( $query );
}

function get_post_id_by_uid($resource_uid) {
	global $wpdb;
	$query = $wpdb->prepare(
		"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
		'_remote_id',
		$resource_uid
	);

	return $wpdb->get_var( $query );
}

function array_diff_assoc_recursive($array1, $array2)
{
  $difference = array();
  foreach ($array1 as $key => $value) {
    if (is_array($value)) {
      if (!isset($array2[$key]) || !is_array($array2[$key])) {
        $difference[$key] = $value;
      } else {
        $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
        if (!empty($new_diff))
          $difference[$key] = $new_diff;
      }
    } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
      $difference[$key] = $value;
    }
  }

  return $difference;
}

function var_error_log($args = array())
{
  if (!WP_DEBUG_LOG) {
    return;
  }

  $args = func_get_args();
  ob_start();
  call_user_func_array('var_dump', $args);
  $contents = ob_get_contents();
  ob_end_clean();
  error_log($contents);
}


function get_organizational_unit_field($field)
{
  $units = get_organizational_unit();
  $field['choices'] = $units;

  if (get_current_user_id() && !current_user_can('manage_options')) {
    $current_user_id = get_current_user_id();
    $org_unit = get_user_meta($current_user_id, 'user_organizational_unit', true);
    $filtered_units = [$org_unit => $units[$org_unit]];

    $field['choices'] = $filtered_units;
  }

  return $field;
}

function is_assoc(array $arr)
{
  if (array() === $arr) return false;
  return array_keys($arr) !== range(0, count($arr) - 1);
}
