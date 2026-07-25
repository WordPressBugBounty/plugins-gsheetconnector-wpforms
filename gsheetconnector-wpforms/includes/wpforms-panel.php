<?php
/*  Exit if accessed directly */
if (!defined('ABSPATH')) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Class FormBuilder handles functionality inside the form builder.
 *
 * @since 1.0.0
 */
class WPGS_FormBuilder
{


	public $_wpgs_googlesheet = false;
	/**
	 * White list of field types to allow for mapping select.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $allowed_field_types = [];

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.0.0
	 */
	protected function hooks()
	{

		add_action('wpforms_form_settings_panel_content', [$this, 'panel_content'],   50);
		// save entry with posted data
		add_action('wpforms_process_entry_save', array($this, 'entry_save'), 30, 4);

		add_action('wpforms_builder_enqueues',            [$this, 'enqueue_assets'],  10);
		add_filter('wpforms_builder_settings_sections',   [$this, 'panel_sidebar'],   50, 2);
		add_filter('wpforms_builder_strings',             [$this, 'builder_strings'], 50, 2);
		add_filter('wpforms_helpers_templates_include_html_located', [$this, 'templates'], 10, 4);

		add_filter('wpforms_save_form_args', [$this, 'save_form_args'], 11, 3);

		
	}

	


	public function entry_save($fields, $entry, $form_id, $form_data = '')
	{

		

		$data = array();

		// Get Entry Id
		$entry_id = wpforms()->process->entry_id;

		// get form data
		$new_setting = get_post_meta($form_id, 'wpform_gs_settings_new');


		// By mistake added wpform_gs_settings_new setting so moved new setting to old. 
		if (!empty($new_setting)) {
			
			update_post_meta($form_id, 'wpform_gs_settings', $new_setting[0]);
		}
		// By mistake added wpform_gs_settings_new setting so moved new setting to old.

		$form_data_get = get_post_meta($form_id, 'wpform_gs_settings');

		if (!empty($form_data_get)) {


			$sheet_name = isset($form_data_get[0]['gs_sheet_manuals_sheet_name']) ? $form_data_get[0]['gs_sheet_manuals_sheet_name'] : "";

			$sheet_id = isset($form_data_get[0]['gs_sheet_manuals_sheet_id']) ? $form_data_get[0]['gs_sheet_manuals_sheet_id'] : "";

			$sheet_tab_name = isset($form_data_get[0]['gs_sheet_manuals_sheet_tab_name']) ? $form_data_get[0]['gs_sheet_manuals_sheet_tab_name'] : "";

			$tab_id = isset($form_data_get[0]['gs_sheet_manuals_sheet_tab_id']) ? $form_data_get[0]['gs_sheet_manuals_sheet_tab_id'] : "";

			$payment_type = array("payment-single", "payment-multiple", "payment-select", "payment-total");

			if ((!empty($sheet_name)) && (!empty($sheet_tab_name)) && !Wpform_gs_Connector_Utility::is_entry_synced($form_id, $entry_id)) {
			
				try {
					
					Wpform_gs_Connector_Utility::mark_entry_synced($form_id, $entry_id);

					include_once(WPFORMS_GOOGLESHEET_ROOT . "/lib/google-sheets.php");
					$doc = new wpfgsc_googlesheet();
					$doc->auth();
					$doc->setSpreadsheetId($sheet_id);
					$doc->setWorkTabId($tab_id);

					//$timestamp = strtotime(date("Y-m-d H:i:s"));
					// Fetched local date and time instaed of unix date and time
					$data['date'] = date_i18n(get_option('date_format'));
					$data['time'] = date_i18n(get_option('time_format'));

					foreach ($fields as $k => $v) {
					
						$get_field = $fields[$k];
						$key = $get_field['name'];
						$value = $get_field['value'];
						if (in_array($get_field['type'], $payment_type)) {
							$value =  html_entity_decode($get_field['value']);
							
						}
						$data[$key] = $value;
					}
					
					$doc->add_row($data);
				} catch (Exception $e) {
					
					$data['ERROR_MSG'] = $e->getMessage();
					$data['TRACE_STK'] = $e->getTraceAsString();
					Wpform_gs_Connector_Utility::gs_debug_log($data);
				}
			}
		}
	}



	public function get_data_to_submit($entry_cells, $googlesheet, $spreadsheet_id, $tab_id, $header_cells = false)
	{

		try {

			if (! $header_cells) {
				$wpgs_googlesheet = $this->get_wpgs_googlesheet();
				$header_cells = $wpgs_googlesheet->get_header_row($spreadsheet_id, $tab_id);
			}
			$send_row_data = array();

			if ($entry_cells && $header_cells && $spreadsheet_id) {

				foreach ($header_cells as $index => $cellName) {

					if (isset($entry_cells[$cellName])) {
						$send_row_data[$index] = $entry_cells[$cellName];
					}
				}

				foreach ($header_cells as $index => $cellName) {
					if (! isset($send_row_data[$index])) {
						$send_row_data[$index] = "";
					}
				}
			}
		} catch (Exception $e) {
			Wpform_gs_Connector_Utility::gs_debug_log(__METHOD__ . " Error while adding the entry to sheet: \n " . $e->getMessage());
			return array();
		}
		return $send_row_data;
	}

