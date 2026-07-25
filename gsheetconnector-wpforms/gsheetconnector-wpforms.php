<?php

/**
 * Plugin Name:       GSheetConnector For WPForms
 * Plugin URI:        https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro
 * Description:       Send your WPForms data to your Google Sheets spreadsheet.
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            GSheetConnector
 * Author URI:        https://www.gsheetconnector.com/
 * Version:           4.1.0
 * Text Domain:       gsheetconnector-wpforms
 * License:           GPLv2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 */

/* Exit if accessed directly. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// ============================================================
// SECTION 1: CONSTANTS & BOOTSTRAP
// ============================================================

define( 'WPFORMS_GOOGLESHEET_VERSION',      '4.1.0' );
define( 'WPFORMS_GOOGLESHEET_DB_VERSION',   '4.1.0' );
define( 'WPFORMS_GOOGLESHEET_ROOT',         dirname( __FILE__ ) );
define( 'WPFORMS_GOOGLESHEET_URL',          plugins_url( '/', __FILE__ ) );
define( 'WPFORMS_GOOGLESHEET_BASE_FILE',    basename( dirname( __FILE__ ) ) . '/gsheetconnector-wpforms.php' );
define( 'WPFORMS_GOOGLESHEET_BASE_NAME',    plugin_basename( __FILE__ ) );
define( 'WPFORMS_GOOGLESHEET_PATH',         plugin_dir_path( __FILE__ ) );
define( 'WPFORMS_GOOGLESHEET_PRODUCT_NAME', 'Wpforms Google Sheet Connector' );
define( 'WPFORMS_GOOGLESHEET_API_URL',      'https://oauth.gsheetconnector.com/api-cred.php' );
define( 'WPFORMS_GOOGLESHEET_CURRENT_THEME', get_stylesheet_directory() );

/* Suppress "doing it wrong" notices from WP core during plugin init. */
add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

/** add languages */
// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
load_plugin_textdomain(
    'gsheetconnector-wpforms',
    false,
    basename( dirname( __FILE__ ) ) . '/languages'
);

// ============================================================
// SECTION 2: WPFORMS DEPENDENCY CHECK
// Sets $activate_the_plugin = true if WPForms is available
// in any supported install scenario.
// ============================================================

global $gsheetconnector_wpforms_activate_the_plugin;
$gsheetconnector_wpforms_activate_the_plugin = false;

/* Slugs for all supported WPForms editions. */
$gsheetconnector_wpforms_lite_check = 'wpforms-lite/wpforms.php';
$gsheetconnector_wpforms_free_check = 'wpforms/wpforms.php';
$gsheetconnector_wpforms_pro_check  = 'wpforms-pro/wpforms.php';

$gsheetconnector_wpforms_current_site_id = get_current_blog_id();

if ( is_multisite() ) {

	/* Case 1 – Per-site activation on a multisite network. */
	if ( ! empty( $gsheetconnector_wpforms_current_site_id ) ) {
		$gsheetconnector_wpforms_active_plugins = gscwpff_get_activated_plugins_for_site( $gsheetconnector_wpforms_current_site_id );

		if (
			in_array( $gsheetconnector_wpforms_lite_check, $gsheetconnector_wpforms_active_plugins ) ||
			in_array( $gsheetconnector_wpforms_free_check, $gsheetconnector_wpforms_active_plugins ) ||
			in_array( $gsheetconnector_wpforms_pro_check,  $gsheetconnector_wpforms_active_plugins )
		) {
			$gsheetconnector_wpforms_activate_the_plugin = true;
		}
	}

	/* Case 2 – Network-wide (sitewide) activation. */
	$gsheetconnector_wpforms_network_plugins = get_site_option( 'active_sitewide_plugins', [] );

	if (
		array_key_exists( $gsheetconnector_wpforms_lite_check, $gsheetconnector_wpforms_network_plugins ) ||
		array_key_exists( $gsheetconnector_wpforms_free_check, $gsheetconnector_wpforms_network_plugins ) ||
		array_key_exists( $gsheetconnector_wpforms_pro_check,  $gsheetconnector_wpforms_network_plugins )
	) {
		$gsheetconnector_wpforms_activate_the_plugin = true;
	}
} else {

	/* Case 3 – Standard single-site install. */
	$gsheetconnector_wpforms_active_plugins = get_option( 'active_plugins', [] );

	if (
		in_array( $gsheetconnector_wpforms_lite_check, $gsheetconnector_wpforms_active_plugins ) ||
		in_array( $gsheetconnector_wpforms_free_check, $gsheetconnector_wpforms_active_plugins ) ||
		in_array( $gsheetconnector_wpforms_pro_check,  $gsheetconnector_wpforms_active_plugins )
	) {
		$gsheetconnector_wpforms_activate_the_plugin = true;
	}
}

