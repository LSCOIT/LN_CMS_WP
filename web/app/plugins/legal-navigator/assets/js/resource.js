(function ($) {
  'use strict';

  $(function () {
    var button = $('#upload-resource');
    button.prop('disabled', true);

    $('input[name="server_id"]').click(function () {
      button.prop('disabled', false);
    });

    button.on('click', function () {
      $.ajax({
        url: lsc_resource_params.ajax_url,
        type: 'post',
        data: {
          server_id: $('input[name="server_id"]:checked').val(),
          security: lsc_resource_params.upload_resource_nonce,
          action: 'lsc_upload_resource',
          post_id: lsc_resource_params.post_id,
        },
        dataType: 'json',
        success: function (json) {
          /* progressbarDialog.dialog('close');
          endImportDialog.dialog('option', 'title', json.title);
          endImportDialog.html('<p>' + json.message + '</p>');
          endImportDialog.dialog('open'); */
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        },
      });
    });
  });
})(jQuery);