	function get_google_sheet_settings($googlesheet, $formdata = false)
	{

		$connection_mode = "manual";

		$wpgs_googlesheet = $this->get_wpgs_googlesheet();


	
		// Check if it's a string and decode it
		if (is_string($googlesheet)) {
			$googlesheet = json_decode($googlesheet, true);
		}

		// Now safely access
		$spreadsheet_id = $googlesheet['gs_sheet_manuals_sheet_id'] ?? '';
		$tab_id = $googlesheet['gs_sheet_manuals_sheet_tab_id'] ?? '';
		$tab_title = $googlesheet['gs_sheet_manuals_sheet_tab_name'] ?? '';
		$spreadsheet_title = $googlesheet['gs_sheet_manuals_sheet_name'] ?? '';

		$sheet_info = array(
			"spreadsheet_id" => $spreadsheet_id,
			"tab_id" => $tab_id,
			"tab_title" => $tab_title,
			"spreadsheet_title" => $spreadsheet_title,
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$sheet_info = apply_filters("gscwpff_filter_sheet_info", $sheet_info);
		// Back-compat alias for the previous, unprefixed hook name.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$sheet_info = apply_filters("gcgf_filter_sheet_info", $sheet_info);
		return $sheet_info;
	}

	public function update_entry_meta_googlesheet($wpgs_feed_id, $googlesheet, $entry_id, $form_id, $user_id)
	{

		$meta = wpforms()->entry_meta->add(
			array(
				'entry_id' => $entry_id,
				'form_id'  => $form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'entry_added',
				'status'     => 'success',
				'data'     => json_encode(
					[
						"wpgs_feed_id" => $wpgs_feed_id,
						"googlesheet_name" => $googlesheet['name'],
						"googlesheet" => $googlesheet,
					]
				),
			),
			'entry_meta'
		);
	}

	/**
	 * Process Conditional Logic for the webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $webhook Webhook data.
	 *
	 * @return bool False if CL rules stopped the connection execution, true otherwise.
	 */
	protected function is_conditionals_passed($googlesheet, $fields, $entry, $form_data, $entry_id)
	{

		// if( ! wpforms()->pro ) {
		// 	return true;
		// }

		if (
			empty($googlesheet['conditional_logic']) ||
			empty($googlesheet['conditionals'])
		) {
			return true;
		}

		$pass = wpforms_conditional_logic()->process($fields, $form_data, $googlesheet['conditionals']);

		if (
			! empty($googlesheet['conditional_type']) &&
			'stop' === $googlesheet['conditional_type']
		) {
			$pass = ! $pass;
		}

		// Check for conditional logic.
		if (! $pass) {
			wpforms_log(
				esc_html__('Publishing processing stopped by conditional logic.', 'gsheetconnector-wpforms'),
				$fields,
				[
					'type'    => ['provider', 'conditional_logic'],
					'parent'  => $entry_id,
					'form_id' => $form_data['id'],
				]
			);
		}

		return $pass;
	}

	/**
	 * Preprocess data before saving it in form_data when editing form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form Form array, usable with wp_update_post.
	 * @param array $data Data retrieved from $_POST and processed.
	 * @param array $args Empty by default, may have custom data not intended to be saved, but used for processing.
	 *
	 * @return array
	 */
	public function save_form_args($form, $data, $args)
	{

		$token = get_option('wpform_gs_access_code');
		if (! $token) {
			return $form;
		}

		$enable_filter = false;
		remove_filter('wpforms_builder_save_form_response_data', [$this, 'set_reload_parameters_in_ajax_response'], 10, 3);

		$form_data = json_decode(stripslashes($form['post_content']), true);


		$wpgs_spreadsheets = isset($form_data['settings']['wpgs_spreadsheets']) ? $form_data['settings']['wpgs_spreadsheets'] : false;

		// $wfgs_google_client = $this->get_wpgs_googlesheet();	

		if ($wpgs_spreadsheets && is_array($wpgs_spreadsheets) && ! empty($wpgs_spreadsheets)) {

			foreach ($wpgs_spreadsheets as $wpgs_feed_id => $gsheet_feed) {

				//$integration_method = $gsheet_feed['gs_sheet_integration_mode'];
				// $gs_sheet_select_name = $gsheet_feed['gs_sheet_select_name'];				

				$google_sheet_settings = $this->get_google_sheet_settings($gsheet_feed, $form);

				$spreadsheet_id = $google_sheet_settings['spreadsheet_id'];
				$tab_id = $google_sheet_settings['tab_id'];

				if (! $spreadsheet_id) {
					continue;
				}

				$form_id = $form_data['id'];

				$get_existing_data = get_post_meta($form_id, 'wpform_gs_settings');


				$gs_sheet_name = $gsheet_feed['gs_sheet_manuals_sheet_name'];
				$gs_sheet_id = $gsheet_feed['gs_sheet_manuals_sheet_id'];
				$gs_tab_name = $gsheet_feed['gs_sheet_manuals_sheet_tab_name'];
				$gs_tab_id = $gsheet_feed['gs_sheet_manuals_sheet_tab_id'];
				// If data exist and user want to disconnect
				if (!empty($get_existing_data) && $gs_sheet_name == "") {
					update_post_meta($form_id, 'wpform_gs_settings', "");
				}

				if (!empty($gs_sheet_name) && (!empty($gs_tab_name))) {
					update_post_meta($form_id, 'wpform_gs_settings', $gsheet_feed);
				}

				$enable_filter = true;
			}
		}

		if (isset($form_data['settings']['wfgs_force_reload']) && $form_data['settings']['wfgs_force_reload'] == 1) {
			$enable_filter = true;
			$form_data['settings']['wfgs_force_reload'] = 0;
		}

		if ($enable_filter) {
			add_filter('wpforms_builder_save_form_response_data', [$this, 'set_reload_parameters_in_ajax_response'], 10, 3);
		}

		$form['post_content'] = wpforms_encode($form_data);
		return $form;
	}



	public function get_google_sheet_settings_dep($feed, $form)
	{

		$connection_mode = $feed['gs_sheet_integration_mode'];

		$wpgs_googlesheet = $this->get_wpgs_googlesheet();

		$spreadsheet_id = $feed['gs_sheet_manuals_sheet_id'];
		$tab_id = $feed['gs_sheet_manuals_sheet_tab_id'];
		$tab_title = $feed['gs_sheet_manuals_sheet_tab_name'];
		$spreadsheet_title = $feed['gs_sheet_manuals_sheet_name'];

		$sheet_info = array(
			"spreadsheet_id" => $spreadsheet_id,
			"tab_id" => $tab_id,
			"tab_title" => $tab_title,
			"spreadsheet_title" => $spreadsheet_title,
		);
		$sheet_info = apply_filters("wpgs_filter_sheet_info", $sheet_info, $form, $feed_id);

		return $sheet_info;
	}

	public function get_wpgs_googlesheet()
	{

		if ($this->_wpgs_googlesheet) {
			return $this->_wpgs_googlesheet;
		}

		$google_sheet = new wpfgsc_googlesheet();
		$google_sheet->auth();

		$this->_wpgs_googlesheet = $google_sheet;
		return $google_sheet;
	}

	/**
	 * Add forece reload parameter for new added option
	 *
	 * @since 1.0.0
	 *
	 * @param array $return_params, default parameters to be returned
	 * @param int $form_id form id
	 * @param array $data Data retrieved from $_POST and processed.
	 *
	 * @return array
	 */
	public function set_reload_parameters_in_ajax_response($return_params, $form_id, $data)
	{

		if (! is_array($return_params)) {
			$return_params = array($return_params);
		}

		$return_params['force_reload'] = 1;

		return $return_params;
	}

	/**
	 * Change a template location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $located  Template location.
	 * @param string $template Template.
	 * @param array  $args     Arguments.
	 * @param bool   $extract  Extract arguments.
	 *
	 * @return string
	 */
	public function templates($located, $template, $args, $extract)
	{

		// Checking if `$template` is an absolute path and passed from this plugin.
		if (
			(0 === strpos($template, WPFORMS_GOOGLESHEET_PATH . 'includes')) &&
			is_readable($template)
		) {
			return $template;
		}

		return $located;
	}


	public function get_spreadsheet_id($name)
	{

		$wpforms_gs_sheetId = $this->get_spreadsheet_options();

		$spreadsheet_id = "";

		if ($wpforms_gs_sheetId) {

			foreach ($wpforms_gs_sheetId as $spreadsheet_name => $spreadsheet) {

				if ($spreadsheet_name == $name) {

					$spreadsheet_id = $spreadsheet['id'];
				}
			}
		}

		return $spreadsheet_id;
	}

	public function get_spreadsheet_tab_id($spreadsheet_id, $name)
	{

		$wpforms_gs_sheetId = $this->get_spreadsheet_options();

		$spreadsheet_tab_id = 0;

		if ($wpforms_gs_sheetId) {

			foreach ($wpforms_gs_sheetId as $spreadsheet_name => $spreadsheet) {

				if ($spreadsheet_id == $spreadsheet['id']) {

					$available_tabs = $spreadsheet['tabId'];
					foreach ($available_tabs as $tab_name => $tab_id) {

						if ($name == $tab_name) {
							$spreadsheet_tab_id = $tab_id;
						}
					}
				}
			}
		}

		return $spreadsheet_tab_id;
	}

	public function get_available_spreadsheets()
	{

		$wpforms_gs_sheetId = $this->get_spreadsheet_options();

		if ($wpforms_gs_sheetId && is_array($wpforms_gs_sheetId)) {

			foreach ($wpforms_gs_sheetId as $spreadsheet_name => $spreadsheet) {
				$spreadsheet_id = $spreadsheet['id'];
				$spreadsheets[$spreadsheet_id] = $spreadsheet_name;
			}
		}

		$spreadsheets["create_new"] = "Create New";
		return $spreadsheets;
	}

	public function get_available_tabs($selected_spreadsheet_id)
	{

		$wpforms_gs_sheetId = $this->get_spreadsheet_options();

		$spreadsheet_tabs = array();

		if ($wpforms_gs_sheetId && is_array($wpforms_gs_sheetId)) {

			foreach ($wpforms_gs_sheetId as $spreadsheet_name => $spreadsheet) {
				$spreadsheet_id = $spreadsheet['id'];
				if ($spreadsheet_id == $selected_spreadsheet_id) {

					$available_tabs = $spreadsheet['tabId'];
					foreach ($available_tabs as $tab_name => $tab_id) {
						$spreadsheet_tabs[$tab_id] = $tab_name;
					}
				}
			}
		}
		if (! $spreadsheet_tabs) {
			$spreadsheet_tabs = array("0" => "Select");
		}
		return $spreadsheet_tabs;
	}

	public function get_spreadsheet_options()
	{

		$wpforms_gs_sheetId = get_option('wpforms_gs_sheetId', true);

		$blank_sheet = array(
			"Select Spreadsheet" => array(
				"id" => 0,
				"tabId" => array(
					"Select Sheet First" => "0"
				)
			)
		);
		if (isset($wpforms_gs_sheetId) && (is_array($wpforms_gs_sheetId)))
			$wpforms_gs_sheetId = array_merge($blank_sheet, $wpforms_gs_sheetId);
		else
			$wpforms_gs_sheetId = array();

		return $wpforms_gs_sheetId;
	}

	/**
	 * Add a content for `WFGS Googlesheet` panel.
	 *
	 * @since 1.0.0
	 *
	 * @param \WPForms_Builder_Panel_Settings $builder_panel_settings WPForms_Builder_Panel_Settings object.
	 */
	public function panel_content($builder_panel_settings)
	{

		$settings = $builder_panel_settings->form_data['settings'];
		$wpgs_spreadsheets = isset($settings['wpgs_spreadsheets']) ? $settings['wpgs_spreadsheets'] : array();

		if (empty($wpgs_spreadsheets)) {
			/* translators: %s - form name. */
			$wpgs_spreadsheets[1]['name']        = "Google Sheet";
			$wpgs_spreadsheets[1]['gsheetconnector-wpforms']        = false;
			$wpgs_spreadsheets[1]['gs_sheet_integration_mode']        = "manual";
			$wpgs_spreadsheets[1]['gs_sheet_select_name']        = "";
			$wpgs_spreadsheets[1]['gs_sheet_select_tab']        = "";
			$wpgs_spreadsheets[1]['gs_sheet_manuals_sheet_name']        = "";
			$wpgs_spreadsheets[1]['gs_sheet_manuals_sheet_id']        = "";
			$wpgs_spreadsheets[1]['gs_sheet_manuals_sheet_tab_id']        = "";
			$wpgs_spreadsheets[1]['gs_sheet_manuals_sheet_tab_name']        = "";
		}
		// echo '<pre>';print_r($wpgs_spreadsheets);die;
		$next_id = max(array_map('intval', array_keys($wpgs_spreadsheets))) + 1;

		$add_new_btn_classes = $this->get_html_class(
			array(
				'wpforms-builder-settings-block-add',
				'wpforms-webooks-add',
			),
			$builder_panel_settings
		);

		$token = get_option('wpform_gs_access_code');
?>

		<div class="wpforms-panel-content-section wpforms-panel-content-section-wf_googlesheets">

	



			<?php
			/*  Check if the user is authenticated */
    
			

			
                $authenticated = get_option('wpform_gs_token');
                $gsc_wpform_auth_setting = get_option('gs_wpforms_manual_setting');
                $gsc_wpform_is_valid = get_option('wpform_gs_verify');
                $service_json = get_option('gs_wpformspro_service_account_json', '');            

                $is_authenticated = false;
                if ((!empty($authenticated) && $gsc_wpform_is_valid == 'valid' && $gsc_wpform_auth_setting == 0) ) {
                    $selected_method = esc_html__('Existing', 'gsheetconnector-wpforms');
                    $is_authenticated = true;


                    $google_sheet  = new WPFGSC_googlesheet();
                    $email_account = $google_sheet->gsheet_print_google_account_email();

                } elseif ($gsc_wpform_auth_setting ==  2 && (!empty($service_json)) ) {
                    $selected_method = esc_html__('Service', 'gsheetconnector-wpforms');
                    $is_authenticated = true;

                    $gs_wpforms_service_json = get_option('gs_wpformspro_service_account_json', '');
                    $email_account = '';
                    $wpformspro_service_valid = false;

                    if (!empty($gs_wpforms_service_json)) {
                        $decoded_json = json_decode($gs_wpforms_service_json, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['client_email'])) {
                            $email_account = $decoded_json['client_email'];
                            $wpformspro_service_valid = true;
                        }
                    }

                } else {
                    $selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');
                    $is_authenticated = false;
                    $email_account = '';
                }



					$wpforms_auth_method = get_option( 'gs_wpforms_manual_setting');
					if($wpforms_auth_method == 0){
							$method_name = 'Existing Client / Secret Key (Auto Setup)';
					}elseif($wpforms_auth_method == 2){
							$method_name =  'Service Account (Recommended)';
					}

				if (!empty($email_account)) {
				
						$wpforms_gs_sheetId = $this->get_spreadsheet_options();
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_enable_control_html() delegates to WPForms core's own wpforms_panel_field() helper, which escapes its own output.
						echo $this->get_enable_control_html($wpgs_spreadsheets, $builder_panel_settings);
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_fields_html() builds its HTML via get_fields_block(), which escapes each dynamic value at concatenation time.
						echo $this->get_fields_html($wpgs_spreadsheets, $builder_panel_settings);
							?>
						<input type="hidden" name="settings[wfgs_force_reload]" value=0 class="wfgs_force_reload">


						<textarea style="display: none;" id="gs_sheet_select_sheets_list"><?php echo json_encode($wpforms_gs_sheetId); ?></textarea><!-- New Code : Resolved issue : Apostrophe(') issue while fetching sheets and tabs. -->

						<?php 
					
					  }else{

						if($wpforms_auth_method == 0){
							?>
							<div class="gscwpff-setup-alert">
								<div class="gscwpff-alert-icon">
									<svg width="30px" height="30px" viewBox="-0.5 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M18.2202 21.25H5.78015C5.14217 21.2775 4.50834 21.1347 3.94373 20.8364C3.37911 20.5381 2.90402 20.095 2.56714 19.5526C2.23026 19.0101 2.04372 18.3877 2.02667 17.7494C2.00963 17.111 2.1627 16.4797 2.47015 15.92L8.69013 5.10999C9.03495 4.54078 9.52077 4.07013 10.1006 3.74347C10.6804 3.41681 11.3346 3.24518 12.0001 3.24518C12.6656 3.24518 13.3199 3.41681 13.8997 3.74347C14.4795 4.07013 14.9654 4.54078 15.3102 5.10999L21.5302 15.92C21.8376 16.4797 21.9907 17.111 21.9736 17.7494C21.9566 18.3877 21.7701 19.0101 21.4332 19.5526C21.0963 20.095 20.6211 20.5381 20.0565 20.8364C19.4919 21.1347 18.8581 21.2775 18.2202 21.25V21.25Z" stroke="#9a3412" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										<path d="M10.8809 17.15C10.8809 17.0021 10.9102 16.8556 10.9671 16.7191C11.024 16.5825 11.1074 16.4586 11.2125 16.3545C11.3175 16.2504 11.4422 16.1681 11.5792 16.1124C11.7163 16.0567 11.8629 16.0287 12.0109 16.03C12.2291 16.034 12.4413 16.1021 12.621 16.226C12.8006 16.3499 12.9398 16.5241 13.0211 16.7266C13.1023 16.9292 13.122 17.1512 13.0778 17.3649C13.0335 17.5786 12.9272 17.7745 12.7722 17.9282C12.6172 18.0818 12.4203 18.1863 12.2062 18.2287C11.9921 18.2711 11.7703 18.2494 11.5685 18.1663C11.3666 18.0833 11.1938 17.9426 11.0715 17.7618C10.9492 17.5811 10.8829 17.3683 10.8809 17.15ZM11.2409 14.42L11.1009 9.20001C11.0876 9.07453 11.1008 8.94766 11.1398 8.82764C11.1787 8.70761 11.2424 8.5971 11.3268 8.5033C11.4112 8.40949 11.5144 8.33449 11.6296 8.28314C11.7449 8.2318 11.8697 8.20526 11.9959 8.20526C12.1221 8.20526 12.2469 8.2318 12.3621 8.28314C12.4774 8.33449 12.5805 8.40949 12.6649 8.5033C12.7493 8.5971 12.8131 8.70761 12.852 8.82764C12.8909 8.94766 12.9042 9.07453 12.8909 9.20001L12.7609 14.42C12.7609 14.6215 12.6808 14.8149 12.5383 14.9574C12.3957 15.0999 12.2024 15.18 12.0009 15.18C11.7993 15.18 11.606 15.0999 11.4635 14.9574C11.321 14.8149 11.2409 14.6215 11.2409 14.42Z" fill="#9a3412" />
									</svg>
								</div>
								<div class="gscwpff-alert-content">
									<div class="gscwpff-feed-alert-header"><?php esc_html_e('Google Sheets Setup Required', 'gsheetconnector-wpforms'); ?></div>
									<p><?php esc_html_e( 'Your selected method is: ', 'gsheetconnector-wpforms' ); ?><?php echo esc_html( $method_name ); ?></p>
									<p><?php esc_html_e('To start sending form entries to Google Sheets, please connect your Google account first.', 'gsheetconnector-wpforms'); ?></p>
									<ul>
										<li><?php esc_html_e('✔ Click on the Sign in with Google button', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Log in using your Google account', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Select the Google account where your Sheets are stored', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Grant access to: Google Drive & Google Sheets', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Save the authentication code if prompted', 'gsheetconnector-wpforms'); ?></li>
									</ul>
									<a href="admin.php?page=wpform-google-sheet-config&tab=integration" class="gscwpff-alert-btn link-hover-white" target="_blank">
										<?php esc_html_e('Go to Integration Setup', 'gsheetconnector-wpforms'); ?>
									</a>
								</div>
							</div>
					<?php }elseif($wpforms_auth_method == '2'){ ?>
							<div class="gscwpff-setup-alert">
								<div class="gscwpff-alert-icon">
									<svg width="30px" height="30px" viewBox="-0.5 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M18.2202 21.25H5.78015C5.14217 21.2775 4.50834 21.1347 3.94373 20.8364C3.37911 20.5381 2.90402 20.095 2.56714 19.5526C2.23026 19.0101 2.04372 18.3877 2.02667 17.7494C2.00963 17.111 2.1627 16.4797 2.47015 15.92L8.69013 5.10999C9.03495 4.54078 9.52077 4.07013 10.1006 3.74347C10.6804 3.41681 11.3346 3.24518 12.0001 3.24518C12.6656 3.24518 13.3199 3.41681 13.8997 3.74347C14.4795 4.07013 14.9654 4.54078 15.3102 5.10999L21.5302 15.92C21.8376 16.4797 21.9907 17.111 21.9736 17.7494C21.9566 18.3877 21.7701 19.0101 21.4332 19.5526C21.0963 20.095 20.6211 20.5381 20.0565 20.8364C19.4919 21.1347 18.8581 21.2775 18.2202 21.25V21.25Z" stroke="#9a3412" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										<path d="M10.8809 17.15C10.8809 17.0021 10.9102 16.8556 10.9671 16.7191C11.024 16.5825 11.1074 16.4586 11.2125 16.3545C11.3175 16.2504 11.4422 16.1681 11.5792 16.1124C11.7163 16.0567 11.8629 16.0287 12.0109 16.03C12.2291 16.034 12.4413 16.1021 12.621 16.226C12.8006 16.3499 12.9398 16.5241 13.0211 16.7266C13.1023 16.9292 13.122 17.1512 13.0778 17.3649C13.0335 17.5786 12.9272 17.7745 12.7722 17.9282C12.6172 18.0818 12.4203 18.1863 12.2062 18.2287C11.9921 18.2711 11.7703 18.2494 11.5685 18.1663C11.3666 18.0833 11.1938 17.9426 11.0715 17.7618C10.9492 17.5811 10.8829 17.3683 10.8809 17.15ZM11.2409 14.42L11.1009 9.20001C11.0876 9.07453 11.1008 8.94766 11.1398 8.82764C11.1787 8.70761 11.2424 8.5971 11.3268 8.5033C11.4112 8.40949 11.5144 8.33449 11.6296 8.28314C11.7449 8.2318 11.8697 8.20526 11.9959 8.20526C12.1221 8.20526 12.2469 8.2318 12.3621 8.28314C12.4774 8.33449 12.5805 8.40949 12.6649 8.5033C12.7493 8.5971 12.8131 8.70761 12.852 8.82764C12.8909 8.94766 12.9042 9.07453 12.8909 9.20001L12.7609 14.42C12.7609 14.6215 12.6808 14.8149 12.5383 14.9574C12.3957 15.0999 12.2024 15.18 12.0009 15.18C11.7993 15.18 11.606 15.0999 11.4635 14.9574C11.321 14.8149 11.2409 14.6215 11.2409 14.42Z" fill="#9a3412" />
									</svg>
								</div>
								<div class="gscwpff-alert-content">
									<div class="gscwpff-feed-alert-header"><?php esc_html_e('Google Sheets Setup Required', 'gsheetconnector-wpforms'); ?></div>
									<p><?php esc_html_e( 'Your selected method is: ', 'gsheetconnector-wpforms' ); ?><?php echo esc_html( $method_name ); ?></p>
									<p><?php esc_html_e('To connect Google Sheets using Service Account:', 'gsheetconnector-wpforms'); ?></p>
									<ul>
										<li><?php esc_html_e('✔ Go to Google Cloud Console', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Create or select a project', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Enable Google Sheets & Drive APIs', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Create a Service Account', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Generate and download the JSON key', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Upload the JSON file here',  'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('✔ Share your Google Sheet with the Service Account email', 'gsheetconnector-wpforms'); ?></li>
									</ul>
									<a href="admin.php?page=wpform-google-sheet-config&tab=integration" class="gscwpff-alert-btn link-hover-white" target="_blank">
										<?php esc_html_e('Go to Integration Setup', 'gsheetconnector-wpforms'); ?>
									</a>
								</div>
							</div>
					<?php } ?>

		<?php 	} ?>
		</div>

	<?php
	}


	/**
	 * Retrieve a HTML for On/Off select control.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_enable_control_html($wpgs_spreadsheets, $builder_panel_settings)
	{
		return wpforms_panel_field(
			'select',
			'settings',
			'gsheetconnector-wpforms',
			$builder_panel_settings->form_data,
			esc_html__('Enable Settings', 'gsheetconnector-wpforms'),
			[
				'default' => '0',
				'options' => [
					'1' => esc_html__('On', 'gsheetconnector-wpforms'),
					'0' => esc_html__('Off', 'gsheetconnector-wpforms'),
				],
			],
			false
		);
	}


	/**
	 * Retrieve a HTML for settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_fields_html($wpgs_spreadsheets, $builder_panel_settings)
	{

		$result   = '';
		foreach ($wpgs_spreadsheets as $wpgs_feed_id => $googlesheet) {

		if (!is_array($googlesheet)) {
				$googlesheet = array();
			}
			$googlesheet['wpgs_feed_id'] = $wpgs_feed_id;

			
			
			$result .= $this->get_fields_block($googlesheet, $builder_panel_settings);
		}

		return $result;
	}


	/**
	 * Retrieve a HTML for setting block.
	 *
	 * @since 1.0.0
	 *
	 * @param array $googlesheet Googlesheet data.
	 * @param array $builder_panel_settings form settings data.
	 *
	 * @return string
	 */
	protected function get_fields_block($googlesheet, $builder_panel_settings)
	{

		$wpgs_feed_id   = $googlesheet['wpgs_feed_id'];
		$form_data    = $builder_panel_settings->form_data;
		$toggle_state = '<i class="fa fa-chevron-up"></i>';
		$closed_state = '';

		if (
			! empty($form_data['id']) &&
			'closed' === wpforms_builder_settings_block_get_state($form_data['id'], $wpgs_feed_id, 'googlesheet')
		) {
			$toggle_state = '<i class="fa fa-chevron-down"></i>';
			$closed_state = 'style="display:none;"';
		}

		$block_classes = $this->get_html_class(
			array(
				'wpforms-builder-settings-block',
				'wpforms-builder-settings-block-googlesheet',
				'feed-block-' . $wpgs_feed_id,
			),
			$builder_panel_settings
		);

	

		
	?>

		<div class="<?php echo esc_attr($block_classes); ?>" data-block-type="googlesheet" data-block-id="<?php echo absint($wpgs_feed_id); ?>">
			<div class="wpforms-builder-settings-block-header">
				<div class="wpforms-builder-settings-block-actions">
					<button type="button" class="wpforms-builder-settings-block-edit" data-id="<?php echo absint($wpgs_feed_id); ?>"><i class="fa fa-pencil"></i></button> 
					<button type="button" class="wpforms-builder-settings-block-toggle"><?php echo wp_kses_post($toggle_state); ?></button>
				</div>

				<div class="wpforms-builder-settings-block-name-holder">
					<span class="gscwpforms-builder-settings-block-name"><?php echo esc_html($googlesheet['name']); ?></span>

					

					<div class="wpforms-builder-settings-block-name-edit">
						<input type="text" name="settings[wpgs_spreadsheets][<?php echo absint($wpgs_feed_id); ?>][name]" value="<?php echo esc_attr($googlesheet['name']); ?>">
					</div>
					
				</div>
				 	<div class="wpforms-builder-settings-block-name-edit-field" data-block-id="<?php echo absint($wpgs_feed_id); ?>">
						<div class=" gscwpff-edit-btn">
						<a class="wpforms-builder-settings-block-name-edit-cancel" data-block-id="<?php echo absint($wpgs_feed_id); ?>">Cancel</a>
						<a class="wpforms-builder-settings-block-name-edit-save" data-block-id="<?php echo absint($wpgs_feed_id); ?>">Update</a>
						</div>
						<i class="wpforms-builder-settings-block-name-edit-loading wpforms-loading-spinner wpforms-loading-inline d-none" data-block-id="<?php echo absint($wpgs_feed_id); ?>"></i>
					</div>
			</div><!-- .wpforms-builder-settings-block-header -->

			<div class="wpforms-builder-settings-block-content" <?php echo wp_kses_post($closed_state); ?>>

				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_fields_for_block() escapes each dynamic value at concatenation time. ?>
				<?php echo $this->get_fields_for_block($googlesheet, $builder_panel_settings); ?>

			</div><!-- .wpforms-builder-settings-block-content -->

		</div><!-- .wpforms-builder-settings-block -->

<?php
	}

	/**
	 * Retrieve HTML for fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $googlesheet Googlesheet data.
	 * @param array $builder_panel_settings form settings data.
	 *
	 * @return string
	 */
	protected function get_fields_for_block($googlesheet, $builder_panel_settings)
	{

		$wpgs_feed_id    = $googlesheet['wpgs_feed_id'];
		$form_data     = $builder_panel_settings->form_data;
		$form_fields   = wpforms_get_form_fields($form_data);
		$form_fields   = empty($form_fields) && ! is_array($form_fields) ? [] : $form_fields;

		$result = "";

	$wpforms_gs_sheetId = $this->get_spreadsheet_options();


	$selected_spreadsheet = isset($googlesheet['gs_sheet_select_name']) && $googlesheet['gs_sheet_select_name'] != "" ? $googlesheet['gs_sheet_select_name'] : "";


	$api_token_auto = get_option('wpform_gs_token');
    $api_token_manual = get_option('gs_wpforms_token_manual');
    $api_token_service = get_option('gs_wpformspro_service_account_json');
    $gs_wpforms_manual_setting = get_option('gs_wpforms_manual_setting');

    $selected_method = '';
    if ($gs_wpforms_manual_setting == 0) {
        $selected_method = esc_html__('Authenticated Using Existing Method', 'gsheetconnector-wpforms');
    }  elseif ($gs_wpforms_manual_setting == 2) {
        $selected_method = esc_html__('Service Account (Recommended)', 'gsheetconnector-wpforms');
    }

    if (!empty($api_token_auto) && $gs_wpforms_manual_setting == 0) {
        /*  The user is authenticated through the auto method */
        $google_sheet_auto = new WPFGSC_googlesheet();

        $email_account_auto = $google_sheet_auto->gsheet_print_google_account_email();
        $connected_email = !empty($email_account_auto) ? esc_html($email_account_auto) : 'Not Connected';
    } elseif (!empty($api_token_service) && $gs_wpforms_manual_setting == 2) {
        $decoded_json = json_decode($api_token_service, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['client_email'])) {
            $client_email = sanitize_email($decoded_json['client_email']); // sanitize before saving
            $connected_email = !empty($client_email) ? esc_html($client_email) : 'Not Connected';
        }
    } else {
        /*  Neither auto nor manual authentication is available */
        $connected_email = 'Not Connected';
    }

		$result .= '<div class="gscwpff-integration-box">
			<div class="gsc-google-auth-card mt-30 mb-30">
				<div>
					<div class="heading mt-0 mb-30">
						' . esc_html__('Google Account Connection', 'gsheetconnector-wpforms') . '
						<span class="badge">' . esc_attr($selected_method) . '</span>
					</div>
				</div>

				<div class="d-flex flex-wrap gap-20 justify-between align-center">
					<div class="gsc-google-auth-left d-flex flex-wrap align-center gap-15">
						<div class="gsc-google-icon">G</div>

						<div class="connected-account">
							<div class="gsc-connected-left d-flex">
								<span class="gsc-connected-label">
									' . esc_html__('Connected Email Account', 'gsheetconnector-wpforms') . '
								</span>

								<span class="connected-account-manual gsc-connected-email">
									' . esc_html($connected_email) . '
								</span>
							</div>
						</div>
					</div>

					<div class="gsc-google-auth-right">
						<div class="gsc-connected-pill">
							<span class="dot"></span>
							' . esc_html__(' Connected', 'gsheetconnector-wpforms') . '
						</div>
					</div>
				</div>
			</div>
		</div>';



		$result .= wpforms_panel_field(
			'select',
			'wpgs_spreadsheets',
			'gs_sheet_integration_mode',
			$form_data,
			esc_html__('Integration Mode', 'gsheetconnector-wpforms'),
			[
				'parent'     => 'settings',
				'subsection' => $wpgs_feed_id,
				'default'    => 'manual',
				'options'     => [
					'manual'  => esc_html__('Manual Google Sheets Configuration', 'gsheetconnector-wpforms'),
					'automatic_disabled'  => esc_html__('Automatic Google Sheets Configuration (Upgrade To Pro)', 'gsheetconnector-wpforms'),

				],

				'tooltip'    => esc_html__('Selection of chosing google sheet for data submission.', 'gsheetconnector-wpforms'),
				'disabled' => 'disabled',
				'class' => "integration_mode_wrapper",
				'input_class' => "integration_mode_input",
				

			],
			false
		);



		$visible = isset($googlesheet['gs_sheet_integration_mode']) && $googlesheet['gs_sheet_integration_mode'] == "manual" ? "" : " style='display: none;' ";
		$result .= "<div class='integratio_manual wpgs_panel_section'>";

		/* MANUAL - ENTER SPREADSHEET NAME FIELD */
		$result .= "<div class='gs_sheet_manuals_sheet_name_wrapper'>";
		$result .= wpforms_panel_field(
			'text',
			'wpgs_spreadsheets',
			'gs_sheet_manuals_sheet_name',
			$form_data,
			esc_html__('Spreadsheet Name', 'gsheetconnector-wpforms'),
			[
				'parent'      => 'settings',
				'subsection'  => $wpgs_feed_id,
				'input_id'    => 'wpforms-panel-field-wpgs_spreadsheets-request-url-' . $wpgs_feed_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'default'     => '',
				'placeholder' => esc_html__('Spreadsheet Name', 'gsheetconnector-wpforms'),
				'tooltip'     => esc_html__('Enter the exact name of your Google Spreadsheet (as shown in Google Sheets).', 'gsheetconnector-wpforms'),
			],
			false
		);
		$result .= "</div>";

		/* MANUAL - ENTER SPREADSHEET ID FIELD */
		$result .= "<div class='gs_sheet_manuals_sheet_name_wrapper'>";
		$result .= wpforms_panel_field(
			'text',
			'wpgs_spreadsheets',
			'gs_sheet_manuals_sheet_id',
			$form_data,
			esc_html__('Spreadsheet ID', 'gsheetconnector-wpforms'),
			[
				'parent'      => 'settings',
				'subsection'  => $wpgs_feed_id,
				'input_id'    => 'wpforms-panel-field-wpgs_spreadsheets-request-url-' . $wpgs_feed_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'default'     => '',
				'placeholder' => esc_html__('Spreadsheet ID ', 'gsheetconnector-wpforms'),
				'tooltip' => sprintf(
					/* translators: %s: example Google Sheets URL. */
					esc_html__('Enter the Spreadsheet ID from your Google Sheet URL. (Example: %s)', 'gsheetconnector-wpforms'),
					'https://docs.google.com/spreadsheets/d/**SPREADSHEET_ID**/edit'
				),
			],
			false
		);
		$result .= "</div>";

		/* MANUAL - ENTER TAB NAME FIELD */
		$result .= "<div class='gs_sheet_manuals_sheet_name_wrapper'>";
		$result .= wpforms_panel_field(
			'text',
			'wpgs_spreadsheets',
			'gs_sheet_manuals_sheet_tab_name',
			$form_data,
			esc_html__('Tab Name', 'gsheetconnector-wpforms'),
			[
				'parent'      => 'settings',
				'subsection'  => $wpgs_feed_id,
				'input_id'    => 'wpforms-panel-field-wpgs_spreadsheets-request-url-' . $wpgs_feed_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'default'     => '',
				'placeholder' => esc_html__('Tab Name', 'gsheetconnector-wpforms'),
				'tooltip'     => esc_html__('Enter the exact sheet tab name (e.g., Sheet1) from the bottom of your Google Spreadsheet.', 'gsheetconnector-wpforms'),
			],
			false
		);
		$result .= "</div>";

		/* MANUAL - ENTER TAB ID FIELD */
		$result .= "<div class='gs_sheet_manuals_sheet_name_wrapper'>";
		$result .= wpforms_panel_field(
			'text',
			'wpgs_spreadsheets',
			'gs_sheet_manuals_sheet_tab_id',
			$form_data,
			esc_html__('Tab ID', 'gsheetconnector-wpforms'),
			[
				'parent'      => 'settings',
				'subsection'  => $wpgs_feed_id,
				'input_id'    => 'wpforms-panel-field-wpgs_spreadsheets-request-url-' . $wpgs_feed_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'default'     => '',
				'placeholder' => esc_html__('Tab ID', 'gsheetconnector-wpforms'),
				'tooltip'     => esc_html__('Get the Tab ID from your sheet URL after gid=', 'gsheetconnector-wpforms'),
			],
			false
		);
		$result .= "</div>";

		if (isset($googlesheet['gs_sheet_manuals_sheet_name']) && isset($googlesheet['gs_sheet_manuals_sheet_id']) && isset($googlesheet['gs_sheet_manuals_sheet_tab_name']) && isset($googlesheet['gs_sheet_manuals_sheet_tab_id'])) {
			if (($googlesheet['gs_sheet_manuals_sheet_name'] != "") && ($googlesheet['gs_sheet_manuals_sheet_id'] != "") && ($googlesheet['gs_sheet_manuals_sheet_tab_name'] != "") && ($googlesheet['gs_sheet_manuals_sheet_tab_id'] != "")) {
				$result .= "<div class='gs_sheet_select_tab_wrapper'>";
				$result .= "<div class='wpforms-panel-field'>";
				$result .= "<label class='sheeturl'>";
				

				$result .= '<a href="https://docs.google.com/spreadsheets/d/' . esc_attr($googlesheet['gs_sheet_manuals_sheet_id']) . '/edit#gid=' . esc_attr($googlesheet['gs_sheet_manuals_sheet_tab_id']) . '" target="_blank" class="button button-primary gscwpff-sheeturl-btn" data-tooltip="' . esc_attr__('View SpreadSheets', 'gsheetconnector-wpforms') . '">';
				$result .= '<i class="fas fa-eye fw-400"></i>';
				$result .= '</a>';

				$result .= '<a class="button button-primary gscwpff-sheeturl-btn btndownload-pro btnsync-pro" data-tooltip="' . esc_attr__('Download CSV (PRO)', 'gsheetconnector-wpforms') . '">';
				$result .= '<i class="fas fa-download fw-400"></i>';
				$result .= '</a>';
				
				$result .= "</label>";
				$result .= "</div>";
				$result .= "</div>";
				$new_link = 'https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/plugin-settings-free-version';
				$result .=	"<p class='header-reference'>
     Please add header manually in google sheet.for the reference  <a target='_blank' href=$new_link>Click here.</a> 
</p>";
			}
		}

		$result .= "</div>";


				
		/* service permission start */
		$gsc_wpform_pro_auth_setting = get_option('gs_wpforms_manual_setting');
		$service_json = get_option('gs_wpformspro_service_account_json', '');

		if ($gsc_wpform_pro_auth_setting == 2 && (!empty($service_json))) {
			$gs_wpformspro_service_json = get_option('gs_wpformspro_service_account_json', '');
			$email_account = '';

			if (!empty($gs_wpformspro_service_json)) {
				$decoded_json = json_decode($gs_wpformspro_service_json, true);
				if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['client_email'])) {
					$email_account = $decoded_json['client_email'];
					$wpformspro_service_valid = true;
				}

				$get_sheet_data = isset($form_data['settings']['wpgs_spreadsheets'][$wpgs_feed_id])
				? $form_data['settings']['wpgs_spreadsheets'][$wpgs_feed_id]
				: [];

				if (isset($get_sheet_data['gs_sheet_integration_mode']) && $get_sheet_data['gs_sheet_integration_mode'] == 'manual') {
					$sheet_id = isset($get_sheet_data['gs_sheet_manuals_sheet_id']) ? $get_sheet_data['gs_sheet_manuals_sheet_id'] : '';
				} else {
					$sheet_id = isset($get_sheet_data['gs_sheet_select_name']) ? $get_sheet_data['gs_sheet_select_name'] : '';
				}

				include_once WPFORMS_GOOGLESHEET_ROOT . '/lib/google-sheets.php';
				$doc = new WPFGSC_googlesheet();
				$get_result = $doc->auth();
				$get_result = $doc->check_sheet_access($sheet_id);

				$result .= '<div class="gs-fields gsc-email-permission">
					<div class="gsc-sheet-status-header">
						<div class="gsc-status-headings fw-600">
							Google Sheets Connection
						</div>
					</div>';

				if ($get_result['status'] == 1) {
					$result .= '<p class="ptag">'.esc_attr__('Your Google Spreadsheet is securely connected and ready to receive form submissions in real time.', 'gsheetconnector-wpforms'). '</p>';
					$result .= '<div class="gsc-sheet-email-box">
									<span class="email-text email-success-service">'
										. esc_html($email_account) .
									'</span>
									<span class="gsc-sheet-badge connected">
										Connected Successfully
									</span>
								</div>';
				} else {
					$result .= '<p class="ptag">'.esc_attr__('Please share your Google Spreadsheet with the following service account to enable automatic syncing.', 'gsheetconnector-wpforms'). '</p>';
					$result .= '<p class="ptag">After sharing the spreadsheet, click Fetch Sheets to get the sheets into the dropdown, then select the sheet you shared and save the settings. The page will refresh and display the sharing status.</p>';
					$result .= '<div class="gsc-email-box d-flex align-center justify-between email-unsuccess-service">
									<div class="gsc-service-email">'
										. esc_html($email_account) .
									'</div>
									<a href="javascript:void(0);"
									data-email="' . esc_attr(trim($email_account)) . '"
									class="gsc-copy-btn-feed-setting text-decoration-none link-hover-white"
									id="copy-service-email">Copy</a>
									<div class="gsc-copy-msg d-none">Copied successfully!</div>
								</div>';
				}

				$result .= '<ul>
					<li><svg fill="#199436" viewBox="10 10 12 12" version="1.1" xmlns="http://www.w3.org/2000/svg"><title>bullet</title><path d="M12.096 16q0 1.632 1.152 2.784t2.752 1.12 2.752-1.12 1.152-2.784-1.152-2.752-2.752-1.152-2.752 1.152-1.152 2.752z"></path></svg>
						'. esc_attr__(' Green email means the spreadsheet  is shared correctly.', 'gsheetconnector-wpforms'). '
					</li>
					<li><svg fill="#d63638" viewBox="10 10 12 12" version="1.1" xmlns="http://www.w3.org/2000/svg"><title>bullet</title><path d="M12.096 16q0 1.632 1.152 2.784t2.752 1.12 2.752-1.12 1.152-2.784-1.152-2.752-2.752-1.152-2.752 1.152-1.152 2.752z"></path></svg>
						'. esc_attr__(' Red email means the spreadsheet is not yet shared — please grant access.', 'gsheetconnector-wpforms'). '
					</li>
				</ul>';

				$result .= '</div>';
			}
		}

