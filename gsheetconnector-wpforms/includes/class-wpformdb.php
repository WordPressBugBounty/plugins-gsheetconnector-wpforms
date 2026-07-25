<?php
/* Exit if accessed directly. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GS_WPFORMDB
{
    private $form_post_id;

    /** 
     * Constructor: Hooks into WPForms submission process to save entries into a custom database table.
     */
    public function __construct() {

    add_action( 'admin_init', array( $this, 'wpgs_sync_wpforms_entries_incremental' ) );

    /* For save DB  */
    add_action('admin_init', array($this, 'save_db_settings'));

    /* Form submission hook – WPForms Lite only (Pro stores entries differently). */
    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    add_action( 'wpforms_process_complete', array( $this, 'gsc_save_wpforms_submission' ), 10, 4 );

    }


    // --------------------------------------------------------
	//  ENTRY SYNC
	// --------------------------------------------------------

	/**
	 * Public wrapper kept for backward compatibility.
	 * Delegates to the incremental sync method.
	 */
	public function wpgs_import_wpforms_entries_when_active() {

		if ( ! function_exists( 'wpforms' ) ) {
			return;
		}

		$this->wpgs_sync_wpforms_entries_incremental();
	}

	/**
	 * Incrementally syncs WPForms Pro entries (stored in wpforms_entries)
	 * into the plugin's own submissions table.
	 *
	 * Only runs when WPForms Pro is active and the wpforms_entries table
	 * exists. Tracks the last synced entry_id in an option so each run
	 * only fetches new rows.
	 *
	 * Hooked to: admin_init
	 */
	public function wpgs_sync_wpforms_entries_incremental() {

		/* Only relevant for the WPForms Pro edition. */
		if ( ! is_plugin_active( 'wpforms/wpforms.php' ) ) {
			return;
		}

		global $wpdb;

		// Table names are built from $wpdb->prefix (never request input), so
		// interpolating them into SQL below is not a user-controlled input risk.
		$wpforms_table = $wpdb->prefix . 'wpforms_entries';
		$custom_table  = $wpdb->prefix . 'gscwpforms_submissions';

		/* Abort gracefully if the source table does not exist yet. */
		$table_exists_cache_key = 'gscwpff_table_exists_' . $wpforms_table;
		$table_exists           = wp_cache_get( $table_exists_cache_key, 'gscwpff' );

		if ( false === $table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpforms_table is derived from $wpdb->prefix, not request input.
			$table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpforms_table ) ) === $wpforms_table );
			wp_cache_set( $table_exists_cache_key, $table_exists, 'gscwpff', 5 * MINUTE_IN_SECONDS );
		}

		if ( ! $table_exists ) {
			Wpform_gs_Connector_Utility::gs_debug_log( 'wpforms_entries table does NOT exist. Sync skipped.' );
			return;
		}

		$last_synced = (int) get_option( 'wpgs_last_synced_wpforms_entry_id', 0 );

		// Not cached: this is an incremental sync cursor and must always read
		// the latest un-synced rows, so a cached/stale result would be wrong.
		// $wpforms_table is derived from $wpdb->prefix, not request input; $last_synced is bound via prepare().
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpforms_table} WHERE entry_id > %d ORDER BY entry_id ASC",
				$last_synced
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() is the WP-blessed write API; no core equivalent exists for this custom table.
			$wpdb->insert(
				$custom_table,
				array(
					'form_id'      => (int) $entry['form_id'],
					'entry_id'     => (int) $entry['entry_id'],
					'submitted_at' => $entry['date'],
					'form_data'    => $entry['fields'],
					'user_ip'      => $entry['ip_address'],
					'browser_info' => $entry['user_agent'],
					'is_read'      => 0,
				)
			);

			/* Advance the cursor so the next run picks up from here. */
			update_option( 'wpgs_last_synced_wpforms_entry_id', (int) $entry['entry_id'] );
		}
	}


    /**
    * Save toggle value into WordPress options
    */
   public function save_db_settings()
   {
      if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gsc_wpforms_db_save_settings' ) ) {
         return;
      }

      $enabled = isset($_POST['gsc_wpforms_db_enabled']) ? 1 : 0;
      update_option('gsc_wpforms_db_enabled', $enabled);
   }


    // --------------------------------------------------------
	// FORM SUBMISSION HANDLER (WPForms Lite)
	// --------------------------------------------------------

	/**
	 * Persists a WPForms Lite submission into the custom table.
	 *
	 * Hooked to: wpforms_process_complete (priority 10)
	 *
	 * @param array $fields    Processed form field data.
	 * @param array $entry     Raw entry data from $_POST.
	 * @param array $form_data Full form configuration array.
	 * @param int   $entry_id  ID of the newly created WPForms entry.
	 * @return bool            True on success, false on failure.
	 */
	public function gsc_save_wpforms_submission( $fields, $entry, $form_data, $entry_id ) {

    if ( ! is_plugin_active( 'wpforms-lite/wpforms.php' ) ) {
        return false;
    }

		global $wpdb;

		$table_name = $wpdb->prefix . 'gscwpforms_submissions';

		/* Extract the first email field value, if present. */
		$user_email = '';
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if (
					isset( $field['type'] ) &&
					$field['type'] === 'email' &&
					! empty( $field['value'] )
				) {
					$user_email = sanitize_email( $field['value'] );
					break;
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() is the WP-blessed write API; no core equivalent exists for this custom table.
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'entry_id'     => absint( $entry_id ),
				'form_id'      => absint( $form_data['id'] ?? 0 ),
				'submitted_at' => current_time( 'mysql' ),
				'form_data'    => wp_json_encode( $fields ),
				'user_email'   => $user_email,
				'user_ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'browser_info' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'is_read'      => 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $inserted === false ) {
			Wpform_gs_Connector_Utility::gs_debug_log(
				'WPForms DB Insert Failed. ' .
				'Entry ID: ' . absint( $entry_id ) . ', ' .
				'Form ID: '  . absint( $form_data['id'] ?? 0 ) . ', ' .
				'DB Error: ' . $wpdb->last_error
			);
			return false;
		}

		return true;
	}

    /**
     * Show UI Toggle + Save button
     */
    public function show_enable_disable_set()
    {
        $enabled = get_option('gsc_wpforms_db_enabled', 0);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameters (which entries list to display), no state change.
        $formId  = isset($_GET['formId']) ? intval($_GET['formId']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter, no state change.
        $entryId = isset($_GET['entryId']) ? intval($_GET['entryId']) : 0;

        /* =================================================
     SHOW SETTINGS ONLY ON MAIN LIST PAGE
     ================================================= */

        if ($formId === 0 && $entryId === 0) {
        ?>
            <form method="post" action="">
                  <?php wp_nonce_field('gsc_wpforms_db_save_settings'); ?>
						<div class="gs-form">
							<div class="gsc-access-wrapper">
								<div>
									<div class="heading mt-0">
										<?php echo esc_html__('WPForms Database Manager', 'gsheetconnector-wpforms'); ?>
									</div>
									<p>
										<?php echo esc_html__('Store and manage WPForms submissions securely inside your WordPress dashboard. Enable database storage to keep a backup of all form entries.', 'gsheetconnector-wpforms'); ?>
									</p>
									<div class="cf7-database-setting gsc-setting-text d-flex flex-wrap gap-20 justify-between align-center pt-15 pb-15 mt-30 bg-white">
										<div>
											<div class="systemifo fw-600 text-dark">
												<?php echo esc_html__('Enable Database Storage', 'gsheetconnector-wpforms'); ?>
											</div>
											<label class="fw-400">
												<?php echo esc_html__('Automatically save all form submissions to your WordPress database.', 'gsheetconnector-wpforms'); ?>
											</label>
										</div>
										 <div class="custom-check d-flex justify-between alien-center">

                               

                                <input
                                    type="checkbox"
                                    id="gsc_wpforms_db_enabled"
                                    class="woforms-gs-checkbox"
                                    name="gsc_wpforms_db_enabled"
                                    value="1"
                                    <?php checked( $enabled, 1 ); ?> />

                                <label for="gsc_wpforms_db_enabled" class="button-toggle"></label>

                            </div>
									</div>
								</div>
								<div class="gsc-access-info">
									<div class='para-heading fw-600 mb-20'>
										<?php esc_html_e('Data Management Guidelines', 'gsheetconnector-wpforms'); ?>
									</div>
									<ul class="mb-0">
										<li><?php esc_html_e('Enable storage before launching live forms', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('Regularly remove spam entries to maintain performance', 'gsheetconnector-wpforms'); ?></li>
										<li><?php esc_html_e('Delete unused form data to reduce database load', 'gsheetconnector-wpforms'); ?></li>
									</ul>
								</div>
							</div>
							<div class="select-info text-right mt-30">
                                <span class="loading-uninstall-free"></span>
								<button type="submit" class="btn btn-primary button-large gscwpfp-db-storage"><?php esc_html_e('Save Settings', 'gsheetconnector-wpforms'); ?></button>
							</div>
						</div>
					</form>

<?php
        }
        /* =================================================
     BELOW IS DATA DISPLAY LOGIC
     ================================================= */

        $gs_wpformsdb_setting = get_option('gsc_wpforms_db_enabled', 0);

        if ($gs_wpformsdb_setting == 1) {

            // 👉 Single entry details page
            if ($formId > 0 && $entryId > 0) {
                $form = wpforms()->form->get($formId);
                $form_name = $form ? $form->post_title : 'Unknown Form';

                $this->getWPFormEntryDetails($formId, $entryId, $form_name);
                return;
            }

            // 👉 Single form entries page
            if ($formId > 0 && $entryId == 0) {
                $form = wpforms()->form->get($formId);
                $form_name = $form ? $form->post_title : 'Unknown Form';

                $this->getWPFormEntries($formId, $form_name);
                return;
            }

            // 👉 All forms list page
            if ($formId == 0 && $entryId == 0) {
                $this->getAllWPFormsList();
                return;
            }
        }
    }


    public function getWPFormEntries($form_id, $form_name)
    {
        require_once plugin_dir_path(__FILE__) . 'wpgs-sheet-functions.php';
        require_once plugin_dir_path(__FILE__) . 'class-wpforms-entries-table.php';

        echo "<div class='gsc-api-box'>";

       

        // 🔹 Back Button
        $back_url = admin_url('admin.php?page=wpform-google-sheet-config&tab=wpform-database');
       
       echo '<a href="' .  esc_url($back_url) . '" class="back-btn btn-spacer btn btn-primary text-decoration-none d-inline-flex align-center gap-10">
            <svg class="back-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span>' . esc_html__('Back to Forms', 'gsheetconnector-wpforms') . '</span>
        </a>';

         // 🔹 Heading
        echo "<div class='heading'>" . esc_html($form_name) . "</div>";

        
     
        echo '<div id="gscwpff-send-csv-free-pro" class="gs-popup-overlay d-none">
            <div class="gs-popups position-relative-popup text-center">
                <button type="button" class="gscwpff-sts-pro-close gsc-pro-close">×</button>
                <div class="gsc-pro-section">
                    <div class="gsc-pro-card">
                        <div class="gsc-pro-headers">
                            <div class="gsc-modal-title">
                                ' . esc_html__('Want to send entries to Google Sheets?', 'gsheetconnector-wpforms') . '
                            </div>
                            <p class="gsc-modal-text">' . esc_html__('Export and sync your form submissions directly to Google Sheets to easily organize, filter, and manage your data in one place. Unlock this feature to simplify your workflow and access your entries anytime.', 'gsheetconnector-wpforms') . '</p>
                            <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" class="btn btn-primary text-decoration-none link-hover-white">' . esc_html__('Upgrade to Unlock', 'gsheetconnector-wpforms') . '</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';



        echo '<div id="gscwpff-free-pro-csv" class="gs-popup-overlay d-none">
            <div class="gs-popups position-relative-popup text-center">
                <button type="button" class="gscwpff-free-pro-csv gsc-pro-close">×</button>
                <div class="gsc-pro-section">
                    <div class="gsc-pro-card">
                        <div class="gsc-pro-headers">
                            <div class="gsc-modal-title">
                                ' . esc_html__('Want to download your form entries?', 'gsheetconnector-wpforms') . '
                            </div>
                            <p class="gsc-modal-text">' . esc_html__('Export your form entries as a CSV file and use them in Sheets. You can sort, filter, and manage everything more easily.', 'gsheetconnector-wpforms') . '</p>
                        </div>
                        <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" class="btn btn-primary text-decoration-none link-hover-white">' . esc_html__('Upgrade to Unlock', 'gsheetconnector-wpforms') . '</a>
                    </div>
                </div>
            </div>
        </div>';


        echo '<a href="#" id="gscwpff-free-csv" class="button">Export CSV</a>';

        $list_table = new WPForms_GSheet_Entries_Table($form_id);
        $list_table->prepare_items();

        // ✅ MUST BE POST FOR BULK ACTIONS
        echo '<form method="post">';

        // Required hidden fields
        echo '<input type="hidden" name="page" value="wpform-google-sheet-config" />';
        echo '<input type="hidden" name="tab" value="wpform-database" />';
        echo '<input type="hidden" name="formId" value="' . esc_attr($form_id) . '" />';

        /* ================= TOP NAV ================= */
        echo '<div class="tablenav top">';

        // RIGHT SIDE (Search box)
        $list_table->search_box('Search Entries', 'entry-search');

        echo '</div>';

        /* ================= TABLE ================= */
        $list_table->display();

        echo '</form>';
        echo '</div>';
    }


    /**
     * Renders a single entry's details, mirroring WPForms' own entry-view layout:
     * a "Fields" table with every submitted field, plus an "Entry Details" meta table.
     */
    public function getWPFormEntryDetails($form_id, $entry_id, $form_name)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gscwpforms_submissions';

        // $table is derived from $wpdb->prefix, not request input; $entry_id/$form_id are bound via prepare().
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND form_id = %d",
                $entry_id,
                $form_id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        echo "<div class='gsc-api-box'>";

       

        // 🔹 Back Button
        $back_url = admin_url("admin.php?page=wpform-google-sheet-config&tab=wpform-database&formId={$form_id}");

        echo '<a href="' . esc_url($back_url) . '" class="back-btn btn-spacer btn btn-primary text-decoration-none d-inline-flex align-center gap-10">
            <svg class="back-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span>' . esc_html__('Back to Entries', 'gsheetconnector-wpforms') . '</span>
        </a>';

         // 🔹 Heading
        echo "<div class='heading'>" . esc_html($form_name) . "</div>";

        if (empty($entry)) {
            echo '<p class="mt-30">' . esc_html__('Entry not found.', 'gsheetconnector-wpforms') . '</p>';
            echo '</div>';
            return;
        }

        $fields = json_decode($entry['form_data'], true);

        $date_value     = ! empty($entry['entry_date']) ? $entry['entry_date'] : ($entry['submitted_at'] ?? '');
        $formatted_date = $date_value ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_value)) : '';

        echo '<div class="gsc-entry-details-wrapper">';

        /* ================= FIELDS ================= */
        echo '<div class="gsc-entry-details-box shadow-box">';
        echo '<div class="gsc-entry-box-heading">' . esc_html__('Fields', 'gsheetconnector-wpforms') . '</div>';
        echo '<table class="full-table gsc-entry-details-table"><tbody>';

        if (is_array($fields)) {
            foreach ($fields as $field) {
                $label = $field['name'] ?? '';
                $value = $field['value'] ?? '';

                if ($label === '' && $value === '') {
                    continue;
                }

                echo '<tr>
                    <td class="gsc-entry-field-label">' . esc_html($label) . '</td>
                    <td class="gsc-entry-field-value">' . nl2br(esc_html($value)) . '</td>
                </tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';

        /* ================= ENTRY DETAILS (META) ================= */
        echo '<div class="gsc-entry-details-box shadow-box">';
        echo '<div class="gsc-entry-box-heading">' . esc_html__('Entry Details', 'gsheetconnector-wpforms') . '</div>';
        echo '<table class="full-table gsc-entry-details-table"><tbody>';

        $meta_rows = [];

        if (! empty($entry['entry_id'])) {
            $meta_rows[__('Entry ID', 'gsheetconnector-wpforms')] = $entry['entry_id'];
        }

        $meta_rows[__('Submitted On', 'gsheetconnector-wpforms')] = $formatted_date;
        $meta_rows[__('User Email', 'gsheetconnector-wpforms')]   = $entry['user_email'] ?? '';
        $meta_rows[__('Remote IP', 'gsheetconnector-wpforms')]    = $entry['user_ip'] ?? '';
        $meta_rows[__('User Agent', 'gsheetconnector-wpforms')]   = $entry['browser_info'] ?? '';

        foreach ($meta_rows as $label => $value) {
            echo '<tr>
                <td class="gsc-entry-field-label">' . esc_html($label) . '</td>
                <td class="gsc-entry-field-value">' . esc_html($value) . '</td>
            </tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>'; // .gsc-entry-details-wrapper
        echo '</div>'; // .gsc-api-box
    }


    public function getAllWPFormsList()
    {
        global $wpdb;

        /* ================= PAGINATION SETUP ================= */
        $per_page = 10;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, no state change.
        $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset   = ($paged - 1) * $per_page;

        /* ================= GET TOTAL FORMS ================= */
        $total_forms = wp_count_posts('wpforms')->publish;

        if ($total_forms == 0) {
            echo '<p>No forms found.</p>';
            return;
        }

        /* ================= FETCH FORMS ================= */
        $forms = get_posts([
            'post_type'      => 'wpforms',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (empty($forms)) {
            echo '<p>No forms found.</p>';
            return;
        }

        $total_pages = ceil($total_forms / $per_page);

        echo '<div class="gsc-api-box"><div class="gsc-table">'; ?>
        <div class="heading mt-0"><?php esc_html_e('WPForms List', 'gsheetconnector-wpforms'); ?></div>
        <p><?php echo esc_html__('View all WPForms that are currently saving submissions to the database. Select a form below to access and manage its stored entries.', 'gsheetconnector-wpforms'); ?></p>
        <?php 
        /* ================= TOP COUNTER (WP STYLE) ================= */
        echo '<div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num">'
            . intval($total_forms) . ' item' . ($total_forms > 1 ? 's' : '') .
            '</span>
            </div>
          </div>';

        /* ================= TABLE ================= */
        echo '<table class="widefat striped">';
        echo '<thead>
            <tr>
                <th>Name</th>
                <th style="width:120px;">Count</th>
            </tr>
          </thead><tbody>';

        foreach ($forms as $form) {

            $form_id = $form->ID;

            $count_cache_key = 'gscwpff_submission_count_' . $form_id;
            $count           = wp_cache_get( $count_cache_key, 'gscwpff' );

            if ( false === $count ) {
                // $wpdb->prefix is not request input; $form_id is bound via prepare().
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}gscwpforms_submissions WHERE form_id=%d",
                        $form_id
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                wp_cache_set( $count_cache_key, $count, 'gscwpff', MINUTE_IN_SECONDS );
            }

            $url = admin_url(
                "admin.php?page=wpform-google-sheet-config&tab=wpform-database&formId={$form_id}"
            );

            echo '<tr>
                <td>
                    <a href="' . esc_url($url) . '">
                        <strong>' . esc_html($form->post_title) . '</strong>
                    </a>
                </td>
                <td><strong>' . intval($count) . '</strong></td>
              </tr>';
        }

        echo '</tbody></table>';

        /* ================= PAGINATION (BOTTOM ONLY LINKS) ================= */
        if ($total_pages > 1) {

            $base_url = remove_query_arg('paged');

            echo '<div class="tablenav bottom">
                <div class="tablenav-pages">';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() escapes its own output.
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%', $base_url),
                'format'    => '',
                'prev_text' => '«',
                'next_text' => '»',
                'total'     => $total_pages,
                'current'   => $paged,
            ]);

            echo '</div></div>';
        }

        echo '</div></div>';
    }
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$gsheetconnector_wpforms_wpformsdb = new GS_WPFORMDB();
