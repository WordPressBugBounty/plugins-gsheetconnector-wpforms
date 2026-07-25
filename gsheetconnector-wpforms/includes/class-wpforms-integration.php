<?php

/**
 * service class for Wpform Google Sheet Connector
 * @since 1.0
 */
if (!defined('ABSPATH')) {
    exit; /*  Exit if accessed directly */
}

/**
 * WPforms_Googlesheet_Services Class
 *
 * @since 1.0
 */
class WPforms_Googlesheet_Services
{

    public function __construct()
    {
        /* get with all data and display form */
        add_action('wp_ajax_get_wpforms', array($this, 'display_wpforms_data'));
        /* get all form data */
        add_action('admin_post_wpform_gs_save', array($this, 'execute_post_data'));

        /*  activation n deactivation ajax call */
        // Verify integration.
		add_action( 'wp_ajax_gscwpf_verify_integration', array( $this, 'gscwpf_verify_integration' ) );
        add_action('wp_ajax_deactivate_wpformgsc_integation', array($this, 'deactivate_wpformgsc_integation'));

        /*  save entry with posted data */
        add_action('wpforms_process_entry_save', array($this, 'entry_save'), 20, 4);
        add_action('wp_ajax_set_upgrade_notification_interval', array($this, 'set_upgrade_notification_interval'));
        add_action('wp_ajax_close_upgrade_notification_interval', array($this, 'close_upgrade_notification_interval'));

        /* Install wp Forms plugin */
        add_action('wp_ajax_gscwpform_install_plugin', array($this, 'gscwpform_install_plugin'));

        /* Activate wp Forms plugin */
        add_action('wp_ajax_gscwp_activate_plugin', array($this, 'gscwp_activate_plugin'));

        /* Deactivate wp Forms plugin */
        add_action("wp_ajax_gscwpform_deactivate_plugin", array($this, "gscwpform_deactivate_plugin"));

        /* Save the Authentication Method */
        add_action('wp_ajax_gscwpf_save_auth_method', array($this, 'gscwpf_save_auth_method'));

        add_action( 'wp_ajax_gscwpf_save_service_account_json',   array( $this, 'gscwpf_save_service_account_json' ) );
        add_action( 'wp_ajax_gscwpf_deactivate_service_account',  array( $this, 'gscwpf_deactivate_service_account' ) );

         /* dismiss  notification */
        add_action('wp_ajax_gscwpff_dismiss_notice', array($this, 'gscwpff_dismiss_notice_callback'));

        /* snooze notitiacation  */
        add_action('wp_ajax_gscwpff_snooze_notice', array($this, 'gscwpff_snooze_notice_callback'));


        add_action('wp_ajax_dismiss_wpfpro_notice', array($this, 'gsheet_dismiss_wpfpro_notice'));

        
        add_action('wp_ajax_gscwpff_save_uninstall_settings_ajax_free', array($this, 'gscwpff_wpforms_uninstall_settings_free'));

         /* clear debug log data  */
      add_action('wp_ajax_gscwpff_clear_debug_logs', array($this, 'gscwpff_clear_debug_logs'));

      /**  */
      add_action('wp_ajax_gscwpfrom_paginate_feed_list',array($this,'gscwpfrom_paginate_feed_list'));

        
    }


