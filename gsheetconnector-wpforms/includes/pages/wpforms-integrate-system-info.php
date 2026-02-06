<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

// 🔒 Prevent Subscribers from seeing sensitive info
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have permission to access this page.', 'gsheetconnector-wpforms' ) );
}

$WpForms_gs_tools_service = new WPforms_Gsheet_Connector_Init();
?>
<div class="system-statuswc">
  <div class="info-container">
    <h2 class="systemifo">
      <?php esc_html_e( 'System Info', 'gsheetconnector-wpforms' ); ?>
    </h2>

    <button onclick="copySystemInfo()" class="copy">
      <?php esc_html_e( 'Copy System Info to Clipboard', 'gsheetconnector-wpforms' ); ?>
    </button>

    <?php
    // Display sanitized system info output
    echo wp_kses_post( $WpForms_gs_tools_service->get_wpforms_system_info() );
    ?>
  </div>
</div>

<div class="system-Error">
  <div class="error-container">
    <h2 class="systemerror">
        <?php esc_html_e( 'Error Log', 'gsheetconnector-wpforms' ); ?>
    </h2>
    <p>
      <?php
      printf(
        /* translators: %s is the URL to the WP debug log guide */
        esc_html__(
          'If you have %1$s enabled, errors are stored in a log file. Here you can find the last 100 lines in reversed order so that you or the GSheetConnector support team can view it easily. The file cannot be edited here.',
          'gsheetconnector-wpforms'
        ),
        '<a href="https://www.gsheetconnector.com/how-to-enable-debugging-in-wordpress" target="_blank" rel="noopener noreferrer">WP_DEBUG_LOG</a>'
      );
      ?>
    </p>

    <button onclick="copyErrorLog()" class="copy">
        <?php esc_html_e( 'Copy Error Log to Clipboard', 'gsheetconnector-wpforms' ); ?>
    </button>

    <button class="clear-content-logs-elemnt">
        <?php esc_html_e( 'Clear', 'gsheetconnector-wpforms' ); ?>
    </button>

    <span class="clear-loading-sign-logs-elemnt">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
    <div class="clear-content-logs-msg-elemnt"></div>

    <input
      type="hidden"
      name="gs-ajax-nonce-ele"
      id="gs-ajax-nonce-ele"
      value="<?php echo esc_attr( wp_create_nonce( 'gs-ajax-nonce' ) ); ?>"
    />

    <div class="copy-message" style="display: none;">
      <?php esc_html_e( 'Copied', 'gsheetconnector-wpforms' ); ?>
    </div>

    <?php
    // Display sanitized error log output
    echo wp_kses_post( $WpForms_gs_tools_service->display_error_log() );
    ?>
  </div>
</div>