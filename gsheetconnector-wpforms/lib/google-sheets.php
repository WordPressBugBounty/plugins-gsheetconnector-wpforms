<?php

if (!defined('ABSPATH'))
   exit;

include_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');

class wpfgsc_googlesheet
{

   private $token;
   private $spreadsheet;
   private $worksheet;


   private static $instance;

   public function __construct()
   {

   }

   public static function setInstance(Google_Client $instance = null)
   {
      self::$instance = $instance;
   }

   public static function getInstance()
   {
      if (is_null(self::$instance)) {
         throw new LogicException("Invalid Client");
      }

      return self::$instance;
   }

   //constructed on call
   public static function preauth($access_code)
   {
      // Fetch API creds
      if (is_multisite()) {
         // Fetch API creds
         $api_creds = get_site_option('Wpformsgsc_api_creds');
      } else {
         // Fetch API creds
         $api_creds = get_option('Wpformsgsc_api_creds');
      }

      $newClientSecret = get_option('is_new_client_secret_wpformsgsc');
      $clientId = ($newClientSecret == 1) ? $api_creds['client_id_web'] : $api_creds['client_id_desk'];
      $clientSecret = ($newClientSecret == 1) ? $api_creds['client_secret_web'] : $api_creds['client_secret_desk'];

      $client = new Google_Client();
      $client->setClientId($clientId);
      $client->setClientSecret($clientSecret);
      $client->setRedirectUri('https://oauth.gsheetconnector.com');
      //$client->setRedirectUri(wpfgsc_googlesheet::redirect);
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
      $client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
      $client->setAccessType('offline');
      $client->fetchAccessTokenWithAuthCode($access_code);
      $tokenData = $client->getAccessToken();

      wpfgsc_googlesheet::updateToken($tokenData);
   }





   public static function updateToken($tokenData)
   {
      $expires_in = isset($tokenData['expires_in']) ? intval($tokenData['expires_in']) : 0;
      $tokenData['expire'] = time() + $expires_in;
      try {

         if (isset($tokenData['scope'])) {
            $permission = explode(" ", $tokenData['scope']);
            if ((in_array("https://www.googleapis.com/auth/drive.metadata.readonly", $permission)) && (in_array("https://www.googleapis.com/auth/spreadsheets", $permission))) {
               update_option('wpform_gs_verify', 'valid');
            } else {
               update_option('wpform_gs_verify', 'invalid-auth');
            }
         }
         $tokenJson = json_encode($tokenData);
         update_option('wpform_gs_token', $tokenJson);


      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log("Token write fail! - " . $e->getMessage());
      }
   }

   public function auth()
   {
      $tokenData = json_decode(get_option('wpform_gs_token'), true);
      if (!isset($tokenData['refresh_token']) || empty($tokenData['refresh_token'])) {
         throw new LogicException("Auth, Invalid OAuth2 access token");
         exit();
      }

      try {
         if (is_multisite()) {
            // Fetch API creds
            $api_creds = get_site_option('Wpformsgsc_api_creds');
         } else {
            // Fetch API creds
            $api_creds = get_option('Wpformsgsc_api_creds');
         }
         $newClientSecret = get_option('is_new_client_secret_wpformsgsc');
         $clientId = ($newClientSecret == 1) ? $api_creds['client_id_web'] : $api_creds['client_id_desk'];
         $clientSecret = ($newClientSecret == 1) ? $api_creds['client_secret_web'] : $api_creds['client_secret_desk'];

         $client = new Google_Client();
         $client->setClientId($clientId);
         $client->setClientSecret($clientSecret);

         $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
         $client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
         // $client->setScopes(Google_Service_Oauth2::USERINFO_EMAIL);
         $client->refreshToken($tokenData['refresh_token']);
         $client->setAccessType('offline');
         wpfgsc_googlesheet::updateToken($tokenData);

         self::setInstance($client);
      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log("Auth, Error fetching OAuth2 access token, message:" . $e->getMessage());
         throw new LogicException("Auth, Error fetching OAuth2 access token, message: " . $e->getMessage());
         exit();
      }
   }







