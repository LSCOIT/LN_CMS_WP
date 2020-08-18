<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
  die('-1');
}

class LSC_Server
{
  function resource_request($server_id, $action, $type, $data = [])
  {
    $server = $this->get_server_by_id($server_id);

    if (!$server) {
      return false;
    }

    $auth_token = $this->get_auth_token($server);

    if (!$auth_token) {
      return false;
    }

    $url = "{$server['connection_url']}api/topics-resources/{$type}";

    switch ($action) {
      case 'insert':
        $url .= '/documents/upsert';
        break;
      case 'delete':
        $url .= '/delete';
        break;
    }

    $params = [
      'sslverify' => true,
      'body' => json_encode($data),
      'headers'  => [
        'Authorization' => $auth_token['token_type'] . ' ' . $auth_token['access_token'],
        'Content-Type' => 'application/json',
      ],
    ];

    $response = wp_remote_post($url, $params);

    if (is_wp_error($response)) {
      return false;
    } elseif (wp_remote_retrieve_response_code($response) === 200) {
      $response_body = wp_remote_retrieve_body($response);
      $resource_array = json_decode($response_body, true);
      if (!empty($resource_array)) {
        return array_shift($resource_array);
      } else {
        var_error_log($resource_array);
        return false;
      }
    } else {
      var_error_log($response);
      return false;
    }
  }

  private function get_auth_token($server)
  {
    $server_id = $server['connection_dir_id'];
    $auth_token = get_transient("server_{$server_id}");

    if ($auth_token) {
      return $auth_token;
    } else {
      $params = [
        'sslverify' => true,
        'body' => [
          'grant_type' => 'client_credentials',
          'client_id' => $server['connection_app_id'],
          'client_secret' => $server['connection_client_secret_id']
        ],
        'headers'  => array(
          'Content-type: application/x-www-form-urlencoded'
        ),
      ];

      $auth_request = wp_remote_post("https://login.microsoftonline.com/{$server_id}/oauth2/token", $params);

      if (is_wp_error($auth_request)) {
        var_error_log($auth_request->get_error_message());

        return false;
      } elseif (wp_remote_retrieve_response_code($auth_request) === 200) {
        $auth_body = wp_remote_retrieve_body($auth_request);
        $auth_token = json_decode($auth_body, true);
        set_transient("server_{$server_id}", $auth_token, $auth_token['expires_in']);

        return $auth_token;
      } else {
        var_error_log($auth_request);

        return false;
      }
    }
  }

  private function get_server_by_id($server_id)
  {
    /** @var array */
    $servers = get_field('connections', 'option');

    if (!$servers) {
      return false;
    }

    $key = array_search($server_id, array_column($servers, 'connection_dir_id'));
    $server = $servers[$key];

    if (!$server) {
      return false;
    }

    return $server;
  }

  function import_data()
  {
    $connection_url = get_field('standard_url', 'option');
    $result = [];
    $default_params = ['sslverify' => true];

    if ($connection_url) {
      $organizational_units = get_organizational_unit();

      foreach (array_keys($organizational_units) as $organizational_unit) {
        $query_params = [
          'state' => $organizational_unit
        ];

        $topics_request_url = add_query_arg($query_params, $connection_url . 'api/topics-resources/topics');
        $response = wp_remote_get($topics_request_url, $default_params);

        if (is_wp_error($response)) {
          $result[] = $response->get_error_message();
        } elseif (wp_remote_retrieve_response_code($response) === 200) {
          $json_topics = wp_remote_retrieve_body($response);
          $topics = json_decode($json_topics, true);

          if ($topics) {
            foreach ($topics as $topic) {

              if ($topic) {
                $this->save_topic($topic);

                $query_params = [
                  'state' => $organizational_unit,
                  'topicName' => urlencode($topic['name'])
                ];

                $resources_request_url = add_query_arg($query_params, $connection_url . 'api/topics-resources/resources');
                $response = wp_remote_get($resources_request_url, $default_params);

                if (is_wp_error($response)) {
                  $result[] = $response->get_error_message();
                } elseif (wp_remote_retrieve_response_code($response) === 200) {
                  $json_resources = wp_remote_retrieve_body($response);
                  $resources = json_decode($json_resources, true);

                  if ($resources) {
                    foreach ($resources as $resource) {
                      if ($resource) {
                        $this->save_resource($resource);
                      }
                    }
                  }
                } else {
                  $result[] = $resources_request_url . ' - ' . wp_remote_retrieve_response_code($response);
                }
              }
            }
          }
        } else {
          $result[] = $topics_request_url . ' - ' . wp_remote_retrieve_response_code($response);
        }
      }
    }

    return $result;
  }

