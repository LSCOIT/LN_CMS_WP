<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
  die('-1');
}

class LSC_Curated_Experiences
{

  function __construct()
  {
    add_action('admin_menu', [$this, 'register_page']);
  }

  function register_page()
  {
    add_menu_page('Curated Experiences', 'Curated Experiences', 'edit_others_posts', 'curated-experiences', [$this, 'page_output'], 'dashicons-editor-ol', 6);
  }

  function page_output()
  {
    $servers = get_field('connections', 'option');
    $table = [];
    foreach ($servers as $server) {
      $server_id = $server['connection_dir_id'];
      $user = wp_get_current_user();
      if (current_user_can('administrator') || array_intersect($user->roles, $server['connection_allowed_for'])) {
        $table[] = [
          'value' => $server_id,
          'title' => $server['connection_name'],
        ];
      }
    }

    $organizational_units = get_organizational_unit();
?>
    <div class="wrap">
      <h1 class="wp-heading-inline">Curated Experiences</h1>
      <button type="button" class="page-title-action" id="js-show-upload">Upload</button>
      <hr class="wp-header-end">
      <form id="upload-form" enctype="multipart/form-data" method="POST">
        <h2>Upload Curated Experiences</h2>
        <p>Upload curated experience template file using this form</p>
        <table class="form-table">
          <tr class="form-field">
            <th>Name*</th>
            <td>
              <input type="text" name="name" required>
            </td>
          </tr>
          <tr class="form-field">
            <th>Description*</th>
            <td>
              <textarea name="description" rows="10" required></textarea>
            </td>
          </tr>
          <tr class="form-field">
            <th>Select Template*</th>
            <td>
              <input type="file" name="templateFile" required>
            </td>
          </tr>
        </table>
        <div id="upload-message" class="notice inline"></div>
        <?php submit_button('Upload', 'primary', 'upload-curated-experiences'); ?>
      </form>
      <div class="checkboxes-box">
        <div class="servers">
          <h3>Servers</h3>
          <?php foreach ($table as $key => $item) { ?>
            <div class="server-item">
              <label>
                <input<?php checked($key, 0) ?> type="radio" name="server_id" value="<?php echo $item['value']; ?>"> <?php echo $item['title']; ?>
              </label>
            </div>
          <?php } ?>
        </div>
        <div class="locations">
          <h3>Organizational Units</h3>
          <?php foreach ($organizational_units as $unit) { ?>
            <div class="server-item">
              <label><input type="radio" name="organizational_unit" value="<?php echo $unit; ?>"> <?php echo $unit; ?></label>
            </div>
          <?php } ?>
        </div>
      </div>
      <table id="curated-experiences-table" class="curated-experiences-table">
        <thead>
          <tr>
            <th>Curated Experience Id</th>
            <th>Title</th>
            <th>Created At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th>Curated Experience Id</th>
            <th>Title</th>
            <th>Created At</th>
            <th>Action</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <br class="clear">
<?php
  }
}
