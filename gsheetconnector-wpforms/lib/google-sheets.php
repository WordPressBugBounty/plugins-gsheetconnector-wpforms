<?php

if (!defined('ABSPATH'))
   exit;

class wpfgsc_googlesheet
{

   private $spreadsheet;
   private $worksheet;
   private $access_token;

   public function __construct()
   {

   }

   /**
    * Thin wrapper around wp_remote_request() for Google REST calls.
    *
    * @param string $method HTTP method.
    * @param string $url    Full REST endpoint URL.
    * @param string $token  Bearer access token.
    * @param array  $args   Extra wp_remote_request() args (body/headers).
    * @return array|WP_Error Decoded JSON body, or WP_Error on failure.
    */
   private static function request($method, $url, $token, $args = array())
   {
      $args['method']  = $method;
      $args['headers'] = array_merge(
         array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
         ),
         isset($args['headers']) ? $args['headers'] : array()
      );

      if (isset($args['body']) && is_array($args['body'])) {
         $args['body'] = wp_json_encode($args['body']);
      }

      $response = wp_remote_request($url, $args);

      if (is_wp_error($response)) {
         return $response;
      }

      $code = wp_remote_retrieve_response_code($response);
      $body = json_decode(wp_remote_retrieve_body($response), true);

      if ($code < 200 || $code >= 300) {
         $message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown Google API error.';
         return new WP_Error('gsc_api_error', $message, array('status' => $code, 'body' => $body));
      }

