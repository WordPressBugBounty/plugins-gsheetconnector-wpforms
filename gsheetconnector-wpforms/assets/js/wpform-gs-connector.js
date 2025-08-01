jQuery(document).ready(function () {
     
   /**
    * verify the api code
    * @since 1.0
    */
    jQuery(document).on('click', '#save-wpform-gs-code', function (event) {
		event.preventDefault();
        jQuery( ".loading-sign" ).addClass( "loading" );
        var data = {
        action: 'verify_wpform_gs_integation',
        code: jQuery('#wpforms-setting-google-access-code').val(),
        security: jQuery('#gs-ajax-nonce').val()
      };
      jQuery.post(ajaxurl, data, function (response ) {
          if( ! response.success ) { 
            jQuery( ".loading-sign" ).removeClass( "loading" );
            jQuery( "#gs-validation-message" ).empty();
            jQuery("<span class='error-message'>Invalid access code entered.</span>").appendTo('#gs-validation-message');
          } else {
            jQuery( ".loading-sign" ).removeClass( "loading" );
            jQuery( "#gs-validation-message" ).empty();
            jQuery("<span class='gs-valid-message'>Your Google Access Code is Authorized or Saved.</span> <br/><br/><span class='wp-valid-notice'> Note: If you are getting any errors or not showing sheet in dropdown, then make sure to check the debug log. To contact us for any issues do send us your debug log.</span>").appendTo('#gs-validation-message');
			//setTimeout(function () { location.reload(); }, 9000);

         setTimeout(function () { 
            window.location.href = jQuery("#redirect_auth_wpforms").val();
         }, 1000);
		  }
      });
      
    });  
    
	function html_decode(input) {
      var doc = new DOMParser().parseFromString(input, "text/html");
      return doc.documentElement.textContent;
   }

	/**
     * On select wpform
     */
   jQuery('#wpforms_select').change(function (e) {
      e.preventDefault();
      var FormId = jQuery(this).val();
      jQuery(".loading-sign-select").addClass("loading-select");
      jQuery.ajax({
         type: "POST",
         url: ajaxurl,
         dataType: "json",
         data: {
            action: 'get_wpforms',
            wpformsId: FormId,
            security: jQuery('#wp-ajax-nonce').val(),
         },
         cache: false,
         success: function (data) {  
         // console.log(data);        
            if (data['data_result'] == '') {
               return;
            }
            else {
                window.location.href = data.data;
                // window.open(data.data, '_blank');
               // jQuery("#inside").empty();
               // jQuery("#inside").append(html_decode(data.data));
               // jQuery(".loading-sign-select").removeClass("loading-select");
            }
         }
      });
   });
   
    /**
     * Clear debug
     */
      jQuery(document).on('click', '.debug-clear-kk', function () {
         jQuery( ".clear-loading-sign" ).addClass( "loading" );
         var data = {
            action: 'wp_clear_logs',
            security: jQuery('#gs-ajax-nonce').val()
         };
         jQuery.post(ajaxurl, data, function (response ) {
             var clear_msg = response.data;
            if( response.success ) { 
               jQuery( ".clear-loading-sign" ).removeClass( "loading" );
               jQuery( "#gs-validation-message" ).empty();
               jQuery("<span class='gs-valid-message'>"+clear_msg+"</span>").appendTo('#gs-validation-message'); 
               setTimeout(function () {
                     location.reload();
                 }, 1000);
            }
         });
      });
	  
	   /**
    * deactivate the api code
    * @since 1.0
    */
    jQuery(document).on('click', '#wp-deactivate-log', function () {
        jQuery(".loading-sign-deactive").addClass( "loading" );
		var txt;
		var r = confirm("Are you sure you want to deactivate Google Sheet Integration ?");
		if (r == true) {
			var data = {
				action: 'deactivate_wpformgsc_integation',
				security: jQuery('#gs-ajax-nonce').val()
			};
			jQuery.post(ajaxurl, data, function (response ) {
				if ( response == -1 ) {
					return false; // Invalid nonce
				}
			 
				if( ! response.success ) {
					alert('Error while deactivation');
					jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
					jQuery( "#deactivate-message" ).empty();
					
				} else {
					jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
					jQuery( "#deactivate-message" ).empty();
					jQuery("</br><span class='gs-valid-message'>Your account is removed, now reauthenticate to configure WPForms to Google Sheet.</span>").appendTo('#deactivate-message');
		   		    setTimeout(function () { location.reload(); }, 1000);
				}
			});
		} else {
			jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
		}
    });

    /**
     * Display Error logs
     */
    jQuery(document).ready(function($) {
      // Hide .wp-system-Error-logs initially
      $('.wp-system-Error-logs').hide();
  
      // Add a variable to track the state
      var isOpen = false;
  
      // Function to toggle visibility and button text
      function toggleLogs() {
          $('.wp-system-Error-logs').toggle();
          // Change button text based on visibility
          $('.wpgsc-logs').text(isOpen ? 'View' : 'Close');
          isOpen = !isOpen; // Toggle the state
      }
  
      // Toggle visibility and button text when clicking .wpgsc-logs button
      $('.wpgsc-logs').on('click', function() {
          toggleLogs();
      });
  
      // Prevent clicks inside the .wp-system-Error-logs div from closing it
      $('.wp-system-Error-logs').on('click', function(e) {
          e.stopPropagation(); // Prevents the div from closing when clicked inside
      });
  
      // Only close the .wp-system-Error-logs when the "Close" button is clicked
      $('.close-button').on('click', function() {
          $('.wp-system-Error-logs').hide();
          $('.wpgsc-logs').text('View');
          isOpen = false;
      });
  });


  // Msg Hide ///
	
jQuery(document).ready(function($) {
   // Check if the message has already been hidden by looking in localStorage
   if (localStorage.getItem('googleDriveMsgHidden') === 'true') {
       jQuery('#google-drive-msg').hide(); // Hide the message if it's already hidden
   }

   // On button click, hide the #google-drive-msg div and store the hidden state in localStorage
   jQuery('.button_wpformgsc').on('click', function() {
       jQuery('#google-drive-msg').hide(); // Hide the message
       localStorage.setItem('googleDriveMsgHidden', 'true'); // Save the hidden state in localStorage
   });

   // On #deactivate-log click, show the #google-drive-msg div and clear localStorage
   jQuery('#wp-deactivate-log').on('click', function() {
       jQuery('#google-drive-msg').show(); // Show the message
       localStorage.removeItem('googleDriveMsgHidden'); // Remove the hidden state from localStorage
   });
});
  
   /**
    * Clear debug for system status tab
    */
   jQuery(document).on('click', '.clear-content-logs-wp', function () {

      jQuery(".clear-loading-sign-logs-wp").addClass("loading");
      var data = {
         action: 'wp_clear_debug_logs',
         security: jQuery('#gs-ajax-nonce').val()
      };
      jQuery.post(ajaxurl, data, function ( response ) {
         if (response == -1) {
            return false; // Invalid nonce
         }
         
         if (response.success) {
            jQuery(".clear-loading-sign-logs-wp").removeClass("loading");
            jQuery('.clear-content-logs-msg-wp').html('Logs are cleared.');
            setTimeout(function () {
                        location.reload();
                    }, 1000);
         }
      });
   }); 
});
jQuery(document).ready(function ($) {
   $('.install-plugin-btn').on('click', function () {
       var button = $(this);
       var pluginSlug = button.data('plugin');
       var downloadUrl = button.data('download');
       var loader = button.find('.loaderimg');

       loader.css('display', 'inline-block'); // Show loader

       button.html('<img src="' + loader.attr('src') + '" alt="Loading..."> Installing...')
           .prop('disabled', true);

       $.ajax({
           url: ajaxurl,
           type: 'POST',
           data: {
               action: 'install_plugin',
               plugin_slug: pluginSlug,
               download_url: downloadUrl,
               security: pluginInstallData.nonce,  // for install
           },
           success: function (response) {
               if (response.success) {
                   // Hide the "Install" button
                   button.hide();

                   // Show the corresponding "Activate" button
                   button.closest('.button-bar').find('.activate-plugin-btn').show();
               } else {
                   alert('Installation failed: ' + (response.data?.message || 'Unknown error'));
                   button.html('Install').prop('disabled', false);
               }
           },

           error: function () {
               button.html('Install').prop('disabled', false);
               alert('Error installing the plugin.');
           }
       });
   });

   // Plugin Activation
   $(document).on('click', '.activate-plugin-btn', function () {
       var button = $(this);
       var pluginSlug = button.data('plugin');
       var loader = button.find('.loaderimg');
       loader.css('display', 'inline-block'); // Show loader

       button.html('<img src="' + loader.attr('src') + '" alt="Loading..."> Activating...')
           .prop('disabled', true);

       $.ajax({
           url: ajaxurl,
           type: 'POST',
           data: {
               action: 'wc_gsheetconnector_activate_plugin',
               plugin_slug: pluginSlug,
               security: pluginActivateData.nonce, // for activate
           },
           success: function (response) {
               if (response.success) {
                   button.text('Activated').prop('disabled', true);
                   loader.hide();
                   location.reload();

               } else {
                   button.html('Activate').prop('disabled', false);
                   alert('Activation failed: ' + (response.data?.message || 'Unknown error'));
               }
           },
           error: function () {
               button.html('Activate').prop('disabled', false);
               alert('Error activating the plugin.');
           }
       });
   });
    $('.deactivate-plugin').on('click', function () {
        var pluginSlug = $(this).data('plugin');

        if (!pluginSlug) {
            alert('Plugin slug not found.');
            return;
        }

        $.ajax({
            url: pluginDeactivateData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wc_gsheetconnector_deactivate_plugin',
                plugin_slug: pluginSlug,
                security: pluginDeactivateData.nonce // for deactivate
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    });

});