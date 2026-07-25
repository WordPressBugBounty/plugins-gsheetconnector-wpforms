<?php
/*
 * Wpforms configuration and Intigration page
 * @since 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe: tab selection used for UI only, no sensitive action
$active_tab = !empty($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'wpform-dashboard';

if (isset($_GET['code'])) {
    if (is_string($_GET['code'])) {
        $active_tab = 'integration';
    }
}

$active_tab_name = '';

if ($active_tab ==  'wpform-dashboard') {
    $active_tab_name = __('Dashboard', 'gsheetconnector-wpforms');
}elseif($active_tab ==  'integration') {
    $active_tab_name = __('Integration', 'gsheetconnector-wpforms');
} elseif ($active_tab ==  'settings') {
    $active_tab_name = __('Form Settings', 'gsheetconnector-wpforms');
} elseif ($active_tab ==  'inner-settings') {
    $active_tab_name = __('Settings', 'gsheetconnector-wpforms');
} elseif ($active_tab ==  'extensions') {
    $active_tab_name = __('Extensions', 'gsheetconnector-wpforms');
} elseif ($active_tab ==  'wpform-database') {
    $active_tab_name = __('WPForms Database', 'gsheetconnector-wpforms');
}elseif ($active_tab ==  'system_status') {
 $active_tab_name = __('System Status', 'gsheetconnector-wpforms');
}


$plugin_version = defined('WPFORMS_GOOGLESHEET_VERSION') ? WPFORMS_GOOGLESHEET_VERSION : 'N/A';



$selected_method = "";// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$authenticated = get_option('wpform_gs_token');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$gscwpff_manual_setting = get_option('gs_wpforms_manual_setting');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$gsc_wpf_is_valid = get_option('wpform_gs_verify');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$gs_wpforms_service_json = get_option('gs_wpformspro_service_account_json', '');

$is_authenticated = false;// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ((!empty($authenticated) && $gsc_wpf_is_valid == 'valid' && $gscwpff_manual_setting == 0)) {
    $selected_method = esc_html__('Existing', 'gsheetconnector-wpforms');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $is_authenticated = true;// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
} elseif (((!empty($gs_wpforms_service_json))  && $gscwpff_manual_setting == 2)) {


    $selected_method = esc_html__('Service', 'gsheetconnector-wpforms');
    $is_authenticated = true;

  
    $email_account = '';
    $wpform_service_valid = false;

    if (!empty($gs_wpforms_service_json)) {
        $decoded_json = json_decode($gs_wpforms_service_json, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['client_email'])) {
            $email_account = $decoded_json['client_email'];
            $wpform_service_valid = true;
        }
    }
  
}else {
    $selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $is_authenticated = false;// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
}



/** notification code start */
$show_auth_notice =  !$is_authenticated;// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$show_showpro_notice =// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    !gscwpff_is_dismissed('showpro') &&
    !gscwpff_is_snoozed('showpro');


$show_enhance_notice =// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    !gscwpff_is_dismissed('enhance') &&
    !gscwpff_is_snoozed('enhance');

    if (!get_option('gscwpff_plugin_activated_at')) {
        update_option('gscwpff_plugin_activated_at', time());
    }


$install_time = (get_option('gscwpff_plugin_activated_at'));// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$is_time_passed = $install_time && (time() -  $install_time >= 2 * DAY_IN_SECONDS);// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$is_dismissed = gscwpff_is_dismissed('review');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$is_snoozed = gscwpff_is_snoozed('review');// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$show_review_notice =// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $is_time_passed &&
    !$is_dismissed &&
    !$is_snoozed;


function gscwpff_is_dismissed($key)// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
{
    return get_option('gscwpff_notice_' . $key) === 'dismissed';
}

function gscwpff_is_snoozed($key)// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
{
    $time = get_option('gscwpff_notice_' . $key . '_time');
    return $time && (time() - $time < 15 * DAY_IN_SECONDS);
}

$has_notice =// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $show_showpro_notice ||
    $show_review_notice ||
    $show_auth_notice ||
    $show_enhance_notice;

?>