      return $body;
   }

   private static function base64url($data)
   {
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
   }

   /**
    * Mint an access token for a service account via the JWT-bearer REST flow
    * (replaces Google_Client::setAuthConfig() + fetchAccessTokenWithAssertion()).
    *
    * @param array $decoded Decoded service-account JSON key.
    * @return string|WP_Error
    */
   private static function service_account_token($decoded)
   {
      $now    = time();
      $header = array('alg' => 'RS256', 'typ' => 'JWT');
      $claims = array(
         'iss'   => $decoded['client_email'],
         'scope' => implode(' ', array(
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive',
         )),
         'aud'   => 'https://oauth2.googleapis.com/token',
         'iat'   => $now,
         'exp'   => $now + 3600,
      );

      $segments      = array(self::base64url(wp_json_encode($header)), self::base64url(wp_json_encode($claims)));
      $signing_input = implode('.', $segments);

      $private_key = openssl_pkey_get_private($decoded['private_key']);
      $signature   = '';

      if (!$private_key || !openssl_sign($signing_input, $signature, $private_key, 'SHA256')) {
         return new WP_Error('gsc_jwt_sign_failed', 'Unable to sign service account JWT.');
      }

      $segments[] = self::base64url($signature);

      $response = wp_remote_post(
         'https://oauth2.googleapis.com/token',
         array(
            'body' => array(
               'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
               'assertion'  => implode('.', $segments),
            ),
         )
      );

      if (is_wp_error($response)) {
         return $response;
      }

      $body = json_decode(wp_remote_retrieve_body($response), true);

      if (empty($body['access_token'])) {
         return new WP_Error('gsc_service_token_failed', 'Service account token request failed.', $body);
      }

      return $body['access_token'];
   }

    private static function creds()
   {
   return is_multisite()
   ? get_site_option('Wpformsgsc_api_creds')
   : get_option('Wpformsgsc_api_creds');
   }

   //constructed on call
   public static function preauth($access_code)
   {
      
      try {
         $creds = self::creds();
         if (!$creds) return;

         $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            [
            'body' => [
               'code'          => $access_code,
               'client_id'     => $creds['client_id_web'],
               'client_secret' => $creds['client_secret_web'],
               'redirect_uri'  => 'https://oauth.gsheetconnector.com',
               'grant_type'    => 'authorization_code'
            ]
            ]
         );
         if (is_wp_error($response)) {
            return false;
         }

         $body = json_decode(wp_remote_retrieve_body($response), true);
         if (!is_array($body)) {
            $body = [];
         }

         self::updateToken($body);

         return !empty($body['access_token']);
      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log('[Auth Exception]. ' . $e->getMessage());
         throw new LogicException('Auth error: ' . esc_html($e->getMessage()));
      }
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

                // Log permission error to error logs
                    if (class_exists('gswpff_error_logs')) {
                        gswpff_error_logs::log_to_db(
                         'Google_Auth_Permission_Error',
                           403,
                           'Google Drive and Google Sheets permissions not granted',
                           [
                           'error_type' => 'Missing Permissions',
                           'message' => 'User did not grant Google Drive and/or Google Sheets permissions during OAuth authentication',
                           'granted_scopes' => $tokenData['scope'] ?? '',
                           'required_drive_scope' => 'https://www.googleapis.com/auth/drive.file OR https://www.googleapis.com/auth/drive.metadata.readonly',
                           'required_sheets_scope' => 'https://www.googleapis.com/auth/spreadsheets',
                           ]
                        );
                    }
            }
         }
         $tokenJson = json_encode($tokenData);
         update_option('wpform_gs_token', $tokenJson);


      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log("Token write fail! - " . $e->getMessage());
      }
   }

   /**
    * Resolve a bearer access token for the configured auth method and cache
    * it on the instance for subsequent Sheets/Drive REST calls.
    *
    * Kept as `auth()` (rather than renamed) so existing callers
    * (wpforms-panel.php, class-wpforms-integration.php, wpgs-sheet-functions.php)
    * do not need to change.
    *
    * @return string|false Access token, or false on failure.
    */
   public function auth() {

    /* -------------------------------------------------------
     * 1. DETERMINE AUTH METHOD
     * ------------------------------------------------------- */
    $auth_method = get_option( 'gs_wpforms_manual_setting', '0' );
    $verify_status = '';

    if ( $auth_method == 0 ) {
        $verify_status = get_option( 'wpform_gs_verify' );
    } elseif ( $auth_method == 2 ) {
        $verify_status = get_option( 'wpform_gs_verify_service' );
    }

    /* -------------------------------------------------------
     * 2. BAIL EARLY IF NOT VERIFIED
     * ------------------------------------------------------- */
    if ( empty( $verify_status ) ) {
        return false;
    }

    /* -------------------------------------------------------
     * 3. AUTHENTICATE BASED ON METHOD
     * ------------------------------------------------------- */
    try {

        /* -------------------------------------------------------
         * SERVICE ACCOUNT
         * ------------------------------------------------------- */
        if ( $auth_method == 2 ) {

            $json_key = get_option( 'gs_wpformspro_service_account_json' );
            $decoded  = json_decode( $json_key, true );

            if ( json_last_error() !== JSON_ERROR_NONE || empty( $decoded['client_email'] ) || empty( $decoded['private_key'] ) ) {
                Wpform_gs_Connector_Utility::gs_debug_log( 'Auth, Service account JSON is invalid or missing required fields.' );
                return false;
            }

            $token = self::service_account_token( $decoded );

            if ( is_wp_error( $token ) ) {
                Wpform_gs_Connector_Utility::gs_debug_log( 'Auth, service account token error: ' . $token->get_error_message() );
                return false;
            }

            $this->access_token = $token;

            return $token;

        /* -------------------------------------------------------
         * EXISTING OAUTH2 (Auto Setup — no manual method in WPForms)
         * ------------------------------------------------------- */
        } elseif ( $auth_method == 0 ) {

            $token = $this->token();

            if ( ! $token ) {
                Wpform_gs_Connector_Utility::gs_debug_log( 'Auth, Invalid or missing OAuth2 token.' );
                return false;
            }

            $this->access_token = $token;

            return $token;

        }

        /* Unknown auth method */
        Wpform_gs_Connector_Utility::gs_debug_log( 'Auth, Unknown auth method: ' . $auth_method );

        return false;

    } catch ( Exception $e ) {

        Wpform_gs_Connector_Utility::gs_debug_log(
            'Auth, Error during authentication, method: ' . $auth_method . ', message: ' . $e->getMessage()
        );

         return false;

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

   /*public function add_row($data)
   {

      try {

         $token = $this->access_token ? $this->access_token : $this->auth();

         if (!$token || empty($data)) {
            return null;
         }

         $spreadsheet_id = $this->getSpreadsheetId();
         $sheet_title    = $this->get_sheet_title_by_id($token, $spreadsheet_id, $this->getWorkTabId());

         if (empty($sheet_title)) {
            return null;
         }

         $header_row = self::request(
            'GET',
            sprintf(
               'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
               rawurlencode($spreadsheet_id),
               rawurlencode($sheet_title . '!1:1')
            ),
            $token
         );

         if (is_wp_error($header_row)) {
            Wpform_gs_Connector_Utility::gs_debug_log($header_row->get_error_message());
            return null;
         }

         $insert_data = array();

         if (!empty($header_row['values'][0])) {
          
            foreach ($header_row['values'][0] as $name) {
             
               $insert_data[] = (isset($data[$name]) && $data[$name] !== '') ? $data[$name] : '';
            }
         }

         // append the spreadsheet (add new row in the sheet)
         $result = self::request(
            'POST',
            sprintf(
               'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
               rawurlencode($spreadsheet_id),
               rawurlencode($sheet_title)
            ),
            $token,
            array('body' => array('values' => array($insert_data)))
         );

         if (is_wp_error($result)) {
         
            Wpform_gs_Connector_Utility::gs_debug_log($result->get_error_message());
            return null;
         }

         return $result;
      } catch (Exception $e) {
        
         Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return null;
      }
   }*/

   
/**
 * Get the active Google API access token based on selected auth mode
 *
 * Modes:

 * 2 = Service Account
 * Default = Existing/Auto token method
 * @since 1.0.0
 */
public function get_active_token()
{
  try {
    $mode = get_option('gs_wpforms_manual_setting');

   

    if ($mode == 2) {
      return $this->get_service_account_token();
    }

  

    return $this->token();
  } catch (Exception $e) {
     Wpform_gs_Connector_Utility::gs_debug_log(
      __METHOD__ . " Error in Getting token: \n " . $e->getMessage()
    );
    return false;
  }
}

public function get_service_account_token()
{
  try {
    $json = get_option('gs_wpformspro_service_account_json');

    if (empty($json)) {
      return false;
    }

    $creds = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($creds['client_email'])) {
      return false;
    }

    return $this->generate_wpforms_service_token($creds);
  } catch (Exception $e) {
     Wpform_gs_Connector_Utility::gs_debug_log(
      __METHOD__ . " Error while getting service token: " . $e->getMessage()
    );
    return null;
  }
}


/**
 * GENERATE JWT AND EXCHANGE IT FOR GOOGLE OAUTH ACCESS TOKEN
 *
 * This method creates a JWT (JSON Web Token) using Service Account credentials
 * and exchanges it with Google OAuth server to obtain an access token.
 *
 * Workflow:
 * 1. Set current timestamp
 * 2. Create JWT header (algorithm + type)
 * 3. Create JWT payload with:
 *    - issuer (client email)
 *    - required API scopes
 *    - audience (Google OAuth token URL)
 *    - expiration time
 *    - issued-at time
 * 4. Base64 URL encode header and payload
 * 5. Sign JWT using private key (RS256)
 * 6. Send JWT to Google OAuth token endpoint
 * 7. Receive and return access token
 *
 * @since 1.0.0
 */
private function generate_wpforms_service_token($creds)
{
	
	
  try {
    $now = time();

    $header = ["alg" => "RS256", "typ" => "JWT"];

    $payload = [
      "iss" => $creds['client_email'],
      "scope" => implode(' ', [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/drive.metadata.readonly'
      ]),
      "aud" => "https://oauth2.googleapis.com/token",
      "exp" => $now + 3600,
      "iat" => $now
    ];

    $base64 = function ($data) {
      return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    };

    $jwt_header = $base64($header);
    $jwt_payload = $base64($payload);

    $signature_input = $jwt_header . "." . $jwt_payload;

    openssl_sign($signature_input, $signature, $creds['private_key'], 'sha256');

    $jwt = $signature_input . "." . strtr(base64_encode($signature), '+/', '-_');

    $response = wp_remote_post(
      'https://oauth2.googleapis.com/token',
      [
        'body' => [
          'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
          'assertion'  => $jwt
        ]
      ]
    );

    if (is_wp_error($response)) {
      return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['access_token'] ?? false;
  } catch (Exception $e) {
     Wpform_gs_Connector_Utility::gs_debug_log(
      __METHOD__ . " Error while generating WPForms Token: " . $e->getMessage()
    );
    return null;
  }
}






       public function add_row($data)
   {
	   

      try {

         $token = $this->get_active_token();

         if (!$token || empty($data)) {
            return null;
         }

         $spreadsheetId = $this->getSpreadsheetId();
         $worksheet_id  = $this->getWorkTabId();

         $meta_response = wp_remote_get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets.properties",
            [
               'headers' => [
                  'Authorization' => 'Bearer ' . $token,
               ],
            ]
         );

         if (is_wp_error($meta_response)) {
            return null;
         }

         $meta_body  = json_decode(wp_remote_retrieve_body($meta_response), true);
         $sheet_name = '';

         foreach ($meta_body['sheets'] ?? [] as $sheet) {
            if ((int) $sheet['properties']['sheetId'] === (int) $worksheet_id) {
               $sheet_name = $sheet['properties']['title'];
               break;
            }
         }

         if (empty($sheet_name)) {
            return null;
         }

         $header_response = wp_remote_get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($sheet_name . '!1:1'),
            [
               'headers' => [
                  'Authorization' => 'Bearer ' . $token,
               ],
            ]
         );

         if (is_wp_error($header_response)) {
            return null;
         }

         $header_body   = json_decode(wp_remote_retrieve_body($header_response), true);
         $header_fields = $header_body['values'][0] ?? [];

         if (empty($header_fields)) {
            return null;
         }

         $insert_data = [];

         foreach ($header_fields as $name) {
            $insert_data[] = (isset($data[$name]) && $data[$name] != '') ? $data[$name] : '';
         }

         $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($sheet_name) . ":append?valueInputOption=USER_ENTERED",
            [
               'headers' => [
                  'Authorization' => 'Bearer ' . $token,
                  'Content-Type'  => 'application/json',
               ],
               'body' => wp_json_encode([
                  'values' => [$insert_data],
               ]),
            ]
         );

         if (is_wp_error($response)) {
            Wpform_gs_Connector_Utility::gs_debug_log('add_row Error: ' . $response->get_error_message());
            return null;
         }

         return true;
      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return null;
      }
   }

   /**
    * Resolve a sheet's title (tab name) from its numeric sheetId, via
    * spreadsheets.get. Shared by add_row() and get_header_row().
    *
    * @param string $token
    * @param string $spreadsheet_id
    * @param string $sheet_id
    * @return string Sheet title, or '' if not found.
    */
   private function get_sheet_title_by_id($token, $spreadsheet_id, $sheet_id)
   {
      $meta = self::request(
         'GET',
         sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets.properties',
            rawurlencode($spreadsheet_id)
         ),
         $token
      );

      if (is_wp_error($meta) || empty($meta['sheets'])) {
         return '';
      }

      foreach ($meta['sheets'] as $sheet) {
         $properties = isset($sheet['properties']) ? $sheet['properties'] : array();

         if (isset($properties['sheetId']) && (string) $properties['sheetId'] === (string) $sheet_id) {
            return isset($properties['title']) ? $properties['title'] : '';
         }
      }

      return '';
   }







   /*******************************************************************************/
   /********************************  VERSION 3.1 *********************************/
   /*******************************************************************************/


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

      try {

         $token = $this->access_token ? $this->access_token : $this->auth();

         if (!$token) {
            return false;
         }

         $sheet_title = $this->get_sheet_title_by_id($token, $spreadsheet_id, $tab_id);

         if (empty($sheet_title)) {
            return array();
         }

         $header_row = self::request(
            'GET',
            sprintf(
               'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
               rawurlencode($spreadsheet_id),
               rawurlencode($sheet_title . '!1:1')
            ),
            $token
         );

         if (is_wp_error($header_row)) {
            Wpform_gs_Connector_Utility::gs_debug_log($header_row->get_error_message());
            return array();
         }

         return !empty($header_row['values'][0]) ? $header_row['values'][0] : array();

      } catch (Exception $e) {
        Wpform_gs_Connector_Utility::gs_debug_log($e->getMessage());
         return array();
      }
   }

    /** 
    * GFGSC_googlesheet::gsheet_get_google_account
    * Get Google Account
    * @since 3.1 
    * @retun $user
    **/
   public function gsheet_get_google_account($token = '')
   {
         try {
         if (!$token) {
            return '';
         }

         $response = wp_remote_get(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            [
            'headers' => [
               'Authorization' => 'Bearer ' . $token
            ]
            ]
         );

         if (is_wp_error($response)) {
            return '';
         }

         $body = json_decode(wp_remote_retrieve_body($response), true);

         if (!empty($body['email'])) {
            return $body['email'];
         }

         return '';
      } catch (Exception $e) {
         Wpform_gs_Connector_Utility::gs_debug_log(
            __METHOD__ . " Error in fetching user info: \n " . $e->getMessage()
         );
         return false;
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

         $token = $this->token();

         if (!$token) {

            update_option('wpgs_email_account', '');

            return false;
         }

         $email = $this->gsheet_get_google_account($token);

         if (empty($email)) {

            $auth_method = get_option(
            'wcgsc_manual_setting',
            '0'
            );

            if ($auth_method == 0) {

            update_option(
               'wpgs_email_account',
               ''
            );

            if (class_exists('GSCWPFP_Error_Logs')) {

               Wpform_gs_Connector_Utility::gs_debug_log(
                  [
                  'error_type' => 'connected_email_empty',
                  'authentication_method' => 'Existing',
                  'message' => 'Failed to retrieve the connected Google account email address.',
                  ]
               );
            }
            }

            return false;
         }

         update_option(
            'wpgs_email_account',
            $email
         );

         return $email;

      } catch (Exception $e) {

         update_option(
            'wpgs_email_account',
            ''
         );

         Wpform_gs_Connector_Utility::gs_debug_log(
            __METHOD__ .
            ' Error fetching email: ' .
            $e->getMessage()
         );

         return false;
      }
   }






   /**
    * Revoke the stored OAuth2 token via Google's REST revoke endpoint
    * (replaces Google_Client::revokeToken()).
    *
    * @param string $token_json JSON-encoded token data (as stored in 'wpform_gs_token').
    * @return bool
    */
   public static function revokeToken_auto($token_json)
   {
      $token_data = json_decode($token_json, true);
      $token      = isset($token_data['access_token']) ? $token_data['access_token'] : '';

      if (empty($token)) {
         return false;
      }

      $response = wp_remote_post(
         'https://oauth2.googleapis.com/revoke',
         [
            'body' => [ 'token' => $token ],
         ]
      );

      if (is_wp_error($response)) {
         Wpform_gs_Connector_Utility::gs_debug_log($response->get_error_message());
         return false;
      }

      return 200 === wp_remote_retrieve_response_code($response);
   }


   private function token()
{
  try {
    $tokenJson = get_option('wpform_gs_token');
    $tokenData = json_decode($tokenJson, true);

    if (empty($tokenData)) {
      return false;
    }


    if (!isset($tokenData['expire'])) {

      update_option('wpforms_account_manual', '');

      return false;
    }

    if (time() > intval($tokenData['expire'])) {

      $newToken = $this->refresh($tokenData);

      if (!empty($newToken['access_token'])) {

        self::updateToken($newToken);

        return $newToken['access_token'];
      }

      update_option('wpforms_account_manual', '');

      return false;
    }

    return $tokenData['access_token'];

  } catch (Exception $e) {
      Wpform_gs_Connector_Utility::gs_debug_log(
      "Error in getting auto token " . $e->getMessage()
    );

  }
}