  function save_topic($topic)
  {
    $topic = lsc_clean($topic);

    $topic_id = get_topic_id_by_uid($topic['id']);

    if ($topic_id) {
      $old_modified_time = get_term_meta($topic_id, '_modified_time', true);
      $current_modified_time = date('Y-m-d H:i:s', date('U', strtotime($topic['modifiedTimeStamp'])));
      if ($old_modified_time === $current_modified_time) {
        return;
      }
    }

    $description = '';

    if ($topic['overview']) {
      $description = '<p>' . $topic['overview'] . '</p>';
    }

    $topic_array = [
      'cat_name' => $topic['name'],
      'category_description' => $description,
      'taxonomy' => 'category'
    ];

    if ($topic_id) {
      $topic_array['cat_ID'] = $topic_id;
    }

    if (!empty($topic['parentTopicId'])) {
      $topic_uid = $topic['parentTopicId'][0]['id'];
      $parent_id = get_topic_id_by_uid($topic_uid);

      if (!$parent_id) {
        if ($saved_id = $this->save_topic_by_uid($topic_uid)) {
          $parent_id = $saved_id;
        }
      }

      $topic_array['category_parent'] = $parent_id;
    }

    $topic_id = wp_insert_category($topic_array);

    if ($topic_id) {
      update_term_meta($topic_id, '_topic_id', $topic['id']);

      $user = get_user_by('slug', $topic['createdBy']);
      if ($user) {
        $created_by = $user->ID;
      } else {
        $created_by = get_current_user_id();
      }

      update_term_meta($topic_id, '_created_by', $created_by);
      update_term_meta($topic_id, '_created_time', date('Y-m-d H:i:s', date('U', strtotime($topic['createdTimeStamp']))));

      $user = get_user_by('slug', $topic['modifiedBy']);
      if ($user) {
        $modified_by = $user->ID;
      } else {
        $modified_by = get_current_user_id();
      }

      update_term_meta($topic_id, '_modified_by', $modified_by);
      update_term_meta($topic_id, '_modified_time', date('Y-m-d H:i:s', date('U', strtotime($topic['modifiedTimeStamp']))));
      update_term_meta($topic_id, 'display', $topic['display'] === 'Yes' ? 1 : 0);
      update_term_meta($topic_id, 'topic_ranking', $topic['ranking'] ?: 1);
      update_term_meta($topic_id, 'topic_organizational_unit', strtoupper($topic['organizationalUnit']));
      update_term_meta($topic_id, 'keywords', $topic['keywords']);

      if ($topic['icon']) {
        $icon_id = $this->image_upload_file($topic['icon']);
        if ($icon_id) {
          update_term_meta($topic_id, 'icon', $icon_id);
        }
      }

      $locations = get_field('topic_locations', "category_{$topic_id}");

      if (!empty($locations)) {
        $diff = array_diff_assoc_recursive($this->transform_resource_locations($locations, 'topic'), $topic['location']);

        if (!empty($diff)) {
          delete_field('topic_locations', "category_{$topic_id}");

          $locations_rows = [];
          foreach ($topic['location'] as $location) {
            $locations_rows[] = [
              'topic_zip_code' => $location['zipCode'],
              'topic_city' => $location['city'],
              'topic_county' => $location['county'],
              'topic_state' => $location['state'],
            ];
          }

          update_field('topic_locations', $locations_rows, "category_{$topic_id}");
        }
      }

      if (empty($locations) && !empty($topic['location'])) {
        $locations_rows = [];
        foreach ($topic['location'] as $location) {
          $locations_rows[] = [
            'topic_zip_code' => $location['zipCode'],
            'topic_city' => $location['city'],
            'topic_county' => $location['county'],
            'topic_state' => $location['state'],
          ];
        }

        update_field('topic_locations', $locations_rows, "category_{$topic_id}");
      }
    }
  }