// ============================================================
// SECTION 3: HELPER FUNCTIONS
// ============================================================

/**
 * Returns the list of active plugins for a specific site on a
 * multisite network.
 *
 * @param  int   $site_id  Blog / site ID.
 * @return array           Array of active plugin slugs.
 */
if ( ! function_exists( 'gscwpff_get_activated_plugins_for_site' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function gscwpff_get_activated_plugins_for_site( $site_id ) {
		switch_to_blog( $site_id );
		$activated_plugins = get_option( 'active_plugins', [] );
		restore_current_blog();
		return $activated_plugins;
	}
}

// ============================================================
// SECTION 4: FREEMIUS SDK INITIALISATION
// Only loaded when WPForms is confirmed active.
// ============================================================

if ( $gsheetconnector_wpforms_activate_the_plugin && ! function_exists( 'gw_fs' ) ) {

	/**
	 * Returns (and lazily initialises) the Freemius SDK instance.
	 *
	 * @return Freemius
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function gw_fs() {
		global $gw_fs;

		if ( ! isset( $gw_fs ) ) {

			/* Enable multisite network integration. */
			if ( ! defined( 'WP_FS__PRODUCT_17697_MULTISITE' ) ) {
				define( 'WP_FS__PRODUCT_17697_MULTISITE', true );
			}

			/* Load the Freemius SDK. */
			require_once dirname( __FILE__ ) . '/lib/vendor/freemius/start.php';

			$gw_fs = fs_dynamic_init( array(
				'id'             => '17697',
				'slug'           => 'gsheetconnector-wpforms',
				'type'           => 'plugin',
				'public_key'     => 'pk_6ebee3f42b58df138fbf6914afb87',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => false,
				'menu'           => array(
					'slug'       => 'wpform-google-sheet-config',
					'first-path' => 'admin.php?page=wpform-google-sheet-config',
					'account'    => false,
					'contact'    => false,
					'support'    => false,
				),
			) );
		}

		return $gw_fs;
	}

	gw_fs();
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	do_action( 'gw_fs_loaded' );
}

// ============================================================
// SECTION 5: EARLY INCLUDES
// Classes that must be available before the main class boots.
// ============================================================

if ( ! class_exists( 'Wpform_gs_Connector_Utility' ) ) {
	include WPFORMS_GOOGLESHEET_ROOT . '/includes/class-wpform-utility.php';
}

if ( ! class_exists( 'Wpform_gs_Connector_role_setting' ) ) {
	include WPFORMS_GOOGLESHEET_ROOT . '/includes/pages/gs-wpform-role-setting.php';
}

// ============================================================
// SECTION 6: WPFORMS-LOADED CALLBACK
// Heavy integration files are deferred until WPForms itself is
// fully initialised, so all WPForms APIs are available.
// ============================================================

/**
 * Loads Google Sheets integration classes after WPForms is ready.
 *
 * Hooked to: wpforms_loaded
 */
