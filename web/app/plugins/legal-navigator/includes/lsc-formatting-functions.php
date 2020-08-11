<?php

if (!defined('ABSPATH')) {
  die('-1');
}

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $var
 *
 * @return string|array
 */
function lsc_clean($var)
{
  if (is_array($var)) {
    return array_map('lsc_clean', $var);
  }

  return is_scalar($var) ? sanitize_text_field($var) : $var;
}

/**
 * Strip protocol from url
 *
 * @param $url
 *
 * @return string
 */
function lsc_strip_protocol($url)
{

  // strip the protical
  return preg_replace('#^https?:#i', '', $url);
}


function lsc_alter_post_modification_time($data, $postarr)
{
  if (!empty($postarr['post_modified']) && !empty($postarr['post_modified_gmt'])) {
    $data['post_modified'] = $postarr['post_modified'];
    $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
  }

  return $data;
}

function lsc_array_insert_after(array $array, $key, array $new)
{
  $keys = array_keys($array);
  $index = array_search($key, $keys);
  $pos = false === $index ? count($array) : $index + 1;
  return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
}
