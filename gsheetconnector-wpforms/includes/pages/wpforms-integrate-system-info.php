<?php
/*  Exit if accessed directly */
if (! defined('ABSPATH')) {
  exit();
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/* Prevent Subscribers from seeing sensitive info */
if (! current_user_can('manage_options')) {
  wp_die(esc_html__('You do not have permission to access this page.', 'gsheetconnector-wpforms'));
}

$WpForms_gs_tools_service = new WPforms_Gsheet_Connector_Init();
?>
<div class="system-statuswc">
  <div class="info-container">
    <div class="heading mt-0">
      <?php esc_html_e('System Information', 'gsheetconnector-wpforms'); ?>
    </div>
    <p><?php echo esc_html__('View detailed information about your plugin, server, and WordPress setup for troubleshooting and support.', 'gsheetconnector-wpforms'); ?></p>

    <div class="text-right d-flex justify-end">
      <button id="gscwpff-free-system-copy" class="btn btn-primary d-flex align-center gap-10 copy">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="gsc-copy-icon">
          <rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"></rect>
          <rect x="2" y="2" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"></rect>
        </svg> <?php esc_html_e('Copy System Info', 'gsheetconnector-wpforms'); ?>
      </button>
       <div class="gsc-copy-msg d-none"></div>
    </div>
    <?php
    global $wpdb;

    /* Get WordPress version */
    $wp_version = get_bloginfo('version');

    /* Get theme info */
    $theme_data = wp_get_theme();
    $theme_name_version = $theme_data->get('Name') . ' ' . $theme_data->get('Version');
    $parent_theme = $theme_data->get('Template');

    if (!empty($parent_theme)) {
      $parent_theme_data = wp_get_theme($parent_theme);
      $parent_theme_name_version = $parent_theme_data->get('Name') . ' ' . $parent_theme_data->get('Version');
    } else {
      $parent_theme_name_version = 'N/A';
    }

    /* Check plugin version and subscription plan */
    $plugin_version = defined('WPFORMS_GOOGLESHEET_VERSION') ? WPFORMS_GOOGLESHEET_VERSION : 'N/A';
    $subscription_plan = 'FREE';

    $api_token_auto = get_option('wpform_gs_token');
    $api_token_manual = get_option('gs_wpforms_token_manual');
    $api_token_service = get_option('gs_wpformspro_service_account_json');
    $gs_wpforms_manual_setting = get_option('gs_wpforms_manual_setting');

    $selected_method = '';
    if ($gs_wpforms_manual_setting == 0) {
        $selected_method = esc_html__('Authenticated Using Existing Method', 'gsheetconnector-wpforms');
    } elseif ($gs_wpforms_manual_setting == 1) {
        $selected_method = esc_html__('Manual Client/Secret Key (Use Your Google API Configuration)', 'gsheetconnector-wpforms');
    } elseif ($gs_wpforms_manual_setting == 2) {
        $selected_method = esc_html__('Service Account (Recommended)', 'gsheetconnector-wpforms');
    }

    if (!empty($api_token_auto) && $gs_wpforms_manual_setting == 0) {
        /*  The user is authenticated through the auto method */
        $google_sheet_auto = new WPFGSC_googlesheet();

        $email_account_auto = $google_sheet_auto->gsheet_print_google_account_email();
        $connected_email = !empty($email_account_auto) ? esc_html($email_account_auto) : 'Not Connected';
    } elseif (!empty($api_token_manual) && $gs_wpforms_manual_setting == 1) {
        /* The user is authenticated through the manual method */
        /*  You can use the manual method to retrieve the email */
        $google_sheet = new WPFGSC_googlesheet();
        $email_account_manual = $google_sheet->gsheet_print_google_account_email_manual();
        $connected_email = !empty($email_account_manual) ? esc_html($email_account_manual) : 'Not Connected';
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

     /*  Check Google Permission */
      $gs_verify_status = get_option('wpform_gs_verify');
      $search_permission = ($gs_verify_status === 'valid') ? 'Granted' : 'Denied';

    /* Check Google Permission */
    $gs_verify_status = get_option('wpform_gs_verify');
    $search_permission = ($gs_verify_status === 'valid') ? 'Granted' : 'Denied';

    /* Create the system info HTML */
    $system_info = '<div class="system-statuswc">';
    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-info-button" class="info-button">GSheetConnector Status<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none" >';
    /*$system_info .= '<h3>GSheetConnector</h3>'; */
    $system_info .= '<table>';
    $system_info .= '<tr>
            <td>Plugin Name</td>
            <td class="fw-600 common-badge-table info-name-blue">GSheetConnector For WPForms</td>
          </tr>';
    $system_info .= '<tr>
            <td>Plugin Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($plugin_version) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Plugin Subscription Plan</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($subscription_plan) . '</td>
          </tr>';

    $system_info .= '<tr><td>Integration Method</td><td class="fw-600">' . esc_html($selected_method) . '</td></tr>';
    $system_info .= '<tr>
            <td>Connected Email Account</td>
            <td class="fw-600">' . $connected_email . '</td>
          </tr>';

    /* $permission_class = ($search_permission === 'Granted') ? 'permission-given' : 'permission-not-given'; */

    $permission_class = ($search_permission === 'Granted') ? 'permission-given' : 'permission-not-given';


    /* Only show if manual setting is not service account mode */
    if (isset($gs_wpforms_manual_setting) && (int) $gs_wpforms_manual_setting !== 2) {
      $system_info .= '<tr>
              <td>Google Drive Permission</td>
              <td class="' . $permission_class . '">' . esc_html($search_permission) . '</td>
            </tr>';


      $system_info .= '<tr>
              <td>' . esc_html__('Google Sheet Permission', 'gsheetconnector-wpforms') . '</td>
              <td class="fw-700 permission-badge ' . esc_attr($permission_class) . '">
                ' . esc_html($search_permission) . '
              </td>
            </tr>';
    }

    $system_info .= '</table>';
    $system_info .= '</div>';
    /* Add WordPress info
     Create a button for WordPress info */
    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-wordpress-info-button" class="info-button">WordPress Info<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="wordpress-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none" >';
    /* $system_info .= '<h3>WordPress Info</h3>'; */
    $system_info .= '<table>';

    $system_info .= '<tr>
            <td>Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html(get_bloginfo('version')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Site Language</td>
            <td class="fw-600">' . esc_html(get_bloginfo('language')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Debug Mode</td>
            <td class="fw-600 common-badge-table info-name-yellow">' . (WP_DEBUG ? 'Enabled' : 'Disabled') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Home URL</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_url(get_home_url()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Site URL</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_url(get_site_url()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Permalink structure</td>
            <td class="fw-600">' . esc_html(get_option('permalink_structure')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Is this site using HTTPS?</td>
            <td class="fw-600">' . (is_ssl() ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Is this a multisite?</td>
            <td class="fw-600">' . (is_multisite() ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Can anyone register on this site?</td>
            <td class="fw-600">' . (get_option('users_can_register') ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Is this site discouraging search engines?</td>
            <td class="fw-600">' . (get_option('blog_public') ? 'No' : 'Yes') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Default comment status</td>
            <td class="fw-600">' . esc_html(get_option('default_comment_status')) . '</td>
          </tr>';

    /*  Validate and sanitize $_SERVER['REMOTE_ADDR'] */
    $server_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $environment_type = ($server_ip === '127.0.0.1' || $server_ip === '::1') ? 'localhost' : 'production';
    $system_info .= '<tr>
            <td>Environment type</td>
            <td class="fw-600 common-badge-table info-name-yellow">' . esc_html($environment_type) . '</td>
          </tr>';

    /*  User count */
    $user_count = count_users();
    $total_users = $user_count['total_users'];
    $system_info .= '<tr>
            <td>User Count</td>
            <td class="fw-600">' . esc_html($total_users) . '</td>
          </tr>';

    /* Safe fallback for blog_publicize option */
    $system_info .= '<tr>
            <td>Communication with WordPress.org</td>
            <td class="fw-600">' . (get_option('blog_publicize') ? 'Yes' : 'No') . '</td>
          </tr>';

    /* Validate and sanitize $_SERVER['SERVER_SOFTWARE'] */
    $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unavailable';
    $system_info .= '<tr>
            <td>Web Server</td>
            <td class="fw-600">' . esc_html($server_software) . '</td>
          </tr>';

    $system_info .= '</table>';
    $system_info .= '</div>';

    /*  info about active theme */
    $active_theme = wp_get_theme();

    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-active-info-button" class="info-button">Active Theme<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="active-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
    /* $system_info .= '<h3>Active Theme</h3>'; */
    $system_info .= '<table>';
    $system_info .= '<tr>
            <td>Name</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($active_theme->get('Name')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($active_theme->get('Version')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Author</td>
            <td class="fw-600">' . esc_html(wp_strip_all_tags($active_theme->get('Author'))) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Author website</td>
            <td class="fw-600">' . esc_html($active_theme->get('AuthorURI')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Theme directory location</td>
            <td class="fw-600">' . esc_html($active_theme->get_template_directory()) . '</td>
          </tr>';
    $system_info .= '</table>';
    $system_info .= '</div>';

    /* Get a list of other plugins you want to check compatibility with */
    $other_plugins = array(
      'plugin-folder/plugin-file.php', /* Replace with the actual plugin slug */
      /* Add more plugins as needed */
    );

    /* Network Active Plugins */
    if (is_multisite()) {
      $network_active_plugins = get_site_option('active_sitewide_plugins', array());
      if (!empty($network_active_plugins)) {
        $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-netplug-info-button" class="info-button">Network Active plugins<span class="dashicons dashicons-arrow-down"></span></div>';
        $system_info .= '<div id="netplug-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
        /* $system_info .= '<h3>Network Active plugins</h3>'; */
        $system_info .= '<table>';
        foreach ($network_active_plugins as $plugin => $plugin_data) {
          $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
          $system_info .= '<tr>
            <td>' . esc_html($plugin_data['Name']) . '</td>
            <td>' . esc_html($plugin_data['Version']) . '</td>
          </tr>';
        }
        /*  Add more network active plugin statuses here... */
        $system_info .= '</table>';
        $system_info .= '</div>';
      }
    }
    /*  Active plugins */
    $active_plugins = get_option('active_plugins', array());
    $total_active_plugins = is_array($active_plugins) ? count($active_plugins) : 0;

    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-acplug-info-button" class="info-button">'
      . esc_html__('Active Plugins', 'gsheetconnector-wpforms') .
      ' (' . esc_html($total_active_plugins) . ')<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="acplug-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
    /* $system_info .= '<h3>Active plugins</h3>'; */
    $system_info .= '<table>';

    /* Retrieve all active plugins data */
    $active_plugins_data = array();
    $active_plugins = get_option('active_plugins', array());
    foreach ($active_plugins as $plugin) {
      $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
      $active_plugins_data[$plugin] = array(
        'name' => $plugin_data['Name'],
        'version' => $plugin_data['Version'],
        'count' => 0,
      );
    }

    /*  Count the number of active installations for each plugin */
    $all_plugins = get_plugins();
    foreach ($all_plugins as $plugin_file => $plugin_data) {
      if (array_key_exists($plugin_file, $active_plugins_data)) {
        $active_plugins_data[$plugin_file]['count']++;
      }
    }

    /*  Sort plugins based on the number of active installations (descending order) */
    uasort($active_plugins_data, function ($a, $b) {
      return $b['count'] - $a['count'];
    });

    /* Display the top 5 most used plugins */
    $counter = 0;
    foreach ($active_plugins_data as $plugin_data) {
      $system_info .= '<tr>
            <td>' . esc_html($plugin_data['name']) . '</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($plugin_data['version']) . '</td>
          </tr>';
    }
    $system_info .= '</table>';
    $system_info .= '</div>';
    /* Webserver Configuration
     Load WP_Filesystem for file permission check */
    if (!function_exists('WP_Filesystem')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    global $wp_filesystem;
    WP_Filesystem();

    /*  Check .htaccess writable status using WP_Filesystem */
    $htaccess_path = ABSPATH . '.htaccess';
    $htaccess_writable = $wp_filesystem->is_writable($htaccess_path) ? 'Writable' : 'Non Writable';

    /* Get current server time using gmdate() (timezone-safe) */
    $current_server_time = gmdate('Y-m-d H:i:s');

    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-server-info-button" class="info-button">Server<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="server-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none" >';
    /* $system_info .= '<h3>Server</h3>'; */
    $system_info .= '<table>';
    $system_info .= '<p>The options shown below relate to your server setup. If changes are required, you may need your web host’s assistance.</p>';

    $system_info .= '<tr>
            <td>Server Architecture</td>
            <td class="fw-600">' . esc_html(php_uname('s')) . '</td>
          </tr>';
    $web_server = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unavailable';
    $system_info .= '<tr>
            <td>Web Server</td>
            <td class="fw-600">' . esc_html($web_server) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html(phpversion()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP SAPI</td>
            <td class="fw-600">' . esc_html(php_sapi_name()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP Max Input Variables</td>
            <td class="fw-600">' . esc_html(ini_get('max_input_vars')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP Time Limit</td>
            <td class="fw-600">' . esc_html(ini_get('max_execution_time')) . ' seconds</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP Memory Limit</td>
            <td class="fw-600">' . esc_html(ini_get('memory_limit')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Max Input Time</td>
            <td class="fw-600">' . esc_html(ini_get('max_input_time')) . ' seconds</td>
          </tr>';
    $system_info .= '<tr>
            <td>Upload Max Filesize</td>
            <td class="fw-600">' . esc_html(ini_get('upload_max_filesize')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>PHP Post Max Size</td>
            <td class="fw-600">' . esc_html(ini_get('post_max_size')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>cURL Version</td>
            <td class="fw-600">' . esc_html(curl_version()['version']) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Is SUHOSIN Installed?</td>
            <td class="fw-600">' . (extension_loaded('suhosin') ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Is the Imagick Library Available?</td>
            <td class="fw-600">' . (extension_loaded('imagick') ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Are Pretty Permalinks Supported?</td>
            <td class="fw-600">' . (get_option('permalink_structure') ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>.htaccess Rules</td>
            <td class="fw-600">' . esc_html($htaccess_writable) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Current Time</td>
            <td class="fw-600">' . esc_html(current_time('mysql')) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Current UTC Time</td>
            <td class="fw-600">' . esc_html(current_time('mysql', true)) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Current Server Time</td>
            <td class="fw-600">' . esc_html($current_server_time) . '</td>
          </tr>';

    $system_info .= '</table>';
    $system_info .= '</div>';


    /* Database Configuration */
    $system_info .= '<div><button id="gscwpff-show-database-info-button" class="info-button">Database<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="database-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
    /* $system_info .= '<h3>Database</h3>'; */
    $system_info .= '<table>';

    $database_extension = 'mysqli';

    /* Cached queries to avoid PHPCS warnings */
    $database_server_version = wp_cache_get('gs_db_server_version', 'gsc');
    if (false === $database_server_version) {
      /*  phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe, read-only server info; wp_cache_get()/set() above already cache this. */
      $database_server_version = $wpdb->get_var("SELECT VERSION() as version");
      wp_cache_set('gs_db_server_version', $database_server_version, 'gsc', 3600);
    }

    $max_allowed_packet_size = wp_cache_get('gs_max_allowed_packet', 'gsc');
    if (false === $max_allowed_packet_size) {
      /*  phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe, used for diagnostics only; wp_cache_get()/set() above already cache this. */
      $max_allowed_packet_size = $wpdb->get_var("SHOW VARIABLES LIKE 'max_allowed_packet'");
      wp_cache_set('gs_max_allowed_packet', $max_allowed_packet_size, 'gsc', 3600);
    }

    $max_connections_number = wp_cache_get('gs_max_connections', 'gsc');
    if (false === $max_connections_number) {
      /*  phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe, used for diagnostics only; wp_cache_get()/set() above already cache this. */
      $max_connections_number = $wpdb->get_var("SHOW VARIABLES LIKE 'max_connections'");
      wp_cache_set('gs_max_connections', $max_connections_number, 'gsc', 3600);
    }

    $database_client_version = $wpdb->db_version();
    $database_username = DB_USER;
    $database_host = DB_HOST;
    $database_name = DB_NAME;
    $table_prefix = $wpdb->prefix;
    $database_charset = $wpdb->charset;
    $database_collation = $wpdb->collate;

    $system_info .= '<tr>
            <td>Extension</td>
            <td class="fw-600">' . esc_html($database_extension) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Server Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($database_server_version) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Client Version</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($database_client_version) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Database Username</td>
            <td class="fw-600">' . esc_html($database_username) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Database Host</td>
            <td class="fw-600 common-badge-table info-name-yellow">' . esc_html($database_host) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Database Name</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html($database_name) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Table Prefix</td>
            <td class="fw-600">' . esc_html($table_prefix) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Database Charset</td>
            <td class="fw-600">' . esc_html($database_charset) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Database Collation</td>
            <td class="fw-600">' . esc_html($database_collation) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Max Allowed Packet Size</td>
            <td class="fw-600">' . esc_html($max_allowed_packet_size) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>Max Connections Number</td>
            <td class="fw-600">' . esc_html($max_connections_number) . '</td>
          </tr>';
    $system_info .= '</table>';
    $system_info .= '</div>';

    /* wordpress constants */
    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-wrcons-info-button" class="info-button">WordPress Constants<span class="dashicons dashicons-arrow-down"></span></div>';
    $system_info .= '<div id="wrcons-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
    /* $system_info .= '<h3>WordPress Constants</h3>'; */
    $system_info .= '<table>';
    /*  Add WordPress Constants information */
    $system_info .= '<tr>
            <td>ABSPATH</td>
            <td class="fw-600">' . esc_html(ABSPATH) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_HOME</td>
            <td class="fw-600 common-badge-table info-name-blue">' . esc_html(home_url()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_SITEURL</td>
            <td class="fw-600">' . esc_html(site_url()) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_CONTENT_DIR</td>
            <td class="fw-600">' . esc_html(WP_CONTENT_DIR) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_PLUGIN_DIR</td>
            <td class="fw-600">' . esc_html(WP_PLUGIN_DIR) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_MEMORY_LIMIT</td>
            <td class="fw-600">' . esc_html(WP_MEMORY_LIMIT) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_MAX_MEMORY_LIMIT</td>
            <td class="fw-600">' . esc_html(WP_MAX_MEMORY_LIMIT) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_DEBUG</td>
            <td class="fw-600">' . (defined('WP_DEBUG') && WP_DEBUG ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_DEBUG_DISPLAY</td>
            <td class="fw-600">' . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>SCRIPT_DEBUG</td>
            <td class="fw-600">' . (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>WP_CACHE</td>
            <td class="fw-600">' . (defined('WP_CACHE') && WP_CACHE ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>CONCATENATE_SCRIPTS</td>
            <td class="fw-600">' . (defined('CONCATENATE_SCRIPTS') && CONCATENATE_SCRIPTS ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>COMPRESS_SCRIPTS</td>
            <td class="fw-600">' . (defined('COMPRESS_SCRIPTS') && COMPRESS_SCRIPTS ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>COMPRESS_CSS</td>
            <td class="fw-600">' . (defined('COMPRESS_CSS') && COMPRESS_CSS ? 'Yes' : 'No') . '</td>
          </tr>';
    /*  Manually define the environment type (example values: 'development', 'staging', 'production') */

    $environment_type = 'development';

    /*  Display the environment type */
    $system_info .= '<tr>
            <td>WP_ENVIRONMENT_TYPE</td>
            <td class="fw-600">' . esc_html($environment_type) . '</td>
          </tr>';

    $system_info .= '<tr>
            <td>WP_DEVELOPMENT_MODE</td>
            <td class="fw-600">' . (defined('WP_DEVELOPMENT_MODE') && WP_DEVELOPMENT_MODE ? 'Yes' : 'No') . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>DB_CHARSET</td>
            <td class="fw-600">' . esc_html(DB_CHARSET) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>DB_COLLATE</td>
            <td class="fw-600">' . esc_html(DB_COLLATE) . '</td>
          </tr>';

    $system_info .= '</table>';
    $system_info .= '</div>';

    /*  Filesystem Permission */
    /*  Load WP_Filesystem if not already available */
    if (!function_exists('WP_Filesystem')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    global $wp_filesystem;
    WP_Filesystem();

    /*  Get directory paths */
    $upload_dir = wp_upload_dir()['basedir'];
    $theme_root = get_theme_root();

    /*  Check writability using WP_Filesystem */
    $main_dir_writable = $wp_filesystem->is_writable(ABSPATH) ? 'Writable' : 'Not Writable';
    $wp_content_writable = $wp_filesystem->is_writable(WP_CONTENT_DIR) ? 'Writable' : 'Not Writable';
    $upload_dir_writable = $wp_filesystem->is_writable($upload_dir) ? 'Writable' : 'Not Writable';
    $plugin_dir_writable = $wp_filesystem->is_writable(WP_PLUGIN_DIR) ? 'Writable' : 'Not Writable';
    $theme_dir_writable = $wp_filesystem->is_writable($theme_root) ? 'Writable' : 'Not Writable';

    $system_info .= '<div class="mb-20 mt-20"><button id="gscwpff-show-ftps-info-button" class="info-button">Filesystem Permission <span class="dashicons dashicons-arrow-down"></span></button></div>';
    $system_info .= '<div id="ftps-info-container" class="info-content shadow-box pt-20 pb-20 pl-30 pr-30 d-none">';
    /* $system_info .= '<h3>Filesystem Permission</h3>'; */
    $system_info .= '<p>Shows whether WordPress is able to write to the directories it needs access to.</p>';
    $system_info .= '<table>';

    $system_info .= '<tr>
            <td>The main WordPress directory</td>
            <td>' . esc_html(ABSPATH) . '</td>
            <td class="fw-600">' . esc_html($main_dir_writable) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>The wp-content directory</td>
            <td>' . esc_html(WP_CONTENT_DIR) . '</td>
            <td class="fw-600">' . esc_html($wp_content_writable) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>The uploads directory</td>
            <td>' . esc_html($upload_dir) . '</td>
            <td class="fw-600">' . esc_html($upload_dir_writable) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>The plugins directory</td>
            <td>' . esc_html(WP_PLUGIN_DIR) . '</td>
            <td class="fw-600">' . esc_html($plugin_dir_writable) . '</td>
          </tr>';
    $system_info .= '<tr>
            <td>The themes directory</td>
            <td>' . esc_html($theme_root) . '</td>
            <td class="fw-600">' . esc_html($theme_dir_writable) . '</td>
          </tr>';

    $system_info .= '</table>';
    $system_info .= '</div>';
    $system_info .= '</div>';

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $system_info is assembled above from static markup with every dynamic value already wrapped in esc_html()/esc_attr() at concatenation time; escaping the aggregated string here would double-encode it and break the HTML.
    echo  $system_info;
    ?>

    <!-- Error Log start -->
    <div class="system-error shadow-box mt-40 p-30">
      <div class="error-container">
        <div class="error-log-head flex-wrap gap-20">
          <div class="heading mt-0 mb-0"><?php esc_html_e('Debug Log', 'gsheetconnector-wpforms'); ?></div>
            <?php 
             $debug_log_file = WP_CONTENT_DIR . '/debug.log';
              if ( file_exists( $debug_log_file ) && filesize( $debug_log_file ) > 0 ) { 
            ?>
            <div class="errorlog-button-list">

              <span class="gscwpff-clear-loading-sign-logs"></span>
              <button type="button" class="button btn-logs gscwpff-clear-content-logs"><?php esc_html_e('Clear Logs', 'gsheetconnector-wpforms'); ?></button>

              <button type="button" class="button button-primary" id="gscwpff-csv-info"><?php esc_html_e('Download CSV', 'gsheetconnector-wpforms'); ?></button>

              <button type="button" class="button btn-logs" id="gscwpff-copy-logs-info"><?php esc_html_e('Copy Logs', 'gsheetconnector-wpforms'); ?></button>
              <div class="gsc-copy-msg d-none">Copied Successfully </div>

            </div>
            <?php } ?>
        </div>

      </div>


      <input type="hidden" name="gscwpff-ajax-nonce" id="gscwpff-ajax-nonce"
        value="<?php echo esc_attr(wp_create_nonce('gscwpff-ajax-nonce')); ?>" />
      <div class="gsc-copy-msg d-none">
        <?php esc_html_e('copied successfully', 'gsheetconnector-wpforms'); ?>
      </div>
      <?php
      $debug_log_file = WP_CONTENT_DIR . '/debug.log';

      if (!file_exists($debug_log_file)) {
        echo '<p>Debug log file not found.</p>';
        return;
      }

      $log_lines = file($debug_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

      /*  Reverse and limit to last 100 */
      $log_lines = array_slice(array_reverse($log_lines), 0, 100);
      ?>
      <div style="max-height:500px; overflow:auto;">
        <table class="widefat striped mt-30">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Message</th>
              <th>File</th>
            </tr>
          </thead>
          <tbody>
            <?php
             $has_logs = false;
            foreach ($log_lines as $line) {

              if (preg_match('/\[(.*?)\]\s(.*?):\s(.*)/', $line, $matches)) {

                $has_logs = true;

                $date = str_replace(' UTC', '', $matches[1]);
                $type = str_replace('PHP ', '', $matches[2]);
                $message = $matches[3];

                $file = '-';
                if (preg_match('/in (.*?) on line/', $message, $file_match)) {
                  $file = $file_match[1];
                }
            ?>
                <tr>
                  <td><?php echo esc_html($date); ?> </td>
                  <td><?php echo esc_html($type); ?></td>
                  <td><?php echo esc_html($message); ?></td>
                  <td><?php echo esc_html($file); ?></td>
                </tr>
            <?php
              }
            }
           
              if ( ! $has_logs ) : ?>
                  <tr>
                      <td colspan="4" class="text-center">
                          <?php echo esc_html__( 'No logs found', 'gsheetconnector-gravityforms-pro' ); ?>
                      </td>
                  </tr>
              <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- Error Log end -->
  </div>
</div>