function wpforms_Googlesheet_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpforms-integration.php';
	include_once WPFORMS_GOOGLESHEET_ROOT . '/lib/google-sheets.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/wpforms-panel.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpformdb.php';
}
add_action( 'wpforms_loaded', 'wpforms_Googlesheet_integration' );

// ============================================================
// SECTION 7: MAIN PLUGIN CLASS
// ============================================================

class WPforms_Gsheet_Connector_Init {

	// --------------------------------------------------------
	// 7.1  CONSTRUCTOR – register all hooks
	// --------------------------------------------------------

	public function __construct() {

		/* Lifecycle hooks. */
		register_activation_hook(   __FILE__, array( $this, 'wpform_gs_connector_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'wpform_gs_connector_deactivate' ) );
		register_uninstall_hook(    __FILE__, array( 'WPforms_Gsheet_Connector_Init', 'wpform_gs_connector_uninstall' ) );

		/* Admin init tasks. */
		add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );
		add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
		add_action( 'admin_init', array( $this, 'redirect_after_upgrade' ), 999 );		

		/* Admin UI. */
		add_action( 'admin_menu',       array( $this, 'register_wpform_menu_pages' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_wpform_gs_connector_summary_widget' ) );

		/* Asset loading. */
		add_action( 'init', array( $this, 'load_css_and_js_files' ) );
		add_action( 'init', array( $this, 'load_all_classes' ) );

		/* AJAX handlers. */
		add_action( 'wp_ajax_wp_clear_logs',       array( $this, 'wp_clear_logs' ) );
		add_action( 'wp_ajax_wp_clear_debug_logs', array( $this, 'wp_clear_debug_logs' ) );

		/* Plugin list customisation. */
		add_filter( 'plugin_action_links_' . WPFORMS_GOOGLESHEET_BASE_NAME, array( $this, 'wpform_gs_connector_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		/** Redirect URL */	
		add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );


		
	}

	// --------------------------------------------------------
	// 7.2  ACTIVATION / DEACTIVATION / UNINSTALL
	// --------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 * Handles both network-wide and per-site activation on multisite.
	 *
	 * @param bool $network_wide True when network-activated.
	 */
	public function wpform_gs_connector_activate( $network_wide ) {

		$this->run_on_activation();

		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->run_for_site();
				restore_current_blog();
			}
			return;
		}

		$this->run_for_site();

		/** Activated plugin and redirect to dashboad */
		set_transient( 'wpform_gs_activation_redirect', true, 30 );
	}

	/**
	 * Runs on plugin deactivation.
	 * Intentionally left empty – no cleanup required on deactivation.
	 */
	public function wpform_gs_connector_deactivate() {}

	/**
	 * Runs on plugin uninstall (static – called without an object instance).
	 * Removes all plugin data from every site on a multisite network,
	 * or from the single site on a standard install.
	 */
	public static function wpform_gs_connector_uninstall() {

		self::run_on_uninstall();

		if ( is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::delete_for_site();
				restore_current_blog();
			}
			return;
		}

		self::delete_for_site();
	}

	// --------------------------------------------------------
	// 7.3  DATABASE SETUP & UPGRADES
	// --------------------------------------------------------

	/**
	 * Creates (or upgrades) the custom submission table and the error-log
	 * table using dbDelta so it is safe to call repeatedly.
	 */
	public function gsc_wpforms_create_table() {

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		/* Submissions table. */
		$submissions_table = $wpdb->prefix . 'gscwpforms_submissions';
		dbDelta( "CREATE TABLE {$submissions_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id      BIGINT(20) UNSIGNED NOT NULL,
			form_id       BIGINT(20) UNSIGNED NOT NULL,
			submitted_at  DATETIME            NOT NULL,
			form_data     LONGTEXT            NOT NULL,
			user_email    VARCHAR(200)        DEFAULT NULL,
			user_ip       VARCHAR(100)        DEFAULT NULL,
			browser_info  VARCHAR(255)        DEFAULT NULL,
			is_read       TINYINT(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY form_id  (form_id),
			KEY entry_id (entry_id)
		) {$charset_collate};" );

		/* Error-log table. */
		$log_table = $wpdb->prefix . 'gscwpf_error_logs';
		dbDelta( "CREATE TABLE {$log_table} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			error_id   VARCHAR(191)    NOT NULL,
			code       INT             NOT NULL,
			message    TEXT            NOT NULL,
			details    LONGTEXT        NULL,
			created_at DATETIME        NOT NULL,
			PRIMARY KEY (id),
			KEY error_id (error_id),
			KEY code     (code)
		) {$charset_collate};" );
	}

	/**
	 * Runs version-specific upgrade routines on every admin page load.
	 * Updates the stored version number after all upgrades complete.
	 */
	public function run_on_upgrade() {

		$plugin_options = get_site_option( 'wpform_GS_info' );

		if ( $plugin_options['version'] <= '1.3' ) {
			$this->upgrade_database_20();
			$this->upgrade_database_21();
		} elseif ( $plugin_options['version'] === '3.4.25' ) {
			$this->upgrade_database_21();
		} elseif ( $plugin_options['version'] === '4.0.4' ) {
			$this->upgrade_database_22();
		}

		update_site_option( 'wpform_GS_info', array(
			'version'    => WPFORMS_GOOGLESHEET_VERSION,
			'db_version' => WPFORMS_GOOGLESHEET_DB_VERSION,
		) );

		/** update existing method default when the plugin  upgrade */
		if (get_option('gs_wpforms_manual_setting') === false) {
			update_option('gs_wpforms_manual_setting', 0);
		}

		/** Oauth Option value migrate function
		 * wpforms_existing = 0
		 * wpforms_service = 2
		 */

		/*if ( method_exists( $this, 'oauth_option_migrate_function' ) ) {
        $this->oauth_option_migrate_function();
    	}*/

	}

	/**
	 * Upgrade routine for schema version 2.0.
	 * Sets a transient so the admin is redirected to the integration page.
	 */
	public function upgrade_database_20() {
		$this->run_multisite_or_single( 'upgrade_helper_20' );
	}

	/** @internal Called per-site by upgrade_database_20(). */
	public function upgrade_helper_20() {
		set_transient( 'wpform_gs_upgrade_redirect', true, 30 );
	}

	/**
	 * Upgrade routine for schema version 2.1.
	 * Re-fetches and saves Google API credentials.
	 */
	public function upgrade_database_21() {
		$this->run_multisite_or_single( 'upgrade_helper_21' );
	}

	/** @internal Called per-site by upgrade_database_21(). */
	public function upgrade_helper_21() {
		Wpform_gs_Connector_Utility::instance()->save_api_credentials();
	}

	/**
	 * Upgrade routine for schema version 2.2.
	 * Creates / updates the custom database tables.
	 */
	public function upgrade_database_22() {
		$this->run_multisite_or_single( 'upgrade_helper_22' );
	}

	/** @internal Called per-site by upgrade_database_22(). */
	public function upgrade_helper_22() {
		$this->gsc_wpforms_create_table();
	}

	/*public function oauth_option_migrate_function(){
		$option_name = 'gs_wpforms_manual_setting';
		$current_value = get_option($option_name);

		// If option doesn't exist, set default value 0
		if ( false === $current_value ) {
			update_option( $option_name, 0 );
			return;
		}

		// Skip if already migrated (numeric value)
		if ( is_numeric( $current_value ) ) {
			return;
		}

		$migration_map = [
			'wpforms_existing' => 0,
			'wpforms_service'  => 2,
		];

		if ( isset( $migration_map[ $current_value ] ) ) {
			update_option( $option_name, $migration_map[ $current_value ] );
		}
	}*/


	/**
	 * Redirects the admin to the integration settings page after a 2.0 upgrade.
	 * Fires on admin_init (priority 999).
	 */
	public function redirect_after_upgrade() {

		if ( ! get_transient( 'wpform_gs_upgrade_redirect' ) ) {
			return;
		}

		$plugin_options = get_site_option( 'wpform_GS_info' );

		if ( $plugin_options['version'] === '2.0' ) {
			delete_transient( 'wpform_gs_upgrade_redirect' );
			wp_safe_redirect( 'admin.php?page=wpform-google-sheet-config&tab=integration' );
		}
	}	

	// --------------------------------------------------------
	// 7.4  ADMIN UI – MENUS, DASHBOARD WIDGET, NOTICES
	// --------------------------------------------------------

	/**
	 * Checks that at least one WPForms edition is active.
	 * Deactivates this plugin and shows an admin notice if not.
	 *
	 * Hooked to: admin_init
	 */
	public function validate_parent_plugin_exists() {

		$plugin = plugin_basename( __FILE__ );

		$wpforms_active =
			is_plugin_active( 'wpforms-lite/wpforms.php' ) ||
			is_plugin_active( 'wpforms/wpforms.php' )      ||
			is_plugin_active( 'wpforms-pro/wpforms.php' );

		if ( ! $wpforms_active ) {
			add_action( 'admin_notices',         array( $this, 'wpforms_missing_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'wpforms_missing_notice' ) );
			deactivate_plugins( $plugin );
			unset( $_GET['activate'] );
		}
	}

	/**
	 * Renders the admin notice shown when WPForms is missing.
	 */
	public function wpforms_missing_notice() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin_notice() intentionally returns HTML markup; the message here is built entirely from static translated strings and a hardcoded link, not request input.
		echo Wpform_gs_Connector_Utility::instance()->admin_notice( array(
			'type'    => 'error',
			'message' => esc_html__( 'WPForms Google Sheet Connector Add-on requires WPForms ', 'gsheetconnector-wpforms' )
				. esc_html__( ' plugin to be installed and activated.', 'gsheetconnector-wpforms' ),
		) );
	}

	/**
	 * Registers the "Google Sheet" submenu under the WPForms top-level menu.
	 *
	 * Hooked to: admin_menu
	 */
	public function register_wpform_menu_pages() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! is_plugin_active( 'gsheetconnector-wpforms-pro/gsheetconnector-wpforms-pro.php' ) ) {

			add_submenu_page(
				'wpforms-overview',
				__( 'Google Sheet', 'gsheetconnector-wpforms' ),
				__( 'Google Sheet', 'gsheetconnector-wpforms' ),
				'manage_options',
				'wpform-google-sheet-config',
				array( $this, 'wpforms_google_sheet_config' )
			);
		}
	}

	/**
	 * Renders the Google Sheets settings page.
	 */
	public function wpforms_google_sheet_config() {
		include WPFORMS_GOOGLESHEET_PATH . 'includes/pages/wpforms-gs-settings.php';
	}

	/**
	 * Registers the dashboard summary widget.
	 *
	 * Hooked to: wp_dashboard_setup
	 */
	public function add_wpform_gs_connector_summary_widget() {

		$title = sprintf(
			"<img style='width:30px;margin-right:10px;' src='%s'><span>%s</span>",
			esc_url( WPFORMS_GOOGLESHEET_URL . 'assets/img/wpforms-gsc.svg' ),
			__( 'GSheetConnector For WPForms', 'gsheetconnector-wpforms' )
		);

		wp_add_dashboard_widget(
			'wpform_gs_dashboard',
			$title,
			array( $this, 'wpform_gs_connector_summary_dashboard' )
		);
	}

	/**
	 * Renders the dashboard widget content.
	 */
	public function wpform_gs_connector_summary_dashboard() {
		include_once WPFORMS_GOOGLESHEET_ROOT . '/includes/pages/wpform-dashboard-widget.php';
	}

	// --------------------------------------------------------
	// 7.5  PLUGIN LIST TABLE CUSTOMISATION
	// --------------------------------------------------------

	/**
	 * Adds Settings and Upgrade-to-PRO links to the plugin's action links.
	 *
	 * @param  array $links Default action links.
	 * @return array        Modified action links.
	 */
	public function wpform_gs_connector_plugin_action_links( $links ) {

		unset( $links['edit'] ); /* Remove the "Edit" link. */
		
		if ( ! is_plugin_active( 'gsheetconnector-wpforms-pro/gsheetconnector-wpforms-pro.php' ) ) {
			return array_merge(
				array(
					'<a href="' . admin_url( 'admin.php?page=wpform-google-sheet-config&tab=integration' ) . '">'
						. __( 'Settings', 'gsheetconnector-wpforms' ) . '</a>',
					'<a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank"> <span style="color: green;">'
						. __( 'Upgrade to PRO', 'gsheetconnector-wpforms' ) . '</span></a>',
				),
				$links
			);
		} else {
			// PRO plugin is active - show Settings link only
			return array_merge(
				array(
					'<a href="' . admin_url( 'admin.php?page=wpform-google-sheet-config&tab=integration' ) . '">'
						. __( 'Settings', 'gsheetconnector-wpforms' ) . '</a>',
				),
				$links
			);
		}
	}

	/**
	 * Appends Docs and Support links to the plugin's row meta.
	 *
	 * @param  array  $plugin_meta Array of existing meta links.
	 * @param  string $plugin_file Plugin basename used for matching.
	 * @return array               Modified meta links.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {

		if ( WPFORMS_GOOGLESHEET_BASE_NAME !== $plugin_file ) {
			return $plugin_meta;
		}

		return array_merge( $plugin_meta, array(
			'docs' => '<a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/introduction" target="_blank" aria-label="'
				. esc_attr( esc_html__( 'View Documentation', 'gsheetconnector-wpforms' ) ) . '">'
				. esc_html__( 'Docs', 'gsheetconnector-wpforms' ) . '</a>',
			'ideo' => '<a href="https://www.gsheetconnector.com/support" aria-label="'
				. esc_attr( esc_html__( 'Get Support', 'gsheetconnector-wpforms' ) ) . '" target="_blank">'
				. esc_html__( 'Support', 'gsheetconnector-wpforms' ) . '</a>',
		) );
	}

	// --------------------------------------------------------
	// 7.6  ASSET LOADING
	// --------------------------------------------------------

	/**
	 * Registers admin_print_styles and admin_print_scripts hooks.
	 *
	 * Hooked to: init
	 */
	public function load_css_and_js_files() {
		add_action( 'admin_print_styles',  array( $this, 'add_css_files' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_js_files' ) );
	}

	/**
	 * Enqueues plugin CSS – only on the plugin's own admin page.
	 */
	public function add_css_files() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-identity check used to decide whether to enqueue assets, no state change.
		if ( ! is_admin() || ! isset( $_GET['page'] ) || $_GET['page'] !== 'wpform-google-sheet-config' ) {
			return;
		}

		$css_files = array(
			'gswpf-css-free'                   => 'assets/css/wpform-gs-connector.css',
			'gswpf-connector-header-free'      => 'assets/css/header.css',
			'gswpf-connector-footer-free'      => 'assets/css/footer.css',
			'gswpf-connector-extra-style-free' => 'assets/css/extra-style.css',
			'gswpf-connector-global-free'      => 'assets/css/global.css',
			'gswpf-connector-responsive-free'  => 'assets/css/responsive.css',
			'gswpf-connector-pro-feature'      => 'assets/css/pro-feature.css',
			'gswpf-connector-font-awesome-free'=> 'assets/css/fontawesome.css',
		);

		foreach ( $css_files as $handle => $path ) {
			wp_enqueue_style( $handle, WPFORMS_GOOGLESHEET_URL . $path, [], WPFORMS_GOOGLESHEET_VERSION, 'all' );
		}
	}

	/**
	 * Enqueues plugin JS.
	 * Page-specific scripts only load on the plugin's own admin page;
	 * notice scripts load on all admin pages.
	 */
	public function add_js_files() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-identity check used to decide whether to enqueue assets, no state change.
		if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'wpform-google-sheet-config' ) {

			/* Main connector script with AJAX object. */
			wp_enqueue_script(
				'wpform-gsconnectorjs',
				WPFORMS_GOOGLESHEET_URL . 'assets/js/wpform-gs-connector.js',
				array( 'jquery' ),
				WPFORMS_GOOGLESHEET_VERSION,
				true
			);
			wp_localize_script( 'wpform-gsconnectorjs', 'gsc_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			) );

			/* System debug and extension scripts. */
			$page_scripts = array(
				'gscwpform-debug-js'      => 'assets/js/system-debug.js',
				'gs-connector-extensions' => 'assets/js/gs-connector-extensions.js',
			);

			foreach ( $page_scripts as $handle => $path ) {
				wp_enqueue_script( $handle, WPFORMS_GOOGLESHEET_URL . $path, array( 'jquery' ), WPFORMS_GOOGLESHEET_VERSION, true );
			}
		}

		/* Notice + add-on scripts: load on all admin pages. */
		if ( is_admin() ) {
			wp_enqueue_script( 'wpform-gs-connector-notice-js', WPFORMS_GOOGLESHEET_URL . 'assets/js/wpforms-gs-connector-notice.js', [], WPFORMS_GOOGLESHEET_VERSION, true );
			wp_enqueue_script( 'wpform-gs-connector-adds-js',  WPFORMS_GOOGLESHEET_URL . 'assets/js/wpform-gs-connector-adds.js',     [], WPFORMS_GOOGLESHEET_VERSION, true );
		}
	}

	// --------------------------------------------------------
	// 7.7  LAZY CLASS LOADING
	// --------------------------------------------------------

	/**
	 * Loads optional feature classes that are not needed at boot time.
	 *
	 * Hooked to: init
	 */
	public function load_all_classes() {

		if ( ! class_exists( 'Wpform_gs_Connector_Adds' ) ) {
			include WPFORMS_GOOGLESHEET_PATH . 'includes/class-wpform-adds.php';
		}

		if ( ! class_exists( 'gswpff_error_logs' ) ) {
			include WPFORMS_GOOGLESHEET_PATH . 'includes/class-gsc-wpforms-error-logs.php';
		}
	}

	// --------------------------------------------------------
	// 7.8  AJAX HANDLERS – LOG CLEARING
	// --------------------------------------------------------

	/**
	 * Clears the plugin's own debug log file.
	 * Callback for: wp_ajax_wp_clear_logs
	 */
	public function wp_clear_logs() {

		check_ajax_referer( 'gs-ajax-nonce', 'security' );

		$wp_filesystem    = $this->get_wp_filesystem();
		$existDebugFile   = get_option( 'wpf_gs_debug_log_file' );
		$clear_file_msg   = '';

		if ( ! empty( $existDebugFile ) && $wp_filesystem->exists( $existDebugFile ) ) {
			$wp_filesystem->put_contents( $existDebugFile, '', FS_CHMOD_FILE );
			$clear_file_msg = 'Logs are cleared.';
		} else {
			$clear_file_msg = 'No log file exists to clear logs.';
		}

		wp_send_json_success( $clear_file_msg );
	}

	/**
	 * Clears the WordPress core debug.log file (System Status tab).
	 * Callback for: wp_ajax_wp_clear_debug_logs
	 */
	public function wp_clear_debug_logs() {

		check_ajax_referer( 'gs-ajax-nonce', 'security' );

		$wp_filesystem = $this->get_wp_filesystem();
		$log_file      = WP_CONTENT_DIR . '/debug.log';

		$wp_filesystem->put_contents( $log_file, '', FS_CHMOD_FILE );

		wp_send_json_success();
	}

	// --------------------------------------------------------
	// 7.9  PRIVATE HELPERS
	// --------------------------------------------------------

	/**
	 * Initialises WP_Filesystem and returns the global instance.
	 *
	 * @return WP_Filesystem_Base
	 */
	private function get_wp_filesystem() {

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		WP_Filesystem();
		return $wp_filesystem;
	}

	/**
	 * Runs a helper method on every site in a multisite network, or once
	 * on a single-site install.
	 *
	 * @param string $method_name Name of the instance method to call.
	 */
	private function run_multisite_or_single( $method_name ) {

		if ( is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( $site_id );
				$this->$method_name();
				restore_current_blog();
			}
			return;
		}

		$this->$method_name();
	}

