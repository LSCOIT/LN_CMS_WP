<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
  die('-1');
}

class LSC_Resources
{

  function __construct()
  {
    add_filter('post_type_labels_post', [$this, 'rename_posts_labels']);
    add_filter('post_updated_messages', [$this, 'review_updated_messages']);
    add_action('acf/init', [$this, 'add_fields']);
    add_action('enqueue_block_editor_assets', [$this, 'enqueue_assets']);
    add_action('admin_menu', [$this, 'remove_default_post_screen_metaboxes']);
    add_action('add_meta_boxes', [$this, 'add_meta_box']);
    add_action('save_post_post', [$this, 'save_meta_fields']);
    add_filter('manage_posts_columns', [$this, 'additional_columns']);
    add_action('manage_posts_custom_column', [$this, 'render_additional_columns'], 10, 2);
    add_action('restrict_manage_posts', [$this, 'posts_filter_dropdown']);
    add_filter('request', [$this, 'filter_resources']);
    add_filter('acf/fields/taxonomy/query/key=field_topics', [$this, 'filter_topics']);
    add_action('before_delete_post', [$this, 'delete_resource_from_server']);
  }

  function rename_posts_labels($labels)
  {
    $new = array(
      'name'                  => 'Resources',
      'singular_name'         => 'Resource',
      'add_new_item'          => 'Add New Resource',
      'edit_item'             => 'Edit Resource',
      'new_item'              => 'New Resource',
      'view_item'             => 'View Resource',
      'search_items'          => 'View Resources',
      'not_found'             => 'No resources found.',
      'not_found_in_trash'    => 'No resources found in Trash.',
      'all_items'             => 'All Resources',
      'archives'              => 'Resource Archives',
      'insert_into_item'      => 'Insert into resource',
      'uploaded_to_this_item' => 'Uploaded to this resource',
      'filter_items_list'     => 'Filter resources list',
      'items_list_navigation' => 'Resources list navigation',
      'items_list'            => 'Resources list',
      'menu_name'             => 'Resources',
      'name_admin_bar'        => 'Resource',
    );

    return (object) array_merge((array) $labels, $new);
  }