		/* service permission end */

		/** Pro Feature section start */
		$result .= '<div class="edit-gs-pro-card shadow-box mt-40 mb-30">
			<div class="edit-gs-pro-header p-20">
				<div class="edit-gs-pro-icon">
					<span class="pro-badge">
						<i class="fas fa-lock gsc-pro-icon"></i>
					</span>
				</div>
				<div class="edit-gs-pro-title">
					<div class="heading mt-0">
						' . esc_html__('Unlock Advanced Features with Form Feeds', 'gsheetconnector-wpforms') . '
					</div>
					<div class="d-flex flex-wrap align-items-center gap-15">
						<span class="edit-gs-pro-badge">
							' . esc_html__('Advanced options are available in PRO', 'gsheetconnector-wpforms') . '
						</span>
						<span class="edit-gs-upgrade-btn">
							<a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" class="text-decoration-none link-hover-white">
								' . esc_html__('Get Advanced Features', 'gsheetconnector-wpforms') . '
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M0.166016 10.6584L8.99102 1.83341H3.49935V0.166748H11.8327V8.50008H10.166V3.00841L1.34102 11.8334L0.166016 10.6584Z" fill="white"></path>
								</svg>
							</a>
						</span>
					</div>
				</div>
			</div>
			
			<!-- Toggle checkbox -->
			<input type="checkbox" id="toggle-features">
			<div class="edit-gs-pro-features p-20">
				<div class="edit-gs-feature-col">
					<div class="mb-20">
						<a href="#integratio_auto">
							' . esc_html__('Automatic Google Sheets Configuration', 'gsheetconnector-wpforms') . '
						</a>
						
