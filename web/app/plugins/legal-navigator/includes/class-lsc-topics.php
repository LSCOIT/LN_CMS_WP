<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
  die('-1');
}

class LSC_Topics
{

  function __construct()
  {
    add_action('admin_init', [$this, 'change_labels']);
    add_action('admin_menu', [$this, 'change_cat_label']);
    add_action('acf/init', [$this, 'add_fields']);
    add_filter('category_edit_form_fields', [$this, 'cat_description'], 9);
    add_action('category_edit_form_fields', [$this, 'server_sync'], 11);
    add_action('admin_head', [$this, 'remove_default_category_description']);
    add_action('created_category', [$this, 'add_meta_fields']);
    add_action('edited_category', [$this, 'update_meta_fields']);
    remove_filter('pre_term_description', 'wp_filter_kses');
    remove_filter('term_description', 'wp_kses_data');
    add_filter('manage_edit-category_columns', array($this, 'additional_columns'));
    add_filter('manage_category_custom_column', array($this, 'render_additional_column'), 10, 3);

    //Add filter
    add_action('category_add_form', array($this, 'pre_form'));
    add_action('after-category-table', array($this, 'add_topic_filters'));
    add_filter('get_terms_args', array($this, 'filter_topics'));
    add_action('pre_delete_term', [$this, 'delete_topic_from_servers'], 10, 2);
  }


  function change_labels()
  {
    global $wp_taxonomies;
    $labels = &$wp_taxonomies['category']->labels;
    $labels->name = 'Topic';
    $labels->singular_name = 'Topic';
    $labels->add_new = 'Add Topic';
    $labels->add_new_item = 'Add Topic';
    $labels->edit_item = 'Edit Topic';
    $labels->new_item = 'Topic';
    $labels->view_item = 'View Topic';
    $labels->search_items = 'Search Topics';
    $labels->not_found = 'No Topics found';
    $labels->not_found_in_trash = 'No Topics found in Trash';
    $labels->all_items = 'All Topics';
    $labels->menu_name = 'Topic';
    $labels->name_admin_bar = 'Topic';
    $labels->parent_item = 'Parent Topic';
    $labels->parent_item_colon = 'Parent Topic:';
    $labels->update_item = 'Update Topic';
    $labels->new_item_name = 'New Topic Name';
    $labels->no_terms = 'No topics';
    $labels->items_list_navigation = 'Topics list navigation';
    $labels->items_list = 'Topics list';
  }

  function change_cat_label()
  {
    global $submenu;
    $submenu['edit.php'][15][0] = 'Topics'; // Rename categories to Authors
  }

  function add_fields()
  {
    acf_add_local_field_group(array(
      'key' => 'group_topic_fields',
      'title' => 'Topic fields',
      'fields' => array(
        array(
          'key' => 'field_topic_organizational_unit',
          'label' => 'Organizational Unit',
          'name' => 'topic_organizational_unit',
          'type' => 'select',
          'required' => 1,
          'choices' => $this->get_organizational_unit(),
        ),
        array(
          'key' => 'field_topic_locations',
          'label' => 'Locations',
          'name' => 'topic_locations',
          'type' => 'repeater',
          'min' => 1,
          'required' => 1,
          'layout' => 'table',
          'sub_fields' => array(
            array(
              'key' => 'field_topic_state',
              'label' => 'State',
              'name' => 'topic_state',
              'type' => 'select',
              'allow_null' => 1,
              'choices' => $this->get_organizational_unit(),
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_topic_county',
              'label' => 'County',
              'name' => 'topic_county',
              'type' => 'text',
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_topic_city',
              'label' => 'City',
              'name' => 'topic_city',
              'type' => 'text',
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_topic_zip_code',
              'label' => 'Zip Code',
              'name' => 'topic_zip_code',
              'type' => 'number',
              'wrapper' => [
                'width' => 16
              ]
            ),
          ),
        ),
        array(
          'key' => 'field_keywords',
          'label' => 'Keywords',
          'name' => 'keywords',
          'type' => 'text',
        ),
        array(
          'key' => 'field_icon',
          'label' => 'Icon',
          'name' => 'icon',
          'type' => 'image',
          'return_format' => 'url',
        ),
        array(
          'key' => 'field_topic_ranking',
          'label' => 'Ranking',
          'name' => 'topic_ranking',
          'type' => 'number',
          'default_value' => 1,
          'required' => 1,
        ),
        array(
          'key' => 'field_display',
          'label' => 'Display',
          'name' => 'display',
          'type' => 'true_false',
          'acfe_permissions' => ['administrator', 'editor'],
          'ui' => 1,
        ),
      ),
      'location' => array(
        array(
          array(
            'param' => 'taxonomy',
            'operator' => '==',
            'value' => 'category',
          ),
        ),
      ),
    ));
  }

