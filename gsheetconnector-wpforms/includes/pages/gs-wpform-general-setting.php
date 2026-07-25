<?php
/* Exit if accessed directly */
if (!defined('ABSPATH')) {
   exit();
}
?>
<!-- uninstall plugin settings -->
<div class="wrap w-100 m-0">
   <div class="system-general_setting inner-wrap w-100 bg-white p-40">
      <div class="info-container">
         <form method="post">
            <div class="gsc-access-wrapper">

               <div>
                  <div class="heading mt-0">
                     <?php echo esc_html__('Plugin Preferences', 'gsheetconnector-wpforms'); ?>
                  </div>

                  <p>
                     <?php echo esc_html__('Manage how plugin settings and data are handled when the plugin is uninstalled.', 'gsheetconnector-wpforms'); ?>
                  </p>

                  <div class="gsc-setting-text d-flex justify-between align-center pt-15 pb-15 mt-30 bg-white">

                     <div>
                        <div class="systemifo fw-600 text-dark">
                           <?php echo esc_html__('Delete Plugin Data on Uninstall', 'gsheetconnector-wpforms'); ?>
                        </div>

                        <label for="gscwpff_wpforms_uninstall_settings_free" class="fw-400">
                           <?php echo esc_html__('Removes all plugin data (options, metadata) when the plugin is deleted.', 'gsheetconnector-wpforms'); ?>
                        </label>
                     </div>

                     <div>
                        
                        <?php 
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                        $get_gscwpff_uninstall_setting =  get_option('gscwpff_wpforms_uninstall_settings_free'); ?>
                        <input type="hidden" name="gscwpff_wpforms_uninstall_settings_free" value="No">

                        <div class="custom-check">
                           <input
                              type="checkbox"
                              class="check-toggle"
                              id="gscwpff_wpforms_uninstall_settings_free"
                              name="gscwpff_wpforms_uninstall_settings_free"
                              value="Yes" <?php checked(1, $get_gscwpff_uninstall_setting); ?>>

                           <label for="gscwpff_wpforms_uninstall_settings_free" class="button-toggle"></label>
                        </div>
                        <input type="hidden" name="gsc-wpforms-setting-ajax-nonce" id="gsc-wpforms-setting-ajax-nonce"
                        value="<?php echo esc_attr(wp_create_nonce('gsc-wpforms-setting-ajax-nonce')); ?>" />

                     </div>
                  </div>
               </div>


               <div class="gsc-access-info">

                  <div class="para-heading fw-600 mb-20">
                     <?php esc_html_e('Uninstall Data Notice', 'gsheetconnector-wpforms'); ?>
                  </div>

                  <ul>
                     <li><?php echo esc_html__('Enable this option only if you want a complete cleanup', 'gsheetconnector-wpforms'); ?></li>
                     <li><?php echo esc_html__('All plugin settings and data will be permanently removed', 'gsheetconnector-wpforms'); ?></li>
                     <li><?php echo esc_html__('Incorrect settings can delete all sensitive form and user data', 'gsheetconnector-wpforms'); ?></li>
                  </ul>

               </div>

            </div>

            <div class="text-right mt-30">

               <span class="loading-uninstall-free"></span>

               <input
                  type="button"
                  class="btn btn-primary gscwpff-uninstall-settings-save-free"
                  name="gsc_wpforms_save_uninstall_settings_free"
                  value="<?php echo esc_html__('Save Settings', 'gsheetconnector-wpforms'); ?>" />

               <div id="gsc_wpforms-uninstall-msg-free" class="gsc-msg d-none fw-400 text-dark text-center pt-10 pb-10 manual-margin"></div>

               <input
                  type="hidden"
                  name="gsc-wpforms-setting-ajax-nonce"
                  id="gsc-wpforms-setting-ajax-nonce"
                  value="<?php echo esc_attr(wp_create_nonce('gsc-wpforms-setting-ajax-nonce')); ?>" />
            </div>
         </form>
      </div>
   </div>
</div>


<div id="gscwpff-confirm-uninstall-data-popup-free" class="gscwpff-popup-overlay d-none">
   <div class="gscwpff-popup text-center">

      <div class="gsc-modal-title gsc-uninstall-modal-title"><?php echo esc_html__('Confirm Data Deletion', 'gsheetconnector-wpforms'); ?></div>
      <p class="gsc-modal-text"><?php echo esc_html__('Enabling this option will permanently delete all plugin data, including settings and integrations, when the plugin is uninstalled.', 'gsheetconnector-wpforms'); ?>
      </p>
      <p class="gsc-modal-text"><?php echo esc_html__('If you plan to upgrade from Free to Pro, you may lose your existing configuration data.', 'gsheetconnector-wpforms'); ?>
      </p>

      <p class="gsc-modal-text"><?php echo esc_html__('Proceed only if you want a complete cleanup. This action cannot be undone.', 'gsheetconnector-wpforms'); ?>
      </p>


      <div class="popup-actions d-flex justify-center gap-10">
         <button type="button" class="btn deactivate-btn" id="gscwpff-cancel-uninstall-free">
            <?php echo esc_html__('Cancel', 'gsheetconnector-wpforms'); ?>
         </button>
         <button type="button" class="btn btn-primary" id="gscwpff-confirm-enable-uninstall-free">
            <?php echo esc_html__('Enable Deletion', 'gsheetconnector-wpforms'); ?>
         </button>
      </div>
   </div>
</div>