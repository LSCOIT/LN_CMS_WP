<?php

namespace LSC\Includes;

class LSC_Users
{

  function __construct()
  {
    add_filter('manage_users_columns', [$this, 'additional_columns']);
    add_action('manage_users_custom_column', [$this, 'render_additional_columns'], 10, 3);
    add_filter('editable_roles', [$this, 'filter_roles']);
    add_action('pre_get_users', [$this, 'filter_users']);
    add_filter('acf/load_field/name=user_organizational_unit', 'get_organizational_unit_field');
  }

  function additional_columns($columns)
  {
    if (empty($columns) && !is_array($columns)) {
      $columns = array();
    }

    if (isset($columns['organizational_unit'])) {
      return $columns;
    }

    $new_columns = [];

    if (current_user_can('manage_options')) {
      $new_columns['organizational_unit'] = 'Organizational Unit';
    }

    if (isset($columns['role'])) {
      return lsc_array_insert_after($columns, 'role', $new_columns);
    }

    return array_merge($columns, $new_columns);
  }

  function render_additional_columns($val, $column_name, $user_id)
  {
    switch ($column_name) {
      case 'organizational_unit':
        $units = get_organizational_unit();
        $user_unit = get_field('user_organizational_unit', "user_{$user_id}");
        return !empty($units[$user_unit]) ? $units[$user_unit] : '';
      default:
        return $val;
    }
  }

  function filter_roles($roles)
  {
    if (current_user_can('manage_options')) {
      return $roles;
    }

    $filtered_roles = [];
    foreach ($roles as $role => $role_info) {
      if (in_array($role, ['state_admin', 'editor', 'author'])) {
        $filtered_roles[$role] = $role_info;
      }
    }

    return $filtered_roles;
  }

  function filter_users($query)
  {
    if (current_user_can('manage_options')) {
      return;
    }

    $current_user_id = get_current_user_id();
    $current_user_unit = get_field('user_organizational_unit', "user_{$current_user_id}");

    $meta_query = array(array(
      'key' => 'user_organizational_unit',
      'value' => $current_user_unit
    ));

    $query->set('meta_query', $meta_query);
  }
}