	/**
	 * Called on activation. Creates site-level options and runs
	 * any pending upgrade routines.
	 */
	private function run_on_activation() {

		$plugin_options = get_site_option( 'wpform_GS_info' );

		$this->gsc_wpforms_create_table();

		if ( $plugin_options === false ) {
			update_site_option( 'wpform_GS_info', array(
				'version'    => WPFORMS_GOOGLESHEET_VERSION,
				'db_version' => WPFORMS_GOOGLESHEET_DB_VERSION,
			) );
		} elseif ( WPFORMS_GOOGLESHEET_DB_VERSION !== $plugin_options['version'] ) {
			$this->run_on_upgrade();
		}

		if (!get_option('gscwpff_plugin_activated_at')) {
			update_option('gscwpff_plugin_activated_at', time());
		}

		Wpform_gs_Connector_Utility::instance()->save_api_credentials();

	
	}


	public function handle_activation_redirect() {
		if ( get_transient( 'wpform_gs_activation_redirect' ) ) {
			delete_transient( 'wpform_gs_activation_redirect' ); // Remove it immediately
			
			wp_safe_redirect( add_query_arg( 
				array( 
					'page' => 'wpform-google-sheet-config',
					'tab'  => 'wpform-dashboard'
				),
				admin_url( 'admin.php' )
			) );
			exit;
		}
	}

