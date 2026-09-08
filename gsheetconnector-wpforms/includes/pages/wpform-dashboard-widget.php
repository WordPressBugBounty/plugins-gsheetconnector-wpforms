<?php
/*
 * Wpform GS Dashboard Widget
 * @since 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit();
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="dashboard-content">
   <?php
   $gs_connector_service = new WPforms_Googlesheet_Services();

   $forms_list = $gs_connector_service->get_forms_connected_to_sheet();
$wpformdb_all_feeds = array();



     if (!empty($forms_list)) {
            foreach ($forms_list as $row) {

                $post_content = maybe_unserialize($row->post_content);
                $get_post_content_array = json_decode($post_content, true);

                $get_feed_array = isset($get_post_content_array['settings']['wpgs_spreadsheets'])
                    ? $get_post_content_array['settings']['wpgs_spreadsheets']
                    : [];

                if (empty($get_feed_array)) {
                    continue; // skip forms with no feeds set up
                }

                foreach ($get_feed_array as $value) {

                    $wpformdb_all_feeds[] = array(
                        'form_id'    => $row->ID,
                        'form_title' => !empty($row->post_title) ? $row->post_title : '',
                        'feed_name'  => isset($value['name']) ? $value['name'] : '',
                        'sheet_name' => isset($value['gs_sheet_manuals_sheet_name']) ? $value['gs_sheet_manuals_sheet_name'] : '',
                        'sheet_id'   => isset($value['gs_sheet_manuals_sheet_id'])   ? $value['gs_sheet_manuals_sheet_id']   : '',
                        'tab_id'     => isset($value['gs_sheet_manuals_sheet_tab_id'])     ? $value['gs_sheet_manuals_sheet_tab_id']     : '',
                        'tab_name'   => isset($value['gs_sheet_manuals_sheet_tab_name'])   ? $value['gs_sheet_manuals_sheet_tab_name']   : '',
                    );
                }
            }
        }
      
   ?>
   <div class="main-content">
      <div>
         <h3><?php echo esc_html__("WPForms Connected with Google Sheets.", "gsheetconnector-wpforms"); ?></h3>

         <style>
			  .widget-table { border:1px solid #eee; width:100%; }
			  .widget-table th { text-align: left; background: #eee; padding: 2px 3px; border-bottom: 1px solid #eee; }
			  .widget-table td { text-align: left; background: #fff; padding: 2px 3px; word-wrap: break-word; }
			  .widget-table td:nth-child(1) {width:50%;}
        </style>
        

    <table class="widget-table">
    <tbody>
        <tr>
            <th>Form Name</th>
            <th>Feed Name</th>
            <th>Sheet Name</th>
        </tr>
        <?php
        if (!empty($wpformdb_all_feeds)) {
            foreach ($wpformdb_all_feeds as $value) {


                // Get the sheet name and ID from metadata
                if (!empty($value['sheet_name'])) {
                    $sheet_name = $value['sheet_name'];
                }else{
                    $sheet_name = ''; 
                }

                if (!empty($value['sheet_id'])) {
                    $sheet_id = $value['sheet_id'];
                }else{
                    $sheet_id = '';
                }


                 if (!empty($value['tab_id'])) {
                    $tab_id = $value['tab_id'];
                }else{
                     $tab_id = '';
                }

                if (!empty($value['tab_name'])) {
                    $tab_name = $value['tab_name'];
                }else{
                    $tab_name ='';
                }

               

                ?>
                <tr>
                    <!-- Form Name -->
                    <td>
                        <?php echo esc_html($value['form_title']); ?>
                    </td>

                    <td>

                      <a href="<?php echo esc_url(admin_url('admin.php?page=wpforms-builder&view=settings&form_id=' . $value['form_id'].'&section=wf_googlesheets')); ?>" target="_blank">
                            <?php echo esc_html($value['feed_name']); ?>
                        </a>
                        
                    </td>

                    <!-- Sheet URL or "Not Connected" -->
                    <td>
                     <?php 
                   
                     
                     ?>
                        <?php if ($sheet_name != "" && $sheet_id != "") { ?>
                          
                            <a href="<?php echo esc_url('https://docs.google.com/spreadsheets/d/' . rawurlencode($sheet_id) . '/edit#gid=' . rawurlencode($value['tab_id'])); ?>" target="_blank">
                                <?php echo esc_html($sheet_name); ?><?php echo !empty($tab_name) ? ' — ' . esc_html($tab_name) : ''; ?>
                            </a>
                        <?php } else { ?>
                            <span><?php echo esc_html__("Not Connected", "gsheetconnector-wpforms"); ?></span>
                        <?php } ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="2"><?php echo esc_html__("No WPForms are Connected with Google Sheets.", "gsheetconnector-wpforms"); ?></td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
</div>
</div> <!-- main-content end -->
</div> <!-- dashboard-content end -->
<style type="text/css">
.postbox-header .hndle {
justify-content: flex-start !important;
}
</style>