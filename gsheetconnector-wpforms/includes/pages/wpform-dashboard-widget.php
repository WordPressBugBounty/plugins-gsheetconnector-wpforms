<?php
/*
 * Wpform GS Dashboard Widget
 * @since 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit();
}
?>
<div class="dashboard-content">
   <?php
   $gs_connector_service = new WPforms_Googlesheet_Services();

   $forms_list = $gs_connector_service->get_forms_connected_to_sheet();
   ?>
   <div class="main-content">
      <div>
         <h3 style="font-weight:bold;"><?php echo __("WPForms Connected with Google Sheets.", "gsheetconnector-wpforms"); ?></h3>

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
            <th>Sheet URL</th>
        </tr>

        <?php
        if (!empty($forms_list)) {
            foreach ($forms_list as $key => $value) {
                $meta_value = maybe_unserialize($value->meta_value);
                $sheet_name = $sheet_id = '';

                // Get the sheet name and ID from metadata
                if (!empty($meta_value['gs_sheet_manuals_sheet_name'])) {
                    $sheet_name = $meta_value['gs_sheet_manuals_sheet_name'];
                }
                if (!empty($meta_value['gs_sheet_manuals_sheet_id'])) {
                    $sheet_id = $meta_value['gs_sheet_manuals_sheet_id'];
                }

                ?>
                <tr>
                    <!-- Form Name -->
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpforms-builder&view=fields&form_id=' . $value->ID)); ?>" target="_blank">
                            <?php echo esc_html($value->post_title); ?>
                        </a>
                    </td>

                    <!-- Sheet URL or "Not Connected" -->
                    <td>
                        <?php if ($sheet_name !== "" && $sheet_id !== "") { ?>
                            <a href="https://docs.google.com/spreadsheets/d/<?php echo esc_attr($sheet_id); ?>/edit" target="_blank">
                                <?php echo esc_html($sheet_name); ?>
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