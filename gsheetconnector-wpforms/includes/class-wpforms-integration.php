<?php
/**
 * service class for Wpform Google Sheet Connector
 * @since 1.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
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
        // get with all data and display form
        add_action('wp_ajax_get_wpforms', array($this, 'display_wpforms_data'));
        // get all form data
        add_action('admin_post_wpform_gs_save', array($this, 'execute_post_data'));

        // activation n deactivation ajax call
        add_action('wp_ajax_deactivate_wpformgsc_integation', array($this, 'deactivate_wpformgsc_integation'));
        // save entry with posted data
        add_action('wpforms_process_entry_save', array($this, 'entry_save'), 20, 4);
        add_action('wp_ajax_set_upgrade_notification_interval', array($this, 'set_upgrade_notification_interval'));
        add_action('wp_ajax_close_upgrade_notification_interval', array($this, 'close_upgrade_notification_interval'));

        // Install Fluent Forms plugin
        add_action('wp_ajax_gscwpform_install_plugin', array($this, 'gscwpform_install_plugin'));

        // Activate Fluent Forms plugin
        add_action('wp_ajax_gscwp_activate_plugin', array($this, 'gscwp_activate_plugin'));

        // Deactivate Fluent Forms plugin
        add_action("wp_ajax_gscwpform_deactivate_plugin", array($this, "gscwpform_deactivate_plugin"));
    }
  function gscwpform_deactivate_plugin()
    {
        // nonce check
        check_ajax_referer('gscwpform_ajax_nonce', 'security');

        if (!current_user_can('activate_plugins')) {
            GravityForms_Gs_Connector_Utility::gfgs_debug_log('Error: User lacks permission.');
            wp_send_json_error('You do not have permission to deactivate plugins.');
        }

        if (!isset($_POST['plugin_slug'])) {
            GravityForms_Gs_Connector_Utility::gfgs_debug_log('Error: Plugin slug missing.');
            wp_send_json_error('Plugin slug is missing.');
        }

        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field(wp_unslash($_POST['plugin_slug'])) : '';

        if (empty($plugin_slug)) {
            GravityForms_Gs_Connector_Utility::gfgs_debug_log('Error: Plugin slug is empty.');
            wp_send_json_error('Invalid plugin.');
        }

        // Ensure plugin exists before attempting to deactivate
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug)) {
            GravityForms_Gs_Connector_Utility::gfgs_debug_log("Error: Plugin file does not exist - " . $plugin_slug);
            wp_send_json_error('Plugin not found.');
        }

        deactivate_plugins($plugin_slug);

        if (is_plugin_active($plugin_slug)) {
            GravityForms_Gs_Connector_Utility::gfgs_debug_log("Error: Plugin deactivation failed - " . $plugin_slug);
            wp_send_json_error('Failed to deactivate plugin.');
        }

        //error_log("Success: Plugin deactivated - " . $plugin_slug);
        wp_send_json_success('Plugin deactivated successfully.');
    }



    // function gscwpform_install_plugin()
    // {
    //     // nonce check
    //     check_ajax_referer('gscwpform_ajax_nonce', 'security');
    //     if (!isset($_POST['plugin_slug'], $_POST['download_url'])) {
    //         wp_send_json_error(['message' => 'Missing required parameters.']);
    //     }

    //     $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field(wp_unslash($_POST['plugin_slug'])) : '';
    //     $download_url = isset($_POST['download_url']) ? esc_url_raw(wp_unslash($_POST['download_url'])) : '';

    //     if (empty($plugin_slug) || empty($download_url)) {
    //         wp_send_json_error(['message' => 'Invalid plugin data.']);
    //     }

    //     include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    //     include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    //     include_once ABSPATH . 'wp-admin/includes/file.php';
    //     include_once ABSPATH . 'wp-admin/includes/update.php';

    //     $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

    //     // Get the list of installed plugins
    //     $installed_plugins = get_plugins();
    //     $plugin_path = '';

    //     // Find the correct plugin file path
    //     foreach ($installed_plugins as $path => $details) {
    //         if (strpos($path, $plugin_slug . '/') === 0) {
    //             $plugin_path = $path;
    //             break;
    //         }
    //     }

    //     // Check if the plugin is already installed
    //     if ($plugin_path) {
    //         // Plugin is installed, check for updates
    //         $update_plugins = get_site_transient('update_plugins');

    //         if (isset($update_plugins->response[$plugin_path])) {
    //             // Upgrade the plugin
    //             $result = $upgrader->upgrade($plugin_path);

    //             if (is_wp_error($result)) {
    //                 wp_send_json_error(['message' => 'Upgrade failed: ' . $result->get_error_message()]);
    //             }

    //             wp_send_json_success(['message' => 'Plugin upgraded successfully.']);
    //         } else {
    //             wp_send_json_error(['message' => 'No updates available for this plugin.']);
    //         }
    //     } else {
    //         // Plugin is NOT installed, install it
    //         $result = $upgrader->install($download_url);

    //         if (is_wp_error($result)) {
    //             wp_send_json_error(['message' => 'Installation failed: ' . $result->get_error_message()]);
    //         }

    //         wp_send_json_success();
    //     }
    // }


function gscwpform_install_plugin()
{
    // Verify nonce
    check_ajax_referer('gscwpform_ajax_nonce', 'security');

    // Capability check
    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => 'Unauthorized request.']);
    }

    // Validate required parameters
    if (!isset($_POST['plugin_slug'], $_POST['download_url'])) {
        wp_send_json_error(['message' => 'Missing required parameters.']);
    }

    // Sanitize inputs
    $plugin_slug  = sanitize_text_field(wp_unslash($_POST['plugin_slug']));
    $download_url = esc_url_raw(wp_unslash($_POST['download_url']));

   

    // Get your domain dynamically
    $current_domain = wp_parse_url(home_url(), PHP_URL_HOST);

    // Allow only safe download sources
    $allowed_domains = [
        $current_domain,              // auto-detected live domain
        'downloads.wordpress.org',    // official WP repo
    ];

    // Extract host from download URL
    $host = wp_parse_url($download_url, PHP_URL_HOST);

    // Validate domain
    if (empty($host) || !in_array($host, $allowed_domains, true)) {
        wp_send_json_error(['message' => 'Download URL is not allowed.']);
    }

    // Load necessary WordPress classes
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/update.php';

    $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

    // Get installed plugins
    $installed_plugins = get_plugins();
    $plugin_path = '';

    // Find plugin folder path
    foreach ($installed_plugins as $path => $details) {
        if (strpos($path, $plugin_slug . '/') === 0) {
            $plugin_path = $path;
            break;
        }
    }

    // Upgrade plugin if it already exists
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

    // Install plugin if not installed
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
        // nonce check
        check_ajax_referer('gscwpform_ajax_nonce', 'security');
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

        // nonce check
        check_ajax_referer('wp-ajax-nonce', 'security');

        // Validate and sanitize input
        if (!isset($_POST['wpformsId'])) {
            wp_send_json_error('Form ID is missing.');
        }

        $form_id_raw = wp_unslash($_POST['wpformsId']); // Remove slashes
        $form_id = absint($form_id_raw); // Ensure it's an integer

        // Get form
        $form = get_post($form_id);
        $form_title = wpforms()->form->get($form_id);

        $form_name = '';
        if (!empty($form_title)) {
            $form_name = $form->post_title;
        }

        ob_start();

        if (!empty($form_id)) {
            $host_name = str_replace('/wp-admin', '', get_admin_url());
            $new_link = admin_url("admin.php?page=wpforms-builder&view=settings&form_id={$form_id}");
        } else {
            $new_link = admin_url("admin.php?page=wpform-google-sheet-config&tab=settings");
        }

        ?>
        <p class="deprecated-notice">
            <?php
            echo wp_kses_post(
                sprintf(
                    __('This settings page is deprecated and will be removed in upcoming version. Move your settings to the <a target="_blank" href="%s">new settings page</a> under GSheetConnector Tab to avoid loss of data.', 'gsheetconnector-wpforms'),
                    esc_url($new_link)
                )
            );
            ?>
        </p>
        <?php

        // Migrate old settings
        $this->save_old_settings_to_new_settings($form_id, $form_name);

        ob_end_clean();

        wp_send_json_success(esc_url_raw($new_link));
    }


    // moved old settings to new settings
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
            // update_post_meta($form_id, 'wpform_gs_settings_new', $gsheet_new);
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
      value="<?php echo esc_attr( wp_create_nonce( 'wpform_gs_nonce' ) ); ?>"
    />
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
   public function execute_post_data() {

      // Capability check
    if ( ! current_user_can('manage_options') ) {
        wp_die('You do not have permission to perform this action.');
    }

    // Nonce check
    if ( ! isset($_POST['wpform_gs_nonce']) ||
         ! wp_verify_nonce($_POST['wpform_gs_nonce'], 'wpform_gs_nonce') ) {
        wp_die('Security check failed');
    }

    // Form ID
    $form_id = isset($_POST['form-id']) ? intval($_POST['form-id']) : 0;
    if ( ! $form_id ) return;

    // Sanitize array
    $raw_data = isset($_POST['wpform-gs']) ? (array) $_POST['wpform-gs'] : array();
    $gs_data  = array_map('sanitize_text_field', $raw_data);

    $sheet_name = $gs_data['sheet-name']     ?? '';
    $sheet_id   = $gs_data['sheet-id']       ?? '';
    $tab_name   = $gs_data['sheet-tab-name'] ?? '';
    $tab_id     = $gs_data['tab-id']         ?? '';

    $existing_data = get_post_meta($form_id, 'wpform_gs_settings', true);

    // Disconnect
    if ( ! empty($existing_data) && $sheet_name === '' ) {
        delete_post_meta($form_id, 'wpform_gs_settings');
        return;
    }

    // Save settings
    if ( $sheet_name !== '' && $tab_name !== '' ) {

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
public function get_forms_connected_to_sheet() { 
    global $wpdb;

    $sql = "
        SELECT p.ID, p.post_title, pm.meta_value
        FROM {$wpdb->posts} AS p
        INNER JOIN {$wpdb->postmeta} AS pm 
            ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
          AND p.post_type = %s
        ORDER BY p.ID
    ";

    return $wpdb->get_results(
        $wpdb->prepare($sql, 'wpform_gs_settings', 'wpforms')
    );
}


    /**
     * function to save the setting data of google sheet
     *
     * @since 1.0
     */
    public function add_integration()
    {

        $wpforms_manual_setting = get_option('wpforms_manual_setting');
        $Code = "";
        $header = "";
        if (isset($_GET['code']) && ($wpforms_manual_setting == 0)) {
            if (is_string($_GET['code'])) {
                $Code = sanitize_text_field($_GET["code"]);
            }
            update_option('is_new_client_secret_wpformsgsc', 1);
            $header = esc_url_raw(admin_url('admin.php?page=wpform-google-sheet-config'));
        }

        ?>

        <div class="main-promotion-box">
            <a href="#" class="close-link"></a>
            <div class="promotion-inner">
                <h2>
                    <?php echo esc_html__('A way to connect WordPress', 'gsheetconnector-wpforms'); ?><br />
                    <?php echo esc_html__('and', 'gsheetconnector-wpforms'); ?>
                    <span><?php echo esc_html__('Google Sheets Pro', 'gsheetconnector-wpforms'); ?></span>
                </h2>

                <p class="ratings">
                    <?php echo esc_html__('Ratings', 'gsheetconnector-wpforms'); ?> : <span></span>
                </p>

                <p>
                    <?php echo esc_html__('The Most Powerful Bridge Between WordPress  and', 'gsheetconnector-wpforms'); ?>
                    <strong><?php echo esc_html__('Google Sheets', 'gsheetconnector-wpforms'); ?></strong>, <br />
                    <?php echo esc_html__('Now available for popular', 'gsheetconnector-wpforms'); ?>
                    <strong><?php echo esc_html__('Contact Forms', 'gsheetconnector-wpforms'); ?></strong>,
                    <strong><?php echo esc_html__('Page Builder Forms', 'gsheetconnector-wpforms'); ?></strong>,<br />
                    <?php echo esc_html__('and', 'gsheetconnector-wpforms'); ?>
                    <strong><?php echo esc_html__('E-commerce', 'gsheetconnector-wpforms'); ?></strong>
                    <?php echo esc_html__('Platforms like ', 'gsheetconnector-wpforms'); ?>
                    <strong><?php echo esc_html__('WooCommerce', 'gsheetconnector-wpforms'); ?></strong><br />
                    <?php echo esc_html__('and', 'gsheetconnector-wpforms'); ?>
                    <strong><?php echo esc_html__('Easy Digital Downloads', 'gsheetconnector-wpforms'); ?></strong>
                    (<?php echo esc_html__('EDD', 'gsheetconnector-wpforms'); ?>).
                </p>

                <div class="button-bar">
                    <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank">
                        <?php echo esc_html__('Buy Now', 'gsheetconnector-wpforms'); ?>
                    </a>
                    <a href="https://demo.gsheetconnector.com/wpforms-google-sheet-connector-pro/" target="_blank">
                        <?php echo esc_html__('Check Demo', 'gsheetconnector-wpforms'); ?>
                    </a>
                </div>
            </div>
            <div class="gsheet-plugins"></div>
        </div>
        <!-- main-promotion-box #end -->

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var closeButton = document.querySelector('.close-link');
                var promotionBox = document.querySelector('.main-promotion-box');

                closeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    // Add URL to open in a new window
                    var url = 'https://www.gsheetconnector.com/'; // Replace 'https://example.com' with your desired URL
                    window.open(url, '_blank');

                    // Hide the promotion box
                    promotionBox.classList.add('hidden');

                    // Store the state of hiding
                    localStorage.setItem('isHidden', 'true');
                });

                // Check if the item is hidden in local storage
                var isHidden = localStorage.getItem('isHidden');
                if (isHidden === 'true') {
                    promotionBox.classList.add('hidden');
                }

                // Listen for page refresh events
                window.addEventListener('beforeunload', function () {
                    // Check if the box is hidden
                    var isHiddenNow = promotionBox.classList.contains('hidden');
                    // Store the state of hiding
                    localStorage.setItem('isHidden', isHiddenNow ? 'true' : 'false');
                });

                // Reset hiding state on page refresh
                window.addEventListener('load', function () {
                    localStorage.removeItem('isHidden');
                    promotionBox.classList.remove('hidden');
                });
            });

        </script>

        <div class="gsc-api-box">
            <h2><?php echo esc_html__('WPForms - Google Sheet Integration', 'gsheetconnector-wpforms'); ?></h2>
            <p><?php echo esc_html__('Choose your Google API Setting from the dropdown. You can select Use Existing Client/Secret Key (Auto Google API Configuration) or Use Manual Client/Secret Key (Use Your Google API Configuration - Pro Version) or Use Service Account (Recommended- Pro Version) . After saving, the related integration settings will appear, and you can complete the setup.', "gsheetconnector-wpforms"); ?>
            </p>
        </div>
        <div class="card-wpforms dropdownoption-wpforms row">

            <label
                for="gs_wpforms_dro_option"><?php echo esc_html__('Choose Google API Setting', 'gsheetconnector-wpforms'); ?></label>

            <div class="drop-down-select-btn">
                <select id="gs_wpforms_dro_option" name="gs_wpforms_dro_option">
                    <option value="wpforms_existing" selected>
                        <?php echo esc_html__('Use Existing Client/Secret Key (Auto Google API Configuration)', 'gsheetconnector-wpforms'); ?>
                    </option>
                    <option value="wpforms_manual" disabled="">
                        <?php echo esc_html__('Use Manual Client/Secret Key (Use Your Google API Configuration) (Upgrade To PRO)', 'gsheetconnector-wpforms'); ?>
                    </option>
                     <option value="wpforms_service" disabled="">
                       <?php echo esc_html__('Service Account (Recommended) (Upgrade To PRO)', 'gsheetconnector-wpforms'); ?>
                    </option>
                </select>
                <p class="int-meth-btn-wpforms">
                    <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank">
                        <input type="button" name="save-method-api-wpforms" id=""
                            value="<?php echo esc_attr__('Upgrade To PRO', 'gsheetconnector-wpforms'); ?>"
                            class="upgrad-btn" />
                    </a>
                </p>

            </div>
        </div>
        <div class="gs-parts-wpform">
            <div class="card-wp">
                <input type="hidden" name="redirect_auth_wpforms" id="redirect_auth_wpforms"
                    value="<?php echo isset($header) ? esc_attr($header) : ''; ?>">
                <span class="wpforms-setting-field log-setting">
                    <h2 class="title">
                        <?php echo esc_html__('Google Sheet Integration - Use Existing Client/Secret Key (Auto Google API Configuration)', 'gsheetconnector-wpforms'); ?>
                    </h2>
                    <p><?php echo esc_html__('Automatic integration allows you to connect WPForms with Google Sheets using built-in Google API configuration. By authorizing your Google account, the plugin will handle API setup and authentication automatically, enabling seamless form data sync. Learn more in the documentation'); ?>
                        <a href="https://www.gsheetconnector.com/docs/wpforms-gsheetconnector/integration-with-google-existing-method"
                            target="_blank"><?php echo esc_html__('click here', 'gsheetconnector-wpforms'); ?></a>.</p>

                    <?php if (empty(get_option('wpform_gs_token'))) { ?>
                        <div class="wpform-gs-alert-kk" id="google-drive-msg">
                            <p class="wpform-gs-alert-heading">
                                <?php echo esc_html__('Authenticate with your Google account, follow these steps:', 'gsheetconnector-wpforms'); ?>
                            </p>
                            <ol class="wpform-gs-alert-steps">
                                <li><?php echo esc_html__('Click on the "Sign In With Google" button.', 'gsheetconnector-wpforms'); ?>
                                </li>
                                <li><?php echo esc_html__('Grant permissions for the following:', 'gsheetconnector-wpforms'); ?>
                                </li>
                                <li><?php echo esc_html__('Google Drive', 'gsheetconnector-wpforms'); ?></li>
                                <li><?php echo esc_html__('Google Sheets', 'gsheetconnector-wpforms'); ?>
                                    <span>
                                        <?php echo esc_html__('* Ensure that you enable the checkbox for each of these services.', 'gsheetconnector-wpforms'); ?></span>
                                </li>
                                <li><?php echo esc_html__('This will allow the integration to access your Google Drive and Google Sheets.', 'gsheetconnector-wpforms'); ?>
                                </li>
                            </ol>
                        </div>
                    <?php } ?>

                    <div class="integration-box row">
                        <label><?php echo esc_html__('Google Access Code', 'gsheetconnector-wpforms'); ?></label>

                        <?php if (!empty(get_option('wpform_gs_token')) && get_option('wpform_gs_token') !== "") { ?>
                            <input type="text" name="google-access-code" id="wpforms-setting-google-access-code" value="" disabled
                                placeholder="<?php echo esc_attr__('Currently Active', 'gsheetconnector-wpforms'); ?>" />

                            <input type="button" name="wp-deactivate-log" id="wp-deactivate-log"
                                value="<?php echo esc_attr__('Deactivate', 'gsheetconnector-wpforms'); ?>"
                                class="button button-primary" />


                            <span class="loading-sign-deactive">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <?php } else {
                            $redirct_uri = admin_url('admin.php?page=wpform-google-sheet-config');
                            ?>


                            <input type="text" name="google-access-code" id="wpforms-setting-google-access-code"
                                value="<?php echo esc_attr($Code); ?>" disabled
                                placeholder="<?php echo esc_html__('Click Sign in with Google', 'gsheetconnector-wpforms'); ?>"
                                oncopy="return false;" onpaste="return false;" oncut="return false;" />

                            <!-- <a href="https://accounts.google.com/o/oauth2/auth?access_type=offline&approval_prompt=force&client_id=1075324102277-drjc21uouvq2d0l7hlgv3bmm67er90mc.apps.googleusercontent.com&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code&scope=https%3A%2F%2Fspreadsheets.google.com%2Ffeeds%2F+https://www.googleapis.com/auth/userinfo.email+https://www.googleapis.com/auth/drive.metadata.readonly" target="_blank" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey"><?php //echo __('Get Code', 'gsheetconnector-wpforms'); ?></a> -->


                            <?php if (empty($Code)) { ?>
                                <a
                                    href="<?php echo esc_url('https://oauth.gsheetconnector.com/index.php?client_admin_url=' . urlencode($redirct_uri) . '&plugin=woocommercegsheetconnector'); ?>">
                                    <img class="button_wpformgsc"
                                        src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL . 'assets/img/btn_google_signin_dark_pressed_web.gif'); ?>">
                                </a>
                            <?php } ?>
                        <?php } ?>
                        <!-- set nonce -->
                        <input type="hidden" name="gs-ajax-nonce" id="gs-ajax-nonce"
                            value="<?php echo esc_attr(wp_create_nonce('gs-ajax-nonce')); ?>" />

                        <?php if (!empty($_GET['code'])) { ?>
                            <input type="submit" name="save-gs"
                                class="wpforms-btn wpforms-btn-md wpforms-btn-orange blinking-button-wc" id="save-wpform-gs-code"
                                value="<?php echo esc_attr__('Click here to Save Authentication Code', 'gsheetconnector-wpforms'); ?>">

                        <?php } ?>
                        <span class="loading-sign">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    </div>
                    <?php
                    //resolved - google sheet permission issues - START
                    if (!empty(get_option('wpform_gs_verify')) && (get_option('wpform_gs_verify') == "invalid-auth")) {
                        ?>
                        <p style="color:#c80d0d; font-size: 14px; border: 1px solid;padding: 8px;">
                            <?php echo esc_html(__('Something went wrong! It looks you have not given the permission of Google Drive and Google Sheets from your google account.Please Deactivate Auth and Re-Authenticate again with the permissions.', 'gsheetconnector-wpforms')); ?>
                        </p>
                        <p style="color:#c80d0d;border: 1px solid;padding: 8px;">
                            <img width="350"
                                src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL . 'assets/img/permission_screen.png'); ?>">
                        </p>

                        <p style="color:#c80d0d; font-size: 14px; border: 1px solid;padding: 8px;">
                            <?php echo esc_html(__('Also,', 'gsheetconnector-wpforms')); ?><a
                                href="https://myaccount.google.com/permissions" target="_blank">
                                <?php echo esc_html(__('Click Here ', 'gsheetconnector-wpforms')); ?></a>
                            <?php echo esc_html(__('and if it displays "GSheetConnector for WP Contact Forms" under Third-party apps with account access then remove it.', 'gsheetconnector-wpforms')); ?>
                        </p>
                    <?php
                    }
                    //resolved - google sheet permission issues - END
                    else {
                        $wp_token = get_option('wpform_gs_token');
                        if (!empty($wp_token) && $wp_token !== "") {
                            $google_sheet = new wpfgsc_googlesheet();
                            $email_account = $google_sheet->gsheet_print_google_account_email();
                            if (!empty($email_account)) {
                                update_option('wpform_gs_auth_expired_free', 'false');
                                ?>
                                    <p class="connected-account-wpform row">
                                        <label>
                                            <?php
                                            // translators: %s is the connected email address.
                                            $raw_output = sprintf(
                                                __( 'Connected Email Account', 'gsheetconnector-wpforms' ),
                                                esc_html( $email_account )
                                            );
                                            echo wp_kses( $raw_output, array( 'u' => array() ) );
                                            ?>
                                        </label>

                                        <?php
                                        // translators: %s is the connected Google email account address.
                                        $raw_output12 = sprintf(
                                            esc_html__( '%s', 'gsheetconnector-wpforms' ),
                                            esc_html( $email_account )
                                        );
                                        echo wp_kses( $raw_output12, array( 'u' => array() ) );
                                        ?>
                                    </p>


                            <?php } else {
                                update_option('wpform_gs_auth_expired_free', 'true'); ?>
                                <p style="color:red">
                                    <?php echo esc_html(__('Something went wrong ! Your Auth Code may be wrong or expired. Please Deactivate AUTH and Re-Authenticate again. ', 'gsheetconnector-wpforms')); ?>
                                </p>
                            <?php
                            }
                        }
                    }
                    ?>



                    <div class="msg success-msg">
                        <i class="fa-solid fa-lock"></i>
                        <p> <?php echo esc_html__(' We do not store any of the data from your Google account on our servers, everything is processed & stored on your server. We take your privacy extremely seriously and ensure it is never misused.', 'gsheetconnector-wpforms'); ?>
                            <a href="https://gsheetconnector.com/usage-tracking/" target="_blank"
                                rel="noopener noreferrer"><?php echo esc_html__('Learn more.', 'gsheetconnector-wpforms'); ?></a>
                        </p>
                    </div>


                    <span class="wpforms-setting-field">
                        <label><?php echo esc_html__('Debug Log', 'gsheetconnector-wpforms'); ?></label>

                        <button class="wpgsc-logs"><?php echo esc_html__('View', 'gsheetconnector-wpforms'); ?></button>
                        <label>
                            <a class="debug-clear-kk"><?php echo esc_html__('Clear', 'gsheetconnector-wpforms'); ?></a>
                        </label>

                        <span class="clear-loading-sign">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <p id="gs-validation-message"></p>
                        <span id="deactivate-message"></span>
                    </span>

            </div>
        </div>
        <div class="wp-system-Error-logs">
            <button id="copy-logs-btn"
                onclick="copyLogs()"><?php echo esc_html__('Copy Logs', 'gsheetconnector-wpforms'); ?></button>
            <div class="wpdisplayLogs">
                <?php
                $wpexistDebugFile = get_option('wpf_gs_debug_log_file');
                // Check if the debug unique log file exists
                if (!empty($wpexistDebugFile) && file_exists($wpexistDebugFile)) {
                    $displaywpfreeLogs = nl2br(file_get_contents($wpexistDebugFile));
                    if (!empty($displaywpfreeLogs)) {
                        echo '<pre id="log-content">' . esc_html($displaywpfreeLogs) . '</pre>';
                    } else {
                        echo esc_html(__('No errors found.', 'gsheetconnector-wpforms'));
                    }
                } else {
                    // If no log file exists
                    echo esc_html(__('No log file exists as no errors are generated.', 'gsheetconnector-wpforms'));
                }
                ?>
            </div>
        </div>

        <script>
            function copyLogs() {
                // Get the log content from the element
                var logContent = document.getElementById('log-content').innerText;

                // Use the clipboard API to copy the log content
                navigator.clipboard.writeText(logContent).then(function () {
                    alert('Logs copied to clipboard!');
                }, function (err) {
                    alert('Failed to copy logs: ' + err);
                });
            }
        </script>

        <div class="two-col wpform-box-help12">
            <div class="col wpform-box12">
                <header>
                    <h3><?php echo esc_html__('Next steps…', 'gsheetconnector-wpforms'); ?></h3>
                </header>
                <div class="wpform-box-content12">
                    <ul class="wpform-list-icon12">
                        <li>
                            <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank">
                                <div>
                                    <button class="icon-button">
                                        <span class="dashicons dashicons-star-filled"></span>
                                    </button>
                                    <strong><?php echo esc_html__('Upgrade to PRO', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__(' Multiple Forms to Sheets, Custom mail tags and much more...', 'gsheetconnector-wpforms'); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/requirements" target="_blank">
                                <div>
                                    <button class="icon-button">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>
                                    <strong><?php echo esc_html__('Compatibility', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('Compatibility with WPForms Third-Party Plugins', 'gsheetconnector-wpforms'); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/plugin-settings-pro-version" target="_blank">
                                <div>
                                    <button class="icon-button">
                                        <span class="dashicons dashicons-chart-bar"></span>
                                    </button>
                                    <strong><?php echo esc_html__('Multi Languages', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('This plugin supports multi-languages as well!', 'gsheetconnector-wpforms'); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/plugin-settings-free-version"
                                target="_blank">
                                <div>
                                    <button class="icon-button">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>
                                    <strong><?php echo esc_html__('Support Wordpress multisites', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('With the use of a Multisite, you’ll also have a new level of user-available: the Super
                                Admin.', 'gsheetconnector-wpforms'); ?></p>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 2nd div -->
            <div class="col wpform-box13">
                <header>
                    <h3><?php echo esc_html__('Product Support', 'gsheetconnector-wpforms'); ?></h3>
                </header>
                <div class="wpform-box-content13">
                    <ul class="wpform-list-icon13">
                        <li>
                            <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms"
                                target="_blank">
                                <span class="dashicons dashicons-book"></span>
                                <div>
                                    <strong><?php echo esc_html__('Online Documentation', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('Understand all the capabilities of WPForms GsheetConnector', 'gsheetconnector-wpforms'); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.gsheetconnector.com/support" target="_blank">
                                <span class="dashicons dashicons-sos"></span>
                                <div>
                                    <strong><?php echo esc_html__('Ticket Support', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('Direct help from our qualified support team', 'gsheetconnector-wpforms'); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.gsheetconnector.com/affiliate-area" target="_blank">
                                <span class="dashicons dashicons-admin-links"></span>
                                <div>
                                    <strong><?php echo esc_html__('Affiliate Program', 'gsheetconnector-wpforms'); ?></strong>
                                    <p><?php echo esc_html__('Earn flat 30', 'gsheetconnector-wpforms'); ?>%
                                        <?php echo esc_html__('on every sale', 'gsheetconnector-wpforms'); ?>!</p>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
            // JavaScript/jQuery code
            jQuery(document).ready(function ($) {
                // Check if the account is connected
                var isAccountConnected =
                    <?php echo (!empty(get_option('wpform_gs_token')) && get_option('wpform_gs_token') !== "") ? 'true' : 'false'; ?>;

                // Toggle the visibility of the alert card
                if (isAccountConnected) {
                    $('.wpform-gs-alert-card').addClass('hidden');
                } else {
                    $('.wpform-gs-alert-card').removeClass('hidden');
                }
            });
        </script>
        <?php
    }

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
        <div class="wp-formSelect">
            <h3><?php echo esc_html__('Select Form', 'gsheetconnector-wpforms'); ?>
            </h3>
        </div>
        <div class="wp-select">
            <select id="wpforms_select" name="wpforms">
                <option value=""><?php echo esc_html__('Select Form', 'gsheetconnector-wpforms'); ?>
                </option>
                <?php foreach ($forms as $form) { ?>
                    <option value="<?php echo $form->ID; ?>"><?php echo $form->post_title; ?></option>
                <?php } ?>
            </select>
            <!-- <span class="loading-sign-select">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> -->
            <input type="hidden" name="wp-ajax-nonce" id="wp-ajax-nonce"
                value="<?php echo esc_attr(wp_create_nonce('wp-ajax-nonce')); ?>" />
        </div>
        <div class="wrap gs-form">
            <div class="wp-parts">

                <div class="card" id="wpform-gs">
                    <form method="post">
                        <h2 class="title">
                            <?php echo esc_html__('WPForms - Google Sheet Settings', 'gsheetconnector-wpforms'); ?>
                        </h2>


                        <p class="deprecated-notice">
                            <?php esc_html_e('This settings page is deprecated and moved old settings to new settings. Follow these below steps to import the old settings into the new settings.', 'gsheetconnector-wpforms'); ?>

                        </p>
                        <p class="old_settings_steps">1)
                            <?php echo esc_html__('Select the form which you want to connect with your spreadsheet.', 'gsheetconnector-wpforms'); ?>

                        </p>
                        <p>
                            <img src="<?php echo WPFORMS_GOOGLESHEET_URL; ?>assets/img/faq-screenshot1.png" class="alignnone">

                        </p>
                        <p class="old_settings_steps">
                            2) <?php esc_html_e('Then you can see your old settings here.', 'gsheetconnector-wpforms'); ?>

                        </p>
                        <p>
                            <img src="<?php echo WPFORMS_GOOGLESHEET_URL; ?>assets/img/new_settings_screenshot.png"
                                class="alignnone">

                        </p>

                        <div id="inside">

                        </div>
                    </form>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * AJAX function - deactivate activation
     * @since 1.0
     */
    public function deactivate_wpformgsc_integation()
    {
        // nonce check
        check_ajax_referer('gs-ajax-nonce', 'security');

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
     * Function - To send wpform data to google spreadsheet
     * @since 1.0
     */
    public function entry_save($fields, $entry, $form_id, $form_data = '')
    {
        $data = array();

        // Get Entry Id
        $entry_id = wpforms()->process->entry_id;

        // get form data
        $form_data_get = get_post_meta($form_id, 'wpform_gs_settings');

        $sheet_name = isset($form_data_get[0]['sheet-name']) ? $form_data_get[0]['sheet-name'] : "";

        $sheet_id = isset($form_data_get[0]['sheet-id']) ? $form_data_get[0]['sheet-id'] : "";

        $sheet_tab_name = isset($form_data_get[0]['sheet-tab-name']) ? $form_data_get[0]['sheet-tab-name'] : "";

        $tab_id = isset($form_data_get[0]['tab-id']) ? $form_data_get[0]['tab-id'] : "";

        $payment_type = array("payment-single", "payment-multiple", "payment-select", "payment-total");

        if ((!empty($sheet_name)) && (!empty($sheet_tab_name))) {
            try {
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

    public function display_upgrade_notice()
    {
        $get_notification_display_interval = get_option('wpforms_gs_upgrade_notice_interval');
        $close_notification_interval = get_option('wpforms_gs_close_upgrade_notice');

        if ($close_notification_interval === "off") {
            return;
        }

        if (!empty($get_notification_display_interval)) {
            $adds_interval_date_object = DateTime::createFromFormat("Y-m-d", $get_notification_display_interval);
            $notice_interval_timestamp = $adds_interval_date_object->getTimestamp();
        }

        if (empty($get_notification_display_interval) || current_time('timestamp') > $notice_interval_timestamp) {
            $ajax_nonce = wp_create_nonce("wpforms_gs_upgrade_ajax_nonce");
            $upgrade_text = '<div class="gs-adds-notice">';
            $upgrade_text .= '<span><b>GSheetConnector WPForms </b> ';
            $upgrade_text .= 'version 2.0 would required you to <a href="' . admin_url("admin.php?page=wpcf7-google-sheet-config") . '">reauthenticate</a> with your Google Account again due to update of Google API V3 to V4.<br/><br/>';
            $upgrade_text .= 'To avoid any loss of data redo the <a href="' . admin_url("admin.php?page=wpform-google-sheet-config&tab=settings") . '">Google Sheet Form Settings</a> of each WPForms again with required sheet and tab details.<br/><br/>';
            $upgrade_text .= 'Also set header names again with the same name as specified for each WPForms field label.<br/><br/>';
            $upgrade_text .= 'Example: "Comment or Message" label must be added similarly for Google Sheet header.</span>';
            $upgrade_text .= '<ul class="review-rating-list">';
            $upgrade_text .= '<li><a href="javascript:void(0);" class="wpforms_gs_upgrade" title="Done">Yes, I have done.</a></li>';
            $upgrade_text .= '<li><a href="javascript:void(0);" class="wpforms_gs_upgrade_later" title="Remind me later">Remind me later.</a></li>';
            $upgrade_text .= '</ul>';
            $upgrade_text .= '<input type="hidden" name="wpforms_gs_upgrade_ajax_nonce" id="wpforms_gs_upgrade_ajax_nonce" value="' . $ajax_nonce . '" />';
            $upgrade_text .= '</div>';

            $upgrade_block = Wpform_gs_Connector_Utility::instance()->admin_notice(array(
                'type' => 'upgrade',
                'message' => $upgrade_text
            ));
            echo wp_kses_post($upgrade_block);

        }
    }

    public function set_upgrade_notification_interval()
    {
        // check nonce
        check_ajax_referer('wpforms_gs_upgrade_ajax_nonce', 'security');
        $time_interval = gmdate('Y-m-d', strtotime('+10 day'));
        update_option('wpforms_gs_upgrade_notice_interval', $time_interval);
        wp_send_json_success();
    }

    public function close_upgrade_notification_interval()
    {
        // check nonce
        check_ajax_referer('wpforms_gs_upgrade_ajax_nonce', 'security');
        update_option('wpforms_gs_close_upgrade_notice', 'off');
        wp_send_json_success();
    }
}

$wpforms_service = new WPforms_Googlesheet_Services();