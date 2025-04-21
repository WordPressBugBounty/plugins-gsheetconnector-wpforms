<?php
/*
 * Wpforms configuration and Intigration page
 * @since 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit();
}

$active_tab = ( isset($_GET['tab']) && sanitize_text_field($_GET["tab"])) ? sanitize_text_field($_GET['tab']) : 'integration';

$active_tab_name = '';
if($active_tab ==  'integration'){
  $active_tab_name = 'Integration';
}
elseif($active_tab ==  'settings'){
  $active_tab_name = 'GoogleSheet Form Settings';
}
elseif($active_tab ==  'system_status'){
  $active_tab_name = 'System Status';
}
elseif($active_tab ==  'extensions'){
  $active_tab_name = 'Extensions';
}


$plugin_version = defined('WPFORMS_GOOGLESHEET_VERSION') ? WPFORMS_GOOGLESHEET_VERSION : 'N/A';
?>

<div class="gsheet-header">
    <div class="gsheet-logo">
        <a href="https://www.gsheetconnector.com/"><i></i></a></div>
    <h1 class="gsheet-logo-text"><span class="title"><?php echo esc_html__( "WPForms - GSheetConnector", "gsheetconnector-wpforms" ); ?></span> <small><?php echo esc_html__( "Version :", "gsheetconnector-wpforms" ); ?> <?php  echo esc_html($plugin_version, WPFORMS_GOOGLESHEET_VERSION); ?> </small></h1>
    <a href="https://support.gsheetconnector.com/kb" title="gsheet Knowledge Base" target="_blank" class="button gsheet-help"><i class="dashicons dashicons-editor-help"></i></a>
</div><!-- header #end -->
<span class="dashboard-gsc"><?php echo esc_html( __('DASHBOARD', 'gsheetconnector-wpforms' ) ); ?></span>
<span class="divider-gsc"> / </span>
<span class="modules-gsc"> <?php echo esc_html( __($active_tab_name, 'gsheetconnector-wpforms' ) ); ?></span>

<div class="wrap">
	
	
   <?php
   $tabs = array(
       'integration'    => __('Integration', 'gsheetconnector-wpforms'),
       'settings'       => __('GoogleSheet Form Settings', 'gsheetconnector-wpforms'),
      // 'faq'            => __('FAQ', 'gsheetconnector-wpforms'),
      // 'demos'          => __('Demos', 'gsheetconnector-wpforms'),
      // 'support'        => __('Support','gsheetconnector-wpforms'),
       'system_status'  => __('System Status', 'gsheetconnector-wpforms'),
	   'extensions'  => __('Extensions', 'gsheetconnector-wpforms'),
   );
   echo '<div id="icon-themes" class="icon32"><br></div>';
   echo '<h2 class="nav-tab-wrapper">';
   foreach ($tabs as $tab => $name) {
      $class = ( $tab == $active_tab ) ? ' nav-tab-active' : '';
      echo "<a class='nav-tab$class' href='?page=wpform-google-sheet-config&tab=$tab'>$name</a>";
   }
   echo '</h2>';
   switch ($active_tab) {
      case 'settings' :
         $wpform_settings = new WPforms_Googlesheet_Services();
         $wpform_settings->add_settings_page();
         break;
      case 'integration' :
         $wpform_integration = new WPforms_Googlesheet_Services();
         $wpform_integration->add_integration();
         break;
     
       case 'system_status' :
         include( WPFORMS_GOOGLESHEET_PATH . "includes/pages/wpforms-integrate-system-info.php" );
         break;
       case 'extensions' :
         include( WPFORMS_GOOGLESHEET_PATH . "includes/pages/extensions.php" );
         break;
   }
   ?>
</div>
<?php include( WPFORMS_GOOGLESHEET_PATH . "/includes/pages/admin-footer.php" ); ?>
