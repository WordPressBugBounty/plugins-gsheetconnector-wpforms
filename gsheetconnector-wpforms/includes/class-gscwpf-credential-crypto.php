<?php
/* Exit if accessed directly */
if (!defined('ABSPATH')) {
   exit;
}

/**
 * Encrypts stored Google credentials (tokens, service account JSON, client secret) at rest with AES-256-GCM.
 *
 * Shared by GSheetConnector for WPForms Free and Pro (same prefix and key) so either plugin reads
 * the other's values. Works through option filters, so existing get_option()/update_option() calls
 * are unchanged and plaintext saved by older versions is still read as-is.
 *
 * @since 4.2.4 (Free) / 4.0.3 (Pro)
 */
if (!class_exists('GSCWPF_Credential_Crypto')) {

   class GSCWPF_Credential_Crypto
   {
      const PREFIX        = 'gscwpfenc:v1:';
      const MIGRATED_FLAG = 'gscwpf_credentials_encrypted';

      /** Options holding credentials. */
      const OPTIONS = array(
         'wpform_gs_token',
         'gs_wpforms_token_manual',
         'gs_wpforms_secret_id',
         'gs_wpformspro_service_account_json',
      );

      private static $registered = false;

      /**
       * Hook encryption on save and decryption on read; encrypt existing values once.
       */
      public static function register()
      {
         if (self::$registered) {
            return;
         }
         self::$registered = true;

         foreach (self::OPTIONS as $option) {
            // sanitize_option_{name} runs in both add_option() and update_option().
            add_filter('sanitize_option_' . $option, array(__CLASS__, 'encrypt'), 999);
            add_filter('option_' . $option, array(__CLASS__, 'decrypt'), 1);
         }

         add_action('admin_init', array(__CLASS__, 'maybe_encrypt_existing'));
      }

      public static function is_available()
      {
         return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && function_exists('random_bytes')
            && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
      }

      public static function is_encrypted($value)
      {
         return is_string($value) && 0 === strpos($value, self::PREFIX);
      }

      private static function key()
      {
         return hash('sha256', wp_salt('auth'), true);
      }

      /**
       * Encrypt a string; empty, non-string or already encrypted values are returned unchanged.
       */
      public static function encrypt($value)
      {
         if (!is_string($value) || '' === $value || self::is_encrypted($value) || !self::is_available()) {
            return $value;
         }

         $iv         = random_bytes(12);
         $tag        = '';
         $ciphertext = openssl_encrypt($value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

         if (false === $ciphertext) {
            return $value;
         }

         return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
      }

      /**
       * Decrypt a value from encrypt(); plaintext is returned unchanged, an unreadable value as ''.
       */
      public static function decrypt($value)
      {
         if (!self::is_encrypted($value)) {
            return $value;
         }

         if (!function_exists('openssl_decrypt')) {
            return '';
         }

         $decoded = base64_decode(substr($value, strlen(self::PREFIX)), true);
         if (false === $decoded || strlen($decoded) < 29) {
            return '';
         }

         $plain = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));

         return false === $plain ? '' : $plain;
      }

      /**
       * Encrypt credentials saved by older versions (once per site).
       */
      public static function maybe_encrypt_existing()
      {
         if (get_option(self::MIGRATED_FLAG) || !self::is_available()) {
            return;
         }

         foreach (self::OPTIONS as $option) {
            $value = get_option($option); // decrypted (or plaintext) value
            if (is_string($value) && '' !== $value) {
               update_option($option, $value); // re-saved through the encrypt filter
            }
         }

         update_option(self::MIGRATED_FLAG, 1);
      }

      /**
       * Store credentials as plaintext again (on plugin deactivation), so an older Free/Pro
       * version activated next can still read them. Re-encrypted on the next admin page load.
       */
      public static function restore_plaintext()
      {
         foreach (self::OPTIONS as $option) {
            $value = get_option($option); // decrypted
            if (!is_string($value) || '' === $value) {
               continue;
            }
            // Without both filters update_option() compares against the stored (encrypted) value and writes plaintext.
            remove_filter('sanitize_option_' . $option, array(__CLASS__, 'encrypt'), 999);
            remove_filter('option_' . $option, array(__CLASS__, 'decrypt'), 1);
            update_option($option, $value);
            add_filter('sanitize_option_' . $option, array(__CLASS__, 'encrypt'), 999);
            add_filter('option_' . $option, array(__CLASS__, 'decrypt'), 1);
         }

         delete_option(self::MIGRATED_FLAG);
      }

      /**
       * restore_plaintext() for the current site, or for every site on a multisite network deactivation.
       */
      public static function restore_plaintext_for_deactivation($network_wide = false)
      {
         if (!$network_wide || !is_multisite()) {
            self::restore_plaintext();
            return;
         }
         foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
            switch_to_blog($site_id);
            self::restore_plaintext();
            restore_current_blog();
         }
      }
   }
}