					</div>
					<div class="gsc-pro-grid">
						<ul>
							<li>' . esc_html__('Auto fetch Google Sheets list', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Auto detect sheet tabs', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('One-click configuration', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Real-time entry sync', 'gsheetconnector-wpforms') . '</li>
						</ul>
					</div>
				</div>
				
				<div class="edit-gs-feature-col">
					<div class="mb-20">
						<a href="#field-mapping">
							' . esc_html__('Select Fields to Sync', 'gsheetconnector-wpforms') . '
						</a>
					</div>
					<div class="gsc-pro-grid">
						<ul>
							<li>' . esc_html__('Drag & drop field reordering', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Rename column headers', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Select specific fields to sync', 'gsheetconnector-wpforms') . '</li>
						</ul>
					</div>
				</div>

				<div class="edit-gs-feature-col">
					<div class="mb-20">
						<a href="#conditional-logic">
							' . esc_html__('Conditional Logic', 'gsheetconnector-wpforms') . '
						</a>
					</div>
					<div class="gsc-pro-grid">
						<ul>
							<li>' . esc_html__('Apply rules based on form values', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Sync data only when conditions match', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Filter unwanted or incomplete entries', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Create dynamic workflows automatically', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Map data conditionally to sheet columns', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Support multiple conditions', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Improve accuracy and reduce extra data', 'gsheetconnector-wpforms') . '</li>
						</ul>
					</div>
				</div>
				
				<div class="edit-gs-feature-col">
					<div class="mb-20">
						<a href="#header-settings-sheet-sorting">
							' . esc_html__('Header Settings', 'gsheetconnector-wpforms') . '
						</a>
					</div>
					<div class="gsc-pro-grid">
						<ul>
							<li>' . esc_html__('Freeze header row', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Custom font styling', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Header & row color control', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Sort by any column', 'gsheetconnector-wpforms') . '</li>
							<li>' . esc_html__('Download spreadsheet as file', 'gsheetconnector-wpforms') . '</li>
						</ul>
					</div>
				</div>
			</div>
			