  function save_topic_by_uid($topic_uid)
  {
    $insert_data = wp_insert_term($topic_uid, 'category');
    if (!is_wp_error($insert_data)) {
      $topic_id = $insert_data['term_id'];
      update_term_meta($topic_id, '_topic_id', $topic_uid);

      return $topic_id;
    }

    return false;
  }

  function save_resource($resource)
  {
    $resource = lsc_clean($resource);

    $modified_time = date('Y-m-d H:i:s', date('U', strtotime($resource['modifiedTimeStamp'])));
    $created_time = date('Y-m-d H:i:s', date('U', strtotime($resource['createdTimeStamp'])));

    $resource_id = get_resource_id_by_uid($resource['id']);

    if ($resource_id) {
      $old_modified_time = get_post_field('post_modified', $resource_id);

      if ($old_modified_time === $modified_time) {
        return;
      }
    }

    $topics_ids = [];
    foreach ($resource['topicTags'] as $topic) {
      $topic_id = get_topic_id_by_uid($topic['id']);
      if (!$topic_id) {
        $topic_id = $this->save_topic_by_uid($topic['id']);
      }
      $topics_ids[] = $topic_id;
    }

    $user = get_user_by('slug', $resource['modifiedBy']);
    if ($user) {
      $modified_by = $user->ID;
    } else {
      $modified_by = get_current_user_id();
    }

    $content = ' ';

    if (!empty($resource['overview'])) {
      $content = '<p>' . $resource['overview'] . '</p>';
    }


    if (!empty($resource['contents'])) {
      foreach ($resource['contents'] as $item) {
        $content .= '<h4>' . $item['headline'] . '</h4>';
        $content .= '<p>' . $item['content'] . '</p>';
      }
    }

    $excerpt = '';

    if ($resource['description']) {
      $excerpt = $resource['description'];
    }

    $resource_data = array(
      'post_title'    => wp_strip_all_tags($resource['name']),
      'post_content'  => $content,
      'post_status'   => $resource['display'] === 'Yes' ? 'publish' : 'draft',
      'post_date'      => $created_time,
      'post_date_gmt'  => get_gmt_from_date($created_time),
      'post_modified'      => $modified_time,
      'post_modified_gmt'  => get_gmt_from_date($modified_time),
      'post_excerpt'   => $excerpt,
      'post_type'      => 'post',
      'post_category'  => $topics_ids,
      'meta_input'     => [
        '_resource_id' => $resource['id'],
        'resource_type' => $resource['resourceType'],
        'organizational_unit' => strtoupper($resource['organizationalUnit']),
        'resource_url' => $resource['url'] ?: '',
        '_modified_by' => $modified_by,
        'resource_display' => $resource['display'] === 'Yes' ? 1 : 0
      ],
    );

    if ('Organizations' === $resource['resourceType']) {
      if (!empty($resource['address'])) {
        $resource_data['meta_input']['resource_address'] = $resource['address'];
      }
      if (!empty($resource['telephone'])) {
        $resource_data['meta_input']['resource_telephone'] = $resource['telephone'];
      }
      if (!empty($resource['specialties'])) {
        $resource_data['meta_input']['resource_specialties'] = $resource['specialties'];
      }
      if (!empty($resource['qualifications'])) {
        $resource_data['meta_input']['resource_qualifications'] = $resource['qualifications'];
      }
      if (!empty($resource['businessHours'])) {
        $resource_data['meta_input']['resource_business_hours'] = $resource['businessHours'];
      }
      if (!empty($resource['resourceCategory'])) {
        $resource_data['meta_input']['resource_category'] = $resource['resourceCategory'];
      }
      if (!empty($resource['eligibilityInformation'])) {
        $resource_data['meta_input']['resource_eligibility_information'] = $resource['eligibilityInformation'];
      }
    }

    if ($user = get_user_by('slug', $resource['createdBy'])) {
      $resource_data['post_author'] = $user->ID;
    }

    if ($resource_id) {
      $resource_data['ID'] = $resource_id;
      wp_delete_object_term_relationships($resource_id, 'category');
    }

    add_filter('wp_insert_post_data', 'lsc_alter_post_modification_time', 99, 2);
    $resource_id = wp_insert_post($resource_data);
    remove_filter('wp_insert_post_data', 'lsc_alter_post_modification_time', 99, 2);

    $locations = get_field('locations', $resource_id);
    if (!empty($locations)) {

      $diff = array_diff_assoc_recursive($this->transform_resource_locations($locations, 'resource'), $resource['location']);

      if (!empty($diff)) {
        delete_field('locations', $resource_id);

        $locations_rows = [];
        foreach ($resource['location'] as $location) {
          $locations_rows[] = [
            'zip_code' => $location['zipCode'],
            'city' => $location['city'],
            'county' => $location['county'],
            'state' => $location['state'],
          ];
        }

        update_field('locations', $locations_rows, $resource_id);
      }
    }

    if (empty($locations) && !empty($resource['location'])) {
      $locations_rows = [];
      foreach ($resource['location'] as $location) {
        $locations_rows[] = [
          'zip_code' => $location['zipCode'],
          'city' => $location['city'],
          'county' => $location['county'],
          'state' => $location['state'],
        ];
      }

      update_field('locations', $locations_rows, $resource_id);
    }

    $topics = get_field('topics', $resource_id);
    if (!empty($topics)) {
      delete_field('topics', $resource_id);
    }

    $topics_rows = [];
    foreach ($topics_ids as $topic_id) {
      $term = get_category($topic_id);
      $topics_rows[] = [
        'topic' => $topic_id,
        'ranking' => 1
      ];
      if (!empty($resource['ranking'][$term->name])) {
        $row['ranking'] = $resource['ranking'][$term->name] ?: 1;
      }
    }

    update_field('topics', $topics_rows, $resource_id);
  }

