<?php

/*
 * Utilities class for wpform google sheet connector
 * @since       1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit;
}

/**
 * Utilities class - singleton class
 * @since 1.0
 */
class Wpform_gs_Connector_Utility
{

   /**
    * Tracks which form entries have already been synced to Google Sheets
    * during the current request, keyed by "{form_id}:{entry_id}".
    *
    * @var array<string,bool>
    */
   private static $synced_entries = array();

   private function __construct() {}

   /**
    * Submissions table schema (shared with the Pro plugin). 2 = entry_source column.
    * @since 4.2.4
    */
   const SUBMISSIONS_DB_VERSION = '2';

   /**
    * Entry IDs saved on WPForms Lite in this request, keyed by form ID.
    * @since 4.2.4
    * @var array<int,int>
    */
   private static $lite_entry_ids = array();

   /** @since 4.2.4 */
   public static function set_lite_entry_id($form_id, $entry_id)
   {
      self::$lite_entry_ids[(int) $form_id] = (int) $entry_id;
   }

   /** @since 4.2.4 */
   public static function get_lite_entry_id($form_id)
   {
      return isset(self::$lite_entry_ids[(int) $form_id]) ? self::$lite_entry_ids[(int) $form_id] : 0;
   }

   /**
    * Whether the entry_source column exists.
    * @since 4.2.4
    * @return bool
    */
   public static function submissions_table_upgraded()
   {
      return get_option('gscwpf_submissions_db_version') === self::SUBMISSIONS_DB_VERSION;
   }

   /**
    * Gives every saved row a real Entry ID: 'wpforms' rows keep the WPForms ID, 'lite' rows use their row id.
    * Safe to re-run. Hooked to init (5).
    * @since 4.2.4
    */
   public static function maybe_upgrade_submissions_table()
   {
      global $wpdb;

      $table      = $wpdb->prefix . 'gscwpforms_submissions';
      $is_current = self::submissions_table_upgraded();

      // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are built from $wpdb->prefix, not request input.
      if ($is_current) {
         if (!is_admin()) {
            return;
         }

         $previous = $wpdb->suppress_errors(true);
         $pending  = $wpdb->get_var("SELECT id FROM `{$table}` WHERE entry_source = '' LIMIT 1");
         $wpdb->suppress_errors($previous);

         if (!$pending) {
            return;
         }
      } else {
         // esc_like(): "_" in a multisite prefix (wp_2_) is a LIKE wildcard.
         if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return;
         }

         if (!$wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'entry_source'")) {
            $previous = $wpdb->suppress_errors(true);
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN entry_source VARCHAR(10) NOT NULL DEFAULT '' AFTER entry_id, ADD KEY entry_source (entry_source)");
            $wpdb->suppress_errors($previous);

            // Column could not be added: keep the old behaviour and retry on the next request.
            if (!$wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'entry_source'")) {
               return;
            }
         }
      }

      // 1. Rows copied from WPForms Pro already hold the real WPForms entry ID.
      $wpdb->query("UPDATE `{$table}` SET entry_source = 'wpforms' WHERE entry_source = '' AND entry_id > 0");

