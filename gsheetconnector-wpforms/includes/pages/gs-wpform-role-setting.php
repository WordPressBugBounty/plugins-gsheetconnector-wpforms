<?php

/**
 * Settings class for Google Sheet Role settings (WPForms)
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit;
}

/**
 * GSCWPForms_Role_Settings Class
 * @since 1.0.0
 */

class GSCWPForms_Role_Settings
{

   /**
    * @var string group name
    */
   protected $gsc_group_name = 'gscwpforms-settings';

   /**
    * @var string roles that can access Google Sheet page
    */
   protected $gsc_wpforms_page_roles_setting_option_name = 'gscwpforms_tab_roles_setting';

   /**
    * Set things up.
    */
   public function __construct()
   {
      add_action('admin_init', array($this, 'init_settings'));
   }

   /**
    * Register settings for WPForms Google Sheets access roles
    */

   public function init_settings()
   {
      register_setting(
         'gscwpforms-settings',
         $this->gsc_wpforms_page_roles_setting_option_name,
         array($this, 'gscwpforms_validate_access_roles')
      );
   }

   /**
    * Validate and sanitize roles
    */

   public function gscwpforms_validate_access_roles($selected_roles)
   {

      $roles = array();
      $system_roles = GSCWPForms_Connector_Utility::instance()->get_system_roles();

      if ($selected_roles && count($selected_roles) > 0) {

         foreach ($system_roles as $role => $display_name) {

            if (is_array($selected_roles) && in_array(esc_attr($role), $selected_roles)) {
               $roles[$role] = $display_name;
            }
         }
      }

      return $roles;
   }

   /**
    * Display role settings page
    */

