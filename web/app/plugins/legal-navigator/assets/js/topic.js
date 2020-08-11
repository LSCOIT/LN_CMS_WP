(function ($) {
  'use strict';

  $(function () {
    var button = $('#upload-topic');
    button.prop('disabled', true);

    $('input[name="server_id"]').click(function () {
      button.prop('disabled', false);
    });

    button.on('click', function () {
      $.ajax({
        url: lsc_topic_params.ajax_url,
        type: 'post',
        data: {
          server_id: $('input[name="server_id"]:checked').val(),
          security: lsc_topic_params.upload_topic_nonce,
          action: 'lsc_upload_topic',
          term_id: lsc_topic_params.term_id,
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

      return false;
    });
  });
})(jQuery);