      // 2. Lite rows already imported into WPForms by the earlier Lite -> Pro migration
      //    (it created the WPForms entry with entry_id = row id and the same date).
      $wpforms_table = $wpdb->prefix . 'wpforms_entries';
      if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpforms_table))) === $wpforms_table) {
         $wpdb->query(
            "UPDATE `{$table}` s
             INNER JOIN `{$wpforms_table}` e
                ON e.entry_id = s.id AND e.form_id = s.form_id AND e.date = s.submitted_at
             SET s.entry_id = s.id, s.entry_source = 'wpforms'
             WHERE s.entry_source = '' AND s.entry_id = 0"
         );
      }

      // 3. Remaining rows were saved on WPForms Lite: the row id becomes their Entry ID.
      $lite_rows = $wpdb->query("UPDATE `{$table}` SET entry_id = id, entry_source = 'lite' WHERE entry_source = '' AND entry_id = 0");
      // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

      if ($lite_rows) {
         // Lets GSheetConnector Pro import them into WPForms once WPForms Pro is active.
         update_option('gscwpf_lite_entries_pending', 1);
      }

      if (!$is_current) {
         update_option('gscwpf_submissions_db_version', self::SUBMISSIONS_DB_VERSION);
      }
   }

   /**
    * Whether an entry has already been synced to Google Sheets during this request.
    *
    * Guards against duplicate rows if both the legacy and active
    * `wpforms_process_entry_save` handlers ever match the same form's settings.
    *
    * @since 4.0.4
    *
    * @param int $form_id
    * @param int $entry_id
    * @return bool
    */
   public static function is_entry_synced($form_id, $entry_id)
   {
      return isset(self::$synced_entries[$form_id . ':' . $entry_id]);
   }

   /**
    * Marks an entry as synced to Google Sheets for the current request.
    *
    * @since 4.0.4
    *
    * @param int $form_id
    * @param int $entry_id
    */
   public static function mark_entry_synced($form_id, $entry_id)
   {
      self::$synced_entries[$form_id . ':' . $entry_id] = true;
   }

   /**
    * Get the singleton instance of the Wpform_gs_Connector_Utility class
    *
    * @return singleton instance of Wpform_gs_Connector_Utility
    */
   public static function instance()
   {

      static $instance = NULL;
      if (is_null($instance)) {
         $instance = new Wpform_gs_Connector_Utility();
      }
      return $instance;
   }

   /**
    * Display error or success message in the admin section
    *
    * @param array $data containing type and message
    * @return string with html containing the error message
    * 
    * @since 1.0 initial version
    */
   public function admin_notice($data = array())
   {
      $message = isset($data['message']) ? $data['message'] : '';
      $message_type = isset($data['type']) ? $data['type'] : '';

      // Nothing to show: bail quietly instead of rendering an empty notice.
      if ('' === trim((string) $message)) {
         return '';
      }

      switch ($message_type) {
         case 'error':
            $admin_notice = '<div id="message" class="error notice is-dismissible">';
            break;
         case 'update':
            $admin_notice = '<div id="message" class="updated notice is-dismissible">';
            break;
         case 'update-nag':
            $admin_notice = '<div id="message" class="update-nag">';
            break;
         case 'upgrade':
            $admin_notice = '<div id="message" class="error notice wpforms-gs-upgrade is-dismissible">';
            break;
         default:
            $admin_notice = '<div id="message" class="notice notice-error is-dismissible">';
            break;
      }

      $admin_notice .= '<p>' . ($message) . '</p>';
      $admin_notice .= "</div>\n";

      return $admin_notice;
   }

   /**
    * Guards an AJAX callback behind a capability check.
    *
    * Sends a JSON error response, logs the denial, and terminates the
    * request (via wp_send_json_error()) if the current user lacks the
    * given capability. Centralizes the pattern used by every
    * auth-state-changing AJAX handler so the check/log/response shape
    * stays identical everywhere it's needed.
    *
    * @since 4.0.4
    *
    * @param string $capability WordPress capability to require, e.g. 'manage_options'.
    * @param string $message    Optional user-facing error message override.
    */
   public static function require_capability($capability = 'manage_options', $message = '')
   {
      if (current_user_can($capability)) {
         return;
      }

      if (empty($message)) {
         $message = __('You do not have permission to perform this action.', 'gsheetconnector-wpforms');
      }

      self::gs_debug_log(sprintf('Permission denied: current user lacks the "%s" capability.', $capability));

      wp_send_json_error(array('message' => $message));
   }

   /**
    * Fetch and save Auto Integration API credentials
    *
    * @since 3.4.26
    */
   public function save_api_credentials()
   {
      // Create a nonce
      $nonce = wp_create_nonce('Wpformsgsc_api_creds');

      // Prepare parameters for the API call
      $params = array(
         'action' => 'get_data',
         'nonce' => $nonce,
         'plugin' => 'WPFORMSGSC',
         'method' => 'get',
      );

      // Add nonce and any other security parameters to the API request
      $api_url = add_query_arg($params, WPFORMS_GOOGLESHEET_API_URL);

      // 4.2.4: timeout, HTTP status check and one retry; returns true when credentials were saved.
      for ($attempt = 1; $attempt <= 2; $attempt++) {
         $response = wp_remote_get($api_url, array('timeout' => 20));

         if (is_wp_error($response)) {
            self::gs_debug_log(__METHOD__ . ' Error: ' . $response->get_error_message());
            continue;
         }

         $code = (int) wp_remote_retrieve_response_code($response);
         if ($code < 200 || $code >= 300) {
            self::gs_debug_log(__METHOD__ . ' Error: unexpected HTTP status ' . $code);
            continue;
         }

         $decoded_response = json_decode(wp_remote_retrieve_body($response));

         if (isset($decoded_response->api_creds) && (!empty($decoded_response->api_creds))) {
            $api_creds = wp_parse_args($decoded_response->api_creds);
            if (empty($api_creds['client_id_web']) || empty($api_creds['client_secret_web'])) {
               self::gs_debug_log(__METHOD__ . ' Error: incomplete API credentials received.');
               return false;
            }
            if (is_multisite()) {
               // If it's a multisite, update the site option (network-wide)
               update_site_option('Wpformsgsc_api_creds', $api_creds);
            } else {
               // If it's not a multisite, update the regular option
               update_option('Wpformsgsc_api_creds', $api_creds);
            }
            return true;
         }

         self::gs_debug_log(__METHOD__ . ' Error: API credentials missing in response.');
         return false;
      }

      return false;
   }

   /**
    * Utility function to get the current user's role
    *
    * @since 1.0
    */
   public static function gs_debug_log($error)
   {
   
      // ===============================
      // 🔥 DATABASE LOG (ADD ONLY THIS)
      // ===============================
      if (class_exists('gswpff_error_logs')) {
         gswpff_error_logs::log_from_debug($error);
      }
   }
}
