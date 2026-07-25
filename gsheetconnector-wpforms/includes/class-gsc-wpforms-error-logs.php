<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create error log table
 * NOTE: Call this from main plugin file on activation
 */
 // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function gswpff_create_error_log_table()
{
    global $wpdb;

    $table = $wpdb->prefix . 'gscwpf_error_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        error_id VARCHAR(191) NOT NULL,
        code INT NOT NULL,
        message TEXT NOT NULL,
        details LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY error_id (error_id),
        KEY code (code)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

if (!class_exists('gswpff_error_logs')) {

    class gswpff_error_logs
    {

        public function __construct()
        {
            add_action('admin_post_gswpff_clear_logs', [$this, 'clear_logs']);
            add_action('admin_post_gswpff_download_logs', [$this, 'download_logs']);
        }

        /* =====================================================
     * STATIC ENTRY POINT
     * ===================================================== */
        public static function render_page()
        {
            (new self())->gswpff_render_page_html();
        }

        /* =====================================================
     * MAIN DB LOGGER
     * ===================================================== */
        public static function log_to_db($error_id, $code, $message, $details = [])
        {
            global $wpdb;

            // Table name is built from $wpdb->prefix (never request input).
            $table = $wpdb->prefix . 'gscwpf_error_logs';
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required table existence check during plugin setup/upgrade.
             if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) != $table ) {/// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required table existence check during plugin setup/upgrade.
                return false;
            }

            /* IMPORTANT FIX START */
            if (is_string($details)) {
                $decoded = json_decode($details, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $details = $decoded; // already JSON → convert to array
                } else {
                    $details = ['raw_error' => $details];
                }
            }

            /* IMPORTANT FIX END */
            return $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $table,
                [
                    'error_id'   => (string) $error_id,
                    'code'       => (int) $code,
                    'message'    => (string) $message,
                    'details'    => wp_json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%d', '%s', '%s', '%s']
            );
        }


        /**
         * Capture request context for error logging
         */
        public static function get_request_context()
        {
            return [
                'request_url'    => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '',
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
                'status_code'    => http_response_code(),
                'remote_ip'      => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
                'user_agent'     => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'referrer'       => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
                'timestamp'      => current_time('mysql'),
            ];
        }


        /* =====================================================
     * DEBUG → DB NORMALIZER
     * ===================================================== */
        public static function log_from_debug($error)
        {
            
            if (is_string($error)) {
                $decoded = json_decode($error, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $error = $decoded;
                }
            }

            if (is_array($error) || is_object($error)) {

                self::log_to_db(
                    'wpforms_gsheet_error',
                    500,
                    'WPForms Google Sheets Error',
                    (array) $error
                );
            } else {

                self::log_to_db(
                    'wpforms_gsheet_error',
                    500,
                    'WPForms Google Sheets Error',
                    [
                        'type'      => 'error',
                        'raw_error' => trim((string) $error),
                    ]
                );
            }
        }


        /* =====================================================
     * ADMIN PAGE
     * ===================================================== */
        public function gswpff_render_page_html()
        {
            global $wpdb;
            // Table name is built from $wpdb->prefix (never request input).
            $table = $wpdb->prefix . 'gscwpf_error_logs';

            $table_exists_cache_key = 'gscwpff_table_exists_' . $table;
            $table_exists           = wp_cache_get( $table_exists_cache_key, 'gscwpff' );

            if ( false === $table_exists ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not request input.
                $table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
                wp_cache_set( $table_exists_cache_key, $table_exists, 'gscwpff', 5 * MINUTE_IN_SECONDS );
            }

            if ( ! $table_exists ) {
                echo '<div class="notice notice-error"><p>Log table not found.</p></div>';
                return;
            }

            // Not cached: this admin page's entire purpose is showing the current debug log.
            // $table is derived from $wpdb->prefix, not request input.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $logs = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY created_at DESC",
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            

?>
            <div class="error-log-main shadow-box mt-40 p-30">

                <div class="error-log-head flex-wrap gap-20">
                    <div>
                        <div class="heading mt-0"><?php echo esc_html__("Error Log", 'gsheetconnector-wpforms'); ?> </div>
                        <p><?php echo esc_html__('Error logs are saved in the database. Please clear them regularly to avoid increasing the database size.', 'gsheetconnector-wpforms'); ?></p>
                    </div>

                    <?php if (!empty($logs)) { ?>
                        <div class="errorlog-button-list">
                            <a href="<?php echo esc_url(
                                            wp_nonce_url(
                                                admin_url('admin-post.php?action=gswpff_clear_logs'),
                                                'gsc_clear_logs_nonce'
                                            )
                                        ); ?>" class="button btn-logs"><?php echo esc_html__("Clear Logs", 'gsheetconnector-wpforms'); ?></a>

                            <a href="<?php echo esc_url(
                                            wp_nonce_url(
                                                admin_url('admin-post.php?action=gswpff_download_logs'),
                                                'gsc_download_logs_nonce'
                                            )
                                        ); ?>" class="button button-primary"><?php echo esc_html__("Download CSV", 'gsheetconnector-wpforms'); ?></a>

                            <button type="button" id="gscwpff-gsc-copy-error-logs" class="button btn-logs"><?php echo esc_html__("Copy Logs", 'gsheetconnector-wpforms'); ?></button>
                            <div class="gsc-copy-msg d-none"></div>
                        </div>
                    <?php } ?>


                </div> <!-- error head #end -->


                <div class="debug-log-div">
                    <table class="widefat striped error-log-table mt-30">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__("Date", 'gsheetconnector-wpforms'); ?></th>
                                <th><?php echo esc_html__("Error ID", 'gsheetconnector-wpforms'); ?></th>
                                <th><?php echo esc_html__("Code", 'gsheetconnector-wpforms'); ?></th>
                                <th><?php echo esc_html__("Message", 'gsheetconnector-wpforms'); ?></th>
                                <th><?php echo esc_html__("Details", 'gsheetconnector-wpforms'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs): foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php
                                            $format = get_option('date_format') . ' ' . get_option('time_format');

                                            // mysql2date() returns formatted date using WP timezone settings
                                            echo esc_html(mysql2date($format, $log['created_at'], false));
                                            ?></td>
                                        <td><?php echo esc_html($log['error_id']); ?></td>
                                        <td>
                                            <span class="sb-error-code" data-code="<?php echo esc_attr($log['code']); ?>">
                                                <?php echo esc_html($log['code']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($log['message']); ?></td>
                                        <td>
                                            <?php
                                            $details = json_decode($log['details'], true);

                                            if (json_last_error() === JSON_ERROR_NONE && is_array($details)) :
                                            ?>
                                                <div class="gsc-error-details">
                                                    <div class="more-error-display">

                                                        <?php
                                                        $decoded = json_decode($log['details'], true);
                                                        $display = '';

                                                        if (is_array($decoded) && !empty($decoded['raw_error'])) {

                                                            $raw = $decoded['raw_error'];

                                                            // Extract text after "message:"
                                                            if (strpos($raw, 'message:') !== false) {
                                                                $parts = explode('message:', $raw);
                                                                $display = trim(end($parts));
                                                            } else {
                                                                $display = $raw;
                                                            }
                                                        } else {
                                                            $display = $log['details'];
                                                        }

                                                        echo esc_html($display);
                                                        ?>
                                                    </div>
                                                </div>

                                            <?php else: ?>
                                                <?php echo esc_html($log['details']); ?>
                                            <?php endif; ?>




                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="5"  class="text-center"><?php echo esc_html__("No logs found", 'gsheetconnector-wpforms'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div> <!-- deubg logo div show / hide --->


                <script>
                    jQuery(document).ready(function($) {

                        var debugStateKey = 'debug_log_state';

                        // ---------- More info toggle (UNCHANGED) ----------
                        $('.more-error-display').each(function() {

                            var box = $(this);
                            var maxHeight = 75;

                            box.css({
                                'max-height': maxHeight + 'px',
                                'overflow': 'hidden'
                            });

                            var clone = box.clone();
                            clone.css({
                                'max-height': 'none',
                                'height': 'auto',
                                'position': 'absolute',
                                'visibility': 'hidden',
                                'overflow': 'visible'
                            });

                            $('body').append(clone);

                            if (clone.outerHeight() > maxHeight) {
                                if (box.next('.more-error-toggle').length === 0) {
                                    var link = $('<a href="#" class="more-error-toggle">More info</a>');
                                    box.after(link);
                                }
                            }

                            clone.remove();
                        });

                        $(document).on('click', '.more-error-toggle', function(e) {
                            e.preventDefault();

                            var link = $(this);
                            var box = link.prev('.more-error-display');

                            if (box.hasClass('expanded')) {
                                box.removeClass('expanded').css('max-height', '75px');
                                link.text('More info');
                            } else {
                                box.addClass('expanded').css('max-height', 'none');
                                link.text('Less info');
                            }
                        });

                        // ---------- Restore debug state on reload ----------
                        //if (localStorage.getItem(debugStateKey) === 'open') {
                        //$('.errorlog-button-list, .debug-log-div').show();
                        //$('.debug-logon')
                        // .removeClass('debug-logon')
                        //.addClass('debug-logoff')
                        //.text('-');
                        //}

                        // ---------- Debug log toggle (+ / -) ----------
                        //$(document).on('click', '.debug-logon, .debug-logoff', function(e) {
                        //e.preventDefault();

                        //$('.errorlog-button-list, .debug-log-div').toggle();

                        //if ($(this).hasClass('debug-logon')) {
                        //$(this).removeClass('debug-logon').addClass('debug-logoff').text('-');
                        //localStorage.setItem(debugStateKey, 'open');
                        // } else {
                        // $(this).removeClass('debug-logoff').addClass('debug-logon').text('+');
                        //localStorage.setItem(debugStateKey, 'closed');
                        //}
                        //});

                    });
                </script>



            </div>
<?php
        }

        /* =====================================================
     * ACTIONS
     * ===================================================== */
        public function clear_logs()
        {
            if (!current_user_can('manage_options')) {
                wp_die('Permission denied.');
            }

            check_admin_referer('gsc_clear_logs_nonce');

            global $wpdb;
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}gscwpf_error_logs");

            wp_safe_redirect(wp_get_referer());
            exit;
        }


        public static function log_js_error()
        {
            check_ajax_referer('gsc_log_js_error_nonce', 'security');

            if (!current_user_can('manage_options')) {
                wp_send_json_error();
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $log = isset($_POST['log']) ? wp_unslash($_POST['log']) : [];

            if (is_string($log)) {
                $decoded = json_decode($log, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log = $decoded;
                }
            }

            self::log_to_db(
                'js_error',
                intval($log['status'] ?? 400),
                sanitize_text_field($log['message'] ?? 'JavaScript Error'),
                [
                    'type'    => $log['type'] ?? 'js',
                    'request' => self::get_request_context(),
                    'payload' => $log,
                ]
            );

            wp_send_json_success();
        }


        public function download_logs()
        {

            if (! current_user_can('manage_options')) {
                wp_die(esc_html__('Permission denied.', 'gsheetconnector-wpforms'));
            }

            check_admin_referer('gsc_download_logs_nonce');

            global $wpdb;
            // Table name is built from $wpdb->prefix (never request input).
            $table = $wpdb->prefix . 'gscwpf_error_logs';

            // Not cached: a CSV export must reflect the current log contents.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not request input.
            $logs = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

            if (empty($logs)) {
                wp_safe_redirect(wp_get_referer());
                exit;
            }

            nocache_headers();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=gsc-error-logs.csv');

            $output = fopen('php://output', 'w');

            if (false === $output) {
                exit;
            }

            // CSV Header Row
            fputcsv($output, array('Date', 'Error ID', 'Code', 'Message', 'Details'));

            foreach ($logs as $log) {
                fputcsv($output, array(
                    $log['created_at'],
                    $log['error_id'],
                    $log['code'],
                    $log['message'],
                    $log['details'],
                ));
            }

            // fclose optional here (php://output auto closes)
            exit;
        }
    }

    new gswpff_error_logs();
}
add_action('wp_ajax_gsc_log_js_error', ['gswpff_error_logs', 'log_js_error']);