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
            $message = __('There\'s something wrong with your code...', 'gsheetconnector-wpforms');
            $admin_notice = "<div id=\"message\" class=\"error\">";
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

      // Make the API call using wp_remote_get
      $response = wp_remote_get($api_url);

      // Check for errors
      if (is_wp_error($response)) {
         // Handle error
         self::gs_debug_log(__METHOD__ . ' Error: ' . $response->get_error_message());
      } else {
         // API call was successful, process the data
         $response = wp_remote_retrieve_body($response);

         $decoded_response = json_decode($response);

         if (isset($decoded_response->api_creds) && (!empty($decoded_response->api_creds))) {
            $api_creds = wp_parse_args($decoded_response->api_creds);
            if (is_multisite()) {
               // If it's a multisite, update the site option (network-wide)
               update_site_option('Wpformsgsc_api_creds', $api_creds);
            } else {
               // If it's not a multisite, update the regular option
               update_option('Wpformsgsc_api_creds', $api_creds);
            }
         }
      }
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