	/**
	 * Creates per-site WordPress options with safe defaults on activation.
	 */
	private function run_for_site() {

		$defaults = array(
			'wpform_gs_access_code'          => '',
			'gs_wpforms_manual_setting'         => '0',
			'wpform_gs_verify'               => '',
			'wpform_gs_verify_service'       => '',
			'wpform_gs_token'                => '',
			'gs_wpformspro_service_account_json'=> '',
			'wpform_uninstall'               => 'false',
			'wpform_gs_verify_manual'        => '',
		);

		foreach ( $defaults as $option => $default_value ) {
			if ( ! get_option( $option ) ) {
				update_option( $option, $default_value );
			}
		}
	}

	/**
	 * Deletes all per-site options and post-meta on uninstall.
	 */
	private static function delete_for_site() {

	 $get_option = get_option('gscwpff_wpforms_uninstall_settings_free');
		if ($get_option === '1') {
			$options = array(
				'wpform_gs_access_code',
				'wpform_gs_verify',
				'wpform_gs_token',
				'gs_wpforms_manual_setting',
				'gs_wpformspro_service_account_json',
				'wpform_uninstall',
				'wpform_gs_verify_manual',
				'wpform_gs_verify_service',
			);

			foreach ( $options as $option ) {
				delete_option( $option );
			}

			delete_post_meta_by_key( 'wpform_gs_settings' );
			delete_post_meta_by_key( 'wpform_gs_settings_new' );
		}
	}

	/**
	 * Deletes the network-level (site) option on uninstall.
	 * Guards against direct file access.
	 */
	private static function run_on_uninstall() {

		if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			exit();
		}

		delete_site_option( 'wpform_GS_info' );
	}

	
}

// ============================================================
// SECTION 8: BOOT
// ============================================================





$gsheetconnector_wpforms_init = new WPforms_Gsheet_Connector_Init();