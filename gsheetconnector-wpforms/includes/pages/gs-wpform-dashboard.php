<?php 
if (!defined('ABSPATH')) {
  exit;
}




	$wpforms_is_authenticated = false;

	$wpforms_authenticated         = get_option('wpform_gs_token'); // Auto
	$wpforms_authenticatedManual   = get_option('gs_wpforms_token_manual'); // Manual
	$wpforms_authenticatedService  = get_option('gs_wpformspro_service_account_json'); // Service JSON
	$wpforms_per                   = get_option('wpform_gs_verify');
    $wpforms_per_manual            = get_option('wpform_gs_verify');
	$wpforms_per_msg               = __('invalid', 'gsheetconnector-wpforms');
	$wpforms_show_setting          = 0;

	$wpforms_auth_method           = get_option('gs_wpforms_manual_setting'); // NEW
	$wpforms_selected_method       = '';
	$wpforms_email_account         = '';



	// echo$wpforms_authenticatedManual;
	if ($wpforms_auth_method === '0' && !empty($wpforms_authenticated) && $wpforms_per !== $wpforms_per_msg) {
		
        include_once( WPFORMS_GOOGLESHEET_ROOT . '/lib/google-sheets.php');
        $wpforms_google_sheet = new WPFGSC_googlesheet();
        $wpforms_email_account = $wpforms_google_sheet->gsheet_print_google_account_email();
        if (!empty($wpforms_email_account)) {
            $wpforms_selected_method = esc_html__('Use Existing Client/Secret Key (Auto Google API Configuration)', 'gsheetconnector-wpforms');
            $wpforms_is_authenticated = true;
        } else {
            $wpforms_selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');
        }
	
	}elseif ($wpforms_auth_method === '2' && !empty($wpforms_authenticatedService)) {
		
		$wpforms_authenticated_Service  = json_decode($wpforms_authenticatedService, true);
		if (json_last_error() === JSON_ERROR_NONE && isset($wpforms_authenticated_Service['client_email'])) {
			$wpforms_gs_client_email  = sanitize_email($wpforms_authenticated_Service['client_email']);
		}

		$wpforms_email_account  = !empty($wpforms_gs_client_email) ? esc_html($wpforms_gs_client_email) : 'Not Connected';

         if (!empty($wpforms_email_account)) {
            $wpforms_selected_method = esc_html__('Service Account (Recommended)', 'gsheetconnector-wpforms');
            $wpforms_is_authenticated = true;
        } else {
            $wpforms_selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');
        }
	} else {
		$wpforms_selected_method = esc_html__('Auth Required', 'gsheetconnector-wpforms');
	}

?>