/**
 * REFRESH ACCESS TOKEN USING GOOGLE OAUTH
 *
 * This function refreshes the expired OAuth access token using the stored refresh token.
 * It calls Google's OAuth token endpoint and updates the WordPress option with the new token data.
 *
 * Steps:
 * 1. Validate refresh token exists
 * 2. Get client credentials
 * 3. Send request to Google OAuth API
 * 4. Validate response
 * 5. Store new access token + expiry time in database
 * 6. Return new access token
 *
 * @since 1.0.0
 */
private function refresh($token)
{
  try {
    if (empty($token['refresh_token'])) {
      return false;
    }
    $creds = self::creds();

    $response = wp_remote_post(
      'https://oauth2.googleapis.com/token',
      [
        'body' => [
          'client_id'     => $creds['client_id_web'],
          'client_secret' => $creds['client_secret_web'],
          'refresh_token' => $token['refresh_token'] ?? '',
          'grant_type'    => 'refresh_token'
        ]
      ]
    );
    if (is_wp_error($response)) {
      return false;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);


    if (!empty($body['access_token'])) {
      $body['refresh_token'] = $token['refresh_token'];
    }

    return $body;
  } catch (Exception $e) {
     Wpform_gs_Connector_Utility::gs_debug_log("Refresh Auto Token fail! - " . $e->getMessage());
  }
}

 public function check_sheet_access( $spreadsheet_id )
{
    try {

        if ( empty( $spreadsheet_id ) ) {
            return [
                'status'  => 0,
                'message' => 'Spreadsheet ID is empty',
            ];
        }

        $token = $this->get_active_token();

        if ( ! $token ) {
            return [
                'status'  => false,
                'message' => 'Token not found',
            ];
        }

        $response = wp_remote_get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?fields=spreadsheetId",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'status'  => false,
                'message' => 'Error: ' . $response->get_error_message(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( ! in_array( $response_code, [ 200, 201 ], true ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return [
                'status'  => false,
                'message' => 'Error: ' . ( $body['error']['message'] ?? 'Unknown error' ),
            ];
        }

        return [
            'status'  => true,
            'message' => 'Sheet is accessible with the given ID.',
        ];

    } catch ( Exception $e ) {
        Wpform_gs_Connector_Utility::wpform_debug_log( $e->getMessage() );
        return [
            'status'  => false,
            'message' => 'Error: ' . $e->getMessage(),
        ];
    }
}




}