   //preg_match is a key of error handle in this case
   public function setSpreadsheetId($id)
   {
      $this->spreadsheet = $id;
   }

   public function getSpreadsheetId()
   {

      return $this->spreadsheet;
   }

   public function setWorkTabId($id)
   {
      $this->worksheet = $id;
   }

   public function getWorkTabId()
   {
      return $this->worksheet;
   }

   public function add_row($data)
   {

      try {

         $client = self::getInstance();
         $service = new Google_Service_Sheets($client);
         $spreadsheetId = $this->getSpreadsheetId();
         $work_sheets = $service->spreadsheets->get($spreadsheetId);



         if (!empty($work_sheets) && !empty($data)) {
            foreach ($work_sheets as $sheet) {

               $properties = $sheet->getProperties();

               $sheet_id = $properties->getSheetId();


               $worksheet_id = $this->getWorkTabId();



               if ($sheet_id == $worksheet_id) {
                  $worksheet_id = $properties->getTitle();



                  $worksheetCell = $service->spreadsheets_values->get($spreadsheetId, $worksheet_id . "!1:1");




                  $insert_data = array();
                  if (isset($worksheetCell->values[0])) {
                     $insert_data_index = 0;

                     foreach ($worksheetCell->values[0] as $k => $name) {

                        if ($insert_data_index == 0) {
                           if (isset($data[$name]) && $data[$name] != '') {

                              $insert_data[] = $data[$name];
                           } else {
                              $insert_data[] = '';
                           }
                        } else {
                           if (isset($data[$name]) && $data[$name] != '') {
                              $insert_data[] = $data[$name];
                           } else {
                              $insert_data[] = '';
                           }
                        }
                        $insert_data_index++;
                     }
                  }
                  $range_new = $worksheet_id;

                  // Create the value range Object
                  $valueRange = new Google_Service_Sheets_ValueRange();

                  // set values of inserted data
                  $valueRange->setValues(["values" => $insert_data]);


                  // Add two values
                  // Then you need to add configuration
                  $conf = ["valueInputOption" => "USER_ENTERED"];

                  // append the spreadsheet(add new row in the sheet)
                  $result = $service->spreadsheets_values->append($spreadsheetId, $range_new, $valueRange, $conf);
               }
            }
         }
      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return null;
         exit();
      }
   }







   //get all the spreadsheets
   public function get_spreadsheets()
   {
      $all_sheets = array();
      try {
         $client = self::getInstance();

         $service = new Google_Service_Drive($client);

         $optParams = array(
            'q' => "mimeType='application/vnd.google-apps.spreadsheet'"
         );
         $results = $service->files->listFiles($optParams);
         foreach ($results->files as $spreadsheet) {
            if (isset($spreadsheet['kind']) && $spreadsheet['kind'] == 'drive#file') {
               $all_sheets[] = array(
                  'id' => $spreadsheet['id'],
                  'title' => $spreadsheet['name'],
               );
            }
         }
      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return null;
         exit();
      }
      return $all_sheets;
   }

   //get worksheets title
   public function get_worktabs($spreadsheet_id)
   {


      $work_tabs_list = array();
      try {
         $client = self::getInstance();
         $service = new Google_Service_Sheets($client);
         $work_sheets = $service->spreadsheets->get($spreadsheet_id);


         foreach ($work_sheets as $sheet) {
            $properties = $sheet->getProperties();
            $work_tabs_list[] = array(
               'id' => $properties->getSheetId(),
               'title' => $properties->getTitle(),
            );
         }
      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return null;
         exit();
      }

      return $work_tabs_list;
   }



   /*******************************************************************************/
   /********************************  VERSION 3.1 *********************************/
   /*******************************************************************************/