			<div class="edit-gs-pro-footer">
				<label for="toggle-features" class="edit-gs-show-btn show">
					' . esc_html__('Show Features ▼', 'gsheetconnector-wpforms') . '
				</label>
				<label for="toggle-features" class="edit-gs-show-btn hide">
					' . esc_html__('Hide Features ▲', 'gsheetconnector-wpforms') . '
				</label>
			</div>
		</div>';
		/** Pro Feature section end */

	$result .='<div class="integratio_auto wpgs_panel_section" id="integratio_auto">
	<div class="gscwpff-pro-heading"> '. esc_html__('Automatic Google Sheets Configuration', 'gsheetconnector-wpforms') . '<span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>
	<p class="ptag">' . esc_html__('Automatic configure your Google Sheet and start syncing form submissions in real time.', 'gsheetconnector-wpforms') . '</p>
	<div class="gs_sheet_select_name_wrapper">
		<div id="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_name-wrap" class="wpforms-panel-field select_spreadsheet_wrapper wpforms-panel-field-select">
			<label for="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_name">Select Spreadsheet Name <i class="fa fa-question-circle-o wpforms-help-tooltip" title="'.esc_attr__('Select google spreadsheet from your google account.', 'gsheetconnector-wpforms'). '"></i></label>
			<select id="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_name" name="settings[wpgs_spreadsheets][0][gs_sheet_select_name]" class="select_spreadsheet_input" disabled>
				<option value="0">Select Spreadsheet</option>
				<option value="">Student Form</option>
				<option value="">Subscription From</option>
			</select>
		</div>
	</div>
	<div class="gs_sheet_select_tab_wrapper">
		<div id="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_tab-wrap" class="wpforms-panel-field select_tabs_wrapper wpforms-panel-field-select">
			<label for="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_tab">Select Tab <i class="fa fa-question-circle-o wpforms-help-tooltip" title="'.esc_attr__('Select tab from above chosen google sheet Tab.', 'gsheetconnector-wpforms'). '"></i></label>
			<select id="wpforms-panel-field-wpgs_spreadsheets-0-gs_sheet_select_tab" name="settings[wpgs_spreadsheets][0][gs_sheet_select_tab]" class="select_tabs_input" disabled>
				<option value="">Sheet1</option>
			
