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

   private function __construct()
   {
      // Do Nothing
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
    * Prints message (string or array) in the debug.log file
    *
    * @param mixed $message
    */
   public function logger($message)
   {
      if (WP_DEBUG === true) {
         if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
         } else {
            error_log($message);
         }
      }
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
      // extract message and type from the $data array
      $message = isset($data['message']) ? $data['message'] : "";
      $message_type = isset($data['type']) ? $data['type'] : "";
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
         case 'auth-expired-notice':
            $admin_notice = '<div id="message" class="error notice wpform-gs-auth-expired-adds is-dismissible">';
            break;
         case 'upgrade':
            $admin_notice = '<div id="message" class="error notice wpforms-gs-upgrade is-dismissible">';
            break;
         default:
            $message = __('There\'s something wrong with your code...', 'gsheetconnector-wpforms');
            $admin_notice = "<div id=\"message\" class=\"error\">\n";
            break;
      }

      $admin_notice .= "    <p>" . __($message, 'gsheetconnector-wpforms') . "</p>\n";
      $admin_notice .= "</div>\n";
      return $admin_notice;
   }

   /**
    * Utility function to get the current user's role
    *
    * @since 1.0
    */
   public function get_current_user_role()
   {
      global $wp_roles;
      foreach ($wp_roles->role_names as $role => $name):
         if (current_user_can($role))
            return $role;
      endforeach;
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
      try {
         if (!is_dir(WPFORMS_GOOGLESHEET_PATH . 'logs')) {
            mkdir(WPFORMS_GOOGLESHEET_PATH . 'logs', 0755, true);
         }
      } catch (Exception $e) {

      }
      try {
         // check if debug log file exists or not
         $wplogFilePathToDelete = WPFORMS_GOOGLESHEET_PATH . "logs/log.txt";
         // Check if the log file exists before attempting to delete
         if (file_exists($wplogFilePathToDelete)) {
            unlink($wplogFilePathToDelete);
         }
         // check if debug unique log file exists or not
         $wpexistDebugFile = get_option('wpf_gs_debug_log_file');
         if (!empty($wpexistDebugFile) && file_exists($wpexistDebugFile)) {
            $wplog = fopen($wpexistDebugFile, 'a');
            if (is_array($error)) {
               fwrite($wplog, print_r(date_i18n('j F Y H:i:s', current_time('timestamp')) . " \t PHP " . phpversion(), TRUE));
               fwrite($wplog, print_r($error, TRUE));
            } else {
               $result = fwrite($wplog, print_r(date_i18n('j F Y H:i:s', current_time('timestamp')) . " \t PHP " . phpversion() . " \t $error \r\n", TRUE));
            }
            fclose($wplog);
         } else {
            // if unique log file not exists then create new file code
            // Your log content (you can customize this)
            $wp_unique_log_content = "Log created at " . date('Y-m-d H:i:s');
            // Create the log file
            $wplogfileName = 'log-' . uniqid() . '.txt';
            // Define the file path
            $wplogUniqueFile = WPFORMS_GOOGLESHEET_PATH . "logs/" . $wplogfileName;
            if (file_put_contents($wplogUniqueFile, $wp_unique_log_content)) {
               // save debug unique file in table
               update_option('wpf_gs_debug_log_file', $wplogUniqueFile);
               // Success message
               // echo "Log file created successfully: " . $logUniqueFile;
               $wplog = fopen($wplogUniqueFile, 'a');
               if (is_array($error)) {
                  fwrite($wplog, print_r(date_i18n('j F Y H:i:s', current_time('timestamp')) . " \t PHP " . phpversion(), TRUE));
                  fwrite($wplog, print_r($error, TRUE));
               } else {
                  $result = fwrite($wplog, print_r(date_i18n('j F Y H:i:s', current_time('timestamp')) . " \t PHP " . phpversion() . " \t $error \r\n", TRUE));
               }
               fclose($wplog);

            } else {
               // Error message
               echo "Error - Not able to create Log File.";
            }
         }

      } catch (Exception $e) {

      }
   }

}