   /** 
    * GFGSC_googlesheet::get_sheet_name
    * get WorkSheet Name
    * @since 3.1 
    * @param string $spreadsheet_id
    * @param string $tab_id
    * @retun string $tab_name
    **/
   public function get_sheet_name($spreadsheet_id, $tab_id)
   {

      $all_sheet_data = get_option('wpforms_gs_sheetId');

      $tab_name = "";
      foreach ($all_sheet_data as $spreadsheet) {

         if ($spreadsheet['id'] == $spreadsheet_id) {
            $tabs = $spreadsheet['tabId'];

            foreach ($tabs as $name => $id) {
               if ($id == $tab_id) {
                  $tab_name = $name;
               }
            }
         }
      }

      return $tab_name;
   }


   /** 
    * GFGSC_googlesheet::get_sheet_name
    * get SpreadSheet Name
    * @since 3.1 
    * @param string $spreadsheet_id
    * @retun string $spreadsheetName
    **/
   public function get_spreadsheet_name($spreadsheet_id)
   {

      $all_sheet_data = get_option('wpforms_gs_sheetId');

      $spreadsheetName = "";
      foreach ($all_sheet_data as $spreadsheet_name => $spreadsheet) {

         if ($spreadsheet['id'] == $spreadsheet_id) {
            $spreadsheetName = $spreadsheet_name;
         }
      }

      return $spreadsheetName;
   }

   /** 
    * GFGSC_googlesheet::get_header_row
    * Send row data to sheet
    * @since 3.1 
    * @param string $spreadsheet_id
    * @param string $tab_id
    * @retun array $header_cells
    **/
   public function get_header_row($spreadsheet_id, $tab_id)
   {

      $header_cells = array();
      try {

         $client = $this->getInstance();

         if (!$client) {
            return false;
         }

         $service = new Google_Service_Sheets($client);

         $work_sheets = $service->spreadsheets->get($spreadsheet_id);

         if ($work_sheets) {

            foreach ($work_sheets as $sheet) {

               $properties = $sheet->getProperties();
               $work_sheet_id = $properties->getSheetId();

               if ($work_sheet_id == $tab_id) {

                  $tab_title = $properties->getTitle();
                  $header_row = $service->spreadsheets_values->get($spreadsheet_id, $tab_title . "!1:1");

                  $header_row_values = $header_row->getValues();

                  if (isset($header_row_values[0]) && $header_row_values[0]) {
                     $header_cells = $header_row_values[0];
                  }
               }
            }
         }
      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         $header_cells = array();
         return $header_cells;
      }

      return $header_cells;
   }

    /** 
    * GFGSC_googlesheet::gsheet_get_google_account
    * Get Google Account
    * @since 3.1 
    * @retun $user
    **/
   public function gsheet_get_google_account()
   {

      try {
         $client = $this->getInstance();

         if (!$client) {
            return false;
         }

         $service = new Google_Service_Oauth2($client);
         $user = $service->userinfo->get();
      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log(__METHOD__ . " Error in fetching user info: \n " . $e->getMessage());
         return false;
      }

      return $user;
   }


   /** 
    * GFGSC_googlesheet::gsheet_get_google_account_email
    * Get Google Account Email
    * @since 3.1 
    * @retun string $email
    **/
   public function gsheet_get_google_account_email()
   {
      $google_account = $this->gsheet_get_google_account();

      if ($google_account) {
         return $google_account->email;
      } else {
         return "";
      }
   }


   /** 
    * GFGSC_googlesheet::gsheet_print_google_account_email
    * Get Google Account Email
    * @since 3.1 
    * @retun string $google_account
    **/
   public function gsheet_print_google_account_email()
   {
      try {
        $google_sheet = new wpfgsc_googlesheet();
         $google_sheet->auth();
         $email = $google_sheet->gsheet_get_google_account_email();
         update_option("wpgs_email_account", $email);
         return $email;


      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return false;
      }
   }






