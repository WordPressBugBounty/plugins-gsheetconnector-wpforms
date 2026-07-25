  <?php
    /* Exit if accessed directly */
    if (!defined('ABSPATH')) {
        exit;
    }


    /* ================= HELPERS ================= */

    function wpgs_normalize_header_key($header)
    {
        return str_replace(' ', '___', trim($header));
    }

    function wpgs_get_column_letter($index)
    {
        $index++;
        $letter = '';

        while ($index > 0) {
            $temp = ($index - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $index = (int)(($index - $temp - 1) / 26);
        }

        return $letter;
    }

    /* ================= MAIN FUNCTION ================= */

    function wpgs_sync_from_db_internal($form_id, $entry_id)
    {
        global $wpdb;

        /* ================= DB ================= */
        // Not cached: this fetches the specific entry being synced right now and must be current.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->prefix is not request input; $form_id/$entry_id are bound via prepare().
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gscwpforms_submissions
             WHERE form_id=%d AND id=%d",
                $form_id,
                $entry_id
            ),
            ARRAY_A
        );

        if (!$row) return;

        $submission_data = json_decode($row['form_data'], true);
        if (empty($submission_data)) return;

        /* ================= FORM ================= */
        $form = get_post($form_id);
        if (!$form) return;

        $form_data = wpforms_decode($form->post_content);
        $feeds = $form_data['settings']['wpgs_spreadsheets'] ?? [];
        if (empty($feeds)) return;

        $googlesheet   = reset($feeds);
        $smart_tag_map = $googlesheet['headers'] ?? [];

        /* ================= GOOGLE AUTH ================= */
        include_once WPFORMS_GOOGLESHEET_ROOT . '/lib/google-sheets.php';

        $doc = new WPFGSC_googlesheet();
        $doc->auth();

        $service = new Google_Service_Sheets($doc->getInstance());

        /* ================= SHEET SETTINGS ================= */
        $builder = new WPGS_FormBuilder();
        $sheet   = $builder->get_google_sheet_settings($googlesheet);

        if (empty($sheet['spreadsheet_id']) || empty($sheet['tab_title'])) return;

        $spreadsheet_id = $sheet['spreadsheet_id'];
        $sheet_name     = trim($sheet['tab_title']);

        /* ================= READ ACTUAL HEADERS ================= */
        $header_response = $service->spreadsheets_values->get(
            $spreadsheet_id,
            $sheet_name . '!A1:ZZ1'
        );

        $sheet_headers = $header_response->getValues()[0] ?? [];
        if (empty($sheet_headers)) return;

        $header_count = count($sheet_headers);
        $last_col     = wpgs_get_column_letter($header_count - 1);

        /* ================= FIND EXISTING ENTRY OR NEXT ROW ================= */
        $colA = $service->spreadsheets_values
            ->get($spreadsheet_id, $sheet_name . '!A:A')
            ->getValues();

        $target_row = null;

        if (!empty($colA)) {
            foreach ($colA as $i => $rowA) {
                if ($i === 0) continue; /*  header */
                if (!empty($rowA[0]) && (int)$rowA[0] === (int)$entry_id) {
                    $target_row = $i + 1;
                    break;
                }
            }
        }

        /* If entry not found → NEXT EMPTY ROW */
        if ($target_row === null) {
            $target_row = count($colA) + 1;
        }

        /* ================= BUILD ROW DATA (MATCH HEADER COUNT) ================= */
        $row_data = [];

        foreach ($sheet_headers as $header) {

            if ($header === 'Entry ID') {
                $row_data[] = $entry_id;
                continue;
            }

            $value = '';
            $normalized = wpgs_normalize_header_key($header);

            /* SMART TAG */
            if (isset($smart_tag_map[$normalized])) {
                $value = wpforms()->obj('smart_tags')->process(
                    $smart_tag_map[$normalized],
                    $form_data,
                    $submission_data,
                    $entry_id
                );
            }
            /* FORM FIELD */ else {
                foreach ($submission_data as $field) {
                    if (($field['name'] ?? '') === $header) {
                        $value = is_array($field['value'])
                            ? implode(', ', $field['value'])
                            : $field['value'];
                        break;
                    }
                }
            }

            $row_data[] = $value;
        }

        /* ================= UPDATE ONLY (NO APPEND) ================= */
        $range = $sheet_name . '!A' . $target_row . ':' . $last_col . $target_row;

        $service->spreadsheets_values->update(
            $spreadsheet_id,
            $range,
            new Google_Service_Sheets_ValueRange([
                'values' => [$row_data],
            ]),
            [
                'valueInputOption' => 'USER_ENTERED',
            ]
        );
    }