  private function image_upload_file($file_url)
  {
    global $wpdb;
    $path_parts = pathinfo($file_url);
    $filename = $path_parts['filename'];
    $file_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'attachment'", $filename));

    if ($file_id) {
      return $file_id;
    }

    $tmp = download_url($file_url);

    $file_array = [
      'name'     => $path_parts['basename'],
      'tmp_name' => $tmp
    ];

    $id =  media_handle_sideload($file_array, 0);
    @unlink($tmp);

    if (is_wp_error($id)) {
      return false;
    }

    return $id;
  }

  private function transform_resource_locations($resource_locations, $type = 'topic')
  {
    $locations = [];
    foreach ($resource_locations as $resource_location) {
      switch ($type) {
        case 'topic':
          $locations[] = [
            'zipCode' => $resource_location['topic_zip_code'],
            'city' => $resource_location['topic_city'],
            'county' => $resource_location['topic_county'],
            'state' => $resource_location['topic_state']
          ];
          break;
        case 'resource':
          $locations[] = [
            'zipCode' => $resource_location['zip_code'],
            'city' => $resource_location['city'],
            'county' => $resource_location['county'],
            'state' => $resource_location['state']
          ];
          break;
      }
    }

    return $locations;
  }

  function get_curated_experiences($server_id, $unit = null)
  {
    $server = $this->get_server_by_id($server_id);

    if (!$server) {
      return false;
    }

    $auth_token = $this->get_auth_token($server);

    if (!$auth_token) {
      return false;
    }

    $url = "{$server['connection_url']}api/curated-experiences";

    $params = [
      'sslverify' => true,
      'headers'  => [
        'Authorization' => $auth_token['token_type'] . ' ' . $auth_token['access_token'],
        'Content-Type' => 'application/json',
      ],
    ];

    $query_params = [];

    if ($unit) {
      $query_params['location'] = $unit;
    }

    $response = wp_remote_get(add_query_arg($query_params, $url), $params);

    if (is_wp_error($response)) {
      return false;
    } elseif (wp_remote_retrieve_response_code($response) === 200) {
      $response_body = wp_remote_retrieve_body($response);
      $response_array = json_decode($response_body, true);
      return $response_array;
    } else {
      var_error_log($response);
      return false;
    }
  }