			</select>
		</div>
	</div>
	</div>';

		
		
  



		$result .= "<div class='field-maps-wrapper wpgs_panel_section'>";
		$result .= "<div class='gscwpforms-panel-field'>";
		$result .= '<div class="gscwpff-pro-wid-heading" id="field-mapping">'. esc_attr__('Select Fields to Sync', 'gsheetconnector-wpforms').' <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		$result .= "</div>";
		$result .= '<p class="ptag">' . esc_html__('Enable the fields and rename columns if needed.', 'gsheetconnector-wpforms') . '</p>';
	
		$result .= "<div class='wpform-toggle-cls field-maps form-fields form-fields-disable form-feed-" . $wpgs_feed_id . "' >";
		
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'enable',
			$form_data,
			esc_html__(' Check All ', 'gsheetconnector-wpforms'),
			[
				'parent'     => 'settings',
				
				'subsection' => $wpgs_feed_id,
				'input_class' => 'field_checkbox_all toggle-checkbox field_checkbox_all' . $wpgs_feed_id,
			],
			false
		);

		$result .= "</div>";

		foreach ($form_fields as $field) {

			$result .= "<div class='wpform-toggle-cls field-maps form-fields form-fields-disable'>";

			$result .= wpforms_panel_field(
				'toggle',
				'wpgs_spreadsheets',
				'enable',
				$form_data,
				' ' . $field['label'],
				[

					'parent'     => 'settings',
					/* translators: %s: form field label. */
					'value'       => false,
				],
				false
			);

			$result .= wpforms_panel_field(
				'text',
				'wpgs_spreadsheets1',
				'column',
				$form_data,
				'',
				[
					'parent'      => 'settings',
					'input_class' => ' disableColumnName',
					'default'     => $field['label'],
					'placeholder' => $field['label'] ,
					'value'       => false,
				],
				false
			);
			$result .= "</div>";
		}
		$result .= "</div>";

		$sync_text = __("Can't find the field you added recently?", 'gsheetconnector-wpforms');
		$sync_link_text = __('Click here to save and reload', 'gsheetconnector-wpforms');
		$tooltip_text = __('Save and reload the form to view new fields.', 'gsheetconnector-wpforms');
		$result .= "<div class='force_reload reload_warning wpforms-panel-field wpgs_panel_section' style='display:none;'>";
		$result .= "<div class='sync-heading'>";
		$result .= "<p>$sync_text <a href='javascript:void(0);'>$sync_link_text</a>";
		$result .= "<input type='hidden' class='wpgs_feed_id' value='$wpgs_feed_id'>";
		$result .= "<i class='fa fa-question-circle wpforms-help-tooltip' title='$tooltip_text'></i>";
		$result .= "<span class='result' style='display: none;'></span>";
		$result .= "</p>";
		$result .= "</div>";
		$result .= "</div>";

		$result .= "<div class='request-header-disable'>";
		$result .= '<div class="gscwpff-pro-wid-pad-heading">'. esc_html__('Smart Tags', 'gsheetconnector-wpforms').'<span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div><p class="ptag">'.esc_html__('Insert dynamic values from your forms', 'gsheetconnector-wpforms').'</p>';
		
		$result .= wpforms_render(
			WPFORMS_GOOGLESHEET_PATH . 'includes/views/settings/fields-mapping',
			[
				'title'         => '',
				'wpgs_feed_id'    => $wpgs_feed_id,
				'fields'        => $form_fields,
				// 'allowed_types' => $allowed_types,
				'meta'          => ! empty($googlesheet['headers']) ? $googlesheet['headers'] : [false],
				'name'          => "settings[wpgs_spreadsheets][{$wpgs_feed_id}][headers]",
				'test'          => $googlesheet,
			]
		);
		
		$result .= "</div>";


		$result .= "<div class='wpform-toggle-cls freeze_header misc_functions wpgs_panel_section freeze_header-disable'>";

		$result .= '<div class="gscwpff-pro-heading" id="header-settings-sheet-sorting">'. esc_html__('Header Behavior', 'gsheetconnector-wpforms').'  <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_freeze_header',
			$form_data,
			esc_html__('Freeze Header ', 'gsheetconnector-wpforms'),
			[
				'tooltip'    => esc_html__('if you want to freeze first row considered as header.', 'gsheetconnector-wpforms'),
			],
			false
		);
		$result .= "</div>";

		$result .= "<div class='wpform-toggle-cls rowcolors misc_functions wpgs_panel_section alternate_colors_disable'>";
		$result .= '<div class="gscwpff-pro-heading">'. esc_html__('Header and Rows Style', 'gsheetconnector-wpforms').'<span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		


		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_alternate_colors',
			$form_data,
			esc_html__('Alternate Colors ', 'gsheetconnector-wpforms'),
			[
				'tooltip'    => esc_html__('Control background colors of odd even rows as well as background color of header row.', 'gsheetconnector-wpforms'),
				'input_class' => "alternate_color_input",
				 'readonly'    => true,  
			],
			false
		);

	$result .= "<div class='alternate_colors_sections'>";
	
    $result .= wpforms_panel_field(
        'text',
        'wpgs_spreadsheets',
        'gscwpgs_header_color',
        $form_data,
        esc_html__('Header Color', 'gsheetconnector-wpforms'),
        [
            
            'input_id'    => 'wpgs_header_color-',
            'input_class' => 'wpgs_header_color',
            'default'     => '#ffffff',
			'input_type'  => 'color', 
			 'readonly'    => true,  
        ],
        false
    );
    $result .= wpforms_panel_field(
        'text',
        'wpgs_spreadsheets',
        'wpgs_odd_color',
        $form_data,
        esc_html__('Odd Color', 'gsheetconnector-wpforms'),
        [
           
            'input_id'    => 'wpgs_odd_color-',
            'input_class' => 'wpgs_header_color',
            'default'     => '',
			 'input_type'  => 'color',  // 👈 ADD THIS
			  'readonly'    => true,  
        ],
        false
    );
    $result .= wpforms_panel_field(
        'text',
        'wpgs_spreadsheets',
        'wpgs_even_color',
        $form_data,
        esc_html__('Even Color', 'gsheetconnector-wpforms'),
        [
          
            'input_id'    => 'wpgs_even_color-',
            'input_class' => 'wpgs_header_color',
            'default'     => '',
			 'input_type'  => 'color',  // 👈 ADD THIS
			  'readonly'    => true,  
        ],
        false
    );
    $result .= "</div>"; /* closes alternate_colors_section */


		$result .= "</div>";

		$result .= "<div class='wpform-toggle-cls rowcolors misc_functions wpgs_panel_section alternate_colors_disable'>";
		$result .= '<div class="gscwpff-pro-heading">'. esc_html__('Header Appearance', 'gsheetconnector-wpforms').' <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_alternate_colors',
			$form_data,
			esc_html__('Header - Font Settings ', 'gsheetconnector-wpforms'),
			[
				'tooltip'    => esc_html__('Control Header setting', 'gsheetconnector-wpforms'),
				'input_class' => "header_font_input",
				 'readonly'    => true,  
			],
			false
		);

		$result .= "<div class='headerfont_colors_section' >";

    $result .= "<div class='wpgs-headerfont-size-row'>";
        $result .= wpforms_panel_field(
            'number',
            'wpgs_spreadsheets',
            'wpgs_headerfont_size',
            $form_data,
            esc_html__('Font Size', 'gsheetconnector-wpforms'),
            [
               
                'input_class' => 'wpgs_headerfont_size',
                'input_attr'  => ['min' => 11, 'max' => 15],
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
     /* closes wpgs-font-size-row */

    $result .= "<div class='wpgs-headerfont-style-row'>";
	
        $result .= wpforms_panel_field(
            'label',
            'wpgs_spreadsheets',
            'wpgs_font_style',
            $form_data,
            esc_html__('Font Style', 'gsheetconnector-wpforms'),
            [
                
                'input_class' => 'wpgs-font-style',
				 'readonly'    => true,  
            ],
            false
        );
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_bold',
            $form_data,
            esc_html__('Bold', 'gsheetconnector-wpforms'),
            [
                
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_italic',
            $form_data,
            esc_html__('Italic', 'gsheetconnector-wpforms'),
            [
               
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
   
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_normal',
            $form_data,
            esc_html__('Normal', 'gsheetconnector-wpforms'),
            [
              
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
    $result .= "<div class='wpgs-headerfont-color-row'>";
        $result .= wpforms_panel_field(
            'text',
            'wpgs_spreadsheets',
            'wpgs_font_color',
            $form_data,
            esc_html__('Font Color', 'gsheetconnector-wpforms'),
            [
               
                'input_id'    => 'wpgs_font_color-' ,
                'input_class' => 'wpgs_header_color',
                'default'     => '',
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
    $result .= "</div>"; /* closes headerfont_colors_section */
		$result .= "</div>";

		$result .= "<div class='wpform-toggle-cls rowcolors misc_functions wpgs_panel_section alternate_colors_disable'>";
		$result .= '<div class="gscwpff-pro-heading">'. esc_html__('Row Appearance', 'gsheetconnector-wpforms').' <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_alternate_colors',
			$form_data,
			esc_html__('Row - Font Settings ', 'gsheetconnector-wpforms'),
			[
				'tooltip'    => esc_html__('Control row setting', 'gsheetconnector-wpforms'),
				'input_class' => "row_font_input",
				 'readonly'    => true,  
			],
			false
		);

		$result .= "<div class='headerfont_colors_section' >";

    $result .= "<div class='wpgs-headerfont-size-row'>";
        $result .= wpforms_panel_field(
            'number',
            'wpgs_spreadsheets',
            'wpgs_headerfont_size',
            $form_data,
            esc_html__('Font Size', 'gsheetconnector-wpforms'),
            [
                'input_class' => 'wpgs_headerfont_size',
                'input_attr'  => ['min' => 11, 'max' => 15],
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
     /* closes wpgs-font-size-row */

    $result .= "<div class='wpgs-headerfont-style-row'>";
	
        $result .= wpforms_panel_field(
            'label',
            'wpgs_spreadsheets',
            'wpgs_font_style',
            $form_data,
            esc_html__('Font Style', 'gsheetconnector-wpforms'),
            [
                'input_class' => 'wpgs-font-style',
				 'readonly'    => true,  
            ],
            false
        );
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_bold',
            $form_data,
            esc_html__('Bold', 'gsheetconnector-wpforms'),
            [
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_italic',
            $form_data,
            esc_html__('Italic', 'gsheetconnector-wpforms'),
            [
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
   
        $result .= wpforms_panel_field(
            'checkbox',
            'wpgs_spreadsheets',
            'wpgs_font_normal',
            $form_data,
            esc_html__('Normal', 'gsheetconnector-wpforms'),
            [
                'input_class' => 'wpgs-font-style-checkbox',
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
    $result .= "<div class='wpgs-headerfont-color-row'>";
        $result .= wpforms_panel_field(
            'text',
            'wpgs_spreadsheets',
            'wpgs_font_color',
            $form_data,
            esc_html__('Font Color', 'gsheetconnector-wpforms'),
            [
           
                'input_id'    => 'wpgs_font_color-' . $wpgs_feed_id,
                'input_class' => 'wpgs_header_color',
                'default'     => '',
				 'readonly'    => true,  
            ],
            false
        );
    $result .= "</div>";
    $result .= "</div>"; /* closes headerfont_colors_section */
		$result .= "</div>";


		$result .= "<div class='wpform-toggle-cls sheetsorting misc_functions wpgs_panel_section sort_sheet_disable'>";
		$result .= '<div class="gscwpff-pro-heading">'. esc_html__('Sheet Sorting', 'gsheetconnector-wpforms').'  <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_sheet_sorting',
			$form_data,
			esc_html__('Sort Sheet ', 'gsheetconnector-wpforms'),
			[
				

				
				'tooltip'    => esc_html__('Set up this field if you want data to be sorted automatically upon the submission based on column.', 'gsheetconnector-wpforms'),
				'input_class' => "sheet_sorting_input",
				 'readonly'    => true,  
			],
			false
		);

		$result .= '<div class="wpgs_sheet_sorting_section" style=""><div id="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_name-wrap" class="wpforms-panel-field  wpforms-panel-field-text"><label for="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_name">Sort Column Name</label><input type="text" id="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_name" name="settings[wpgs_spreadsheets][1][wpgs_sort_column_name]" value="" placeholder="" class="wpgs_sort_column_name" disabled></div><div id="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_order-wrap" class="wpforms-panel-field integration_mode_wrapper wpforms-panel-field-select"><label for="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_order">Column Order</label><select id="wpforms-panel-field-wpgs_spreadsheets-1-wpgs_sort_column_order" name="settings[wpgs_spreadsheets][1][wpgs_sort_column_order]" class="wpgs_sort_column_order" disabled><option value="ASCENDING">Ascending</option><option value="DESCENDING">Descending</option></select></div></div>';

		$result .= "</div>";


		$result .= "<div class='wpform-toggle-cls sheetsorting misc_functions wpgs_panel_section conditional_logic_disable'>";
		$result .= '<div class="gscwpff-pro-heading" id="conditional-logic">'. esc_html__('Conditional Logic', 'gsheetconnector-wpforms').'  <span class="wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded">Pro</span></div>';
		$result .= wpforms_panel_field(
			'toggle',
			'wpgs_spreadsheets',
			'wpgs_sheet_sorting',
			$form_data,
			esc_html__('Enable Conditional Logic ', 'gsheetconnector-wpforms'),
			[
				

				
				'tooltip'    => esc_html__('Enable Conditional Logic', 'gsheetconnector-wpforms'),
				'input_class' => "sheet_sorting_input",
				 'readonly'    => true,  
			],
			false
		);

		$result .= '<div class="wpforms-conditional-groups wpforms-undo-redo-container" id="wpforms-conditional-groups-settings-wpgs_spreadsheets-0">
			<select name="settings[wpgs_spreadsheets][0][conditional_type]" disabled>
				<option value="go">Send</option>
				<option value="stop">Don\'t send</option>
			</select>
			to google sheet if

			<div class="wpforms-conditional-group" data-reference="">
				<table>
					<tbody>
						<tr class="wpforms-conditional-row" data-field-id="" data-input-name="settings[wpgs_spreadsheets][1]">
							<td class="field">
								<select name="settings[wpgs_spreadsheets][1][conditionals][0][0][field]" class="wpforms-conditional-field" data-groupid="0" data-ruleid="0" disabled>
									<option value="">--- Select Field ---</option>
									<option value="4">Full Name</option>
									<option value="6">Phone number</option>
									<option value="2">Email</option>
									<option value="5">Dinner</option>
									<option value="9">Hobbies</option>
									<option value="7">Citizenship</option>
									<option value="3">Message</option>
								</select>
							</td>
							<td class="operator">
								<select name="settings[wpgs_spreadsheets][1][conditionals][0][0][operator]" class="wpforms-conditional-operator" disabled>
									<option value="==">is</option>
									<option value="!=">is not</option>
									<option value="e">empty</option>
									<option value="!e">not empty</option>
									<option value="c">contains</option>
									<option value="!c">does not contain</option>
									<option value="^">starts with</option>
									<option value="~">ends with</option>
									<option value="&gt;">greater than</option>
									<option value="&lt;">less than</option>
								</select>
							</td>
							<td class="value">
								<select name="settings[wpgs_spreadsheets][1][conditionals][0][0][value]" class="wpforms-conditional-value" disabled>
									<option value="">--- Select Choice ---</option>
								</select>
							</td>
							<td class="actions">
								<button class="wpforms-conditional-rule-add wpforms-btn wpforms-btn-sm wpforms-btn-blue" title="Create new rule">And</button>
								<button class="wpforms-conditional-rule-delete" title="Delete rule"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>';
				$result .= "</div>";
		// Get the earliest and latest dates from the wpforms_entries table
		if (wpforms()->is_pro()) {
			global $wpdb;

			$entry_date_range_cache_key = 'gscwpff_entry_date_range';
			$entry_date_range           = wp_cache_get( $entry_date_range_cache_key, 'gscwpff' );

			if ( false === $entry_date_range ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->prefix is not request input.
				$from_date = $wpdb->get_var("SELECT MIN(`date`) FROM {$wpdb->prefix}wpforms_entries");
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->prefix is not request input.
				$to_date   = $wpdb->get_var("SELECT MAX(`date`) FROM {$wpdb->prefix}wpforms_entries");

				$entry_date_range = array( 'from' => $from_date, 'to' => $to_date );
				wp_cache_set( $entry_date_range_cache_key, $entry_date_range, 'gscwpff', MINUTE_IN_SECONDS );
			}

			// Set defaults if no entries exist
			$from_date = $entry_date_range['from'] ? gmdate('Y-m-d', strtotime($entry_date_range['from'])) : gmdate('Y-m-d');
			$to_date = $entry_date_range['to'] ? gmdate('Y-m-d', strtotime($entry_date_range['to'])) : gmdate('Y-m-d');


			$sync_text = __('Sync to Google Sheet', 'gsheetconnector-wpforms');
			$sync_link_text = __('Click here', 'gsheetconnector-wpforms');
			$tooltip_text = __('Click here to sync all the entries to the above selected Google Sheet. Make sure it will add all the form entries filled till now. If find duplicate then remove it manually or select the new spreadsheet or a new tab in same sheet.', 'gsheetconnector-wpforms');
			$result .= "<div class='sync-posts wpforms-panel-field wpgs_panel_section'>";
			
			$result .= "<div class='sync-heading'>";
			$result .= "<p>$sync_text <a href='javascript:void(0);' class='button button-primary btnsync-pro'>$sync_link_text </a> <span class='wpforms-badge wpforms-badge-sm wpforms-badge-inline wpforms-badge-silver wpforms-badge-rounded'>Pro</span>";
			$result .= "<input type='hidden' class='wpgs_feed_id' value='$wpgs_feed_id'>";
			$result .= "<i class='fa fa-question-circle-o wpforms-help-tooltip tooltipstered' title='$tooltip_text'></i>";

			$result .= "<span class='result' style='display: none;'></span>";

			$result .= "</p>";
			$result .= "</div>";
			$result .= "<div class='sync-date'>";
			$result .= "<p style='margin-top: 10px;'>";
			// Date Range Filter
			$result .= "<label for='sync-from-date' style='padding: 5px 0;
    margin: 10px 0;
    display: contents !important;'>" . __('From Date', 'gsheetconnector-wpforms') . ":</label>";
			$result .= "<input type='date' id='sync-from-date' name='sync_from_date' value='$from_date' min='$from_date' max='$to_date' class='wpgs-date-picker' readonly>";

			$result .= "<label for='sync-to-date' style='padding: 5px 0;
    margin: 10px 0;
    display: contents !important;'>" . __('To Date', 'gsheetconnector-wpforms') . ":</label>";
			$result .= "<input type='date' id='sync-to-date' name='sync_to_date' value='$to_date' min='$from_date' max='$to_date' class='wpgs-date-picker' style='
    margin: 0 10px;
    padding: 5px 10px;' readonly>";
			$result .= "</p>";
			
			$result .= "</div>";
			$result .= '<div class="feed-sync__list" id="entryList"><div class="feed-sync__entry"><input type="checkbox" id="syncall" name="syncall" value="1" disabled><label for="syncall">'. esc_attr__('Sync All Entries with this feed', 'gsheetconnector-wpforms').'</label></div><span class="result" style="display: none;"></span></div>';
			$result .= "</div>";
			
		}
		return apply_filters('wpforms_googlesheets_form_builder_get_googlesheet_fields', $result, $form_data, $wpgs_feed_id);
	}


	/**
	 * Retrieve string of the class names.
	 *
	 * @since 1.0.0
	 *
	 * @param array $classes Array of class names for element.
	 *
	 * @return string
	 */
	protected function get_html_class($classes, $builder_panel_settings)
	{

		if (! is_array($classes)) {
			$classes = (array) $classes;
		}

		$settings = $builder_panel_settings->form_data['settings'];
		$gsheetconnector_wpforms = isset($settings['gsheetconnector-wpforms']) ? $settings['gsheetconnector-wpforms'] : false;

		if (! $gsheetconnector_wpforms) {
			$classes[] = 'hidden';
		}

		$classes = array_unique(array_map('esc_attr', $classes));

		return implode(' ', $classes);
	}


	/**
	 * Add a new item `Webhooks` to panel sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sections  Registered sections.
	 * @param array $form_data Contains array of the form data (post_content).
	 *
	 * @return array
	 */
	public function panel_sidebar($sections, $form_data)
	{


		$selected_method = "";
		$gsc_wpform_auth_setting = get_option('gs_wpforms_manual_setting');
		$gscwpff_authenticated = get_option('wpform_gs_token');
		$gscwpff_is_valid = get_option('wpform_gs_verify');

		
        $service_json = get_option('gs_wpformspro_service_account_json', '');      




		if ((!empty($gscwpff_authenticated) && $gscwpff_is_valid == 'valid' && $gsc_wpform_auth_setting == 0)) {
			$selected_method = esc_html__('Existing', 'gsheetconnector-wpforms');
		}elseif(((!empty($service_json)) && $gsc_wpform_auth_setting == 2)){
			$selected_method = esc_html__('Service', 'gsheetconnector-wpforms');
		}else {
			$selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');
		}

		$GSCWPFF_Title = sprintf(
			__( 'GSheetConnector (%s)', 'gsheetconnector-wpforms' ),
			$selected_method
		);


		$sections['wf_googlesheets'] = $GSCWPFF_Title;

		return $sections;
	}


	/**
	 * Add own localized strings to the Builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $strings Localized strings.
	 * @param object $form    Current form.
	 *
	 * @return array
	 */
	public function builder_strings($strings, $form)
	{

		$strings['googlesheet_prompt']        = esc_html__('Enter a Feed Name', 'gsheetconnector-wpforms');
		$strings['googlesheet_ph']            = '';
		$strings['googlesheet_error']         = esc_html__('You must provide a googlesheet name', 'gsheetconnector-wpforms');
		$strings['googlesheet_error2']        = esc_html__('To disable all wpgs_spreadsheets use the "Webhooks" dropdown setting.', 'gsheetconnector-wpforms');
		$strings['googlesheet_delete']        = esc_html__('Are you sure that you want to delete this googlesheet?', 'gsheetconnector-wpforms');
		$strings['googlesheet_def_name']      = esc_html__('Unnamed Googlesheet', 'gsheetconnector-wpforms');
		$strings['googlesheet_required_flds'] = esc_html__('Your form contains required Googlesheet settings that have not been configured. Please double-check and configure these settings to complete the connection setup.', 'gsheetconnector-wpforms');

		return $strings;
	}


	/**
	 * Enqueue a JavaScript file and inline CSS styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets()
	{

		wp_enqueue_script('wp-color-picker');

		wp_enqueue_style('wp-color-picker');

		wp_enqueue_script(
			'wpforms-wfgs-jquery-ui',
			WPFORMS_GOOGLESHEET_URL . "assets/js/jquery-ui.min.js",
			['wpforms-builder'],
			WPFORMS_GOOGLESHEET_VERSION,
			true
		);

		wp_enqueue_script(
			'wpforms-wfgs-admin-builder',
			WPFORMS_GOOGLESHEET_URL . "assets/js/wpforms-gs-panel.js",
			['wpforms-builder'],
			WPFORMS_GOOGLESHEET_VERSION,
			true
		);

		wp_enqueue_style(
			'wpforms-wfgs-admin-builder',
			WPFORMS_GOOGLESHEET_URL . "assets/css/wpforms-gs-panel.css",
			['wpforms-builder'],
			WPFORMS_GOOGLESHEET_VERSION
		);
	}
}


add_action('plugins_loaded', 'wpgs_init_components', 110, 1);
function wpgs_init_components()
{
	$form_builder = new WPGS_FormBuilder();
	$form_builder->init();
}
