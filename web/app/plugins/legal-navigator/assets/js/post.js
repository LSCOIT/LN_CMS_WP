(function ($) {
  'use strict';

  $(function () {
    var button = $('#upload-post');
    button.prop('disabled', true);

    $('input[name="scope_id"]').click(function () {
      button.prop('disabled', false);
    });

    button.on('click', function (event) {
      event.preventDefault();

      const scope_id = $('input[name="scope_id"]:checked').val();
      $.ajax({
        url: lsc_post_params.ajax_url,
        type: 'post',
        data: {
          scope_id: scope_id,
          security: lsc_post_params.upload_post_nonce,
          action: 'lsc_upload_post',
          post_id: lsc_post_params.post_id,
        },
        dataType: 'json',
        success: function (json) {
          const uploadMessage = $('#upload-message');
          uploadMessage.removeClass().text('');
          uploadMessage.text(json.data.text);
          if (json.success) {
            uploadMessage.addClass('success');
            $('.upload-post-table .date_' + scope_id).text(json.data.date);
          } else {
            uploadMessage.addClass('error');
          }
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        },
      });
    });
  });
})(jQuery);
