/* global wpforms_builder, wpf */
'use strict';

var WPFormsBuilderGooglesheets = window.WPFormsBuilderGooglesheets || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.0.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * jQuery selector for holder.
		 *
		 * @since 1.0.0
		 *
		 * @type {object}
		 */
		$holder: $( '.wpforms-panel-content-section-wf_googlesheets' ),

		/**
		 * Start the engine.
		 *
		 * @since 1.0.0
		 */
		init: function() {

			// Do that when DOM is ready.
			$( document ).ready( app.ready );
		},

		/**
		 * DOM is fully loaded.
		 *
		 * @since 1.0.0
		 */
		ready: function() {

			app.events();
			$('#wpforms-panel-field-wpgs_spreadsheets-1-gs_sheet_integration_mode option[value="automatic_disabled"]').prop('disabled', true);
			
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.0.0
		 */
		events: function() {

			$( '#wpforms-builder' )
				.on( 'wpformsSaved', app.wpformsSaved )
				.on( 'wpformsFieldAdd', app.showReloadWarning )
				.on( 'wpformsFieldDelete', app.showReloadWarning )
				.on( 'change.wpformsGooglesheets', '#wpforms-panel-field-settings-gsheetconnector-wpforms', app.googlesheetsToggle )

				.on( 'change', '.select_spreadsheet_input', app.populateSheetInfo)
				.on( 'change', '.select_tabs_input, .select_spreadsheet_input', app.populateSheetLink)
				.on( 'click', '.force_reload p a', app.saveAndReload);
			
			
		},
		
		
		
		populateSheetLink: function( e, data ) {
			var $parent = $(this).parents(".wpforms-builder-settings-block-content");
			
			var $sheet_id = $parent.find(".select_spreadsheet_input").val();
			var $tab_id = $parent.find(".select_tabs_input").val();

			var $link = "https://docs.google.com/spreadsheets/d/"+$sheet_id+"/edit#gid="+$tab_id+"";
			
			$parent.find("a.google_sheet_url").attr("href", $link);
		},
		
		populateSheetLink2: function( $element ) {
			var $parent = $element.parents(".wpforms-builder-settings-block-content");
			
			var $sheet_id = $parent.find(".select_spreadsheet_input").val();
			var $tab_id = $parent.find(".select_tabs_input").val();

			var $link = "https://docs.google.com/spreadsheets/d/"+$sheet_id+"/edit#gid="+$tab_id+"";
			
			$parent.find("a.google_sheet_url").attr("href", $link);
		},
		
		populateSheetInfo: function( e, data ) {
			//console.log($(this).val());
			var $selected_option = $(this).val();
			var $parent = $(this).parents(".wpforms-builder-settings-block-content");
				
			if( $selected_option == "create_new" ){
				$parent.find(".gs_sheet_select_tab_wrapper").hide();
				$parent.find(".gs_sheet_create_new_name_wrapper").show();
			}
			else {
				
				$parent.find(".gs_sheet_select_tab_wrapper").show();
				$parent.find(".gs_sheet_create_new_name_wrapper").hide();
				
				var $sheets = $("#gs_sheet_select_sheets_list").val();
				var $decodeSheets = JSON.parse($sheets);
				
				
				$.each( $decodeSheets, function(sheetName, sheetObject) {			
					var $sheetID = sheetObject.id;				
					var $tab_options = "";
					
					if( $sheetID == $selected_option ) {
						$.each( sheetObject.tabId, function(tabName, tabID) {			
							$tab_options += '<option value="'+tabID+'">'+tabName+'</option>';
						});
						
						$parent.find(".select_tabs_input").html($tab_options);
					}
					
				} );
				
			}
		},
		
		saveAndReload: function( e, data ) {
			$(".wfgs_force_reload").val(1);
			WPFormsBuilder.formSave();
		},
		
		showReloadWarning: function( e, data ) {
			$( ".reload_warning" ).show();		 
		},
		
		
		wpformsSaved: function( e, data ) {
			
			if( typeof data != 'undefined' && typeof data.force_reload != 'undefined' && data.force_reload == 1 ) {
				
				window.location.reload(true);
			}
		},

		/**
		 * Toggle the displaying googlesheet settings depending on if the
		 * googlesheets are enabled.
		 *
		 * @since 1.0.0
		 */
		googlesheetsToggle: function() {

			app.$holder
				.find( '.wpforms-builder-settings-block-googlesheet, .wpforms-webooks-add' )
				.toggleClass( 'hidden', '0' === $( this ).val() );
		},

	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

WPFormsBuilderGooglesheets.init();

jQuery(document).ready(function ($) {
  jQuery(document).on("click", "#copy-wpfservicefree-email", function (e) {
      e.preventDefault();

      var $this = jQuery(this);
      var email = $this.data("email").trim();

      // Copy to clipboard
      navigator.clipboard.writeText(email).then(function () {

          var $msg = $this.siblings(".gsc-copy-msg");

          // Show message
          $msg.removeClass("d-none");

          // Hide after 1 second
          setTimeout(function () {
              $msg.addClass("d-none");
          }, 1000);

      });
  });
});



jQuery(document).ready(function ($) {
    // Disable all options except the first one
    jQuery('#wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_name option:not(:first)').prop('disabled', true);
});

jQuery(document).ready(function ($) {
    $(document).on('click', '.gsc-copy-btn-feed-setting', function (e) {
        e.preventDefault();
        
        const email = $(this).data('email');
        const $msg = $(this).closest('.gsc-email-box').find('.gsc-copy-msg');
        
        // Copy to clipboard
        navigator.clipboard.writeText(email).then(function () {
            // Show message
            $msg.removeClass('d-none');
            
            // Hide after 2 seconds
            setTimeout(function () {
                $msg.addClass('d-none');
            }, 2000);
        }).catch(function () {
            alert('Failed to copy email');
        });
    });
});

jQuery(document).ready(function ($) {
	jQuery(".wpgs_header_color").wpColorPicker();
});



/** Edit feed name n inner feed section  */
/*jQuery(document).on('click', '.wpforms-builder-settings-block-edit', function() {
   
    var blockId = 1;
   
    jQuery('.wpforms-builder-settings-block-name').hide();
    jQuery('.wpforms-builder-settings-block-edit').hide();

   
    jQuery('.wpforms-builder-settings-block-name-edit').show();
    jQuery('.wpforms-builder-settings-block-name-edit-cancel').show();
});
*/



/** Edit feed name n inner feed section  */
jQuery(document).on('click', '.wpforms-builder-settings-block-edit', function() {
    
    var blockId = jQuery(this).data('id');
    /*  Hide the span */
    jQuery('.gscwpforms-builder-settings-block-name').hide();
    jQuery('.wpforms-builder-settings-block-edit[data-id="' + blockId + '"]').hide();

    /*  Show the input div */
	jQuery('.wpforms-builder-settings-block-name-edit-field[data-block-id="' + blockId + '"]').show();
    jQuery('.wpforms-builder-settings-block-name-edit').show();
    jQuery('.wpforms-builder-settings-block-name-edit-cancel[data-block-id="' + blockId + '"]').show();
});


/** Edit feed name close inner feed section  */
jQuery(document).on('click', '.wpforms-builder-settings-block-name-edit-cancel', function() {
    
    var blockId = jQuery(this).data('block-id');
    /*  Hide the span */
     jQuery('.gscwpforms-builder-settings-block-name').show();
    jQuery('.wpforms-builder-settings-block-edit[data-id="' + blockId + '"]').show();

    /*  Show the input div */
	jQuery('.wpforms-builder-settings-block-name-edit-field[data-block-id="' + blockId + '"]').hide();
    jQuery('.wpforms-builder-settings-block-name-edit').hide();
    jQuery('.wpforms-builder-settings-block-name-edit-cancel[data-block-id="' + blockId + '"]').hide();
});


/** Save */
jQuery(document).on('click', '.wpforms-builder-settings-block-name-edit-save', function() {

    var blockId = jQuery(this).data('block-id');
    var $saveBtn = jQuery(this);
    var $cancelBtn = jQuery('.wpforms-builder-settings-block-name-edit-cancel[data-block-id="' + blockId + '"]');
    var $loading = $saveBtn.siblings('.wpforms-builder-settings-block-name-edit-loading');
    var editvalue = jQuery('input[name="settings[wpgs_spreadsheets][' + blockId + '][name]"]').val();

    /* Disable both buttons */
    $saveBtn.prop('disabled', true).addClass('disabled');
    $cancelBtn.prop('disabled', true).addClass('disabled');

    /* Show loading spinner */
    $loading.removeClass('d-none');

    /* Update UI with new name */
    jQuery('.gscwpforms-builder-settings-block-name').html(editvalue).show();
    jQuery('.wpforms-builder-settings-block-edit').show();
    jQuery('.wpforms-builder-settings-block-name-edit').hide();
    jQuery('.wpforms-builder-settings-block-name-edit-field[data-block-id="' + blockId + '"]').hide();
    $cancelBtn.hide();

    /* Trigger WPForms' own save (this fires an internal AJAX request) */
    jQuery('#wpforms-save').trigger('click');

    /* Listen for that AJAX request to finish, then reset button/spinner state */
    jQuery(document).one('ajaxComplete', function(event, xhr, settings) {
        $saveBtn.prop('disabled', false).removeClass('disabled');
        $cancelBtn.prop('disabled', false).removeClass('disabled');
        $loading.addClass('d-none');
    });

});