  private function get_organizational_unit()
  {
    $units = get_organizational_unit();

    if (get_current_user_id() && !current_user_can('manage_options')) {
      $current_user_id = get_current_user_id();
      $org_unit = get_field('user_organizational_unit', "user_{$current_user_id}");
      $filtered_unit = [$org_unit => $units[$org_unit]];
      $units = $filtered_unit;
    }

    return $units;
  }

  function cat_description($term)
  {
?>
    <table class="form-table">
      <?php if ($topic_id = get_term_meta($term->term_id, '_topic_id', true)) { ?>
        <tr class="form-field">
          <th>Topic ID</th>
          <td><?php echo $topic_id; ?></td>
        </tr>
      <?php } ?>

      <?php if ($author_id = get_term_meta($term->term_id, '_created_by', true)) {
        $author = get_user_by('id', $author_id);
        if ($author) { ?>
          <tr class="form-field">
            <th>Created By</th>
            <td><?php echo $author->display_name; ?></td>
          </tr>
      <?php
        }
      } ?>

      <?php if ($created_time = get_term_meta($term->term_id, '_created_time', true)) { ?>
        <tr class="form-field">
          <th>Created Date</th>
          <td><?php echo wp_date('j F Y H:i', strtotime($created_time)); ?></td>
        </tr>
      <?php } ?>

      <?php if ($modified_id = get_term_meta($term->term_id, '_modified_by', true)) {
        $modified = get_user_by('id', $modified_id);
        if ($modified) { ?>
          <tr class="form-field">
            <th>Modified By</th>
            <td><?php echo $modified->display_name; ?></td>
          </tr>
      <?php
        }
      } ?>

      <?php if ($modified_time = get_term_meta($term->term_id, '_modified_time', true)) { ?>
        <tr>
          <th>Modified Date</th>
          <td><?php echo wp_date('j F Y H:i', strtotime($modified_time)); ?></td>
        </tr>
      <?php } ?>

      <tr class="form-field">
        <th scope="row" valign="top"><label for="description"><?php _ex('Description', 'Taxonomy Description'); ?></label></th>
        <td>
          <?php
          $settings = array('wpautop' => true, 'media_buttons' => true, 'quicktags' => true, 'textarea_rows' => '15', 'textarea_name' => 'description');
          wp_editor(html_entity_decode($term->description, ENT_QUOTES, 'UTF-8'), 'cat_description', $settings);
          ?>
          <br />
          <span class="description"><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></span>
        </td>
      </tr>
    </table>
  <?php
  }

  function server_sync($term)
  {
    $servers = get_field('connections', 'option');
    $upload_time = get_term_meta($term->term_id, '_server_updates', true);
    $table = [];
    foreach ($servers as $server) {
      $server_id = $server['connection_dir_id'];
      $user = wp_get_current_user();
      if (current_user_can('administrator') || array_intersect($user->roles, $server['connection_allowed_for'])) {
        $table[] = [
          'value' => $server_id,
          'title' => $server['connection_name'],
          'time' => !empty($upload_time[$server_id]) ? wp_date('d/m/Y H:i', strtotime($upload_time[$server_id])) : 'Never'
        ];
      }
    }
  ?>
    <table class="form-table">
      <tr class="form-field">
        <th>Topic Upload</th>
        <td>
          <table class="upload-topic-table">
            <tr>
              <th></th>
              <th>Server Name</th>
              <th>Upload Time</th>
            </tr>
            <?php foreach ($table as $item) { ?>
              <tr>
                <td>
                  <input type="radio" name="server_id" value="<?php echo esc_attr($item['value']); ?>">
                </td>
                <td><?php echo $item['title']; ?></td>
                <td class="date_<?php echo esc_attr($item['value']); ?>"><?php echo $item['time']; ?></td>
              </tr>
            <?php } ?>
          </table>
          <div id="upload-message"></div>
          <?php submit_button('Upload to server', 'primary', 'upload-topic', false); ?>
        </td>
      </tr>
    </table>
    <?php
  }