   /**
    * Generate token for the user and refresh the token if it's expired.
    *
    * @return array
    */
   public static function getClient_auth($flag = 0, $gscwpforms_clientId = '', $gscwpforms_clientSecert = '')
   {
      $gscwpforms_client = new Google_Client();
      $gscwpforms_client->setApplicationName('Manage wpforms Forms with Google Spreadsheet');
      $gscwpforms_client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $gscwpforms_client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
      $gscwpforms_client->addScope(Google_Service_Sheets::SPREADSHEETS);
      $gscwpforms_client->addScope('https://www.googleapis.com/auth/userinfo.email');
      //$gscwpforms_client->addScope( 'https://www.googleapis.com/auth/userinfo.profile' );
      $gscwpforms_client->setClientId($gscwpforms_clientId);
      $gscwpforms_client->setClientSecret($gscwpforms_clientSecert);
      $gscwpforms_client->setRedirectUri(esc_html(admin_url('admin.php?page=wpform-google-sheet-config')));
      $gscwpforms_client->setAccessType('offline');
      $gscwpforms_client->setApprovalPrompt('force');
      try {
         if (empty($gscwpforms_auth_token)) {
            $gscwpforms_auth_url = $gscwpforms_client->createAuthUrl();
            return $gscwpforms_auth_url;
         }
         if (!empty($gscwpforms_gscwpforms_accessToken)) {
            $gscwpforms_accessToken = json_decode($gscwpforms_gscwpforms_accessToken, true);
         } else {
            if (empty($gscwpforms_auth_token)) {
               $gscwpforms_auth_url = $gscwpforms_client->createAuthUrl();
               return $gscwpforms_auth_url;
            }

         }

         $gscwpforms_client->setAccessToken($gscwpforms_accessToken);
         // Refresh the token if it's expired.
         if ($gscwpforms_client->isAccessTokenExpired()) {
            // save refresh token to some variable
            $gscwpforms_refreshTokenSaved = $gscwpforms_client->getRefreshToken();
            $gscwpforms_client->fetchAccessTokenWithRefreshToken($gscwpforms_client->getRefreshToken());
            // pass access token to some variable
            $gscwpforms_accessTokenUpdated = $gscwpforms_client->getAccessToken();
            // append refresh token
            $gscwpforms_accessTokenUpdated['refresh_token'] = $gscwpforms_refreshTokenSaved;
            //Set the new acces token
            $gscwpforms_accessToken = $gscwpforms_refreshTokenSaved;
            gscwpforms::gscwpforms_update_option('wpformssheets_google_accessToken', json_encode($gscwpforms_accessTokenUpdated));
            $gscwpforms_accessToken = json_decode(json_encode($gscwpforms_accessTokenUpdated), true);
            $gscwpforms_client->setAccessToken($gscwpforms_accessToken);
         }
      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         if ($flag) {
            return $e->getMessage();
         } else {
            return false;
         }
      }
      return $gscwpforms_client;
   }



   public static function revokeToken_auto($access_code)
   {
      if (is_multisite()) {
         // Fetch API creds
         $api_creds = get_site_option('Wpformsgsc_api_creds');
      } else {
         // Fetch API creds
         $api_creds = get_option('Wpformsgsc_api_creds');
      }
      $newClientSecret = get_option('is_new_client_secret_wpformsgsc');
      $clientId = ($newClientSecret == 1) ? $api_creds['client_id_web'] : $api_creds['client_id_desk'];
      $clientSecret = ($newClientSecret == 1) ? $api_creds['client_secret_web'] : $api_creds['client_secret_desk'];

      $client = new Google_Client();
      $client->setClientId($clientId);
      $client->setClientSecret($clientSecret);
      $tokendecode = json_decode($access_code);
      $token = $tokendecode->access_token;
      $client->revokeToken($token);
   }





}