<div class="gswpf-free">
    <!--Start NOTICE BAR-->
     <?php  if (!isset($_COOKIE['gsheetwpf_pro_notice_dismissed'])) { ?>
    <div id="pro-notice-bar" class="pro-header-notice">
        <span class="wpfpro-notice-bar-message"><?php echo esc_html__("You`re using GSheetConnector for WPForms Lite. To unlock more features consider ", 'gsheetconnector-wpforms'); ?><a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" rel="noopener"><?php echo esc_html__('upgrading to Pro', 'gsheetconnector-wpforms'); ?></a></span>
        <button type="button" id="wpfpro-dismiss-header-notice" title="Dismiss this message" data-page="overview" class="pro-dismiss">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.8327 5.34175L14.6577 4.16675L9.99935 8.82508L5.34102 4.16675L4.16602 5.34175L8.82435 10.0001L4.16602 14.6584L5.34102 15.8334L9.99935 11.1751L14.6577 15.8334L15.8327 14.6584L11.1744 10.0001L15.8327 5.34175Z" fill="white"></path>
            </svg>
        </button>
    </div>
    <?php  } ?>
    <!-- End NOTICE BAR -->

     <!-- START NOTICE SLIDER -->
    <div class="notification-gscwpff-notice-slider">
       <div class="notification-gscwpff-slider-track">
            <input type="hidden" name="gswpff-banner-ajax-nonce" id="gswpff-banner-ajax-nonce"
            value="<?php echo esc_attr(wp_create_nonce('gswpff-banner-ajax-nonce')); ?>" />

            <!-- Slide 1 -->
            <?php if($show_auth_notice){ ?>
            <div class="notification-gscwpff-slide">
                <div class="gscwpff-activate-banner">

                    <div class="gscwpff-activate-content">
                        <div class="gscwpff-activate-content-header">
                            <?php esc_html_e('Authenticate with Your Google Account', 'gsheetconnector-wpforms'); ?>
                        </div>

                        <p>
                            <?php esc_html_e('Your connection has expired or hasn’t been set up yet.', 'gsheetconnector-wpforms'); ?>
                        </p>

                        <p>
                            <?php esc_html_e('Please reauthenticate with your google account to continue syncing data without interruptions.', 'gsheetconnector-wpforms'); ?>
                        </p>

                        <div class="gscwpff-activate-actions">

                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpform-google-sheet-config&tab=integration')); ?>"
                                class="gscwpff-btn-activate link-hover-white ">
                                <?php esc_html_e('Authenticate Now', 'gsheetconnector-wpforms'); ?>
                            </a>


                            <a href="<?php echo esc_url('https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms'); ?>"
                                target="_blank" rel="noopener noreferrer" class="gscwpff-btn-secondary">
                                <?php esc_html_e('Learn How', 'gsheetconnector-wpforms'); ?>
                            </a>

                        </div>

                    </div>
                </div>
            </div>
            <?php } ?>

              <!--  slider two-->

            <?php if ($show_review_notice && $is_authenticated) { ?>
                <div class="notification-gscwpff-slide">
                    <div class="gscwpff-review-banner">

                        <div class="gscwpff-review-content">
                            <div class="gscwpff-review-heading">
                                <?php esc_html_e('Enjoying the Plugin?', 'gsheetconnector-wpforms'); ?>
                            </div>

                            <p>
                                <?php esc_html_e('If you are enjoying the plugin, please consider leaving a 5-star review. Your support helps us improve and grow.', 'gsheetconnector-wpforms'); ?>
                            </p>

                            <div class="gscwpff-notification-actions">

                                <a href="<?php echo esc_url('https://wordpress.org/support/plugin/gsheetconnector-wpforms/reviews/'); ?>"
                                    target="_blank" rel="noopener noreferrer" class="gscwpff-btn-review gsc-review-btn link-hover-white">
                                    <?php esc_html_e('Ok, you deserve it!', 'gsheetconnector-wpforms'); ?>
                                </a>

                                <button class="gscwpff-review-dismiss-btn gscwpff-dismiss-btn" data-key="review">
                                    <?php esc_html_e('I already did', 'gsheetconnector-wpforms'); ?>
                                </button>


                                <a href="<?php echo esc_url('https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms'); ?>"
                                    target="_blank" rel="noopener noreferrer" class="gscwpff-btn-secondary">
                                    <?php esc_html_e('I need help', 'gsheetconnector-wpforms'); ?>
                                </a>


                                <button class="gscwpff-review-btn-later" data-key="review">
                                    <?php esc_html_e('Maybe Later', 'gsheetconnector-wpforms'); ?>
                                </button>

                            </div>
                        </div>
                        <button class="gscwpff-review-close" data-key="review">✕</button>
                    </div>
                </div>
            <?php } ?>


              <!-- Slider three-->
            <?php if ($show_enhance_notice && $is_authenticated) { ?>
                <div class="notification-gscwpff-slide">
                    <div class="gscwpff-enhance-banner">
                        <div class="gscwpff-enhance-content">

                            <div class="gscwpff-enhance-content-header">
                                <?php esc_html_e(' Enhance Your Setup', 'gsheetconnector-wpforms'); ?>
                            </div>
                            <p>

                                <?php esc_html_e('Extend your workflow with our add-ons.', 'gsheetconnector-wpforms'); ?>
                            </p>
                            <p>
                                <?php esc_html_e('Discover tools that integrate seamlessly and help you get more done.', 'gsheetconnector-wpforms'); ?>
                            </p>
                            <div class="gscwpff-notification-actions ">
                                <a href="https://www.gsheetconnector.com/plugins" target="_blank" class="gsc-btn-ad link-hover-white">
                                    Explore Add-ons
                                </a>
                                <a href="<?php echo esc_url('https://www.gsheetconnector.com/docs'); ?>" target="_blank"
                                    class="gscwpff-btn-enhance link-hover-white">
                                    <?php esc_html_e('View Details', 'gsheetconnector-wpforms'); ?>
                                </a>
                                <button class="gscwpff-enhance-btn-later" data-key="enhance">Maybe Later</button>
                            </div>

                        </div>

                        <button class="gscwpff-enhance-close" data-key="enhance">✕</button>
                    </div>
                </div>
            <?php } ?>

            
            <!--  slider four -->
           <?php if ($show_showpro_notice && $is_authenticated) { ?>
                <div class="notification-gscwpff-slide">
                    <div class="gscwpff-Showpro-banner">

                        <div class="gscwpff-Showpro-content">
                            <div class="gscwpff-Showpro-heading">
                                <?php esc_html_e('Unlock Advance Features of WPForms Pro version 🚀', 'gsheetconnector-wpforms'); ?>
                            </div>

                            <p>
                                <?php esc_html_e('Use advanced features like Manual Authentication and automatic field mapping, no need to create columns in Google Sheets manually.', 'gsheetconnector-wpforms'); ?>
                            </p>
                            <p>
                                <?php esc_html_e('Choose only the fields you need with simple toggles, use advanced tags, sync past form entries, and get priority support.', 'gsheetconnector-wpforms'); ?>
                            </p>

                            <div class="gscwpff-notification-actions">

                                <a href="<?php echo esc_url('https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro'); ?>"
                                    target="_blank" rel="noopener noreferrer" class="gscwpff-btn-Showpro gsc-review-btn link-hover-white">
                                    <?php esc_html_e('View License Types', 'gsheetconnector-wpforms'); ?>
                                </a>

                                <a href="<?php echo esc_url('https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro#compare'); ?>"
                                    target="_blank" rel="noopener noreferrer" class="gscwpff-btn-secondary">
                                    <?php esc_html_e('Compare Features', 'gsheetconnector-wpforms'); ?>
                                </a>

                                <button class="gscwpff-Showpro-btn-later" data-key="showpro">
                                    <?php esc_html_e('Maybe Later', 'gsheetconnector-wpforms'); ?>
                                </button>

                            </div>
                        </div>
                        <button class="gscwpff-showpro-close" data-key="showpro">✕</button>
                    </div>
                </div>
            <?php } ?>


            <!-- Right Side Arrows -->

            <?php if ($has_notice) { ?>
                <div class="notification-gscwpff-slider-arrows">
                    <button class="notification-gscwpff-slider-btn prev">❮</button>
                    <button class="notification-gscwpff-slider-btn next">❯</button>
                </div>
            <?php } ?>



        </div>
    </div>
    <!-- END NOTICE SLIDER -->


    <!--Start Gsheet-Header Section-->
    <div class="gsheet-header-wrapper pt-10 pb-10 justify-between bg-white">
        <div class="container">
            <div class="row justify-between align-center">
                <div class="left-gsheet-header d-flex align-center">
                    <div class="gsheet-header-logo">
                        <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms" target="_blank"><i class="d-block"></i></a>
                    </div>
                    <div class="gsheet-header-logo-text">
                        <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms" class="text-decoration-none" target="_blank">
                            <div class="line-height-zero m-0">
                                <span class="title fw-600"><?php echo esc_html__("GSheetConnector For WPForms", "gsheetconnector-wpforms"); ?></span>
                            </div>
                        </a>
                        <small class="p-0"><?php echo esc_html__('v', 'gsheetconnector-wpforms'); ?><?php echo esc_html($plugin_version, WPFORMS_GOOGLESHEET_VERSION); ?> </small>
                    </div>
                </div>
                <div class="right-gsheet-header">
                    <ul class="d-flex gap-10">
                        <li>
                            <a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms" class="d-flex justify-center align-center bg-white" title="Document" target="_blank">
                                <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 6.00002V6.75002H18.75V6.00002H18ZM15.7172 2.32614L15.6111 1.58368L15.7172 2.32614ZM4.91959 3.86865L4.81353 3.12619H4.81353L4.91959 3.86865ZM5.07107 6.75002H18V5.25002H5.07107V6.75002ZM18.75 6.00002V4.30604H17.25V6.00002H18.75ZM15.6111 1.58368L4.81353 3.12619L5.02566 4.61111L15.8232 3.0686L15.6111 1.58368ZM4.81353 3.12619C3.91638 3.25435 3.25 4.0227 3.25 4.92895H4.75C4.75 4.76917 4.86749 4.63371 5.02566 4.61111L4.81353 3.12619ZM18.75 4.30604C18.75 2.63253 17.2678 1.34701 15.6111 1.58368L15.8232 3.0686C16.5763 2.96103 17.25 3.54535 17.25 4.30604H18.75ZM5.07107 5.25002C4.89375 5.25002 4.75 5.10627 4.75 4.92895H3.25C3.25 5.9347 4.06532 6.75002 5.07107 6.75002V5.25002Z" fill="#666"></path>
                                    <path d="M8 12H16" stroke="#666" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M8 15.5H13.5" stroke="#666" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M4 6V19C4 20.6569 5.34315 22 7 22H17C18.6569 22 20 20.6569 20 19V14M4 6V5M4 6H17C18.6569 6 20 7.34315 20 9V10" stroke="#666" stroke-width="1.5" stroke-linecap="round"></path>
                                </svg>
                            </a>
                        </li>
                        <li>
                            <a href="https://wordpress.org/support/plugin/gsheetconnector-wpforms/" class="d-flex justify-center align-center bg-white" title="Support" target="_blank">
                                <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 6L14.8284 9.17157M14.8284 9.17157C14.1046 8.44772 13.1046 8 12 8C10.8954 8 9.89543 8.44772 9.17157 9.17157M14.8284 9.17157C15.5523 9.89543 16 10.8954 16 12C16 13.1046 15.5523 14.1046 14.8284 14.8284M18 18L14.8284 14.8284M14.8284 14.8284C14.1046 15.5523 13.1046 16 12 16C10.8954 16 9.89543 15.5523 9.17157 14.8284M6 18L9.17157 14.8284M9.17157 14.8284C8.44772 14.1046 8 13.1046 8 12C8 10.8954 8.44772 9.89543 9.17157 9.17157M6 6L9.17157 9.17157M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </a>
                        </li>
                        <li>
                            <a href="https://wordpress.org/plugins/gsheetconnector-wpforms/#developers" class="d-flex justify-center align-center bg-white" title="Changelog" target="_blank">
                                <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19.4423 2.60315C19.7838 2.77155 20 3.11926 20 3.50001V7.46482L20.8906 8.05856C22.2084 8.93711 23 10.4162 23 12C23 13.5838 22.2084 15.0629 20.8906 15.9415L20 16.5352V19.5C20 19.8774 19.7876 20.2226 19.4507 20.3927C19.1139 20.5627 18.7101 20.5287 18.4064 20.3048C18.4064 20.3048 18.4064 20.3048 18.4064 20.3048C18.4064 20.3048 18.4064 20.3047 18.4063 20.3047L18.4063 20.3047L18.4054 20.3041L18.4012 20.301L18.3831 20.2876L18.3098 20.2344C18.2453 20.1876 18.1506 20.1194 18.0313 20.0349C17.7926 19.8657 17.4571 19.6319 17.0712 19.3747C16.2873 18.8523 15.3391 18.2625 14.5765 17.9059C13.1878 17.2566 11.7408 16.7733 10.6322 16.4513C10.1547 16.3125 9.74373 16.2048 9.43209 16.1275C8.63487 17.4199 8.92926 19.1226 10.1451 20.0682C11.3765 21.026 10.6993 23 9.13919 23H6C5.59997 23 5.23843 22.7616 5.08085 22.3939L4.69925 21.5035C3.87957 19.5909 3.83735 17.4342 4.58156 15.491L4.62696 15.3725C2.51738 14.8594 1 12.9633 1 10.7539C1 8.12839 3.12838 6.00001 5.75387 6.00001H9C9.02628 6.00001 9.05256 6.00104 9.07876 6.00311C9.07893 6.00313 9.0791 6.00314 9.07927 6.00315C9.07943 6.00317 9.07959 6.00318 9.07974 6.00319C9.07975 6.00319 9.07975 6.00319 9.07976 6.00319L9.08164 6.00333L9.10038 6.00461C9.1185 6.00579 9.14773 6.00754 9.18726 6.00945C9.26636 6.01329 9.38647 6.01774 9.54125 6.01952C9.85127 6.02309 10.2977 6.01586 10.8305 5.97193C11.9038 5.8834 13.2878 5.64894 14.6043 5.08164C15.3591 4.75639 16.2945 4.1762 17.0738 3.64858C17.456 3.38981 17.7874 3.15279 18.023 2.98068C18.1406 2.89473 18.2339 2.82527 18.2972 2.77773L18.369 2.72362L18.3866 2.71022L18.3906 2.70716L18.3913 2.70662L18.3913 2.70658L18.3914 2.70655C18.6934 2.47485 19.1009 2.43476 19.4423 2.60315ZM8 8.00001H5.75387C4.23295 8.00001 3 9.23295 3 10.7539C3 12.1213 4.00336 13.2816 5.35646 13.4789L6.14107 13.5933L8 13.8515V8.00001ZM10 14.2079C10.3214 14.2886 10.7267 14.396 11.1901 14.5306C12.3557 14.8692 13.9087 15.3859 15.4235 16.0941C16.2629 16.4866 17.2274 17.082 18 17.5909V16V8.00001V5.43572C17.2289 5.9496 16.2582 6.54673 15.3957 6.91837C13.8127 7.6005 12.1967 7.86604 10.9949 7.96516C10.6233 7.9958 10.2876 8.01083 10 8.0169V14.2079ZM7.36806 15.7829L6.64962 15.6832L6.44927 16.2063C5.89112 17.6637 5.92278 19.2812 6.53754 20.7157L6.6594 21H8.22938C6.9697 19.5684 6.63958 17.5343 7.36806 15.7829ZM20 14.1152C20.6294 13.5985 21 12.8238 21 12C21 11.1762 20.6294 10.4015 20 9.88478V14.1152Z" fill="#666" />
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!--End Gsheet-Header Section-->

    <!--Start Breadcrumb Section-->
    <div class="breadcrumb-wrapper pt-13 pb-13 text-uppercase fw-500 text-gray">
        <div class="container">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpform-google-sheet-config')); ?>" class="text-primary text-decoration-none">
                <?php echo esc_html__('Google Sheet', 'gsheetconnector-wpforms'); ?>
            </a>
            <span>/</span>
            <span><?php echo esc_html($active_tab_name); ?></span>
        </div>
    </div>
    <!--End Breadcrumb Section-->

    <!-- Code for auth method appear in tab  -->
    <?php


    $is_authenticated = false;
    $selected_method  = '';

    $wp_token              = get_option( 'wpform_gs_token' );
    $wpforms_service_json  = get_option( 'gs_wpformspro_service_account_json' );
    $wpforms_auth_method   = get_option( 'gs_wpforms_manual_setting' );
    $wpforms_verify        = get_option( 'wpform_gs_verify' );

    /* Check if the user is authenticated based on selected auth method */
    if ( ! empty( $wp_token ) && $wpforms_verify === 'valid' && $wpforms_auth_method == 0 ) {

        $is_authenticated = true;
        $selected_method  = esc_html__( 'Existing', 'gsheetconnector-wpforms' );

    } elseif ( ! empty( $wpforms_service_json ) && $wpforms_auth_method == 2 ) {

        $is_authenticated = true;
        $selected_method  = esc_html__( 'Service', 'gsheetconnector-wpforms' );

    } else {

        $selected_method = esc_html__( 'Auth Required', 'gsheetconnector-wpforms' );

    }

    echo '<div class="d-none">
        <div class="gscwpff-selected-method"
        data-value="' . esc_attr($selected_method) . '">'
        . esc_html($selected_method) .
        '</div>
        </div>';
    ?>

    <?php
    $tabs = array(
        'wpform-dashboard'    => esc_html(__('Dashboard', 'gsheetconnector-wpforms')),
        'integration'    => esc_html(__('Integration', 'gsheetconnector-wpforms')),
        'settings'       => esc_html__('Form Settings', 'gsheetconnector-wpforms'),
        'inner-settings' => esc_html__('Settings', 'gsheetconnector-wpforms'),
        'system_status' => esc_html__('System Status', 'gsheetconnector-wpforms'),
        'wpform-database' => esc_html__('WPForms Database', 'gsheetconnector-wpforms'),
        'extensions'     => esc_html__('Extensions', 'gsheetconnector-wpforms'),
    );

    echo '<div id="icon-themes" class="icon32"></div>';
    echo '<div class="nav-tab-wrapper d-flex justify-flex-start w-100 m-0">';
    foreach ($tabs as $tab => $name) {
        $class = ($tab === $active_tab) ? ' nav-tab-active' : '';
        $tab_url = esc_url(add_query_arg(['page' => 'wpform-google-sheet-config', 'tab' => $tab]));
        $tab_name = esc_html($name);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tab_url/$tab_name are already esc_url()/esc_html()-wrapped above.
        echo '<a class="nav-tab text-decoration-none fw-500 text-center ' . esc_attr($class) . '" href="' . $tab_url . '">' . $tab_name . '</a>';
    }

    echo '</div>';
    switch ($active_tab) {

        case 'wpform-dashboard':
            include(WPFORMS_GOOGLESHEET_PATH . "includes/pages/gs-wpform-dashboard.php");
            break;
        case 'settings':
            echo '<div class="wrap w-100 m-0"><div class="inner-wrap  w-100 bg-white p-40">';
            $wpform_settings = new WPforms_Googlesheet_Services();
            $wpform_settings->add_settings_page();
            echo '</div></div>';
            break;
        case 'integration':
            echo '<div class="wrap w-100 m-0"><div class="inner-wrap  w-100 bg-white p-40">';
            $wpform_integration = new WPforms_Googlesheet_Services();
            $wpform_integration->add_integration();
            echo '</div></div>';
            break;
        case 'wpform-database':
            echo '<div class="wrap w-100 m-0"><div class="inner-wrap  w-100 bg-white p-40">';
            $GS_WPFORMDB = new GS_WPFORMDB();
            $GS_WPFORMDB->show_enable_disable_set();
            echo '</div></div>';
            break;
        case 'inner-settings':
            include(WPFORMS_GOOGLESHEET_PATH . "includes/pages/gs-wpform-sheet-setting.php");
            break;

        case 'system_status':
            echo '<div class="wrap w-100 m-0"><div class="inner-wrap  w-100 bg-white p-40">';
            include(WPFORMS_GOOGLESHEET_PATH . "includes/pages/wpforms-integrate-system-info.php");
            echo '</div></div>';
            break;

        case 'extensions':
            include(WPFORMS_GOOGLESHEET_PATH . "includes/pages/extensions.php");
            break;
    }
    ?>
