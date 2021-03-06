<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
  die('-1');
}

class LSC_Setup
{

  function __construct()
  {
    add_action('init', [$this, 'unregister_post_tag_taxonomy']);
    add_filter('acf/settings/show_admin', '__return_false');
    add_filter('acf/settings/show_updates', '__return_false');
    add_action('admin_menu', [$this, 'remove_menu_items']);
    add_action('admin_footer', 'lsc_print_js', 25);
    add_action('acf/init', [$this, 'add_user_fields']);
    add_action('template_redirect', [$this, 'redirect_to_admin']);
  }


  function unregister_post_tag_taxonomy()
  {
    global $pagenow;

    register_taxonomy('post_tag', array());

    $tax = array('post_tag');

    if (!empty($_GET['taxonomy']) && $pagenow == 'edit-tags.php' && in_array($_GET['taxonomy'], $tax)) {
      wp_die('Invalid taxonomy');
    }
  }


  function remove_menu_items()
  {
    remove_menu_page('edit-comments.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('themes.php');
  }

  function add_user_fields()
  {
    acf_add_local_field_group(array(
      'key' => 'group_user_fields',
      'title' => 'User organizational unit',
      'fields' => array(
        array(
          'key' => 'field_user_organizational_unit',
          'label' => 'Organizational Unit',
          'name' => 'user_organizational_unit',
          'type' => 'select',
          'required' => 1,
          'choices' => get_organizational_unit(),
        ),
      ),
      'location' => array(
        array(
          array(
            'param' => 'current_user_role',
            'operator' => '==',
            'value' => 'administrator',
          ),
          array(
            'param' => 'user_form',
            'operator' => '==',
            'value' => 'edit',
          ),
          array(
            'param' => 'user_role',
            'operator' => '!=',
            'value' => 'administrator',
          ),
        ),
      )
    ));
  }

  function redirect_to_admin()
  {
    if (!is_admin()) {
      wp_redirect(admin_url());
      exit;
    }
  }
}