    function gscwpform_deactivate_plugin()
    {
        /* nonce check */
        check_ajax_referer('gscwpff-ajax-nonce', 'security');

        if (!current_user_can('activate_plugins')) {
            Wpform_gs_Connector_Utility::gs_debug_log('Error: User lacks permission.');
            wp_send_json_error('You do not have permission to deactivate plugins.');
        }

        if (!isset($_POST['plugin_slug'])) {
            Wpform_gs_Connector_Utility::gs_debug_log('Error: Plugin slug missing.');
            wp_send_json_error('Plugin slug is missing.');
        }

        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field(wp_unslash($_POST['plugin_slug'])) : '';

        if (empty($plugin_slug)) {
            Wpform_gs_Connector_Utility::gs_debug_log('Error: Plugin slug is empty.');
            wp_send_json_error('Invalid plugin.');
        }

        /* Ensure plugin exists before attempting to deactivate */
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug)) {
            Wpform_gs_Connector_Utility::gs_debug_log("Error: Plugin file does not exist - " . $plugin_slug);
            wp_send_json_error('Plugin not found.');
        }

        deactivate_plugins($plugin_slug);

        if (is_plugin_active($plugin_slug)) {
            Wpform_gs_Connector_Utility::gs_debug_log("Error: Plugin deactivation failed - " . $plugin_slug);
            wp_send_json_error('Failed to deactivate plugin.');
        }

        wp_send_json_success('Plugin deactivated successfully.');
    }



    function gscwpform_install_plugin()
    {
        /*  Verify nonce */
        check_ajax_referer('gscwpff-ajax-nonce', 'security');

        /* Capability check */
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => 'Unauthorized request.']);
        }

        /*  Validate required parameters */
        if (!isset($_POST['plugin_slug'], $_POST['download_url'])) {
            wp_send_json_error(['message' => 'Missing required parameters.']);
        }

        /* Sanitize inputs */
        $plugin_slug  = sanitize_text_field(wp_unslash($_POST['plugin_slug']));
        $download_url = esc_url_raw(wp_unslash($_POST['download_url']));



        /* Get your domain dynamically */
        $current_domain = wp_parse_url(home_url(), PHP_URL_HOST);

        /*  Allow only safe download sources */
        $allowed_domains = [
            $current_domain,              /* auto-detected live domain */
            'downloads.wordpress.org',    /* official WP repo */
        ];

        /* Extract host from download URL */
        $host = wp_parse_url($download_url, PHP_URL_HOST);

        /*  Validate domain */
        if (empty($host) || !in_array($host, $allowed_domains, true)) {
            wp_send_json_error(['message' => 'Download URL is not allowed.']);
        }

        /*  Load necessary WordPress classes */
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/update.php';

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

        /*  Get installed plugins */
        $installed_plugins = get_plugins();
        $plugin_path = '';

        /*  Find plugin folder path */
        foreach ($installed_plugins as $path => $details) {
            if (strpos($path, $plugin_slug . '/') === 0) {
                $plugin_path = $path;
                break;
            }
        }

        /*  Upgrade plugin if it already exists */
        if ($plugin_path) {
            $update_plugins = get_site_transient('update_plugins');

            if (isset($update_plugins->response[$plugin_path])) {
                $result = $upgrader->upgrade($plugin_path);

                if (is_wp_error($result)) {
                    wp_send_json_error([
                        'message' => 'Plugin upgrade failed: ' . $result->get_error_message()
                    ]);
                }

                wp_send_json_success(['message' => 'Plugin upgraded successfully.']);
            } else {
                wp_send_json_error(['message' => 'No updates available for this plugin.']);
            }
        }

        /* Install plugin if not installed */
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => 'Plugin installation failed: ' . $result->get_error_message()
            ]);
        }

        wp_send_json_success(['message' => 'Plugin installed successfully.']);
    }

    function gscwp_activate_plugin()
    {
        /*  nonce check */
        check_ajax_referer('gscwpff-ajax-nonce', 'security');
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        if (!isset($_POST['plugin_slug'])) {
            wp_send_json_error(['message' => 'Missing plugin slug.']);
        }

        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field(wp_unslash($_POST['plugin_slug'])) : '';

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $activated = activate_plugin($plugin_slug);

        if (is_wp_error($activated)) {
            wp_send_json_error(['message' => $activated->get_error_message()]);
        }

        wp_send_json_success();
    }
    /**
     * AJAX function - get wpforms details with sheet data
     * @since 1.1
     */
    function display_wpforms_data()
    {

        /*  nonce check */
        check_ajax_referer('wp-ajax-nonce', 'security');

        /*  capability check */
        Wpform_gs_Connector_Utility::require_capability('manage_options');

        /*  Validate and sanitize input */
        if (!isset($_POST['wpformsId'])) {
            wp_send_json_error('Form ID is missing.');
        }

        $form_id = absint( wp_unslash( $_POST['wpformsId'] ) ); /* Remove slashes and ensure it's an integer */

        /*  Get form */
        $form = get_post($form_id);
        $form_title = wpforms()->form->get($form_id);

        $form_name = '';
        if (!empty($form_title)) {
            $form_name = $form->post_title;
        }

        ob_start();

        if (!empty($form_id)) {
            $host_name = str_replace('/wp-admin', '', get_admin_url());
            $new_link = admin_url("admin.php?page=wpforms-builder&view=settings&form_id={$form_id}&section=wf_googlesheets");
        } else {
            $new_link = admin_url("admin.php?page=wpform-google-sheet-config&tab=settings");
        }

?>
        <p class="deprecated-notice">
            <?php
            /* translators: %s: URL of the new Google Sheets settings page. */
            $deprecated_notice = ('This settings page is deprecated and will be removed in an upcoming version. Move your settings to the <a target="_blank" href="%s">new settings page</a> under the GSheetConnector tab to avoid data loss.');

            echo wp_kses_post(
                sprintf(
                    $deprecated_notice,
                    esc_url($new_link)
                )
            );
            ?>
        </p>
    <?php

        /* Migrate old settings */
        $this->save_old_settings_to_new_settings($form_id, $form_name);

        ob_end_clean();

        wp_send_json_success(esc_url_raw($new_link));
    }


    /*  moved old settings to new settings */
    public function save_old_settings_to_new_settings($form_id, $form_name)
    {

        $get_existing_data = get_post_meta($form_id, 'wpform_gs_settings');

        $gheet_new = [];
        if (!empty($get_existing_data)) {
            foreach ($get_existing_data as $ge) {

                $gsheet_new['name'] = $form_name . ' ' . 'GoogleSheet';
                $gsheet_new['gs_sheet_integration_mode'] = 'manual';
                $gsheet_new['gs_sheet_manuals_sheet_name'] = $ge['sheet-name'];
                $gsheet_new['gs_sheet_manuals_sheet_id'] = $ge['sheet-id'];
                $gsheet_new['gs_sheet_manuals_sheet_tab_name'] = $ge['sheet-tab-name'];
                $gsheet_new['gs_sheet_manuals_sheet_tab_id'] = $ge['tab-id'];
            }
            update_post_meta($form_id, 'wpform_gs_settings', $gsheet_new);
        }
    }

    /**
     * Function - save the setting data of google sheet with sheet name and tab name
     * @since 1.0
     */
    public function wpforms_googlesheet_settings_content($form_id, $form_name)
    {

        $get_data = get_post_meta($form_id, 'wpform_gs_settings');

        $get_disable_setting = get_post_meta($form_id, 'wpform_gs_old_settings');
        $check = $disable_text = '';
        if (isset($get_disable_setting[0]) && $get_disable_setting[0] == 1) {
            $check = 'checked';
            $disable_text = 'disabled';
        }
        $saved_sheet_name = isset($get_data[0]['sheet-name']) ? $get_data[0]['sheet-name'] : "";
        $saved_tab_name = isset($get_data[0]['sheet-tab-name']) ? $get_data[0]['sheet-tab-name'] : "";
        $saved_sheet_id = isset($get_data[0]['sheet-id']) ? $get_data[0]['sheet-id'] : "";
        $saved_tab_id = isset($get_data[0]['tab-id']) ? $get_data[0]['tab-id'] : "";

        echo '<div class="wpforms-panel-content-section-googlesheet-tab">';
        echo '<div class="wpforms-panel-content-section-title">';
    ?>

        <div class="wpforms-old-settings">
            <label class="switch">
                <input type="checkbox" class="checkbox disable_old_settings" name="disable_old_settings"
                    form-id="<?php echo esc_attr($form_id); ?>" form-title="<?php echo esc_attr($form_name); ?>" value=""
                    <?php checked($check); ?>>
                <span class="slider round"></span>
            </label>Disable Old Settings
            <span class="gs-disble-setting-message"></span>
        </div>
        <div class="wpforms-gs-fields">


            <h3><?php esc_html_e('Google Sheet Settings', 'gsheetconnector-wpforms'); ?>
                <strong class="gs-info-wpform">( Fetch your sheets automatically using PRO <a
                        href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro?gsheetconnector-ref=17"
                        target="_blank">Upgrade to PRO</a> )</strong>
            </h3>

            <p>
                <label><?php echo esc_html__('Google Sheet Name', 'gsheetconnector-wpforms'); ?></label>
                <input type="text" name="wpform-gs[sheet-name]" id="wpforms-gs-sheet-name"
                    value="<?php echo esc_attr($saved_sheet_name); ?>" <?php echo esc_attr($disable_text); ?> />
                <a href="" class="gs-name help-link">
                    <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>assets/img/help.png" class="help-icon">
                    <span class='hover-data'>
                        <?php echo esc_html__('Go to your google account and click on "Google apps" icon and then click "Sheets". Select the name of the appropriate sheet you want to link your contact form or create a new sheet.', 'gsheetconnector-wpforms'); ?>
                    </span>
                </a>
            </p>

            <p>
                <label><?php echo esc_html__('Google Sheet Id', 'gsheetconnector-wpforms'); ?></label>
                <input type="text" name="wpform-gs[sheet-id]" id="wpforms-gs-sheet-id"
                    value="<?php echo esc_attr($saved_sheet_id); ?>" <?php echo esc_attr($disable_text); ?> />
                <a href="" class="gs-name help-link">
                    <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>assets/img/help.png" class="help-icon">
                    <span class='hover-data'>
                        <?php echo esc_html__('You can get sheet ID from your sheet URL.', 'gsheetconnector-wpforms'); ?>
                    </span>
                </a>
            </p>

            <p>
                <label><?php echo esc_html__('Google Sheet Tab Name', 'gsheetconnector-wpforms'); ?></label>
                <input type="text" name="wpform-gs[sheet-tab-name]" id="wpforms-sheet-tab-name"
                    value="<?php echo esc_attr($saved_tab_name); ?>" <?php echo esc_attr($disable_text); ?> />
                <a href="" class="gs-name help-link">
                    <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>assets/img/help.png" class="help-icon">
                    <span class='hover-data'>
                        <?php echo esc_html__('Open your Google Sheet you want to link with your contact form. You will notice tab names at the bottom. Copy the name of the tab where you want entries.', 'gsheetconnector-wpforms'); ?>
                    </span>
                </a>
            </p>

            <p>
                <label><?php echo esc_html__('Google Tab Id', 'gsheetconnector-wpforms'); ?></label>
                <input type="text" name="wpform-gs[tab-id]" id="wpforms-gs-tab-id"
                    value="<?php echo esc_attr($saved_tab_id); ?>" <?php echo esc_attr($disable_text); ?> />
                <a href="" class="gs-name help-link">
                    <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>assets/img/help.png" class="help-icon">
                    <span class='hover-data'>
                        <?php echo esc_html__('You can get the tab ID from your Google Sheet URL.', 'gsheetconnector-wpforms'); ?>
                    </span>
                </a>
            </p>

            <?php
            if (
                (!empty($saved_sheet_name)) &&
                (!empty($saved_tab_name)) &&
                (!empty($saved_sheet_id)) &&
                (!empty($saved_tab_id))
            ) {
                $sheet_url = "https://docs.google.com/spreadsheets/d/" . urlencode($saved_sheet_id) . "/edit#gid=" . urlencode($saved_tab_id);
            ?>
                <p>
                    <a href="<?php echo esc_url($sheet_url); ?>" target="_blank" class="cf7_gs_link_wpfrom">
                        <?php echo esc_html__('Google Sheet Link', 'gsheetconnector-wpforms'); ?>
                    </a>
                </p>
            <?php } ?>
        </div>

        <input type="hidden" name="form-id" id="form-id" value="<?php echo esc_attr($form_id); ?>">
        <input
            type="hidden"
            name="wpform_gs_nonce"
            id="wpform_gs_nonce"
            value="<?php echo esc_attr(wp_create_nonce('wpform_gs_nonce')); ?>" />
        <!-- REQUIRED FOR SECURITY -->
        <input type="hidden" name="action" value="wpform_gs_save">
        </div>
        <!-- Upgrade to PRO -->
        <br />
        <hr class="divide">
        <div class="upgrade_pro_wpform">
            <div class="wpform_pro_demo">
                <div class="cd-faq-content" style="display: block;">
                    <div class="gs-demo-fields gs-second-block">

                        <h2 class="upgradetoprotitlewpform">
                            <?php echo esc_html(__('Upgrade to WPForms Google sheet Connector PRO', 'gsheetconnector-wpforms')); ?>
                        </h2>
                        <hr class="divide">
                        <p>
                            <a class="wpform_pro_link" target="_blank"
                                href="https://wpformsdemo.gsheetconnector.com"><label><?php echo esc_html(__('Click Here Demo', 'gsheetconnector-wpforms')); ?></label></a>
                        </p>
                        <p>
                            <a class="wpform_pro_link"
                                href="https://docs.google.com/spreadsheets/d/1ooBdX0cgtk155ww9MmdMTw8kDavIy5J1m76VwSrcTSs/edit#gid=1289172471"
                                target="_blank"
                                rel="noopener"><label><?php echo esc_html(__('Sheet URL (Click Here to view Sheet with submitted data.)', 'gsheetconnector-wpforms')); ?></label></a>
                        </p>

                        <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro?gsheetconnector-ref=17"
                            target="_blank">
                            <h3><?php echo esc_html(__('WPForms Google Sheet Connector PRO Features', 'gsheetconnector-wpforms')); ?>
                            </h3>
                        </a>
                        <div class="gsh_wpform_pro_fatur_int1">
                            <ul style="list-style: square;margin-left:30px">
                                <li><?php echo esc_html(__('Google Sheets API (Up-to date)', 'gsheetconnector-wpforms')); ?>
                                </li>
                                <li><?php echo esc_html(__('One Click Authentication', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Click & Fetch Sheet Automated', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Automated Sheet Name & Tab Name', 'gsheetconnector-wpforms')); ?>
                                </li>
                                <li><?php echo esc_html(__('Manually Adding Sheet Name & Tab Name', 'gsheetconnector-wpforms')); ?>
                                </li>
                                <li><?php echo esc_html(__('Supported WPForms Lite/Pro', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Latest WordPress & PHP Support', 'gsheetconnector-wpforms')); ?>
                                </li>
                                <li><?php echo esc_html(__('Support WordPress Multisite', 'gsheetconnector-wpforms')); ?></li>
                            </ul>
                        </div>
                        <div class="gsh_wpform_pro_img_int">
                            <img width="250" height="200" alt="wpform-GSheetConnector"
                                src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL . 'assets/img/WPForms-GSheetConnector-desktop-img.png'); ?>"
                                class="">
                        </div>
                        <div class="gsh_wpform_pro_fatur_int2">
                            <ul style="list-style: square;margin-left:68px">
                                <li><?php echo esc_html(__('Multiple Forms to Sheet', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Roles Management', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Creating New Sheet Option', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Authenticated Email Display', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Automatic Updates', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Using Smart Tags', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Custom Ordering', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Image / PDF Attachment Link', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Sheet Headers Settings', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Click to Sync', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Sheet Sorting', 'gsheetconnector-wpforms')); ?></li>
                                <li><?php echo esc_html(__('Excellent Priority Support', 'gsheetconnector-wpforms')); ?></li>
                            </ul>
                        </div>
                        <p>
                            <a class="wpform_pro_link_buy"
                                href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro?gsheetconnector-ref=17"
                                target="_blank"
                                rel="noopener"><label><?php echo esc_html(__('Buy Now', 'gsheetconnector-wpforms')); ?></label></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Upgrade to PRO -->
    <?php
    }

    /**
     * function to get all the custom posted header fields
     *
     * @since 1.0
     */
    public function execute_post_data()
    {

        /* Capability check */
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        /* Nonce check */
        if (
            ! isset($_POST['wpform_gs_nonce']) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpform_gs_nonce'] ) ), 'wpform_gs_nonce' )
        ) {
            wp_die('Security check failed');
        }

        /* Form ID */
        $form_id = isset($_POST['form-id']) ? intval($_POST['form-id']) : 0;
        if (! $form_id) return;

        /* Sanitize array */
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_data = isset($_POST['wpform-gs']) ? wp_unslash( (array) $_POST['wpform-gs'] ) : array();
        $gs_data  = array_map('sanitize_text_field', $raw_data);

        $sheet_name = $gs_data['sheet-name']     ?? '';
        $sheet_id   = $gs_data['sheet-id']       ?? '';
        $tab_name   = $gs_data['sheet-tab-name'] ?? '';
        $tab_id     = $gs_data['tab-id']         ?? '';

        $existing_data = get_post_meta($form_id, 'wpform_gs_settings', true);

        /*  Disconnect */
        if (! empty($existing_data) && $sheet_name === '') {
            delete_post_meta($form_id, 'wpform_gs_settings');
            return;
        }

        /* Save settings */
        if ($sheet_name !== '' && $tab_name !== '') {

            $safe_settings = array(
                'sheet-name'     => $sheet_name,
                'sheet-id'       => $sheet_id,
                'sheet-tab-name' => $tab_name,
                'tab-id'         => $tab_id,
            );

            update_post_meta($form_id, 'wpform_gs_settings', $safe_settings);
        }
    }


    /**
     * Function - fetch WPform list that is connected with google sheet
     * @since 1.0
     */
    public function get_forms_connected_to_sheet()
    {
        global $wpdb;

        $cache_key = 'gscwpff_forms_connected_to_sheet';
        $results   = wp_cache_get( $cache_key, 'gscwpff' );

        if ( false === $results ) {
            $sql = "
            SELECT p.ID, p.post_title, pm.meta_value
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
              AND p.post_type = %s
            ORDER BY p.ID
        ";

            // $wpdb->posts/$wpdb->postmeta are core table properties, not request input; the two dynamic values are %s-bound via prepare().
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results(
                $wpdb->prepare($sql, 'wpform_gs_settings', 'wpforms')
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            wp_cache_set( $cache_key, $results, 'gscwpff', MINUTE_IN_SECONDS );
        }

        return $results;
    }


/**
 * function to save the setting data of google sheet
 *
 * @since 1.0
 */
public function add_integration() {

    /* -------------------------------------------------------
     * 1. SETUP — resolve auth code, token, email once
     * ------------------------------------------------------- */
    $wpforms_manual_setting = get_option('wpforms_manual_setting');
    $Code                   = '';
    $header                 = '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the OAuth redirect callback from Google; a WP nonce is not applicable to a third-party OAuth redirect (the OAuth 'state' parameter is the CSRF control for this flow, not a WP nonce).
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['code'] ) && ( $wpforms_manual_setting == 0 ) && is_string( $_GET['code'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $Code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $header = esc_url_raw( admin_url( 'admin.php?page=wpform-google-sheet-config&tab=integration' ) );
        update_option( 'is_new_client_secret_wpformsgsc', 1 );
    }

    $wp_token      = get_option( 'wpform_gs_token' );
    $email_account = '';

    if ( ! empty( $wp_token ) ) {
        $google_sheet  = new wpfgsc_googlesheet();
        $email_account = $google_sheet->gsheet_print_google_account_email();
    }

    /* -------------------------------------------------------
     * 2. RESOLVE STATE FLAGS
     * ------------------------------------------------------- */
    $has_permission_error = ( get_option( 'wpform_gs_verify' ) === 'invalid-auth' );
    $is_connected         = ( ! empty( $email_account ) );
    $is_token_expired     = ( ! empty( $wp_token ) && ! $is_connected && ! $has_permission_error );
    $has_token            = ( ! empty( $wp_token ) && $wp_token !== '' );
    $redirect_uri         = admin_url( 'admin.php?page=wpform-google-sheet-config&tab=integration' );

    /* Update auth-expired flag */
    if ( $is_connected ) {
        update_option( 'wpform_gs_auth_expired_free', 'false' );
    } elseif ( $is_token_expired ) {
        update_option( 'wpform_gs_auth_expired_free', 'true' );
    }

    ?>

    <!-- =====================================================
         PAGE HEADING
    ====================================================== -->
    <div class="heading mt-0 mb-0">
        <?php echo esc_html__( 'Google Sheets Integration for WPForms', 'gsheetconnector-wpforms' ); ?>
    </div>

    <!-- =====================================================
         API SETTING SELECTOR
    ====================================================== -->
    <?php $wpforms_auth_method = get_option( 'gs_wpforms_manual_setting'); ?>

    <div class="card-wp dropdownoption-wpforms-free border-select-box row shadow-box mt-40 p-30">
        <div class="col-6">
            <div class="form-group">
                <input type="hidden" name="redirect_auth_wpforms" id="redirect_auth_wpforms"
                    value="<?php echo esc_attr( $header ); ?>">

                <label for="gs_wpforms_dro_option">
                    <?php echo esc_html__( 'Choose Google API Setting', 'gsheetconnector-wpforms' ); ?>
                </label>

                <select id="gs_wpforms_dro_option" name="gs_wpforms_dro_option" class="gsc-select">
                    <option value="0" <?php selected( $wpforms_auth_method, 0 ); ?>>
                        <?php echo esc_html__( 'Existing Client / Secret Key (Auto Setup)', 'gsheetconnector-wpforms' ); ?>
                    </option>
                    <option value="1" <?php selected( $wpforms_auth_method, 1 ); ?>>
                        <?php echo esc_html__( 'Manual Client/Secret Key (Use Your Google API Configuration)', 'gsheetconnector-wpforms' ); ?>
                    </option>
                    <option value="2" <?php selected( $wpforms_auth_method, 2 ); ?>>
                        <?php echo esc_html__( 'Service Account (Recommended)', 'gsheetconnector-wpforms' ); ?>
                    </option>
                </select>
            </div>

            <p class="api-select-help mb-0">
                <?php echo esc_html__( 'Select how WPForms should authenticate with Google Sheets.', 'gsheetconnector-wpforms' ); ?>
            </p>
            <!-- Validation Message -->
            <div id="gscwpf-auth-method-message"></div>

            <!-- Nonce -->
            <input type="hidden" name="gs-ajax-nonce" id="gs-ajax-nonce"
                value="<?php echo esc_attr( wp_create_nonce( 'gs-ajax-nonce' ) ); ?>" />
        </div>

        <div class="col-6 pl-15 mt-25">
            <input type="button"
                name="gscwpf_save_auth_method"
                id="gscwpf-save-auth-method"
                value="<?php echo esc_attr__( 'Save', 'gsheetconnector-wpforms' ); ?>"
                class="gsc-btn gsc-btn-primary save-btn" />
            <span class="loading-sign-auth-method"></span>
            
        </div>
    </div>
    <!-- card-wp #end -->

    <!-- =====================================================
         OAUTH METHOD SECTION
    ====================================================== -->
    <?php if ($wpforms_auth_method == 0) { ?>
    <div class="gs-parts-wpform oauth-method row justify-between shadow-box mt-40 p-30">

        <!-- LEFT COLUMN (col-7) -->
        <div class="col-7">
            <div class="card-wp mr-20">

                <input type="hidden" name="redirect_auth_wpforms" id="redirect_auth_wpforms_2"
                    value="<?php echo esc_attr( $header ); ?>">

                <!-- Section heading -->
                <div class="heading mt-0 pt-20">
                    <?php echo esc_html__( 'Google Account Connection', 'gsheetconnector-wpforms' ); ?>
                    <span class="badge"><?php echo esc_html__( 'Auto Setup', 'gsheetconnector-wpforms' ); ?></span>
                </div>

                <p>
                    <?php echo esc_html__( 'Sign in with your Google account to connect WPForms with Google Sheets. Once connected, your data syncs based on your settings. Learn more in the documentation', 'gsheetconnector-wpforms' ); ?>
                    <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/integration-with-google-existing-method" target="_blank">
                        <?php echo esc_html__( 'click here', 'gsheetconnector-wpforms' ); ?>
                    </a>.
                </p>

                <!-- Auth steps (shown only when not connected and no error) -->
                <?php if ( ! $has_token && ! $has_permission_error ) : ?>
                    <div class="gsc-auth-steps mt-30">
                        <div class="authentication-heading">
                            <?php echo esc_html__( 'Authenticate with Your Google Account', 'gsheetconnector-wpforms' ); ?>
                        </div>
                        <ul>
                            <li>
                            <?php echo wp_kses_post( __( 'Click on the', 'gsheetconnector-wpforms' ) ); ?>    
                            <strong><?php echo wp_kses_post( __( 'Sign in with Google', 'gsheetconnector-wpforms' ) ); ?></strong>
                            <?php echo wp_kses_post( __( ' button.', 'gsheetconnector-wpforms' ) ); ?></li>
                            <li><?php echo esc_html__( 'Log in using your Google account.', 'gsheetconnector-wpforms' ); ?></li>
                            <li><?php echo esc_html__( 'Select the Google account where your Sheets are stored.', 'gsheetconnector-wpforms' ); ?></li>
                            <li>
                                <?php echo esc_html__( 'Grant access to:', 'gsheetconnector-wpforms' ); ?>
                                <ul>
                                    <li><?php echo esc_html__( 'Google Drive', 'gsheetconnector-wpforms' ); ?></li>
                                    <li><?php echo esc_html__( 'Google Sheets', 'gsheetconnector-wpforms' ); ?></li>
                                </ul>
                            </li>
                            <li><?php echo wp_kses_post( __( 'Click', 'gsheetconnector-wpforms' ) ); ?>
                                <strong><?php echo wp_kses_post( __( 'Allow to finish authorization.', 'gsheetconnector-wpforms' ) ); ?></strong>
                                <?php echo wp_kses_post( __( 'to finish authorization.', 'gsheetconnector-wpforms' ) ); ?>
                            </li>
                            <li><?php echo esc_html__( 'Save the authentication code if prompted.', 'gsheetconnector-wpforms' ); ?></li>
                        </ul>
                        <p class="gsc-auth-note mb-0">
                            <?php echo esc_html__( 'This allows the plugin to securely sync your form data with Google Sheets.', 'gsheetconnector-wpforms' ); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- =====================================================
                     INTEGRATION BOX — Google auth card
                     Hidden entirely when permission error is present
                ====================================================== -->
                <div class="integration-box">

                    <?php if ( $has_permission_error ) : ?>

                        <!-- PERMISSION ERROR — auth card suppressed -->
                        <div class="gsc-msg gsc-error gscwpff-permission-error fw-400 text-dark  pt-10 pb-10 manual-margin">
                            <?php
                            $msg_para1 = __('Google Drive and Google Sheets permissions were not granted during authentication with Google.', 'gsheetconnector-wpforms');
                            $msg_para2 = __('Refer to Step 4 in the Connection Guide shown alongside.', 'gsheetconnector-wpforms');
                            $msg_para3 = __('Then, deactivate the connection and re-authorize it, ensuring both Google Drive and Google Sheets permissions are enabled.', 'gsheetconnector-wpforms');

                            echo wp_kses_post(
                                '<p class="fw-400 text-dark"><strong>' . esc_html__('Google Drive', 'gsheetconnector-wpforms') . '</strong> ' . esc_html__('and', 'gsheetconnector-wpforms') . ' <strong>' . esc_html__('Google Sheets', 'gsheetconnector-wpforms') . '</strong> ' . esc_html($msg_para1) . '</p>'
                                . '<p class="fw-400 text-dark"><strong>' . esc_html($msg_para2) . '</strong><br> ' . esc_html($msg_para3) . '</p>'
                            );
                            ?>
                        </div>

                    <?php elseif ( $is_token_expired ) : ?>

                        <!-- EXPIRED / INVALID TOKEN ERROR -->
                        <div id="gsc-permission-error" class="gsc-msg gsc-error fw-400 text-dark pt-10 pb-10 manual-margin">
                            <?php echo esc_html__( 'Something went wrong! Your auth code may be wrong or expired. Please deactivate auth and re-authenticate again.', 'gsheetconnector-wpforms' ); ?>
                        </div>

                    <?php else : ?>

                        <!-- NORMAL AUTH CARD -->
                        <div class="gsc-google-auth-card d-flex flex-wrap gap-20 justify-between align-center mt-30 mb-30">

                            <!-- Left: icon + label/email -->
                            <div class="gsc-google-auth-left d-flex flex-wrap align-center gap-15">
                                <div class="gsc-google-icon">G</div>

                                <?php if ( $is_connected ) : ?>

                                    <div class="connected-account">
                                        <div class="gsc-connected-left d-flex">
                                            <span class="gsc-connected-label">
                                                <?php echo esc_html__( 'Connected Google Account', 'gsheetconnector-wpforms' ); ?>
                                            </span>
                                            <span class="connected-account-manual gsc-connected-email">
                                                <?php echo esc_html( $email_account ); ?>
                                            </span>
                                        </div>
                                    </div>

                                <?php elseif ( ! empty( $Code ) ) : ?>

                                    <div class="gsc-google-auth-text">
                                        <strong><?php echo esc_html__( 'Client Token', 'gsheetconnector-wpforms' ); ?></strong>
                                    </div>

                                <?php else : ?>

                                    <div class="gsc-google-auth-text">
                                        <strong><?php echo esc_html__( 'Connect Your Google Account', 'gsheetconnector-wpforms' ); ?></strong>
                                        <p><?php echo esc_html__( 'Securely link your Google account to start syncing form entries automatically.', 'gsheetconnector-wpforms' ); ?></p>
                                    </div>

                                <?php endif; ?>
                            </div>

                            <!-- Right: connected pill / token input / sign-in button -->
                            <div class="gsc-google-auth-right">

                                <?php if ( $is_connected ) : ?>

                                    <div class="gsc-connected-pill">
                                        <span class="dot"></span>
                                        <?php echo esc_html__( 'Connected', 'gsheetconnector-wpforms' ); ?>
                                    </div>

                                <?php elseif ( ! empty( $Code ) ) : ?>

                                    <div class="token-box-width-exist">
                                        <input type="password"
                                            name="google-access-code"
                                            class="form-control"
                                            id="wpforms-setting-google-access-code"
                                            value="<?php echo esc_attr( $Code ); ?>"
                                            readonly
                                            placeholder="<?php echo esc_html__( 'Click Sign in with Google', 'gsheetconnector-wpforms' ); ?>"
                                            oncopy="return false;"
                                            onpaste="return false;"
                                            oncut="return false;" />
                                    </div>

                                <?php else : ?>

                                    <a href="<?php echo esc_url( 'https://oauth.gsheetconnector.com/index.php?client_admin_url=' . urlencode( $redirect_uri ) . '&plugin=woocommercegsheetconnector' ); ?>"
                                        class="gsc-google-btn link-hover-white">
                                        <img src="<?php echo esc_url( WPFORMS_GOOGLESHEET_URL . 'assets/img/g-logo.png' ); ?>"
                                            alt="<?php esc_attr_e( 'Sign in with Google', 'gsheetconnector-wpforms' ); ?>"
                                            loading="lazy">
                                        <?php echo esc_html__( 'Sign in with Google', 'gsheetconnector-wpforms' ); ?>
                                    </a>

                                <?php endif; ?>

                            </div>
                        </div>
                        <!-- gsc-google-auth-card #end -->

                    <?php endif; ?>
                    <!-- permission-error / expired / normal #end -->

                    <!-- =====================================================
                         DEACTIVATE / HIDDEN CODE INPUT
                    ====================================================== -->
                    <?php if ( $has_token ) : ?>

                        <input type="button"
                            name="gscwpff-confirm-deactive-popup-btn"
                            id="gscwpff-confirm-deactive-popup-btn"
                            value="<?php echo esc_attr__( 'Deactivate', 'gsheetconnector-wpforms' ); ?>"
                            class="gsc-btn gsc-btn-gray btn deactivate-btn" />

                        <span class="loading-sign-deactive">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>

                        <div>
                            <span id="gsc-validation-message"></span>
                            <span id="gsc-validation-deactivate-message"></span>
                        </div>

                        <!-- Deactivate confirm popup -->
                        <div id="gscwpff-confirm-deactive-popup-free" class="gscwpff-popup-overlay d-none">
                            <div class="gscwpff-popup text-center">
                                <div class="gsc-modal-icon">
                                    <svg width="30px" height="30px" viewBox="-0.5 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18.2202 21.25H5.78015C5.14217 21.2775 4.50834 21.1347 3.94373 20.8364C3.37911 20.5381 2.90402 20.095 2.56714 19.5526C2.23026 19.0101 2.04372 18.3877 2.02667 17.7494C2.00963 17.111 2.1627 16.4797 2.47015 15.92L8.69013 5.10999C9.03495 4.54078 9.52077 4.07013 10.1006 3.74347C10.6804 3.41681 11.3346 3.24518 12.0001 3.24518C12.6656 3.24518 13.3199 3.41681 13.8997 3.74347C14.4795 4.07013 14.9654 4.54078 15.3102 5.10999L21.5302 15.92C21.8376 16.4797 21.9907 17.111 21.9736 17.7494C21.9566 18.3877 21.7701 19.0101 21.4332 19.5526C21.0963 20.095 20.6211 20.5381 20.0565 20.8364C19.4919 21.1347 18.8581 21.2775 18.2202 21.25V21.25Z" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M10.8809 17.15C10.8809 17.0021 10.9102 16.8556 10.9671 16.7191C11.024 16.5825 11.1074 16.4586 11.2125 16.3545C11.3175 16.2504 11.4422 16.1681 11.5792 16.1124C11.7163 16.0567 11.8629 16.0287 12.0109 16.03C12.2291 16.034 12.4413 16.1021 12.621 16.226C12.8006 16.3499 12.9398 16.5241 13.0211 16.7266C13.1023 16.9292 13.122 17.1512 13.0778 17.3649C13.0335 17.5786 12.9272 17.7745 12.7722 17.9282C12.6172 18.0818 12.4203 18.1863 12.2062 18.2287C11.9921 18.2711 11.7703 18.2494 11.5685 18.1663C11.3666 18.0833 11.1938 17.9426 11.0715 17.7618C10.9492 17.5811 10.8829 17.3683 10.8809 17.15ZM11.2409 14.42L11.1009 9.20001C11.0876 9.07453 11.1008 8.94766 11.1398 8.82764C11.1787 8.70761 11.2424 8.5971 11.3268 8.5033C11.4112 8.40949 11.5144 8.33449 11.6296 8.28314C11.7449 8.2318 11.8697 8.20526 11.9959 8.20526C12.1221 8.20526 12.2469 8.2318 12.3621 8.28314C12.4774 8.33449 12.5805 8.40949 12.6649 8.5033C12.7493 8.5971 12.8131 8.70761 12.852 8.82764C12.8909 8.94766 12.9042 9.07453 12.8909 9.20001L12.7609 14.42C12.7609 14.6215 12.6808 14.8149 12.5383 14.9574C12.3957 15.0999 12.2024 15.18 12.0009 15.18C11.7993 15.18 11.606 15.0999 11.4635 14.9574C11.321 14.8149 11.2409 14.6215 11.2409 14.42Z" fill="#d97706"></path>
                                    </svg>
                                </div>
                                <div class="gsc-modal-title">
                                    <?php echo esc_html__( 'Deactivate Integration', 'gsheetconnector-wpforms' ); ?>
                                </div>
                                <p class="gsc-modal-text">
                                    <?php echo esc_html__( 'Are you sure you want to deactivate Google Sheets integration? This will stop syncing your form entries.', 'gsheetconnector-wpforms' ); ?>
                                </p>
                                <div class="popup-actions d-flex justify-center gap-10">
                                    <button type="button" class="btn deactivate-btn" id="gscwpff-deactive-popup-free-cancel">
                                        <?php echo esc_html__( 'Cancel', 'gsheetconnector-wpforms' ); ?>
                                    </button>
                                    <button type="button" class="btn btn-primary" id="wpform-free-deactivate">
                                        <?php echo esc_html__( 'Deactivate', 'gsheetconnector-wpforms' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                    <?php else : ?>

                        <!-- Hidden code input (used by JS to read the auth code) -->
                        <input type="text" style="display:none;"
                            name="google-access-code"
                            id="wpforms-setting-google-access-code"
                            value="<?php echo esc_attr( $Code ); ?>"
                            readonly
                            placeholder="<?php echo esc_html__( 'Click Sign in with Google', 'gsheetconnector-wpforms' ); ?>"
                            oncopy="return false;"
                            onpaste="return false;"
                            oncut="return false;" />

                    <?php endif; ?>

                    <!-- Nonce -->
                    <input type="hidden" name="gs-ajax-nonce" id="gs-ajax-nonce"
                        value="<?php echo esc_attr( wp_create_nonce( 'gs-ajax-nonce' ) ); ?>" />

                    <!-- Save button (shown only when a fresh code is present in the URL) -->
                    <?php if ( ! empty( $Code ) ) : ?>
                        <input type="submit"
                            name="save-gs"
                            id="save-wpform-gs-code"
                            class="save-wpform-gs-code btn btn-primary btn-pulse"
                            value="<?php echo esc_attr__( 'Save Client Token', 'gsheetconnector-wpforms' ); ?>" />
                    <?php endif; ?>

                    <span class="loading-sign"></span>

                </div>
                <!-- integration-box #end -->

                <div>
                    <span id="gscwpff-validation-message"></span>
                    <span id="gscwpff-validation-deactivate-message"></span>
                </div>

                <!-- Privacy note -->
                <div class="gsc-privacy-note mt-30 pt-10 pb-10 text-dark d-flex gap-5">
                    <div class="gsc-privacy-note-image">
                        <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 14.5V16.5M7 10.0288C7.47142 10 8.05259 10 8.8 10H15.2C15.9474 10 16.5286 10 17 10.0288M7 10.0288C6.41168 10.0647 5.99429 10.1455 5.63803 10.327C5.07354 10.6146 4.6146 11.0735 4.32698 11.638C4 12.2798 4 13.1198 4 14.8V16.2C4 17.8802 4 18.7202 4.32698 19.362C4.6146 19.9265 5.07354 20.3854 5.63803 20.673C6.27976 21 7.11984 21 8.8 21H15.2C16.8802 21 17.7202 21 18.362 20.673C18.9265 20.3854 19.3854 19.9265 19.673 19.362C20 18.7202 20 17.8802 20 16.2V14.8C20 13.1198 20 12.2798 19.673 11.638C19.3854 11.0735 18.9265 10.6146 18.362 10.327C18.0057 10.1455 17.5883 10.0647 17 10.0288M7 10.0288V8C7 5.23858 9.23858 3 12 3C14.7614 3 17 5.23858 17 8V10.0288" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div>
                        <?php echo esc_html__( 'We do not store any of the data from your Google account on our servers, everything is processed & stored on your server. We take your privacy extremely seriously and ensure it is never misused. Learn more in the documentation', 'gsheetconnector-wpforms' ); ?>
                        <a href="https://gsheetconnector.com/usage-tracking/" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html__( 'click here.', 'gsheetconnector-wpforms' ); ?>
                        </a>
                    </div>
                </div>

            </div>
            <!-- card-wp #end -->

            <!-- =====================================================
                 CONNECTION STATUS & NEXT STEPS
                 Shown only when successfully connected
            ====================================================== -->
            <?php if ( $is_connected && ( ! $has_permission_error ) ) : ?>

                <div class="gsc-connection-box mt-20">

                    <div class="heading mt-0">
                        <?php echo esc_html__( 'Connection Status & Next Steps', 'gsheetconnector-wpforms' ); ?>
                    </div>

                    <p class="gsc-desc">
                        <?php echo esc_html__( 'Your Google account has been successfully connected. You are now ready to sync your form submissions with Google Sheets securely and automatically.', 'gsheetconnector-wpforms' ); ?>
                    </p>

                    <div class="gsc-steps mb-0">
                        <?php
                        $steps = array(
                            array( 'label' => __( 'Create New Feed', 'gsheetconnector-wpforms' ), 'pro' => false ),
                            array( 'label' => __( 'Select Google Spreadsheet', 'gsheetconnector-wpforms' ), 'pro' => false ),
                            array( 'label' => __( 'Add Labels in Sheet Headers Manually', 'gsheetconnector-wpforms' ), 'pro' => false ),
                            array( 'label' => __( 'Enable Fields to Sync', 'gsheetconnector-wpforms' ), 'pro' => true ),
                            array( 'label' => __( 'Customize the Headers Appearance', 'gsheetconnector-wpforms' ), 'pro' => true ),
                            array( 'label' => __( 'Use Sync Settings (Past Entries)', 'gsheetconnector-wpforms' ), 'pro' => true ),
                        );
                        foreach ( $steps as $step ) :
                        ?>
                            <div class="gsc-step d-flex align-center gap-5">
                                <svg fill="#999999" width="12px" height="12px" viewBox="0 0 32 32">
                                    <path d="M0 16q0-3.232 1.28-6.208t3.392-5.12 5.12-3.392 6.208-1.28q3.264 0 6.24 1.28t5.088 3.392 3.392 5.12 1.28 6.208q0 3.264-1.28 6.208t-3.392 5.12-5.12 3.424-6.208 1.248-6.208-1.248-5.12-3.424-3.392-5.12-1.28-6.208zM8 16q0 3.328 2.336 5.664t5.664 2.336 5.664-2.336 2.336-5.664-2.336-5.632-5.664-2.368-5.664 2.368-2.336 5.632z"></path>
                                </svg>
                                <?php echo esc_html( $step['label'] ); ?>
                                <?php if ( $step['pro'] ) : ?>
                                    <span class="gsc-pro-badge spacing-bdg-pro"><?php echo esc_html__( 'PRO', 'gsheetconnector-wpforms' ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro"
                        target="_blank"
                        class="btn btn-primary text-decoration-none mt-30 link-hover-white">
                        <?php echo esc_html__( 'Upgrade to unlock', 'gsheetconnector-wpforms' ); ?>
                    </a>

                </div>

            <?php endif; ?>

        </div>
        <!-- col-7 #end -->

        <!-- RIGHT COLUMN (col-5) — Connection Guide Slider -->
        <div class="col-5">
            <div class="step-guide-col ml-20">

                <div class="heading mt-0">
                    <?php echo esc_html__( 'Connection Guide', 'gsheetconnector-wpforms' ); ?>
                    <span class="badge"><?php echo esc_html__( 'Step-by-Step', 'gsheetconnector-wpforms' ); ?></span>
                </div>

                <p>
                    <?php echo esc_html__( 'Follow these steps to connect your Google account and start syncing your form data with Google Sheets.', 'gsheetconnector-wpforms' ); ?>
                </p>

                <div class="gsc-slider-wrapper gscwpff-connection-guide-slider mt-30">
                    <div class="gsc-slider">
                        <?php
                        $doc_url = 'https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/integration-with-google-existing-method';
                        $slides  = array(
                            array(
                                'step'    => 1,
                                'title'   => __( 'Step-1 Connect Your Google Account', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Sign in with your Google account to start the automatic Google Sheets integration.', 'gsheetconnector-wpforms' ),
                                'link'    => 'https://gmail.com/',
                                'link_label' => __( 'Sign in with Google', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step1.png',
                            ),
                            array(
                                'step'    => 2,
                                'title'   => __( 'Step-2 Choose Google Account', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Choose the Google account where your Sheets are stored.', 'gsheetconnector-wpforms' ),
                                'link'    => $doc_url,
                                'link_label' => __( 'check our detailed guideline', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step2.png',
                            ),
                            array(
                                'step'    => 3,
                                'title'   => __( 'Step-3 Review Access Information', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Google will show what information the plugin can access.', 'gsheetconnector-wpforms' ),
                                'link'    => $doc_url,
                                'link_label' => __( 'check our detailed guideline', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step3.png',
                            ),
                            array(
                                'step'    => 4,
                                'title'   => __( 'Step-4 Grant Required Permissions', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Allow required permissions for Google Sheets and Drive access.', 'gsheetconnector-wpforms' ),
                                'link'    => $doc_url,
                                'link_label' => __( 'check our detailed guideline', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step4.png',
                            ),
                            array(
                                'step'    => 5,
                                'title'   => __( 'Step-5 Save Authentication Code', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Save the authentication code to complete the Google account connection.', 'gsheetconnector-wpforms' ),
                                'link'    => $doc_url,
                                'link_label' => __( 'check our detailed guideline', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step5.png',
                            ),
                            array(
                                'step'    => 6,
                                'title'   => __( 'Step-6 Integration Completed', 'gsheetconnector-wpforms' ),
                                'tooltip' => __( 'Your Google account is now successfully connected.', 'gsheetconnector-wpforms' ),
                                'link'    => $doc_url,
                                'link_label' => __( 'check our detailed guideline', 'gsheetconnector-wpforms' ),
                                'img'     => 'existing-step6.png',
                            ),
                        );

                        $help_icon = '<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 19.5C16.1421 19.5 19.5 16.1421 19.5 12C19.5 7.85786 16.1421 4.5 12 4.5C7.85786 4.5 4.5 7.85786 4.5 12C4.5 16.1421 7.85786 19.5 12 19.5ZM12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21ZM12.75 15V16.5H11.25V15H12.75ZM10.5 10.4318C10.5 9.66263 11.1497 9 12 9C12.8503 9 13.5 9.66263 13.5 10.4318C13.5 10.739 13.3151 11.1031 12.9076 11.5159C12.5126 11.9161 12.0104 12.2593 11.5928 12.5292L11.25 12.7509V14.25H12.75V13.5623C13.1312 13.303 13.5828 12.9671 13.9752 12.5696C14.4818 12.0564 15 11.3296 15 10.4318C15 8.79103 13.6349 7.5 12 7.5C10.3651 7.5 9 8.79103 9 10.4318H10.5Z" fill="#080341"/></svg>';

                        foreach ( $slides as $slide ) :
                        ?>
                            <div class="gsc-slide">
                                <div class="gsc-slider-headers fw-600 mb-10 text-dark">
                                    <?php echo esc_html( $slide['title'] ); ?>
                                    <a href="#" class="i-help" hover-tooltip="<?php echo esc_attr( $slide['tooltip'] ); ?>">
                                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help_icon is a hardcoded SVG string defined in this file, not request input. ?>
                                        <?php echo $help_icon; // Already safe SVG markup ?>
                                    </a>
                                </div>
                                <a href="<?php echo esc_url( $slide['link'] ); ?>" target="_blank" class="link">
                                    <?php echo esc_html( $slide['link_label'] ); ?>
                                </a>
                                <img src="<?php echo esc_url( WPFORMS_GOOGLESHEET_URL . 'assets/img/' . $slide['img'] ); ?>"
                                    alt="<?php echo esc_attr( $slide['title'] ); ?>" />
                            </div>
                        <?php endforeach; ?>

                    </div>
                    <!-- gsc-slider #end -->

                    <button class="gsc-nav prev">❮</button>
                    <button class="gsc-nav next">❯</button>
                </div>
                <!-- gsc-slider-wrapper #end -->

            </div>
            <!-- step-guide-col #end -->
        </div>
        <!-- col-5 #end -->

    </div>
    <?php } 
if ($wpforms_auth_method == '2') {
    $gs_wpforms_service_json = get_option('gs_wpformspro_service_account_json', '');
    $wpforms_service_email = '';
    $wpforms_service_valid = false;

    if (!empty($gs_wpforms_service_json)) {
        $decoded_json = json_decode($gs_wpforms_service_json, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['client_email'])) {
            $wpforms_service_email = $decoded_json['client_email'];
            $wpforms_service_valid = true;
        }
    }
    include(WPFORMS_GOOGLESHEET_PATH . "includes/pages/wpforms-gs-service-integration.php");
}
?>

    <!-- oauth-method #end -->

    <!-- =====================================================
         DEBUG LOG
    ====================================================== -->
    <?php
    if ( class_exists( 'gswpff_error_logs' ) ) {
        $logs = new gswpff_error_logs();
        $logs->gswpff_render_page_html();
    }
    ?>

    <!-- =====================================================
         PRO POPUP — Manual Client/Secret Key
    ====================================================== -->
    <div id="gscwpff-confirm-manual-popup-pro" class="gscwpff-popup-overlay d-none">
        <div class="gscwpff-popups position-relative-popup text-center">
            <button class="gscwpff-popup-close-pro gsc-pro-close">×</button>
            <div class="gsc-pro-section">
                <div class="gsc-pro-card">
                    <div class="gsc-pro-headers">
                        <span class="gsc-pro-badge">PRO</span>
                        <div class="main-popup-heading mb-20 fw-600">
                            <?php esc_html_e( 'Manual Google Sheets Integration', 'gsheetconnector-wpforms' ); ?>
                        </div>
                        <p class="mb-0 text-center">
                            <?php echo esc_html__( 'Connect WPForms to Google Sheets using your own Google Cloud project. Ideal for advanced users who need full API control, custom OAuth setup, and independent credential management.', 'gsheetconnector-wpforms' ); ?>
                        </p>
                    </div>
                    <div class="gsc-pro-features">
                        <?php
                        $manual_features = array(
                            array(
                                'icon'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M21 10h-6l-2-2H3v8h10l2-2h6v-4z" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                                'heading' => __( 'Custom API Credentials', 'gsheetconnector-wpforms' ),
                                'text'    => __( 'Connect using your own Google Cloud Client ID and Client Secret for full authentication control.', 'gsheetconnector-wpforms' ),
                            ),
                            array(
                                'icon'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7l7-4z" stroke="#000" stroke-width="2"/></svg>',
                                'heading' => __( 'Secure Authentication', 'gsheetconnector-wpforms' ),
                                'text'    => __( 'Authenticate directly with Google using a secure Authentication flow without third-party dependency.', 'gsheetconnector-wpforms' ),
                            ),
                            array(
                                'icon'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 8a4 4 0 100 8 4 4 0 000-8z" stroke="#000" stroke-width="2"/><path d="M2 12h2M20 12h2M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4" stroke="#000" stroke-width="2"/></svg>',
                                'heading' => __( 'Advanced Configuration', 'gsheetconnector-wpforms' ),
                                'text'    => __( 'Set custom Redirect URIs, manage scopes, and configure API settings based on your project needs.', 'gsheetconnector-wpforms' ),
                            ),
                            array(
                                'icon'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M18 8a6 6 0 10-12 0v4a2 2 0 002 2h1v-4H8a4 4 0 118 0h-1v4h1a2 2 0 002-2V8z" stroke="#000" stroke-width="2"/></svg>',
                                'heading' => __( 'Priority Technical Support', 'gsheetconnector-wpforms' ),
                                'text'    => __( 'Get fast assistance from our expert team for setup, troubleshooting, and optimization.', 'gsheetconnector-wpforms' ),
                            ),
                        );
                        foreach ( $manual_features as $feature ) :
                        ?>
                            <div class="gsc-feature-item">
                                <div class="gsc-feature-icon"><?php echo $feature['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $feature['icon'] is hardcoded SVG markup defined in the $manual_features array above, not request input. ?></div>
                                <div class="gsc-feature-content">
                                    <div class="gsc-popup-header"><?php echo esc_html( $feature['heading'] ); ?></div>
                                    <p><?php echo esc_html( $feature['text'] ); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="gsc-pro-actions justify-center d-flex flex-wrap gap-20 mt-20">
                        <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" class="btn btn-primary text-decoration-none link-hover-white">
                            <?php echo esc_html__( 'Upgrade to Unlock', 'gsheetconnector-wpforms' ); ?>
                        </a>
                        <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro#features" target="_blank" class="btn deactivate-btn text-decoration-none">
                            <?php echo esc_html__( 'View Pro Features', 'gsheetconnector-wpforms' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- manual popup #end -->

    <?php
    if ( class_exists( 'gsff_error_logs' ) ) {
        $logs = new gswpff_error_logs();
        $logs->gswpff_render_page_html();
    }

} // end add_integration()

    /**
     * get form data on ajax fire inside div
     * @since 1.1
     */
    public function add_settings_page()
    {
        $forms = get_posts(array(
            'post_type' => 'wpforms',
            'numberposts' => -1
        ));
    ?>

        <div class="heading mt-0">
            <?php echo esc_html__('WPForms - Google Sheet Settings', 'gsheetconnector-wpforms'); ?>
        </div>
        <p class="wpforms-settings-description">
            <?php esc_html_e('Select a form to configure the WPForms GSheetConnector integration.', 'gsheetconnector-wpforms'); ?>
        </p>

        <div class="card-wp border-select-box dropdownoption-wpforms row align-end shadow-box mt-40 p-30">
            <div class="col-6">
                <div class="form-group">
                   
                    <label for="gscwpfp_dropdown_option">
                        <?php echo esc_html__('Choose WPForms', 'gsheetconnector-wpforms'); ?>
                    </label>
                  
                        <select id="wpforms_select" name="wpforms" class="gsc-select">
                            <option value=""><?php echo esc_html__('Select Form', 'gsheetconnector-wpforms'); ?>
                            </option>
                            <?php foreach ($forms as $form) { ?>
                                <option value="<?php echo esc_attr($form->ID); ?>"><?php echo esc_html($form->post_title); ?></option>
                            <?php } ?>
                        </select>
                        
                    

                    <input type="hidden" name="wp-ajax-nonce" id="wp-ajax-nonce"
                        value="<?php echo esc_attr(wp_create_nonce('wp-ajax-nonce')); ?>" />

                </div>
            </div>
            <div class="col-6">
                <span class="loading-sign-select"></span>
            </div>

        </div> 
        <!-- card wp #end -->
<?php
    }


    /**
        * AJAX function - verifies the token
        *
        * @since 1.0
        */
        public function gscwpf_verify_integration() {
            // Nonce check
            check_ajax_referer('gs-ajax-nonce', 'security');

            // Capability check
            Wpform_gs_Connector_Utility::require_capability('manage_options');

            // Sanitize input
            $Code = isset($_POST["code"]) ? sanitize_text_field( wp_unslash( $_POST["code"] ) ) : '';

            if (empty($Code)) {
                wp_send_json_error(array(
                    'message' => __('Invalid access code entered.', 'gsheetconnector-wpforms')
                ));
            }

            // KEEPING YOUR ORIGINAL LOGIC
            update_option('wpform_gs_access_code', $Code);

            if (get_option('wpform_gs_access_code') != '') {

                include_once(WPFORMS_GOOGLESHEET_ROOT . '/lib/google-sheets.php');

                wpfgsc_googlesheet::preauth(get_option('wpform_gs_access_code'));

                wp_send_json_success(array(
                    'message' => __('Your Google Access Code is Authorized and Saved', 'gsheetconnector-wpforms'),
                    'redirect' => isset($_POST['redirect']) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : ''
                ));

            } else {

                update_option('wpform_gs_verify', 'invalid');

                wp_send_json_error(array(
                    'message' => __('Invalid access code entered.', 'gsheetconnector-wpforms')
                ));
            }
        }


    /**
     * AJAX function - deactivate activation
     * @since 1.0
     */
    public function deactivate_wpformgsc_integation()
    {
        // nonce check
        check_ajax_referer('gs-ajax-nonce', 'security');

        // capability check
        Wpform_gs_Connector_Utility::require_capability('manage_options');

        if (get_option('wpform_gs_token') != '') {

            $accesstoken = get_option('wpform_gs_token');
            $client = new wpfgsc_googlesheet();
            $client->revokeToken_auto($accesstoken);

            delete_option('wpform_gs_token');
            delete_option('wpform_gs_access_code');
            delete_option('wpform_gs_verify');
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * AJAX function - save authentication method
     * @since 4.0.4
     */
   /*public function gscwpf_save_auth_method() {
    try {

        $msg = array();

       
        check_ajax_referer( 'gs-ajax-nonce', 'security' );

       
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        $method = isset( $_POST['api_auth_method'] )
            ? sanitize_text_field( wp_unslash( $_POST['api_auth_method'] ) )
            : '';

        $allowed_methods = array( 'wpforms_existing', 'wpforms_service' );

        if ( ! in_array( $method, $allowed_methods, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid authentication method.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        
        update_option( 'gs_wpforms_manual_setting', $method );

       

        wp_send_json_success( array( 'message' => __( 'Authentication method saved successfully.', 'gsheetconnector-wpforms' ) ) );

    } catch ( Exception $e ) {

        $msg['ERROR_MSG'] = $e->getMessage();
        $msg['TRACE_STK'] = $e->getTraceAsString();
        Wpform_gs_Connector_Utility::gs_debug_log( $msg );
        wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'gsheetconnector-wpforms' ) ) );

    }
}*/

public function gscwpf_save_auth_method() {
    try {

        $msg = array();

        /* Verify nonce */
        check_ajax_referer( 'gs-ajax-nonce', 'security' );

        /* Check user permissions */
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        $method = isset( $_POST['api_auth_method'] )
            ? sanitize_text_field( wp_unslash( $_POST['api_auth_method'] ) )
            : '';

        $allowed_methods = array( '0', '2' );

        if ( ! in_array( $method, $allowed_methods, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid authentication method.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        /* Save selected method */
        update_option( 'gs_wpforms_manual_setting', $method );


        if($method == 0){

          
            update_option( 'gs_wpform_auth_method', 'wpforms_existing');
        }elseif($method == 2){
          
            update_option( 'gs_wpform_auth_method', 'wpforms_service');
        }

        wp_send_json_success( array( 'message' => __( 'Authentication method saved successfully.', 'gsheetconnector-wpforms' ) ) );

    } catch ( Exception $e ) {

        $msg['ERROR_MSG'] = $e->getMessage();
        $msg['TRACE_STK'] = $e->getTraceAsString();
        Wpform_gs_Connector_Utility::gs_debug_log( $msg );
        wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'gsheetconnector-wpforms' ) ) );

    }
}


    /**
     * AJAX function - save service account JSON
     * @since 4.0.4
     */
    public function gscwpf_save_service_account_json() {
    try {

        $msg = array();

        check_ajax_referer( 'gs-ajax-nonce', 'security' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        $clean = isset( $_POST['json'] ) ? wp_kses( wp_unslash( $_POST['json'] ), array() ) : '';

        if ( empty( $clean ) ) {
            wp_send_json_error( array( 'message' => __( 'JSON data is empty. Please provide valid credentials.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        /* Validate JSON structure */
        $decoded = json_decode( $clean, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $decoded['client_email'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Your uploaded JSON key is invalid', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        update_option( 'gs_wpformspro_service_account_json', $clean );

        wp_send_json_success( array( 'message' => __( 'Service account credentials saved successfully.', 'gsheetconnector-wpforms' ) ) );

    } catch ( Exception $e ) {

        $msg['ERROR_MSG'] = $e->getMessage();
        $msg['TRACE_STK'] = $e->getTraceAsString();
        Wpform_gs_Connector_Utility::gs_debug_log( $msg );
        wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'gsheetconnector-wpforms' ) ) );

    }
}

/**
 * AJAX function - deactivate service account integration
 * @since 4.0.4
 */
public function gscwpf_deactivate_service_account() {
    try {

        $msg = array();

        check_ajax_referer( 'gs-ajax-nonce', 'security' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gsheetconnector-wpforms' ) ) );
            return;
        }

        delete_option( 'gs_wpformspro_service_account_json' );

        wp_send_json_success( array( 'message' => __( 'Service account deactivated successfully.', 'gsheetconnector-wpforms' ) ) );

    } catch ( Exception $e ) {

        $msg['ERROR_MSG'] = $e->getMessage();
        $msg['TRACE_STK'] = $e->getTraceAsString();
        Wpform_gs_Connector_Utility::gs_debug_log( $msg );
        wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'gsheetconnector-wpforms' ) ) );

    }
}

    /**
     * Function - To send wpform data to google spreadsheet
     * @since 1.0
     */
    public function entry_save($fields, $entry, $form_id, $form_data = '')
    {
        $data = array();

        /* Get Entry Id */
        $entry_id = wpforms()->process->entry_id;

        /*  get form data */
        $form_data_get = get_post_meta($form_id, 'wpform_gs_settings');

        $sheet_name = isset($form_data_get[0]['sheet-name']) ? $form_data_get[0]['sheet-name'] : "";

        $sheet_id = isset($form_data_get[0]['sheet-id']) ? $form_data_get[0]['sheet-id'] : "";

        $sheet_tab_name = isset($form_data_get[0]['sheet-tab-name']) ? $form_data_get[0]['sheet-tab-name'] : "";

        $tab_id = isset($form_data_get[0]['tab-id']) ? $form_data_get[0]['tab-id'] : "";

        $payment_type = array("payment-single", "payment-multiple", "payment-select", "payment-total");

        if ((!empty($sheet_name)) && (!empty($sheet_tab_name)) && !Wpform_gs_Connector_Utility::is_entry_synced($form_id, $entry_id)) {
            try {
                Wpform_gs_Connector_Utility::mark_entry_synced($form_id, $entry_id);

                include_once(WPFORMS_GOOGLESHEET_ROOT . "/lib/google-sheets.php");
                $doc = new wpfgsc_googlesheet();
                $doc->auth();
                $doc->setSpreadsheetId($sheet_id);
                $doc->setWorkTabId($tab_id);

                /* $timestamp = strtotime(date("Y-m-d H:i:s"));
                 Fetched local date and time instaed of unix date and time */
                $data['date'] = date_i18n(get_option('date_format'));
                $data['time'] = date_i18n(get_option('time_format'));

                foreach ($fields as $k => $v) {
                    $get_field = $fields[$k];
                    $key = $get_field['name'];
                    $value = $get_field['value'];
                    if (in_array($get_field['type'], $payment_type)) {
                        $value = html_entity_decode($get_field['value']);
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

    public function gscwpff_dismiss_notice_callback(){
     
     if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'gswpff-banner-ajax-nonce')) {
      wp_send_json_error('Invalid nonce');
      }

      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
      if (!isset($_POST['key'])) {
      wp_send_json_error('Missing key');
      }
      
    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
      $key = sanitize_text_field($_POST['key']);
      update_option('gscwpff_notice_' . $key, 'dismissed');
      wp_send_json_success();
    }

public function gscwpff_snooze_notice_callback()
   {
      /*if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'gf-ajax-nonce')) {*/
      if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'gswpff-banner-ajax-nonce')) {
      wp_send_json_error('Invalid nonce');
      }
      if (!isset($_POST['key'])) {
      wp_send_json_error('Missing key');
      }
   
      $key = sanitize_text_field(wp_unslash($_POST['key']));
      update_option('gscwpff_notice_' . $key . '_time', time());
      wp_send_json_success();
   }

public function gsheet_dismiss_wpfpro_notice(){
    /*$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';*/
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if ( ! wp_verify_nonce($nonce, 'gs-ajax-nonce') ) {
        wp_send_json_error('Invalid nonce');
    }

    setcookie(
        'gsheetwpf_pro_notice_dismissed',
        '1',
        time() + (7 * 24 * 60 * 60),
        COOKIEPATH,
        COOKIE_DOMAIN
    );

    wp_send_json_success();
}

public function gscwpff_wpforms_uninstall_settings_free(){
		check_ajax_referer('gsc-wpforms-setting-ajax-nonce', 'security');
		$value = isset($_POST['uninstall_setting']) ? intval($_POST['uninstall_setting']) : 0;
		update_option('gscwpff_wpforms_uninstall_settings_free', $value);
		wp_send_json_success();
	}

public function gscwpff_clear_debug_logs(){

      /*  Initialize the WP_Filesystem global */
      global $wp_filesystem;
      if (empty($wp_filesystem)) {
         require_once ABSPATH . 'wp-admin/includes/file.php';
         WP_Filesystem();
      }


      /*  Define log path */
      $debug_file = WP_CONTENT_DIR . '/debug.log';

      /*  If file exists and is writable, clear it; otherwise create an empty file */
      if ($wp_filesystem->exists($debug_file)) {
         if ($wp_filesystem->is_writable($debug_file)) {
            $result = $wp_filesystem->put_contents($debug_file, '', FS_CHMOD_FILE);
         } else {
            wp_send_json_error('Debug log is not writable', 403);
         }
      } else {
         /*  Create the file if it doesn’t exist */
         $result = $wp_filesystem->put_contents($debug_file, '', FS_CHMOD_FILE);
      }

      if (false === $result) {
         wp_send_json_error('Failed to clear debug log', 500);
      }

      wp_send_json_success();

}


public function gscwpfrom_paginate_feed_list(){

  check_ajax_referer('wpformdb-pagination-nonce', 'security');
    $wpformdb_paged    = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
     $wpformdb_per_page = 3;


      global $wpdb;
    

         $wpformdb_feed_sheet_list = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_content, pm.meta_value
            FROM {$wpdb->prefix}posts AS p
            LEFT JOIN {$wpdb->prefix}postmeta AS pm
                ON p.ID = pm.post_id AND pm.meta_key = 'wpform_gs_settings'
            WHERE p.post_type = 'wpforms'
            AND p.post_status = 'publish'
            ORDER BY p.ID"
        );


   
        $wpformdb_result = $this->wpformdb_render_feed_page($wpformdb_feed_sheet_list, $wpformdb_paged, $wpformdb_per_page);

        wp_send_json_success($wpformdb_result);


}


   function wpformdb_render_feed_page($wpformdb_feed_sheet_list, $wpformdb_paged, $wpformdb_per_page){

        // Flatten every form's feeds into a single list first, since the table
        // renders one row per feed (not per form). Pagination must be computed
        // over that flattened list, otherwise "N per page" doesn't match what
        // is actually displayed.
        $wpformdb_all_feeds = array();

        if (!empty($wpformdb_feed_sheet_list)) {
            foreach ($wpformdb_feed_sheet_list as $row) {

                $post_content = maybe_unserialize($row->post_content);
                $get_post_content_array = json_decode($post_content, true);

                $get_feed_array = isset($get_post_content_array['settings']['wpgs_spreadsheets'])
                    ? $get_post_content_array['settings']['wpgs_spreadsheets']
                    : [];

                if (empty($get_feed_array)) {
                    continue; // skip forms with no feeds set up
                }

                foreach ($get_feed_array as $value) {
                    $wpformdb_all_feeds[] = array(
                        'form_id'    => $row->ID,
                        'form_title' => !empty($row->post_title) ? $row->post_title : '',
                        'feed_name'  => isset($value['name']) ? $value['name'] : '',
                        'sheet_name' => isset($value['gs_sheet_manuals_sheet_name']) ? $value['gs_sheet_manuals_sheet_name'] : '',
                        'sheet_id'   => isset($value['gs_sheet_manuals_sheet_id'])   ? $value['gs_sheet_manuals_sheet_id']   : '',
                        'tab_id'     => isset($value['gs_sheet_manuals_sheet_tab_id'])     ? $value['gs_sheet_manuals_sheet_tab_id']     : '',
                        'tab_name'   => isset($value['gs_sheet_manuals_sheet_tab_name'])   ? $value['gs_sheet_manuals_sheet_tab_name']   : '',
                    );
                }
            }
        }

        $wpformdb_total_rows  = count($wpformdb_all_feeds);
        $wpformdb_total_pages = (int) ceil($wpformdb_total_rows / $wpformdb_per_page);
        $wpformdb_paged       = max(1, min($wpformdb_paged, max(1, $wpformdb_total_pages)));
        $wpformdb_offset      = ($wpformdb_paged - 1) * $wpformdb_per_page;

        $wpformdb_list_paged = array_slice($wpformdb_all_feeds, $wpformdb_offset, $wpformdb_per_page);


        ob_start();
        if (!empty($wpformdb_list_paged)) {
            foreach ($wpformdb_list_paged as $feed) {
                ?>
                <tr>
                    <td><?php echo esc_html($feed['form_title']); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpforms-builder&view=settings&form_id=' . urlencode($feed['form_id']) . '&section=wf_googlesheets')); ?>" target="_blank">
                            <?php echo esc_html($feed['feed_name']); ?>
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($feed['sheet_id'])) { ?>
                            <a href="<?php echo esc_url('https://docs.google.com/spreadsheets/d/' . rawurlencode($feed['sheet_id']) . '/edit#gid=' . rawurlencode($feed['tab_id'])); ?>" target="_blank">
                                <?php echo esc_html($feed['sheet_name']); ?><?php echo !empty($feed['tab_name']) ? ' — ' . esc_html($feed['tab_name']) : ''; ?>
                            </a>
                        <?php } else { ?>
                            <span class="gscdf-not-connected"><?php echo esc_html__('Not connected', 'gsheetconnector-wpforms'); ?></span>
                        <?php } ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="3" class="wpformprodb-feed-empty-cell">
                    <div class="wpformprodb-feed-empty text-center">
                        <div class="heading">
                            <?php echo esc_html__('No Form Feeds Created Yet', 'gsheetconnector-wpforms'); ?>
                        </div>
						<p> <?php echo esc_html__('Connect your form to Google Sheets to automatically sync submissions in real time. Create a feed to start sending data to your spreadsheet.', 'gsheetconnector-wpforms'); ?></p>
                        <a class="btn btn-primary link-hover-white"
                            href="<?php echo esc_url(admin_url('admin.php?page=wpforms-overview')); ?>" target="_blank">
                            <?php echo esc_html__('Create Feed', 'gsheetconnector-wpforms'); ?>
                        </a>
                    </div>
                </td>
            </tr>
            <?php
        }
        $wpformdb_rows_html = ob_get_clean();

        ob_start();
        if ($wpformdb_total_pages > 1) {
            for ($i = 1; $i <= $wpformdb_total_pages; $i++) {
            ?>
                <a href="javascript:void(0);"
                    class="text-decoration-none wpformdb-page-link <?php echo ($i === $wpformdb_paged) ? 'active' : ''; ?>"
                    data-page="<?php echo esc_attr($i); ?>">
                    <?php echo esc_html($i); ?>
                </a>
        <?php
            }
        }
        $wpformdb_pagination_html = ob_get_clean();

        return [
            'rows_html'       => $wpformdb_rows_html,
            'pagination_html' => $wpformdb_pagination_html,
        ];

   }


}

$wpforms_service = new WPforms_Googlesheet_Services();
