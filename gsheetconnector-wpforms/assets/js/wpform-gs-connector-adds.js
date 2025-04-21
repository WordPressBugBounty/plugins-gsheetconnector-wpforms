jQuery(document).ready(function () {

 jQuery('.wpform-gs-set-auth-expired-adds-interval').click(function () {
      var data = {
         action: 'wpform_gs_set_auth_expired_adds_interval',
         security: jQuery('#wpform_gs_auth_expired_adds_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         if (response.success) {
            jQuery('.wpform-gs-auth-expired-adds').slideUp('slow');
         }
      });
   });

 jQuery('.wpform-gs-close-auth-expired-adds-interval').click(function () {
      var data = {
         action: 'wpform_gs_close_auth_expired_adds_interval',
         security: jQuery('#wpform_gs_auth_expired_adds_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         if (response.success) {
            jQuery('.wpform-gs-auth-expired-adds').slideUp('slow');
         }
      });
   });

});