  function remove_default_category_description()
  {
    global $current_screen;
    if ($current_screen->id == 'edit-category') {
      lsc_enqueue_js("
        $('textarea#description').closest('tr.form-field').remove();
        $('input#slug').closest('tr.form-field').remove();
      ");
    ?>
      <?php
    }
  }

  function add_meta_fields($term_id)
  {
    $user_id = get_current_user_id();
    $date = wp_date('Y-m-d H:i:s');
    add_term_meta($term_id, '_created_time', $date);
    add_term_meta($term_id, '_created_by', $user_id);
    add_term_meta($term_id, '_modified_time', $date);
    add_term_meta($term_id, '_modified_by', $user_id);
  }

  function update_meta_fields($term_id)
  {
    $user_id = get_current_user_id();
    $date = wp_date('Y-m-d H:i:s');
    update_term_meta($term_id, '_modified_time', $date);
    update_term_meta($term_id, '_modified_by', $user_id);
  }

  function additional_columns($columns)
  {
    if (empty($columns) && !is_array($columns)) {
      $columns = array();
    }

    unset($columns['slug']);

    $new_columns = [];

    if (current_user_can('manage_options')) {
      $new_columns['organizational_unit'] = 'Organizational Unit';
    }

    $new_columns['ranking'] = 'Ranking';
    $new_columns['display'] = 'Display';

    return lsc_array_insert_after($columns, 'name', $new_columns);
  }

  function render_additional_column($columns, $column, $term_id)
  {
    if ('organizational_unit' === $column) {
      $units = get_organizational_unit();
      $post_unit = get_term_meta($term_id, 'topic_organizational_unit', true);
      echo !empty($units[$post_unit]) ? $units[$post_unit] : '';
    }

    if ('ranking' === $column) {
      echo get_term_meta($term_id, 'topic_ranking', true);
    }

    if ('display' === $column) {
      echo get_term_meta($term_id, 'display', true) == 1 ? 'Yes' : 'No';
    }

    return $columns;
  }

  public function pre_form()
  {
    ob_start();
  }


  public function add_topic_filters()
  {
    $html                    = ob_get_clean();
    $__preg_replace_callback = function ($match) {
      ob_start();
      if (current_user_can('manage_options')) {
        $current_org_unit = filter_input(INPUT_GET, 'org_unit');
      ?>
        <select name="org_unit" id="org_unit">
          <option value="">Select Unit</option>
          <?php
          $units = get_organizational_unit();
          foreach ($units as $key => $unit) { ?>
            <option<?php selected($key, $current_org_unit); ?> value="<?php echo $key; ?>"><?php echo $unit; ?></option>
            <?php } ?>
        </select>
        <?php submit_button(__('Filter'), 'secondary', 'filter_action', false, array('id' => 'post-query-submit')); ?>
        <script>
          (function($) {
            $('#posts-filter').attr('method', 'get');
          })(jQuery);
        </script>
<?php }

      return $match[1] . ob_get_clean();
    };

    echo preg_replace_callback('~(id="doaction[^<]+</div>)~', $__preg_replace_callback, $html, 1);
  }


  public function filter_topics($args)
  {
    $args['meta_query'] = [];

    if (get_current_user_id() && !current_user_can('manage_options')) {
      $current_user_id = get_current_user_id();
      $org_unit = get_field('user_organizational_unit', "user_{$current_user_id}");
      $args['meta_query'][] = [
        'key' => 'topic_organizational_unit',
        'value' => $org_unit,
      ];
    }

    if (function_exists('get_current_screen')) {
      $screen = get_current_screen();

      if (is_admin() && $screen && $screen->taxonomy == 'category' && !wp_doing_ajax() && $screen->id == 'edit-category') {
        if (isset($_GET['filter_action'])) {
          if ($current_org_unit = filter_input(INPUT_GET, 'org_unit')) {
            $args['meta_query'][] = [
              'key'   => 'topic_organizational_unit',
              'value' => $current_org_unit
            ];
          }
        }
      }
    }

    return $args;
  }

  function delete_topic_from_servers($term_id, $taxonomy)
  {
    if ('category' !== $taxonomy) {
      return;
    }

    $topic_uid = get_term_meta($term_id, '_topic_id', true);
    $servers = get_field('connections', 'option');

    $topic = [
      'id' => $topic_uid,
      'display' => 'No'
    ];

    $server = new LSC_Server();

    foreach ($servers as $serv) {
      $server->resource_request($serv['connection_dir_id'], 'insert', 'topics', [$topic]);
    }
  }
}
