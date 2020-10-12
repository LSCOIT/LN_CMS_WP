<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
    die('-1');
}

class LSC_Options
{
    public function __construct()
    {
        add_filter('acf/load_field/name=connection_allowed_for', [$this, 'get_roles']);
        add_action('load-settings_page_integration-settings', [$this, 'import_block']);
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

    function get_roles($field)
    {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $roles_array = [];

        foreach ($roles as $key => $value) {
            $roles_array[$key] = $value['name'];
        }

        $field['choices'] = $roles_array;

        return $field;
    }
}