   public function add_role_setting_page()
   {

      if (!current_user_can('administrator')) {

         echo '<span class="per_not_allo">' .
            esc_html__("Permission Not Allowed", 'gsheetconnector-wpforms') .
            '</span>';

         return;
      }

      $gscwpforms_page_roles = get_option($this->gsc_wpforms_page_roles_setting_option_name);

?>

      <form id="gsc_wpforms_settings_form" method="post" action="options.php">

         <?php
         settings_fields('gscwpforms-settings');
         settings_errors();
         ?>

         <!-- PRO Promotion -->
         <div class="gsc-pro-promo ml-15 mr-pro-15">

            <div class="gsc-pro-header">

               <div class="gsc-pro-icon">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="2">
                     <path d="M5 19c-1 1-2 1-3 1 0-1 0-2 1-3l4-4"></path>
                     <path d="M14 3l7 7"></path>
                     <path d="M9 18l-4 4"></path>
                     <path d="M15 3c2 0 6 4 6 6-2 2-6 6-8 8l-6-6c2-2 6-8 8-8z"></path>
                     <circle cx="15" cy="9" r="1.5"></circle>
                  </svg>
               </div>

               <div>
                  <div class="unlock-header">
                     <?php echo esc_html(__('Unlock Role-Based Access Control', 'gsheetconnector-wpforms')); ?>
                  </div>

                  <span class="gsc-pro-badge">
                     <?php echo esc_html(__('Advanced options are available in PRO', 'gsheetconnector-wpforms')); ?>
                  </span>
               </div>

            </div>

            <!-- Feature Tabs -->
            <div class="gsc-pro-tabs pt-20 pb-20 pl-20 pr-20">
               <div>
                  <div class="mb-20 fw-600 text-dark pro-roll-sub-header"><?php echo esc_html(__('Role Permissions', 'gsheetconnector-wpforms')); ?></div>
                  <div class="gsc-pro-grid">
                     <ul>
                        <li><?php esc_html_e('Allow specific WordPress roles', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Enable/disable integration access', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Control form feed visibility', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Restrict settings management', 'gsheetconnector-wpforms'); ?></li>
                     </ul>
                  </div>
               </div>

               <div>
                  <div class="mb-20 fw-600 text-dark pro-roll-sub-header"><?php echo esc_html(__('Security Control', 'gsheetconnector-wpforms')); ?></div>
                  <div class="gsc-pro-grid">
                     <ul>
                        <li><?php esc_html_e('Prevent unauthorized changes', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Secure Google Sheet credentials', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Role-based configuration control', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Protect integration settings', 'gsheetconnector-wpforms'); ?></li>
                     </ul>
                  </div>
               </div>

               <div>
                  <div class="mb-20 fw-600 text-dark pro-roll-sub-header"><?php echo esc_html(__('Management Benefits', 'gsheetconnector-wpforms')); ?></div>
                  <div class="gsc-pro-grid">
                     <ul>
                        <li><?php esc_html_e('Grant access to trusted editors', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Hide settings from subscribers', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Team-based permission structure', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Improved dashboard security', 'gsheetconnector-wpforms'); ?></li>
                     </ul>
                  </div>
               </div>

               <div>
                  <div class="mb-20 fw-600 text-dark pro-roll-sub-header"><?php echo esc_html(__('Audit & Monitoring', 'gsheetconnector-wpforms')); ?></div>
                  <div class="gsc-pro-grid">
                     <ul>
                        <li><?php esc_html_e('Track role-based changes', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Monitor integration access', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Review permission updates', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php esc_html_e('Maintain admin accountability', 'gsheetconnector-wpforms'); ?></li>
                     </ul>
                  </div>
               </div>
            </div>

            <div class="gsc-pro-footer text-center">

               <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro"
                  target="_blank"
                  class="btn btn-primary text-decoration-none link-hover-white">

                  <?php echo esc_html(__('Upgrade to Unlock', 'gsheetconnector-wpforms')); ?>

               </a>

            </div>

         </div>

         <!-- Access Control -->

         <div class="gscwpforms-role-settings" id="gsc-googlesheet">

            <div class="wrap w-100 m-0">

               <div class="inner-wrap w-100 bg-white p-40 blur-pro-feature">

                  <div class="heading mt-0">
                     <?php echo esc_html__('Access Management', 'gsheetconnector-wpforms'); ?>
                  </div>

                  <p>
                     <?php echo esc_html__('Control which user roles are allowed to access and manage the Google Sheets integration for your forms.', 'gsheetconnector-wpforms'); ?>
                  </p>

                  <div class="gscwpforms-card">

                     <?php

                     $selected_row = '';
                     $checked = '';
                     $roles = array();
                     $setting_name = get_option('gscwpff_wpform_tab_roles_setting');


                     $participating_roles = array();
                     $editable_roles = get_editable_roles();
                     foreach ($editable_roles as $role => $details) {
                        $participating_roles[$role] = $details['name'];
                     }

                     $system_roles =   $participating_roles;
                     if (!empty($selected_roles)) {
                        foreach ($selected_roles as $role => $display_name) {
                           array_push($roles, $role);
                        }
                     }

                     $selected_row = '';
                     $selected_row  .= "<div class='gsc-access-wrapper mt-30'>";
                         $selected_row .= "<div class='gsc-access-box bg-white pt-15 pb-15 pl-15 pr-15'><div class='para-heading fw-600 mb-20'>" . esc_html__('Plugin Access Control', 'gsheetconnector-wpforms') . "</div>";
                           $selected_row .= "<div class='access-info mb-20'>" . esc_html__('Control who can access and manage the GSheetConnector plugin settings.', 'gsheetconnector-wpforms') . "</div>";


                        $selected_row .= "<div class='gsc-role-card mb-10'>
        <div class='custom-check d-flex justify-between alien-center'>
        <label class='role-label gsc-switch'> ";
                     $selected_row .= __("Administrator", 'gsheetconnector-wpforms');
                     $selected_row .= "</label>";
                     $selected_row .= "<input type='checkbox' class='check-toggle' disabled='disabled' checked='checked'/><label class='button-toggle'></label>";
                     $selected_row .= "</div>";
                     $selected_row .= "</div>";

                     $allowed_accress_roles = array('administrator', 'editor', 'author','contributor');
                     foreach ($system_roles as $role => $display_name) {

                        if (in_array($role, $allowed_accress_roles)) {
                           if ($role === "administrator") {
                              continue;
                           }
                           if (!empty($roles) && is_array($roles) && in_array(esc_attr($role), $roles)) { // preselect specified role
                              $checked = " checked='checked'";
                           } else {
                              $checked = '';
                           }

                           $selected_row .= "<div class='gsc-role-card mb-10'><div class='custom-check d-flex justify-between alien-center'><label class='role-label gsc-switch'>";
                           $selected_row .= esc_html($display_name, 'gsheetconnector-wpforms');
                           $selected_row .= "</label><input type='checkbox' class=''check-toggle'
            name='" . esc_attr($setting_name) . "[]' value='" . esc_attr($role) . "'" . $checked . "/>";
                           $selected_row .= "<label class='button-toggle'></label>";
                           $selected_row .= "</div></div>";
                        }
                     }

                   

                     echo wp_kses($selected_row, array(
                        'div' => array('class' => array()),
                        'label' => array('class' => array(), 'for' => array()),
                        'input' => array(
                           'type' => array(),
                           'class' => array(),
                           'name' => array(),
                           'value' => array(),
                           'checked' => array(),
                           'disabled' => array()
                        ),
                        'span' => array('class' => array())
                     ));

                     ?>

                  </div>

                  <div class="gsc-access-info">

                     <div class="para-heading fw-600 mb-20">
                        <?php echo esc_html__('Permission Guidelines', 'gsheetconnector-wpforms'); ?>
                     </div>

                     <ul>

                        <li><?php echo esc_html__('Control which user roles can access the GSheetConnector plugin', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php echo esc_html__('Allow selected users to manage Google Sheets integration settings', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php echo esc_html__('Restrict access to sensitive features like feeds, logs, and configurations', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php echo esc_html__('Only enabled roles will see the plugin menu in the admin panel', 'gsheetconnector-wpforms'); ?></li>
                        <li><?php echo esc_html__('Recommended: Allow only Administrators and trusted Editors', 'gsheetconnector-wpforms'); ?></li>


                     </ul>

                  </div>

               </div>

               <div class="select-info text-right mt-30">

                  <input type="submit"
                     class="btn btn-primary button-large"
                     name="gscwpforms_settings"
                     value="<?php echo esc_html__("Save Settings", 'gsheetconnector-wpforms'); ?>" />

               </div>

            </div>

         </div>

      </form>
      </div>
</div>

<?php

   }
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$gsheetconnector_wpforms_role_settings = new GSCWPForms_Role_Settings();
