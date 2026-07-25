     <?php
        /* Exit if accessed directly */
        if (!defined('ABSPATH')) {
            exit;
        }


        if (! class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }


        class WPForms_GSheet_Entries_Table extends WP_List_Table
        {

            protected $form_id;
            protected $date_column = 'submitted_at'; // default fallback


            function __construct($form_id)
            {
                global $wpdb;
                $this->form_id = $form_id;

                // Auto-detect if entry_date exists
                $table_columns_cache_key = 'gscwpff_table_columns_' . $wpdb->prefix . 'gscwpforms_submissions';
                $columns                 = wp_cache_get( $table_columns_cache_key, 'gscwpff' );

                if ( false === $columns ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- DESCRIBE has no core API; $wpdb->prefix is not request input.
                    $columns = $wpdb->get_results("DESCRIBE {$wpdb->prefix}gscwpforms_submissions", ARRAY_A);
                    wp_cache_set( $table_columns_cache_key, $columns, 'gscwpff', 5 * MINUTE_IN_SECONDS );
                }

                foreach ($columns as $col) {
                    if ($col['Field'] === 'entry_date') {
                        $this->date_column = 'entry_date';
                        break;
                    }
                }

                parent::__construct([
                    'singular' => 'entry',
                    'plural'   => 'entries',
                    'ajax'     => false,
                ]);
            }


            function column_cb($item)
        {
            return sprintf(
                '<input type="checkbox" name="entry_ids[]" value="%s" />',
                esc_attr($item['id'])
            );
        }

        


            /** HEADER COLUMNS (dynamic first 4 fields) **/
            function get_columns()
            {
                global $wpdb;

                $columns = [
                    'cb' => '<input type="checkbox" />'
                ];

                // Get latest row to detect field labels
                $columns_row_cache_key = 'gscwpff_latest_row_' . $this->form_id;
                $row                   = wp_cache_get( $columns_row_cache_key, 'gscwpff' );

                if ( false === $row ) {
                    // $wpdb->prefix and $this->date_column (constrained to 'submitted_at'/'entry_date' in the constructor) are not request input; $this->form_id is bound via prepare().
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT form_data FROM {$wpdb->prefix}gscwpforms_submissions
                        WHERE form_id=%d
                        ORDER BY {$this->date_column} DESC
                        LIMIT 1",
                            $this->form_id
                        ),
                        ARRAY_A
                    );
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    wp_cache_set( $columns_row_cache_key, $row, 'gscwpff', MINUTE_IN_SECONDS );
                }

                if (!empty($row)) {
                    $fields = json_decode($row['form_data'], true);

                    $limit = 4; //👈 show only first 4
                    $count = 0;

                    foreach ($fields as $fid => $field) {
                        if ($count >= $limit) break;

                        $label = $field['name'] ?? "Field {$fid}";
                        $columns["field_$fid"] = esc_html($label);
                        $count++;
                    }
                }

                $columns['date'] = 'Date';
                $columns['sendss'] = 'Send to SpreadSheet'; 


                return $columns;
            }

            function column_sendss($item)
                {
                    return sprintf(
                        '<button type="button" class="button button-outline gscwpff-send-to-sheet" data-entry-id="%d">
                            %s
                        </button>',
                        esc_attr($item['id']),
                        esc_html__('Send To SpreadSheet', 'gsheetconnector-wpforms')
                    );
                }
            /** BULK ACTIONS **/
            function get_bulk_actions()
            {
                return [
                    'delete'      => __('Delete', 'gsheetconnector-wpforms'),
                    'mark_read'   => __('Mark Read', 'gsheetconnector-wpforms'),
                    'mark_unread' => __('Mark Unread', 'gsheetconnector-wpforms'),
                    'send_sheet'  => __('Send to Sheet', 'gsheetconnector-wpforms'),
                ];
            }

      

            
            function column_send($item)
            {
                $form_id  = intval($this->form_id);
                $entry_id = intval($item['id']);

                $nonce = wp_create_nonce('wpgs_ajax_nonce');

                return sprintf(
                    '<button
            class="button action sendToGoogleSheetWPFORMS"
            data-formid="%d"
            data-entryid="%d"
            data-nonce="%s">
            Send To Spreadsheet
        </button>',
                    $form_id,
                    $entry_id,
                    esc_attr($nonce)
                );
            }
            /** DEFAULT PRINT **/
            // function column_default($item, $column_name)
            // {
            //     return esc_html($item[$column_name] ?? '');
            // }
            function column_default($item, $column_name)
            {
                $value = esc_html($item[$column_name] ?? '');

                // Only apply bold on form fields (field_*)
                if (
                    strpos($column_name, 'field_') === 0 &&
                    (int) ($item['is_read'] ?? 0) === 0
                ) {
                    $value = $value ; // UNREAD
                }

                // Link every field column through to the entry details page.
                if (strpos($column_name, 'field_') === 0) {
                    $entry_url = add_query_arg(
                        [
                            'page'    => 'wpform-google-sheet-config',
                            'tab'     => 'wpform-database',
                            'formId'  => $this->form_id,
                            'entryId' => (int) $item['id'],
                        ],
                        admin_url('admin.php')
                    );

                    $value = '<a href="' . esc_url($entry_url) . '" class="row-title gscwpff-ru">' . $value . '</a>';
                }

                return $value; // READ
            }

            public function process_bulk_action()
            {
                if (empty($_POST['entry_ids'])) {
                    return;
                }

                // Nonce field is auto-rendered by WP_List_Table::display_tablenav()
                // using the same 'plural' arg ('entries') this table was constructed with.
                check_admin_referer( 'bulk-' . $this->_args['plural'] );

                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die( esc_html__( 'You do not have permission to perform this action.', 'gsheetconnector-wpforms' ) );
                }

                global $wpdb;

                $entry_ids = array_map('absint', (array) $_POST['entry_ids']);
                $entry_ids = array_filter($entry_ids);
                if (empty($entry_ids)) {
                    return;
                }

                $action = $this->current_action();
                if (!$action) {
                    return;
                }

                $table        = $wpdb->prefix . 'gscwpforms_submissions';
                $placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));

                // $table is derived from $wpdb->prefix, not request input. $placeholders is a
                // run-time-built string of '%d' repeated once per id, so PHPCS's static
                // analysis can't verify it against $entry_ids the way it can a literal
                // placeholder list -- but every id in $entry_ids is still bound through
                // $wpdb->prepare() below, so the query is properly parameterized.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                switch ($action) {

                    case 'delete':
                        $wpdb->query(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $entry_ids )
                        );
                        break;

                    case 'mark_read':
                        $wpdb->query(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->prepare( "UPDATE {$table} SET is_read = 1 WHERE id IN ({$placeholders})", $entry_ids )
                        );
                        break;

                    case 'mark_unread':
                        $wpdb->query(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->prepare( "UPDATE {$table} SET is_read = 0 WHERE id IN ({$placeholders})", $entry_ids )
                        );
                        break;
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

                    case 'send_sheet':
                        foreach ($entry_ids as $entry_id) {
                            wpgs_sync_from_db_internal($this->form_id, $entry_id);
                        }
                        break;
                }
            }


            function wpgs_send_single_entry_to_sheet($form_id, $entry_id)
            {
                // 👉 Call your existing function
                // This should contain your Google Sheet insert logic

                if (function_exists('wpgs_sync_from_db_internal')) {
                    wpgs_sync_from_db_internal($form_id, $entry_id);
                }
            }


            function prepare_items()
            {
                global $wpdb;
                // $this->process_bulk_action();
                $per_page     = 10;
                $current_page = $this->get_pagenum();
                $offset       = ($current_page - 1) * $per_page;

                // Bulk actions


                /* ===== SEARCH ===== */
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search/filter parameter, no state change.
                $search = '';
                // phpcs:ignore	WordPress.Security.NonceVerification.Recommended
                if (!empty($_REQUEST['s'])) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $search = sanitize_text_field(wp_unslash($_REQUEST['s']));
                }

                /* ===== BASE SQL ===== */
                $where = "WHERE form_id = %d";
                $params = [$this->form_id];

                if ($search !== '') {
                    $like = '%' . $wpdb->esc_like($search) . '%';

                    $where .= " AND (
            form_data LIKE %s
            OR user_email LIKE %s
            OR entry_id LIKE %s
                )";

                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }

                $sql = "
                SELECT *
                FROM {$wpdb->prefix}gscwpforms_submissions
                {$where}
                ORDER BY {$this->date_column} DESC
                LIMIT %d OFFSET %d
            ";

                $params[] = $per_page;
                $params[] = $offset;

                // $wpdb->prefix and $this->date_column (constrained to 'submitted_at'/'entry_date') are not request input; every actual value in $where/$params is %d/%s-bound via prepare().
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
                $entries = $wpdb->get_results(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare($sql, $params),
                    ARRAY_A
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

                /* ===== BUILD ROWS ===== */
                $data = [];

                


                foreach ((array) $entries as $entry) {
                    $raw = json_decode($entry['form_data'], true);

                    $row = [
                        'id'      => $entry['id'],
                        'is_read' => $entry['is_read'] ?? 0,
                      'date'    => !empty($entry[$this->date_column])
                        ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry[$this->date_column]))
                        : '',
                    ];

                    if (is_array($raw)) {
                        $count = 0;
                        foreach ($raw as $fid => $field) {
                            if ($count >= 4) break;
                            $row["field_$fid"] = $field['value'] ?? '';
                            $count++;
                        }
                    }

           

                    $data[] = $row;
                }

                $this->items = $data;

                /* ===== COUNT (IMPORTANT FOR PAGINATION) ===== */
                $count_sql = "
                    SELECT COUNT(*)
                    FROM {$wpdb->prefix}gscwpforms_submissions
                    {$where}
                ";

                // $wpdb->prefix is not request input; every actual value in $where/$params is %d/%s-bound via prepare(). Not cached: pagination totals must reflect the just-applied search/filter.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
                $total_items = (int) $wpdb->get_var(
                    $wpdb->prepare($count_sql, array_slice($params, 0, count($params) - 2))
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

                $this->_column_headers = [$this->get_columns(), [], []];

                $this->set_pagination_args([
                    'total_items' => $total_items,
                    'per_page'    => $per_page,
                    'total_pages' => ceil($total_items / $per_page),
                ]);
            }
        }
