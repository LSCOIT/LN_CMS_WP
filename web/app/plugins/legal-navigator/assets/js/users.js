(function ($) {
  'use strict';

  $(function () {
    $('#role').change(function () {
      var acf_form = $('#acf-form-data');
      if (acf_form.length > 0) {
        var user_role = $(this).val();
        var acf_header = acf_form.next();
        var acf_fields = $(acf_header).next();

        if (user_role === 'administrator') {
          $(acf_header).hide();
          $(acf_fields).hide();
        } else {
          $(acf_header).show();
          $(acf_fields).show();
        }
      }
    });
  });
})(jQuery);
