<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
    die('-1');
}

class LSC_Options
{
    public function __construct()
    {
        add_action('acf/init', [$this, 'add_options_page']);
        add_action('acf/init', [$this, 'add_fields']);
        add_action('load-settings_page_integration-settings', [$this, 'import_block']);
    }

    public function add_options_page()
    {
        acf_add_options_sub_page(array(
            'page_title' => __('Integration Settings', 'legal-navigator'),
            'menu_title' => __('Integration Settings', 'legal-navigator'),
            'parent_slug' => 'options-general.php',
            'menu_slug' => 'integration-settings',
            'capability' => 'manage_options'
        ));
    }

    public function add_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_standard_connection',
            'title' => 'Standard Connection',
            'fields' => array(
                array(
                    'key' => 'field_standard_url',
                    'label' => 'Standard Url',
                    'name' => 'standard_url',
                    'type' => 'url',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_connections',
                    'label' => 'Connections',
                    'name' => 'connections',
                    'type' => 'repeater',
                    'layout' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_connection_name',
                            'label' => 'Connection Name',
                            'name' => 'connection_name',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => array(
                                'width' => 33,
                            ),
                        ),
                        array(
                            'key' => 'field_connection_url',
                            'label' => 'Url',
                            'name' => 'connection_url',
                            'type' => 'url',
                            'required' => 1,
                            'wrapper' => array(
                                'width' => 34,
                            ),
                        ),
                        array(
                            'key' => 'field_connection_allowed_for',
                            'label' => 'Allowed for',
                            'name' => 'connection_allowed_for',
                            'type' => 'checkbox',
                            'required' => 1,
                            'choices' => $this->get_roles(),
                            'wrapper' => [
                                'width' => 33
                            ]
                        ),
                        array(
                            'key' => 'field_connection_dir_id',
                            'label' => 'Directory (tenant) ID',
                            'name' => 'connection_dir_id',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => array(
                                'width' => 33,
                            ),
                        ),
                        array(
                            'key' => 'field_connection_app_id',
                            'label' => 'Application (client) ID',
                            'name' => 'connection_app_id',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => array(
                                'width' => 34,
                            ),
                        ),
                        array(
                            'key' => 'field_connection_client_secret_id',
                            'label' => 'Client Secret ID',
                            'name' => 'connection_client_secret_id',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => array(
                                'width' => 33,
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'integration-settings',
                    ),
                ),
            ),
            'style' => 'seamless',
        ));
    }

    public function import_block()
    {
        add_action('acf/render_fields', [$this, 'import_output']);
    }

    public function import_output()
    {
        if (get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
?>
        <?php submit_button('Import data from standard server', 'primary', 'import-data'); ?>

        <div id="import-data-dialog" title="Import data from Legal Navigator">
            <p>Import data from standard server. All data will be overwritten.</p>
            <p>Are you want to continue?</p>
        </div>

        <div id="progressbar-dialog">
            <div class="progressbar-title">Loading...</div>
            <div class="progressbar"></div>
        </div>

        <div id="end-import-dialog"></div>
<?php
    }

    public function get_roles()
    {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $roles_array = [];

        foreach ($roles as $key => $value) {
            $roles_array[$key] = $value['name'];
        }

        return $roles_array;
    }
}
