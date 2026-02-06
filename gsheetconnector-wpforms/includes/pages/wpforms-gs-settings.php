<?php
/*
 * Wpforms configuration and Intigration page
 * @since 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
   exit();
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe: tab selection used for UI only, no sensitive action
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
    <h1 class="gsheet-logo-text"><span class="title"><?php echo esc_html__( "GSheetConnector For WPForms", "gsheetconnector-wpforms" ); ?></span> <small><?php echo esc_html__( "Version :", "gsheetconnector-wpforms" ); ?> <?php  echo esc_html($plugin_version, WPFORMS_GOOGLESHEET_VERSION); ?> </small></h1>
    
	<ul> 
		<li><a href="https://www.gsheetconnector.com/docs/wpforms-gsheetconnector/introduction" title="Document" target="_blank"><i class="fa-regular fa-file-lines"></i></a></li>
		<li><a href="https://www.gsheetconnector.com/support" title="Support" target="_blank"><i class="fa-regular fa-life-ring"></i></a></li>
		<li><a href="https://wordpress.org/plugins/gsheetconnector-wpforms/#developers" title="Changelog" target="_blank"><i class="fa-solid fa-bullhorn"></i></a></li>
	</ul>
	
</div><!-- header #end -->

<div class="breadcrumb">
	<span class="dashboard-gsc"><?php echo esc_html( __('DASHBOARD', 'gsheetconnector-wpforms' ) ); ?></span>
	<span class="divider-gsc"> / </span>
	<span class="modules-gsc"> <?php echo esc_html( __($active_tab_name, 'gsheetconnector-wpforms' ) ); ?></span>
</div>


	
   <?php
  $tabs = array(
    'integration'    => esc_html__( 'Integration', 'gsheetconnector-wpforms' ),
    'settings'       => esc_html__( 'GoogleSheet Form Settings', 'gsheetconnector-wpforms' ),
    'system_status'  => esc_html__( 'System Status', 'gsheetconnector-wpforms' ),
    'extensions'     => esc_html__( 'Extensions', 'gsheetconnector-wpforms' ),
);

   echo '<div id="icon-themes" class="icon32"><br></div>';
   echo '<div class="nav-tab-wrapper">';
    foreach ( $tabs as $tab => $name ) {
        $class = ( $tab === $active_tab ) ? ' nav-tab-active' : '';
        $tab_url = esc_url( add_query_arg( [ 'page' => 'wpform-google-sheet-config', 'tab' => $tab ] ) );
        $tab_name = esc_html( $name );
        echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . $tab_url . '">' . $tab_name . '</a>';
    }

   echo '</div><div class="wrap-gsc">';
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
