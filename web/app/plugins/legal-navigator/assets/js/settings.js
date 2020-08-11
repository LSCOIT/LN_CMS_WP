(function ($) {
  'use strict';

  $(function () {
    const importDialog = $('#import-data-dialog').dialog({
      dialogClass: 'wp-dialog',
      autoOpen: false,
      modal: true,
      width: 500,
      resizable: false,
      draggable: false,
      buttons: [
        {
          text: 'Import',
          click: function () {
            $(this).dialog('close');
            progressbarDialog.dialog('open');
            $('.progressbar').progressbar({
              value: false,
            });
            import_data();
          },
        },
      ],
    });

    const progressbarDialog = $('#progressbar-dialog').dialog({
      dialogClass: 'no-close',
      autoOpen: false,
      modal: true,
      width: 600,
      height: 58,
      resizable: false,
      draggable: false,
    });

    const endImportDialog = $('#end-import-dialog').dialog({
      dialogClass: 'wp-dialog',
      autoOpen: false,
      modal: true,
      width: 500,
      resizable: false,
      draggable: false,
    });

    $('#import-data').click(function () {
      importDialog.dialog('open');
    });

    function import_data() {
      $.ajax({
        url: lsc_import_params.ajax_url,
        type: 'post',
        data: {
          security: lsc_import_params.import_data_nonce,
          action: 'lsc_import_data',
        },
        dataType: 'json',
        success: function (json) {
          progressbarDialog.dialog('close');
          endImportDialog.dialog('option', 'title', json.title);
          endImportDialog.html('<p>' + json.message + '</p>');
          endImportDialog.dialog('open');
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        },
      });
    }
  });
})(jQuery);