</div>


<!--Start Common Pro Feature-->
<div class="gswpf-free">
    <?php if ($active_tab != 'wpform-dashboard') { ?>
        <div class="common-section-gsc-promo-wrapper">
            <!-- Left Image Area -->
            <div class="d-flex flex-wrap gap-50 align-center">
                <div class="cf7-to-gsheet">
                    <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/pro-wpf-gsc.webp">
                </div>
                <!-- Right Content -->
                <div class="common-section-gsc-promo-content">
                    <div class="common-section-heading"><?php echo esc_html(__('Advanced Tools for Easy Spreadsheet Control', 'gsheetconnector-wpforms')); ?></div>
                    <p class="mb-0"><?php echo esc_html(__('Improve your sheet management with smart automation and flexible customization features.', 'gsheetconnector-wpforms')); ?></p>
                    <div class="d-flex gap-40">
                        <ul>
                            <li><?php echo esc_html__('Google Sheets API v4', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('One-Click Authentication', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Authenticated Email Display', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Click & Fetch Automation', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Create New Spreadsheet', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Manual Sheet / Tab Name', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Freeze & Color Headers', 'gsheetconnector-wpforms'); ?></li>
                        </ul>
                        <ul>
                            <li><?php echo esc_html__('Automated Sheet & Tab', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Multiple Feeds to Sheets', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Send Missed Entries to GSheet', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__(' Smart Tags', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Drag-and-Drop Column Order', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Headers On / Off + Rename', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Image / PDF Attachment Link', 'gsheetconnector-wpforms'); ?></li>
                        </ul>
                        <ul>
                            <li><?php echo esc_html__('Freeze & Color Headers', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Sync Past Entries', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Role Management', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Quick Configuration', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Multi-Language Support', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Multi-Site Support', 'gsheetconnector-wpforms'); ?></li>
                            <li><?php echo esc_html__('Latest WP & PHP Support', 'gsheetconnector-wpforms'); ?></li>
                        </ul>
                    </div>
                    <div class="mt-30 d-flex align-center gap-20">
                        <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank" class="btn btn-primary link-hover-white text-decoration-none">Upgrad Now</a>
                        <a class="text-decoration-none free-pro-btn" href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro#compare" target="_blank">Free vs Pro</a>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
 <!--End Common Pro Feature-->

<script>
/** jQuery for notification slider  */
document.addEventListener("DOMContentLoaded", function() {
    const slides = document.querySelectorAll(".notification-gscwpff-slide");
    const prevBtn = document.querySelector(".notification-gscwpff-slider-btn.prev");
    const nextBtn = document.querySelector(".notification-gscwpff-slider-btn.next");
    let index = 0;
      function updateSlider() {
          if (!slides || slides.length === 0) return;
          slides.forEach(slide => slide.classList.remove("active"));
          if (slides[index]) {
              slides[index].classList.add("active");
          }
      }

      nextBtn.addEventListener("click", function() {
          index++;
          if (index >= slides.length) {
              index = 0;
          }
          updateSlider();
      });

      prevBtn.addEventListener("click", function() {
          index--;
          if (index < 0) {
              index = slides.length - 1;
          }
          updateSlider();
      });
      updateSlider();
});


</script>



<?php include(WPFORMS_GOOGLESHEET_PATH . "/includes/pages/admin-footer.php"); ?>