  function delete_curated_experience($server_id, $ce)
  {
    $server = $this->get_server_by_id($server_id);

    if (!$server) {
      return false;
    }

    $auth_token = $this->get_auth_token($server);

    if (!$auth_token) {
      return false;
    }

    $url = "{$server['connection_url']}api/curated-experiences/{$ce['id']}/{$ce['title']}";

    $params = [
      'sslverify' => true,
      'headers'  => [
        'Authorization' => $auth_token['token_type'] . ' ' . $auth_token['access_token'],
        'Content-Type' => 'application/json',
      ],
      'method' => 'DELETE'
    ];

    $response = wp_remote_request($url, $params);

    if (is_wp_error($response)) {
      return false;
    } elseif (wp_remote_retrieve_response_code($response) === 200) {
      return true;
    } else {
      var_error_log($response);
      return false;
    }
  }

  function upload_curated_experience($server_id, $data)
  {
    $server = $this->get_server_by_id($server_id);

    if (!$server) {
      return false;
    }

    $auth_token = $this->get_auth_token($server);

    if (!$auth_token) {
      return false;
    }

    $url = "{$server['connection_url']}api/admin/curated-experience";

    $local_file = !empty($data['file']) ? $data['file']['tmp_name'] : '';

    $post_fields = array(
      'name' => $data['name'],
      'description' => $data['description']
    );

    $boundary = wp_generate_password(24);
    $headers  = array(
      'Authorization' => $auth_token['token_type'] . ' ' . $auth_token['access_token'],
      'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
    );

    $payload = '';

    // First, add the standard POST fields:
    foreach ($post_fields as $name => $value) {
      $payload .= '--' . $boundary;
      $payload .= "\r\n";
      $payload .= 'Content-Disposition: form-data; name="' . $name .
        '"' . "\r\n\r\n";
      $payload .= $value;
      $payload .= "\r\n";
    }

    // Upload the file
    if ($local_file) {
      $payload .= '--' . $boundary;
      $payload .= "\r\n";
      $payload .= 'Content-Disposition: form-data; name="templateFile"; filename="' . $data['file']['name'] . '"' . "\r\n";
      $payload .= 'Content-Type: "' . $data['file']['type'] . '"' . "\r\n";
      $payload .= "\r\n";
      $payload .= file_get_contents($local_file);
      $payload .= "\r\n";
    }

    $payload .= '--' . $boundary . '--';

    $response = wp_remote_post(
      $url,
      array(
        'sslverify' => true,
        'headers'    => $headers,
        'body'       => $payload,
      )
    );

    if (is_wp_error($response)) {
      return false;
    } elseif (wp_remote_retrieve_response_code($response) === 200) {
      $response_body = wp_remote_retrieve_body($response);
      $response_array = json_decode($response_body, true);
      return $response_array;
    } elseif (wp_remote_retrieve_response_code($response) === 400) {
      $response_body = wp_remote_retrieve_body($response);
      $response_array = json_decode($response_body, true);
      return $response_array;
    } else {
      var_error_log($response);
      return false;
    }
  }
}