  function review_updated_messages($messages)
  {
    global $post;

    $messages['post'] = array(
      0  => '', // Unused. Messages start at index 1.
      1  => 'Resource updated.',
      2  => 'Custom field updated.',
      3  => 'Custom field deleted.',
      4  => 'Review updated.',
      /* translators: %s: date and time of the revision */
      5  => isset($_GET['revision']) ? sprintf('Resource restored to revision from %s', wp_post_revision_title((int) $_GET['revision'], false)) : false,
      6  => 'Resource published.',
      7  => 'Resource saved.',
      8  => 'Resource submitted.',
      9  => sprintf(
        'Review scheduled for: <strong>%1$s</strong>.',
        // translators: Publish box date format, see http://php.net/date
        date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date))
      ),
      10 => 'Review draft updated.',
    );

    return $messages;
  }

  public function add_fields()
  {
    acf_add_local_field_group(array(
      'key' => 'group_resource_fields',
      'title' => 'Resource fields',
      'fields' => array(
        array(
          'key' => 'field_resource_type',
          'label' => 'Resource Type',
          'name' => 'resource_type',
          'type' => 'select',
          'required' => 1,
          'choices' => array_combine(get_resource_types(), get_resource_types()),
          'wrapper' => [
            'width' => 25
          ]
        ),
        array(
          'key' => 'field_organizational_unit',
          'label' => 'Organizational Unit',
          'name' => 'organizational_unit',
          'type' => 'select',
          'required' => 1,
          'choices' => $this->get_organizational_unit(),
          'wrapper' => [
            'width' => 25
          ]
        ),
        array(
          'key' => 'field_resource_url',
          'label' => 'URL',
          'name' => 'resource_url',
          'type' => 'url',
          'wrapper' => [
            'width' => 50
          ]
        ),
        array(
          'key' => 'field_resource_address',
          'label' => 'Address',
          'name' => 'resource_address',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 33
          ],
        ),
        array(
          'key' => 'field_resource_telephone',
          'label' => 'Telephone',
          'name' => 'resource_telephone',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 33
          ]
        ),
        array(
          'key' => 'field_resource_specialties',
          'label' => 'Specialties',
          'name' => 'resource_specialties',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 34
          ]
        ),
        array(
          'key' => 'field_resource_qualifications',
          'label' => 'Qualifications',
          'name' => 'resource_qualifications',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 33
          ]
        ),
        array(
          'key' => 'field_resource_business_hours',
          'label' => 'Business Hours',
          'name' => 'resource_business_hours',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 33
          ]
        ),
        array(
          'key' => 'field_resource_category',
          'label' => 'Category',
          'name' => 'resource_category',
          'type' => 'text',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
          'wrapper' => [
            'width' => 33
          ]
        ),
        array(
          'key' => 'field_resource_eligibility_information',
          'label' => 'Eligibility Information',
          'name' => 'resource_eligibility_information',
          'type' => 'textarea',
          'conditional_logic' => [
            [
              [
                'field' => 'field_resource_type',
                'operator' => '==',
                'value' => 'Organizations',
              ],
            ],
          ],
        ),
        array(
          'key' => 'field_locations',
          'label' => 'Locations',
          'name' => 'locations',
          'type' => 'repeater',
          'layout' => 'table',
          'min' => 1,
          'required' => 1,
          'sub_fields' => array(
            array(
              'key' => 'field_state',
              'label' => 'State',
              'name' => 'state',
              'type' => 'select',
              'allow_null' => 1,
              'choices' => $this->get_organizational_unit(),
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_county',
              'label' => 'County',
              'name' => 'county',
              'type' => 'text',
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_city',
              'label' => 'City',
              'name' => 'city',
              'type' => 'text',
              'wrapper' => [
                'width' => 28
              ]
            ),
            array(
              'key' => 'field_zip_code',
              'label' => 'Zip Code',
              'name' => 'zip_code',
              'type' => 'number',
              'wrapper' => [
                'width' => 16
              ]
            ),
          ),
        ),
        array(
          'key' => 'field_topics',
          'label' => 'Topics',
          'name' => 'topics',
          'type' => 'repeater',
          'layout' => 'table',
          'min' => 1,
          'sub_fields' => array(
            array(
              'key' => 'field_topic',
              'label' => 'Topic',
              'name' => 'topic',
              'type' => 'taxonomy',
              'taxonomy' => 'category',
              'field_type' => 'select',
              'load_save_terms' => 1,
              'return_format' => 'object',
              'required' => 1,
              'add_term'      => 0,
              'wrapper' => [
                'width' => 90
              ]
            ),
            array(
              'key' => 'field_ranking',
              'label' => 'Ranking',
              'name' => 'ranking',
              'type' => 'number',
              'default_value' => 1,
              'required' => 1,
              'wrapper' => [
                'width' => 10
              ]
            ),
          ),
        ),
        array(
          'key' => 'field_resource_display',
          'label' => 'Display',
          'name' => 'resource_display',
          'type' => 'true_false',
          'acfe_permissions' => ['administrator', 'editor'],
          'ui' => 1,
        ),
      ),
      'location' => array(
        array(
          array(
            'param' => 'post_type',
            'operator' => '==',
            'value' => 'post',
          ),
        ),
      )
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

  function enqueue_assets()
  {
    wp_enqueue_script('lsc-gutenberg-sidebar', lsc_asset_path('js/resources-sidebar.js'), array('wp-blocks', 'wp-edit-post'), LSC_VERSION);
  }

  function remove_default_post_screen_metaboxes()
  {
    remove_meta_box('postcustom', 'post', 'normal');
    remove_meta_box('commentstatusdiv', 'post', 'normal');
    remove_meta_box('trackbacksdiv', 'post', 'normal');
    remove_meta_box('slugdiv', 'post', 'normal');
    remove_meta_box('authordiv', 'post', 'normal');
  }

  function add_meta_box()
  {
    add_meta_box('resource-meta', 'Resource Information', [$this, 'resource_meta_box'], 'post', 'side');
    add_meta_box('upload-resource-meta', 'Resource Upload', [$this, 'upload_resource_meta_box'], 'post', 'side');
  }

  function resource_meta_box($post)
  {
    if ($resource_id = get_post_meta($post->ID, '_resource_id', true)) {
      echo '<p><strong>Resource ID:</strong> ' . esc_attr($resource_id) . '</p>';
    }

    $author = get_user_by('id', $post->post_author);
    echo '<p><strong>Created By:</strong> ' . $author->display_name . '</p>';

    if ($modified_by = get_post_meta($post->ID, '_modified_by', true)) {
      $modified_by_user = get_user_by('id', $modified_by);
      echo '<p><strong>Modified By:</strong> ' . $modified_by_user->display_name . '</p>';
    }

    echo '<p><strong>Updated Date:</strong> ' . date('j F Y H:i', strtotime($post->post_modified)) . '</p>';
  }

  function upload_resource_meta_box($post)
  {
    $servers = get_field('connections', 'option');
    $upload_time = get_post_meta($post->ID, '_server_updates', true);
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
    <table class="upload-resource-table">
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
    <?php submit_button('Upload to server', 'primary', 'upload-resource', false); ?>
    <?php
  }

  function save_meta_fields($post_ID)
  {
    update_post_meta($post_ID, '_modified_by', get_current_user_id());
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

    $new_columns['resource_type'] = 'Resource Type';

    if (isset($columns['title'])) {
      return lsc_array_insert_after($columns, 'title', $new_columns);
    }

    if (isset($columns['name'])) {
      return lsc_array_insert_after($columns, 'name', $new_columns);
    }

    return array_merge($columns, $new_columns);
  }

  function render_additional_columns($column, $post_id)
  {
    if ('organizational_unit' === $column) {
      $units = get_organizational_unit();
      $post_unit = get_post_meta($post_id, 'organizational_unit', true);

      echo $units[$post_unit];
    }

    if ('resource_type' === $column) {
      echo get_post_meta($post_id, 'resource_type', true);
    }
  }

  function posts_filter_dropdown()
  {
    if ('edit-post' !== get_current_screen()->id) {
      return;
    }

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
    <?php }

    $current_resource_type = filter_input(INPUT_GET, 'resource_type');
    ?>
    <select name="resource_type" id="resource_type">
      <option value="">Select Type</option>
      <?php
      $resource_type = get_resource_types();
      foreach ($resource_type as $type) { ?>
        <option<?php selected($type, $current_resource_type); ?> value="<?php echo $type; ?>"><?php echo $type; ?></option>
        <?php } ?>
    </select>