<div class="wrap w-100 m-0">
    <div class="inner-wrap  w-100 bg-white p-40">
        <div class="gsc-dashboard">

            <div class="row">
                <div class="col-6">
                    <div class="dashboard-left-wrapper mr-15">
                        <!---Start Welcome-Header Section--->
                        <div class="welcome-wrapper mb-30">
                            <div class="welcome-content">
                                <div class="welcome-heading mb-20">
                                    <span><?php echo esc_html__('Welcome To GSheetConnector', 'gsheetconnector-wpforms'); ?></span>
                                </div>
                                <p>
                                    <?php echo esc_html__('GSheetConnector is a powerful automation plugin that syncs WordPress data with Google Sheets in real time. It supports WooCommerce, Easy Digital Downloads, and popular form plugins such as WPForms, Contact Form 7, Elementor Forms, along with 10+ additional WordPress integrations for efficient data management.', 'gsheetconnector-wpforms'); ?>
                                </p>
                            </div>

                             <?php
                                 if (!empty($wpforms_email_account)){
                                    /** Connected Email box start   */ ?>
                                     <div class="gscwpfp-integration-box">
                                        <div class="gsc-google-auth-card mt-30 mb-30">
                                            <div>
                                                <div class="heading mt-0 mb-30">
                                                    <?php echo esc_html__('Google Account Connection', 'gsheetconnector-wpforms'); ?>
                                                    <span class="badge"><?php echo esc_html($wpforms_selected_method); ?></span>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-20 justify-between align-center">
                                                <div class="gsc-google-auth-left d-flex flex-wrap align-center gap-15">
                                                    <div class="gsc-google-icon">G</div>
                                                    <div class="connected-account">
                                                        <div class="gsc-connected-left d-flex">
                                                            <span class="gsc-connected-label">
                                                                <?php echo esc_html__('Connected Email Account', 'gsheetconnector-wpforms'); ?>
                                                            </span>
                                                            <span class="connected-account-manual gsc-connected-email">
                                                                <?php echo esc_html($wpforms_email_account); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="gsc-google-auth-right">
                                                    <div class="gsc-connected-pill">
                                                        <span class="dot"></span>
                                                        <?php
                                                        echo esc_html__('Connected', 'gsheetconnector-wpforms');

                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="wpformdb-feed-table-wrap">
                                        <table class="widget-table" id="wpformdb-feed-table" data-page="1">
                                            <thead>
                                                <tr>
                                                    <th><?php echo esc_html__('Form Name', 'gsheetconnector-wpforms'); ?></th>
                                                    <th><?php echo esc_html__('Feed Name', 'gsheetconnector-wpforms'); ?></th>
                                                    <th><?php echo esc_html__('Sheet Name', 'gsheetconnector-wpforms'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="wpformdb-feed-table-body">
                                                <tr class="wpformdb-feed-loading-row">
                                                    <td colspan="3">
                                                        <span class="wpformdb-loader"></span>
                                                        <span class="wpformdb-loader-text"><?php echo esc_html__('Loading feeds...', 'gsheetconnector-wpforms'); ?></span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <div class="wpformdb-pagination d-flex justify-center gap-10 " id="wpformdb-pagination-wrap">
                                            <!-- page links injected via AJAX -->
                                        </div>

                                        <input type="hidden" id="wpformdb-ajax-nonce-pagination" value="<?php echo esc_attr(wp_create_nonce('wpformdb-pagination-nonce')); ?>" />
                                    </div>
                                <?php 
                                    /** Connected Email box end   */ 
                                }else{  ?>

                                <div class="unlock-pro-button-sections mt-20">
                                    <a class="btn btn-primary link-hover-white text-decoration-none" href="<?php echo esc_url(admin_url('admin.php?page=wpform-google-sheet-config&tab=integration')); ?>">
                                        <?php echo esc_html__("Let's Connect", 'gsheetconnector-wpforms'); ?>
                                    </a>
                                </div> 
                            <?php } ?>



                        </div>
                        <!---End Welcome-Header Section--->

                        <!-- HERO -->
                        <div class="set-up-guid-wrapper welcome-wrapper">
                            <div class="welcome-content">
                                <div class="welcome-heading mb-20">
                                    <span><?php echo esc_html__('Setup Guide & Troubleshooting', 'gsheetconnector-wpforms'); ?></span>
                                </div>
                                <p>
                                    <?php echo esc_html__('Sync WPForms data with Google Sheets in real-time effortlessly and accurately.', 'gsheetconnector-wpforms'); ?>
                                </p>
                            </div>

                            <div class="setup-content-data mt-20">
                                <div class="setup-row d-flex justify-between gap-20">
                                    <div class="google-api-setting-guide">
                                        <div class="dashboard-pro-small-head"><?php echo esc_html__('Getting Started', 'gsheetconnector-wpforms'); ?></div>
                                        <ul>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/installation-process-free-version" target="_blank"><?php echo esc_html__('Installation Process', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/integration-with-google-existing-method" target="_blank"><?php echo esc_html__('Integration with Google (Existing Method)', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/service-account-setting-pro-version" target="_blank"><?php echo esc_html__('Integration with Google (Service Method)', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/plugin-settings-free-version#gsheetconnector" target="_blank"><?php echo esc_html__('Integration of WP Forms with Google Sheet', 'gsheetconnector-wpforms'); ?></a></li>
                                        </ul>
                                    </div>
                                    <div class="google-api-setting-guide">
                                        <div class="dashboard-pro-small-head"><?php echo esc_html__('Docs & Troubleshooting', 'gsheetconnector-wpforms'); ?></div>
                                        <ul>
                                            <li><a href=" https://www.gsheetconnector.com/docs/general/how-to-enable-debugging-in-wordpress" target="_blank"><?php echo esc_html__('How to Enable Debugging in WordPress', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/general/common-errors-issues#toc-heading-1" target="_blank"><?php echo esc_html__('Invalid OAuth2 token', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/general/how-to-change-date-time-format-and-time-zone-in-google-sheets" target="_blank"><?php echo esc_html__('Change Date/Time Format and Time Zone in Google Sheets', 'gsheetconnector-wpforms'); ?></a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="setup-row">
                                    <div class="google-api-setting-guide">
                                        <div class="dashboard-pro-small-head"><?php echo esc_html__('Additional Resources', 'gsheetconnector-wpforms'); ?></div>
                                        <ul>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/integration-with-google-manual-method" target="_blank"><?php echo esc_html__('Integration with Google (Manual Method)', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/plugin-settings-pro-version#automatic-adding-sheet-name-tab-name" target="_blank"><?php echo esc_html__('Automatic Select Spreadsheet', 'gsheetconnector-wpforms'); ?></a></li>
                                            <li><a href="https://www.gsheetconnector.com/docs/gsheetconnnector-for-wpforms/role-setting-pro-version" target="_blank"><?php echo esc_html__('Role Settings', 'gsheetconnector-wpforms'); ?></a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="plugin-category-wrapper welcome-wrapper ml-15">
                        <div class="welcome-heading mb-20">
                            <span><?php echo esc_html__('Plugins by Category', 'gsheetconnector-wpforms'); ?></span>
                        </div>
                        <p>
                            <?php echo esc_html__('Find the perfect connector for your WordPress workflow.', 'gsheetconnector-wpforms'); ?>
                        </p>
                        <div class="plugin-category-section">
                            <a href="https://www.gsheetconnector.com/plugins#contactform" target="_blank" class="plugin-category-box text-decoration-none">
                                <div class="plugin-category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text w-6 h-6 text-emerald-600" aria-hidden="true">
                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                        <path d="M10 9H8"></path>
                                        <path d="M16 13H8"></path>
                                        <path d="M16 17H8"></path>
                                    </svg>
                                </div>
                                <div class="plugin-category-content">
                                    <div class="plugin-category-name fw-600">
                                        <?php echo esc_html__('Contact Form Connectors', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                    <div class="plugin-category-badge">
                                        <?php echo esc_html__('6 plugins available', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                </div>
                                <div class="plugin-category-arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>

                            <a href="https://www.gsheetconnector.com/plugins#ecommerce" target="_blank" class="plugin-category-box text-decoration-none">
                                <div class="plugin-category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart w-6 h-6 text-emerald-600" aria-hidden="true">
                                        <circle cx="8" cy="21" r="1"></circle>
                                        <circle cx="19" cy="21" r="1"></circle>
                                        <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                                    </svg>
                                </div>
                                <div class="plugin-category-content">
                                    <div class="plugin-category-name fw-600">
                                        <?php echo esc_html__('eCommerce Connectors', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                    <div class="plugin-category-badge">
                                        <?php echo esc_html__('2 plugins available', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                </div>
                                <div class="plugin-category-arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>

                            <a href="https://www.gsheetconnector.com/plugins#pagebuilderform" target="_blank" class="plugin-category-box text-decoration-none">
                                <div class="plugin-category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panels-top-left w-6 h-6 text-emerald-600" aria-hidden="true">
                                        <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                        <path d="M3 9h18"></path>
                                        <path d="M9 21V9"></path>
                                    </svg>
                                </div>
                                <div class="plugin-category-content">
                                    <div class="plugin-category-name fw-600">
                                        <?php echo esc_html__('Page Builder Forms', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                    <div class="plugin-category-badge">
                                        <?php echo esc_html__('3 plugins available', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                </div>
                                <div class="plugin-category-arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>



                            <a href="https://www.gsheetconnector.com/gsheetconnector-for-wp-core" class="plugin-category-box text-decoration-none" target="_blank">
                                <div class="plugin-category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-database w-6 h-6 text-emerald-600" aria-hidden="true">
                                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                        <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                        <path d="M3 12A9 3 0 0 0 21 12"></path>
                                    </svg>
                                </div>
                                <div class="plugin-category-content">
                                    <div class="plugin-category-name fw-600">
                                        <?php echo esc_html__('WP Core Connector', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                    <div class="plugin-category-badge">
                                        <?php echo esc_html__('1 plugin available', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                </div>
                                <div class="plugin-category-arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>

                            <a href="https://profiles.wordpress.org/gsheetconnector/" class="plugin-category-box text-decoration-none" target="_blank">
                                <div class="plugin-category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-gift w-6 h-6 text-emerald-600" aria-hidden="true">
                                        <rect x="3" y="8" width="18" height="4" rx="1"></rect>
                                        <path d="M12 8v13"></path>
                                        <path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"></path>
                                        <path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"></path>
                                    </svg>
                                </div>
                                <div class="plugin-category-content">
                                    <div class="plugin-category-name fw-600">
                                        <?php echo esc_html__('Free Plugins', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                    <div class="plugin-category-badge">
                                        <?php echo esc_html__('12 plugin available', 'gsheetconnector-wpforms'); ?>
                                    </div>
                                </div>
                                <div class="plugin-category-arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>
                        </div>
                    </div>
                    <!---Start Support  ticket--->

                    <div class="gsc-support-card mt-30 welcome-wrapper ml-15">

                        <!-- LEFT SIDE -->
                        <div class="gsc-support-left">

                            <div class="gsc-support-icon d-flex justify-center align-center">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19.8335 14V3.50004C19.8335 3.19062 19.7106 2.89388 19.4918 2.67508C19.273 2.45629 18.9762 2.33337 18.6668 2.33337H3.50016C3.19074 2.33337 2.894 2.45629 2.6752 2.67508C2.45641 2.89388 2.3335 3.19062 2.3335 3.50004V19.8334L7.00016 15.1667H18.6668C18.9762 15.1667 19.273 15.0438 19.4918 14.825C19.7106 14.6062 19.8335 14.3095 19.8335 14ZM24.5002 7.00004H22.1668V17.5H7.00016V19.8334C7.00016 20.1428 7.12308 20.4395 7.34187 20.6583C7.56066 20.8771 7.85741 21 8.16683 21H21.0002L25.6668 25.6667V8.16671C25.6668 7.85729 25.5439 7.56054 25.3251 7.34175C25.1063 7.12296 24.8096 7.00004 24.5002 7.00004Z" fill="#141B38"></path>
                                </svg>
                            </div>

                            <div class="gsc-content">
                                <div class="support-headings"><?php echo esc_html__('Need more support? We\'re here to help.', 'gsheetconnector-wpforms'); ?></div>

                                <a href="https://wordpress.org/support/plugin/gsheetconnector-wpforms/" target="_blank" class="btn btn-primary mt-10 link-hover-white text-decoration-none">
                                    <?php echo esc_html__('Submit a Support Ticket', 'gsheetconnector-wpforms'); ?>
                                    <svg width="10" height="10" viewBox="0 0 6 8" fill="#fff" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1.66681 0L0.726807 0.94L3.78014 4L0.726807 7.06L1.66681 8L5.66681 4L1.66681 0Z"></path>
                                    </svg>
                                </a>
                            </div>

                        </div>

                        <!-- RIGHT SIDE -->
                        <div class="gsc-support-right">

                            <div class="gsc-avatars justify-center">
                                <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/avatar-2.jfif" alt="">
                                <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/avatar-3.png" alt="">
                                <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/avatar-5.jfif" alt="">
                                <img src=" <?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/avatar-4.png" alt="">
                                <img src="<?php echo esc_url(WPFORMS_GOOGLESHEET_URL); ?>/assets/img/avatar.jpeg" alt="">
                            </div>

                            <p class="text-center"><?php echo esc_html__('Our fast and friendly support team is always happy to help!', 'gsheetconnector-wpforms'); ?></p>

                        </div>

                    </div>

                    <!---End Support  ticket--->
                </div>
            </div>


            <!---Start PRO FEATURE--->
            <div class="pro-container mt-30 welcome-wrapper">
                <span class="pro-badge"><?php echo esc_html(__('Amazing Key Features', 'gsheetconnector-wpforms')); ?></span>
                <div class="welcome-heading mb-15 mt-20"><?php echo esc_html(__('Everything You Need to Sync Data', 'gsheetconnector-wpforms')); ?></div>
                <p>
                    <?php echo esc_html(__('Common features shared across every GSheetConnector Pro add-on built for reliability, flexibility, and scale.', 'gsheetconnector-wpforms')); ?>
                </p>

                <!-- LEFT -->
                <div class="d-flex gap-30 mt-30 d-flex-responsiveness">
                    <div class="pro-left w-50">
                        <div class="list dashboard-pro-features">
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


                        <div class="pro-actions mt-30 gap-20">
                            <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro" target="_blank"><button class="pro-btn"> <?php echo esc_html(__('Upgrade to Pro', 'gsheetconnector-wpforms')); ?></button></a>
                            <a href="https://www.gsheetconnector.com/wpforms-google-sheet-connector-pro#features" target="_blank"><?php echo esc_html(__('View Full Features', 'gsheetconnector-wpforms')); ?></a>
                        </div>

                    </div>

                    <!-- RIGHT -->
                    <div class="pro-right w-50">

                        <div class="right-card">

                            <!-- FLOW -->
                            <div class="flow-ui">
                                <div class="flow-step"><?php echo esc_html(__('Form', 'gsheetconnector-wpforms')); ?></div>
                                <div class="line"></div>
                                <div class="flow-step mid"><?php echo esc_html(__('Processing', 'gsheetconnector-wpforms')); ?></div>
                                <div class="line"></div>
                                <div class="flow-step success"><?php echo esc_html(__('Sheet', 'gsheetconnector-wpforms')); ?></div>
                            </div>

                            <!-- STATS -->
                            <div class="sync-stats">
                                <div>
                                    <strong><?php echo esc_html(__('Instant', 'gsheetconnector-wpforms')); ?></strong>
                                    <p><?php echo esc_html(__('Real-time updates', 'gsheetconnector-wpforms')); ?></p>
                                </div>
                                <div>
                                    <strong><?php echo esc_html(__('100%', 'gsheetconnector-wpforms')); ?></strong>
                                    <p><?php echo esc_html(__('Accuracy', 'gsheetconnector-wpforms')); ?></p>
                                </div>
                                <div>
                                    <strong><?php echo esc_html(__('Flexible', 'gsheetconnector-wpforms')); ?></strong>
                                    <p><?php echo esc_html(__('Custom mapping', 'gsheetconnector-wpforms')); ?></p>
                                </div>
                            </div>
                        </div>

                    </div>


                </div>
            </div>
            <!---End PRO FEATURE--->


            <!---Start Video Tutorial Section--->
            <div class="video-section-wrapper mt-30 welcome-wrapper">
                <div class="welcome-heading mb-30">
                    <span><?php echo esc_html__('Video Tutorials', 'gsheetconnector-wpforms'); ?></span>
                </div>
                <div class="video-grid">
                    <div class="video-item">
                        <iframe class="w-100" height="200" src="https://www.youtube.com/embed/ZWlxTnR4eJI" title="Integration of Google Sheets with WordPress WP Forms | Step by Step Guide | FREE Version" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div> 
                    <div class="video-item">
                        <iframe class="w-100" height="200" src="https://www.youtube.com/embed/tgF9GfDjQOw" title="Integration of Google Sheets with WordPress WP Forms | Step by Step Guide | FREE Version" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>


                </div>
            </div>
            <!---End Video Tutorial Section--->

        </div>
    </div>
</div>