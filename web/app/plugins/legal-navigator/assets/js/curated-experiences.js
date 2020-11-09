(function ($) {
  'use strict';

  $(function () {
    $('#js-show-upload').click(function () {
      $('#upload-form').toggle();
    });

    let org_unit = null;
    let scope_id = $('[name="scope_id"]:checked').val();
    const table = $('#curated-experiences-table').DataTable({
      ajax: {
        url: lsc_ce_params.ajax_url,
        type: 'POST',
        data: function (data) {
          data.security = lsc_ce_params.curated_experiences_nonce;
          data.scope_id = scope_id;
          data.org_unit = org_unit ? org_unit : null;
          data.action = 'lsc_get_curated_experiences';
        },
        dataSrc: function (json) {
          json.forEach((element) => {
            var date = new Date(element._ts * 1000);
            var dd = date.getDate();
            var mm = date.getMonth() + 1;
            var yyyy = date.getFullYear();
            if (dd < 10) {
              dd = '0' + dd;
            }
            if (mm < 10) {
              mm = '0' + mm;
            }
            let formatted_date = dd + '/' + mm + '/' + yyyy;
            element.date = formatted_date;
          });
          return json;
        },
      },
      processing: true,
      language: {
        loadingRecords: '&nbsp;',
        processing: '<div class="spinner"></div>',
      },
      order: [[2, 'desc']],
      columns: [{ data: 'id' }, { data: 'title' }, { data: 'date' }, null, { data: '_ts' }],
      columnDefs: [
        {
          targets: 3,
          data: null,
          defaultContent: '<button>Delete</button>',
          sorting: false,
        },
        { orderData: [4], targets: [2] },
        {
          targets: [4],
          visible: false,
          searchable: false,
        },
      ],
    });

    $('[name="organizational_unit"]').click(function () {
      if ($(this).hasClass('check')) {
        $(this).prop('checked', false).removeClass('check');
      } else {
        $(this).prop('checked', true).addClass('check');
      }

      org_unit = $('[name="organizational_unit"]:checked').val();
      $('[name="organizational_unit"]').not(this).removeClass('check');
      table.ajax.reload();
    });

    $('[name="scope_id"]').change(function () {
      if ($(this).prop('checked', true)) {
        scope_id = $(this).val();
        table.ajax.reload();
      }
    });

    $('#curated-experiences-table tbody').on('click', 'button', function () {
      var data = table.row($(this).parents('tr')).data();
      $.ajax({
        url: lsc_ce_params.ajax_url,
        type: 'post',
        data: {
          security: lsc_ce_params.curated_experiences_nonce,
          action: 'lsc_delete_curated_experience',
          scope_id: scope_id,
          item: data,
        },
        dataType: 'json',
        success: function (json) {
          if (json.success) {
            table.ajax.reload();
          }
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        },
      });
    });

    $('#upload-form').submit(function (e) {
      e.preventDefault();
      var form = $(this);
      var form_data = new FormData(this);
      form_data.append('security', lsc_ce_params.curated_experiences_nonce);
      form_data.append('scope_id', scope_id);
      form_data.append('org_unit', org_unit ? org_unit : null);
      form_data.append('action', 'lsc_upload_curated_experience');

      $.ajax({
        url: lsc_ce_params.ajax_url,
        type: 'post',
        data: form_data,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function (json) {
          const uploadMessage = $('#upload-message');
          uploadMessage.show().removeClass('notice-success').removeClass('notice-error').text('');
          uploadMessage.text(json.data);
          if (json.success) {
            form.find('[name="name"]').val('');
            form.find('[name="description"]').val('');
            form.find('[name="templateFile"]').val('');
            uploadMessage.addClass('notice-success');
          } else {
            uploadMessage.addClass('notice-error');
          }
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        },
      });
    });
  });
})(jQuery);