<?php }

  function filter_resources($args)
  {

    $args['meta_query'] = [];

    if (get_current_user_id() && !current_user_can('manage_options')) {
      $current_user_id = get_current_user_id();
      $org_unit = get_field('user_organizational_unit', "user_{$current_user_id}");
      $args['meta_query'][] = [
        'key' => 'organizational_unit',
        'value' => $org_unit,
      ];
    }

    if ($org_unit = filter_input(INPUT_GET, 'org_unit')) {
      $args['meta_query'][] = [
        'key' => 'organizational_unit',
        'value' => $org_unit,
      ];
    }

    if ($resource_type = filter_input(INPUT_GET, 'resource_type')) {
      $args['meta_query'][] = [
        'key' => 'resource_type',
        'value' => $resource_type,
      ];
    }

    return $args;
  }

  function filter_topics($args)
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

    return $args;
  }

  function delete_resource_from_server($post_id)
  {
    $resource_uid = get_post_meta($post_id, '_resource_id', true);
    $servers = get_field('connections', 'option');

    $resource = [
      $resource_uid => get_post_field('post_title', $post_id)
    ];

    $server = new LSC_Server();

    foreach ($servers as $serv) {
      $server->resource_request($serv['connection_dir_id'], 'delete', 'resources', $resource);
    }
  }
}
