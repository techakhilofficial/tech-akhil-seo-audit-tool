<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TA_SACT_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer_branding' ) );
		add_action( 'wp_ajax_ta_sact_refresh_nonce', array( __CLASS__, 'refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_ta_sact_refresh_nonce', array( __CLASS__, 'refresh_nonce' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TA_SACT_PATH . 'tech-akhil-seo-audit-tool.php' ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'seo_audit_checklist_tool', array( __CLASS__, 'shortcode' ) );
	}
	public static function menu() {
		add_menu_page( __( 'Dashboard', 'tech-akhil-seo-audit-tool' ), __( 'SEO Audit', 'tech-akhil-seo-audit-tool' ), 'manage_options', 'tech-akhil-seo-audit-tool', array( __CLASS__, 'dashboard_page' ), 'dashicons-search', 58 );
		add_submenu_page( 'tech-akhil-seo-audit-tool', 'Run Audit', 'Run Audit', 'manage_options', 'ta-sact-audit-tool', array( __CLASS__, 'admin_page' ) );
		add_submenu_page( 'tech-akhil-seo-audit-tool', 'Reports & Export', 'Reports & Export', 'manage_options', 'ta-sact-reports', array( __CLASS__, 'reports_page' ) );
		add_submenu_page( 'tech-akhil-seo-audit-tool', 'Settings', 'Settings', 'manage_options', 'tech-akhil-seo-audit-tool-settings', array( __CLASS__, 'settings_page' ) );
		// Keep supporting pages directly addressable from dashboard cards without adding duplicate submenu items.
		add_submenu_page( null, 'Audit Activity', 'Audit Activity', 'manage_options', 'ta-sact-activity', array( __CLASS__, 'activity_page' ) );
		add_submenu_page( null, 'Search Console Setup', 'Search Console Setup', 'manage_options', 'ta-sact-gsc-guide', array( __CLASS__, 'gsc_guide_page' ) );
		// WordPress automatically inserts a first submenu matching the parent label; rename it to Dashboard.
		global $submenu;
		if ( isset( $submenu['tech-akhil-seo-audit-tool'][0][0] ) ) { $submenu['tech-akhil-seo-audit-tool'][0][0] = __( 'Dashboard', 'tech-akhil-seo-audit-tool' ); }
	}
	public static function plugin_action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=tech-akhil-seo-audit-tool-settings' ) ) . '">Settings</a>' );
		return $links;
	}
	public static function register_settings() {
		register_setting( 'ta_sact_settings', 'ta_sact_theme_mode', array( 'type'=>'string', 'sanitize_callback'=>static function($v){ return in_array($v,array('dark','light'),true)?$v:'dark'; }, 'default'=>'dark' ) );
		register_setting( 'ta_sact_settings', 'ta_sact_accent_color', array( 'type' => 'string', 'sanitize_callback' => static function( $value ) { return is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#6d3df5'; }, 'default' => '#6d3df5' ) );
		$features = array( 'ta_sact_enable_keyword' => true, 'ta_sact_show_action_plan' => true, 'ta_sact_show_signal_summary' => true, 'ta_sact_show_page_table' => true, 'ta_sact_enable_csv' => true, 'ta_sact_enable_print' => true );
		register_setting( 'ta_sact_settings', 'ta_sact_public_branding_enabled', array( 'type' => 'boolean', 'sanitize_callback' => static function( $v ) { return in_array( $v, array( true, 1, '1', 'on' ), true ); }, 'default' => false ) );
		foreach ( $features as $option => $default ) { register_setting( 'ta_sact_settings', $option, array( 'type'=>'boolean', 'sanitize_callback'=>static function( $v ) { return in_array( $v, array( true, 1, '1', 'on' ), true ); }, 'default'=>$default ) ); }

	}
	public static function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'tech-akhil-seo-audit-tool' ) || false !== strpos( $hook, 'ta-sact-' ) ) { wp_enqueue_style( 'ta-sact-admin', TA_SACT_URL . 'assets/admin.css', array(), TA_SACT_VERSION ); wp_enqueue_script( 'ta-sact-admin', TA_SACT_URL . 'assets/admin.js', array(), TA_SACT_VERSION, true ); }
	}
	public static function register_assets() {
		wp_register_style( 'ta-sact-front', TA_SACT_URL . 'assets/front.css', array(), TA_SACT_VERSION );
		wp_register_script( 'ta-sact-front', TA_SACT_URL . 'assets/front.js', array(), TA_SACT_VERSION, true );
		wp_localize_script( 'ta-sact-front', 'taSactFront', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}
	/**
	 * Return a fresh audit nonce for pages restored from browser cache or served through page cache.
	 * The nonce endpoint only issues a token for the public audit form; it does not run an audit.
	 */
	public static function refresh_nonce() {
		nocache_headers();
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'ta_sact_run_audit' ) ) );
	}

	/**
	 * Show consistent Tech Akhil branding on plugin-owned admin screens.
	 */
	public static function admin_footer_branding() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		// Use the current screen API instead of reading the request's page parameter.
		// This is a read-only screen check, so no request nonce is needed.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = ( is_object( $screen ) && isset( $screen->id ) ) ? (string) $screen->id : '';
		$plugin_pages = array(
			'tech-akhil-seo-audit-tool', 'tech-akhil-seo-audit-tool-settings', 'ta-sact-audit-tool',
			'ta-sact-reports', 'ta-sact-activity', 'ta-sact-gsc-guide', 'ta-sact-gsc',
		);
		$is_plugin_screen = 'toplevel_page_tech-akhil-seo-audit-tool' === $screen_id;
		foreach ( $plugin_pages as $plugin_page ) {
			if ( false !== strpos( $screen_id, '_page_' . $plugin_page ) || 0 === strpos( $screen_id, 'admin_page_' . $plugin_page ) ) {
				$is_plugin_screen = true;
				break;
			}
		}
		if ( ! $is_plugin_screen ) { return; }
		echo '<div class="ta-sact-admin-brand-footer">' .
			esc_html__( 'Powered by', 'tech-akhil-seo-audit-tool' ) . ' <a href="https://techakhil.com/" target="_blank" rel="noopener noreferrer">Tech Akhil</a></div>';
	}

	public static function dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$records = get_option( 'ta_sact_audited_sites', array() ); if ( ! is_array( $records ) ) { $records = array(); }
		$hosts = array(); $today = current_time( 'Y-m-d' ); $today_count = 0;
		foreach ( $records as $record ) { $host = isset( $record['site'] ) ? sanitize_text_field( $record['site'] ) : ''; $stamp = isset( $record['time'] ) ? absint( $record['time'] ) : 0; if ( $host ) { $hosts[$host] = true; } if ( $stamp && wp_date( 'Y-m-d', $stamp, wp_timezone() ) === $today ) { $today_count++; } }
		$enabled = 0; foreach ( array( 'ta_sact_enable_keyword','ta_sact_show_action_plan','ta_sact_show_signal_summary','ta_sact_show_page_table','ta_sact_enable_csv','ta_sact_enable_print' ) as $opt ) { if ( (bool) get_option( $opt, true ) ) { $enabled++; } }
		?>
		<div class="wrap ta-sact-admin"><section class="sact-admin-hero"><div><span class="sact-kicker">TECH AKHIL · SEO CONTROL CENTER</span><h1>Welcome to Tech Akhil SEO Audit Tool</h1><p>Website audit insights, report controls and integrations — all in one place.</p></div><div class="sact-status"><span></span><div><strong>Plugin is active</strong><small>Version <?php echo esc_html( TA_SACT_VERSION ); ?></small></div></div><div class="sact-hero-links"><a class="sact-btn sact-btn-light" href="<?php echo esc_url( admin_url( 'admin.php?page=ta-sact-audit-tool' ) ); ?>">Run an audit →</a><a class="sact-btn sact-btn-glass" href="https://techakhil.com/free-seo-audit-tool/" target="_blank" rel="noopener noreferrer">Documentation ↗</a></div></section>
		<div class="sact-stat-grid"><article class="sact-stat sact-blue"><span class="sact-stat-icon">◎</span><div><small>Unique websites audited</small><strong><?php echo esc_html( count( $hosts ) ); ?></strong><em>Based on saved activity</em></div></article><article class="sact-stat sact-pink"><span class="sact-stat-icon">◷</span><div><small>Audits recorded today</small><strong><?php echo esc_html( $today_count ); ?></strong><em>Current WordPress day</em></div></article><article class="sact-stat sact-orange"><span class="sact-stat-icon">▤</span><div><small>Report controls enabled</small><strong><?php echo esc_html( $enabled ); ?>/6</strong><em>Configurable features</em></div></article><article class="sact-stat sact-green"><span class="sact-stat-icon">✓</span><div><small>Crawl limit</small><strong>30 pages</strong><em>Bounded same-host crawl</em></div></article></div>
		<section class="sact-panel sact-shortcode-panel"><div class="sact-panel-head"><div><span class="sact-panel-icon purple">⌘</span><div><h2>Public audit form shortcode</h2><p>Copy this shortcode into a WordPress Shortcode block or page-builder shortcode widget.</p></div></div></div><div class="sact-shortcode-copy-row"><code id="ta-sact-shortcode">[seo_audit_checklist_tool]</code><button type="button" class="sact-btn sact-btn-primary" data-sact-copy="ta-sact-shortcode">Copy shortcode</button><span class="sact-copy-status" aria-live="polite"></span></div></section>
		<div class="sact-admin-grid"><section class="sact-panel sact-panel-main"><div class="sact-panel-head"><div><span class="sact-panel-icon purple">✦</span><div><h2>Quick actions</h2><p>Open each function in its dedicated page — no duplicate settings.</p></div></div></div><div class="sact-action-grid"><a class="sact-action-card" href="<?php echo esc_url( admin_url( 'admin.php?page=ta-sact-audit-tool' ) ); ?>"><span class="purple">⌕</span><strong>Run Audit</strong><small>Open the working audit form and run an audit.</small><b>Start audit →</b></a><a class="sact-action-card" href="<?php echo esc_url( admin_url( 'admin.php?page=ta-sact-activity' ) ); ?>"><span class="blue">◷</span><strong>Audit Activity</strong><small>Domains and date filters</small><b>View activity →</b></a><a class="sact-action-card" href="<?php echo esc_url( admin_url( 'admin.php?page=ta-sact-reports' ) ); ?>"><span class="orange">▤</span><strong>Reports & Export</strong><small>Control report sections</small><b>Manage reports →</b></a><a class="sact-action-card" href="<?php echo esc_url( admin_url( 'admin.php?page=ta-sact-gsc-guide' ) ); ?>"><span class="green">↗</span><strong>Integrations</strong><small>Search Console and external tools</small><b>Connection guide →</b></a></div></section>
		<section class="sact-panel"><div class="sact-panel-head"><div><span class="sact-panel-icon green">✓</span><div><h2>Audit coverage</h2><p>Current built-in checks</p></div></div></div><div class="sact-coverage"><div><span>Metadata & headings</span><b>Included</b></div><div><span>Links & images</span><b>Included</b></div><div><span>Robots & sitemap</span><b>Included</b></div><div><span>Schema signals</span><b>Basic checks</b></div><div><span>Google PageSpeed API</span><b class="muted">External tool only</b></div><div><span>Live keyword rankings</span><b class="muted">Not included</b></div></div></section></div>
		<section class="sact-tip"><span>✦</span><div><strong>Good to know</strong><p>Dashboard statistics use saved activity records only. SEO score, critical issue counts and page performance are shown in an individual audit report, not fabricated here.</p></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=tech-akhil-seo-audit-tool-settings' ) ); ?>" class="sact-btn sact-btn-primary">Dashboard settings</a></section></div>
		<?php
	}
	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap ta-sact-admin"><section class="sact-page-hero"><span class="sact-kicker">PERSONALIZE YOUR REPORT</span><h1>SEO Audit Settings</h1><p>Configure report appearance and turn optional report sections on or off.</p></section><form method="post" action="options.php" class="sact-settings-form"><?php settings_fields( 'ta_sact_settings' ); ?>
		<section class="sact-panel"><h2>Appearance</h2><p class="description">These options affect the public shortcode report.</p><div class="sact-settings-row"><div><label for="ta_sact_theme_mode"><strong>Report theme</strong></label><p>Choose the report surface style.</p></div><select id="ta_sact_theme_mode" name="ta_sact_theme_mode"><option value="dark" <?php selected(get_option('ta_sact_theme_mode','dark'),'dark'); ?>>Dark</option><option value="light" <?php selected(get_option('ta_sact_theme_mode','dark'),'light'); ?>>Light</option></select></div><div class="sact-settings-row"><div><label for="ta_sact_accent_color"><strong>Accent colour</strong></label><p>Buttons and highlights use this colour.</p></div><input type="color" id="ta_sact_accent_color" name="ta_sact_accent_color" value="<?php echo esc_attr(get_option('ta_sact_accent_color','#6d3df5')); ?>"></div></section>
		<section class="sact-panel"><h2>Report sections & actions</h2><p class="description">Switches apply to the public audit form and report.</p><?php $toggles = array( 'ta_sact_enable_keyword' => array('Focus Keyword Analyzer','Show the optional focus and related keyword fields.'), 'ta_sact_show_action_plan' => array('Prioritized action plan','Show the ranked recommended-fix summary.'), 'ta_sact_show_signal_summary' => array('Technical signal summary','Show robots, sitemap, JSON-LD and related signals.'), 'ta_sact_show_page_table' => array('Page-by-page data table','Show the detailed crawl results table.'), 'ta_sact_enable_csv' => array('CSV export','Allow the report CSV download action.'), 'ta_sact_enable_print' => array('Print / Save PDF','Show the browser print action.')); foreach ( $toggles as $option => $data ) : $checked = (bool) get_option( $option, true ); ?><div class="sact-settings-row"><div><strong><?php echo esc_html( $data[0] ); ?></strong><p><?php echo esc_html( $data[1] ); ?></p></div><label class="sact-switch"><input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $checked ); ?>><span></span><em><?php echo $checked ? 'On' : 'Off'; ?></em></label></div><?php endforeach; ?></section>
		<section class="sact-panel"><h2>Public attribution</h2><p class="description">Optional credit shown below the public shortcode only when you explicitly enable it. It is off by default and does not affect plugin functionality.</p><div class="sact-settings-row"><div><strong>Show “Powered by Tech Akhil” on public audit form</strong><p>Enabling this displays a clickable Tech Akhil link on the public form and report. Leave it off if you do not want public attribution.</p></div><label class="sact-switch"><input type="hidden" name="ta_sact_public_branding_enabled" value="0"><input type="checkbox" name="ta_sact_public_branding_enabled" value="1" <?php checked( (bool) get_option( 'ta_sact_public_branding_enabled', false ) ); ?>><span></span><em><?php echo get_option( 'ta_sact_public_branding_enabled', false ) ? 'On' : 'Off'; ?></em></label></div></section>
		<div class="sact-save-row"><?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?><a class="sact-btn sact-btn-light" href="<?php echo esc_url( admin_url( 'admin.php?page=tech-akhil-seo-audit-tool' ) ); ?>">Back to dashboard</a></div></form><section class="sact-panel"><h2>External tools</h2><p>These links open independent services; they do not imply a server-side API integration.</p><div class="sact-link-row"><a class="sact-btn sact-btn-blue" target="_blank" rel="noopener noreferrer" href="https://pagespeed.web.dev/">PageSpeed Insights ↗</a><a class="sact-btn sact-btn-green" target="_blank" rel="noopener noreferrer" href="https://search.google.com/search-console/">Google Search Console ↗</a><a class="sact-btn sact-btn-purple" target="_blank" rel="noopener noreferrer" href="https://validator.schema.org/">Schema Validator ↗</a></div><h3>Public audit form</h3><p>Place this shortcode on a WordPress page:</p><code>[seo_audit_checklist_tool]</code></section></div>
		<?php
	}
	public static function reports_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap ta-sact-admin"><section class="sact-page-hero"><span class="sact-kicker">REPORT CONTROL</span><h1>Reports & Export</h1><p>Report section switches are managed centrally in Settings to avoid duplicate controls.</p><a class="sact-btn sact-btn-primary" href="'.esc_url( admin_url( 'admin.php?page=tech-akhil-seo-audit-tool-settings' ) ).'">Configure report sections →</a></section><section class="sact-panel"><h2>Available report outputs</h2><div class="sact-coverage"><div><span>Prioritized action plan</span><b>'.(get_option('ta_sact_show_action_plan',true)?'Enabled':'Disabled').'</b></div><div><span>Technical signal summary</span><b>'.(get_option('ta_sact_show_signal_summary',true)?'Enabled':'Disabled').'</b></div><div><span>Page-by-page data</span><b>'.(get_option('ta_sact_show_page_table',true)?'Enabled':'Disabled').'</b></div><div><span>CSV export</span><b>'.(get_option('ta_sact_enable_csv',true)?'Enabled':'Disabled').'</b></div><div><span>Print / Save PDF</span><b>'.(get_option('ta_sact_enable_print',true)?'Enabled':'Disabled').'</b></div></div></section></div>';
	}

	public static function gsc_guide_page() {
		if ( ! current_user_can('manage_options') ) return;
		echo '<div class="wrap"><h1>Google Search Console setup</h1><p>Search Console data is private property data and requires OAuth authorization. Current connection is administrator-only; public visitors cannot yet connect their own Google accounts.</p><p><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="https://search.google.com/search-console/">Open Search Console</a> <a class="button" target="_blank" rel="noopener noreferrer" href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com">Enable Search Console API</a> <a class="button" target="_blank" rel="noopener noreferrer" href="https://console.cloud.google.com/auth/clients">OAuth Clients</a> <a class="button" target="_blank" rel="noopener noreferrer" href="https://developers.google.com/webmaster-tools/v1/how-tos/authorizing">Official OAuth guide</a></p><ol><li>Create/select a Google Cloud project.</li><li>Enable Search Console API.</li><li>Configure Google Auth Platform consent details.</li><li>Create OAuth client type Web application.</li><li>Add the exact redirect URI shown under SEO Audit → Search Console to Authorized redirect URIs.</li><li>Save Client ID and Secret under SEO Audit → Search Console and click Connect.</li><li>Select a property your Google account can access.</li></ol><p>Do not share the client secret. API quotas apply.</p><p><a class="button" href="'.esc_url(admin_url('admin.php?page=ta-sact-gsc')).'">Open Search Console connection</a></p></div>';
	}
	public static function activity_page() {
		if ( ! current_user_can('manage_options') ) return;
		self::migrate_legacy_activity();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; value is allow-listed immediately below.
		$filter = isset( $_GET['sact_period'] ) && is_scalar( $_GET['sact_period'] ) ? sanitize_key( wp_unslash( $_GET['sact_period'] ) ) : 'today';
		$allowed = array( 'today', 'month', 'year', 'custom_date', 'custom_range' );
		if ( ! in_array( $filter, $allowed, true ) ) { $filter = 'today'; }
		$today = current_time( 'Y-m-d' );
		$start_date = $today; $end_date = $today;
		if ( 'month' === $filter ) { $start_date = current_time( 'Y-m-01' ); }
		elseif ( 'year' === $filter ) { $start_date = current_time( 'Y-01-01' ); }
		elseif ( 'custom_date' === $filter ) {
			$date = isset( $_GET['sact_date'] ) && is_scalar( $_GET['sact_date'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_date'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date filter; strict YYYY-MM-DD validation follows.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && strtotime( $date ) ) { $start_date = $date; $end_date = $date; }
		}
		elseif ( 'custom_range' === $filter ) {
			$from = isset( $_GET['sact_from'] ) && is_scalar( $_GET['sact_from'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_from'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date filter; strict YYYY-MM-DD validation follows.
			$to = isset( $_GET['sact_to'] ) && is_scalar( $_GET['sact_to'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_to'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date filter; strict YYYY-MM-DD validation follows.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && strtotime( $from ) && strtotime( $to ) ) { $start_date = ( $from <= $to ) ? $from : $to; $end_date = ( $from <= $to ) ? $to : $from; }
		}
		$start_ts = (int) ( new DateTimeImmutable( $start_date . ' 00:00:00', wp_timezone() ) )->getTimestamp();
		$end_ts = (int) ( new DateTimeImmutable( $end_date . ' 23:59:59', wp_timezone() ) )->getTimestamp();
		$records = get_option( 'ta_sact_audited_sites', array() ); if ( ! is_array( $records ) ) { $records = array(); }
		$sites = array();
		foreach ( $records as $record ) { $stamp = isset( $record['time'] ) ? absint( $record['time'] ) : 0; $site = isset( $record['site'] ) ? sanitize_text_field( $record['site'] ) : ''; if ( $stamp >= $start_ts && $stamp <= $end_ts && '' !== $site ) { if ( ! isset( $sites[ $site ] ) || $stamp > $sites[ $site ] ) { $sites[ $site ] = $stamp; } } }
		arsort( $sites );
		echo '<div class="wrap"><h1>Audited Websites</h1><p>This page stores only the website domain and audit date. It does not show visitor names, individual report details, action logs or IP addresses. Records are retained for up to one year, subject to a storage cap.</p>';
		echo '<form method="get" class="sact-audit-filter"><input type="hidden" name="page" value="ta-sact-activity"><label for="sact_period"><strong>Date filter </strong></label> <select name="sact_period" id="sact_period"><option value="today" '.selected( $filter, 'today', false ).'>Today</option><option value="month" '.selected( $filter, 'month', false ).'>This month</option><option value="year" '.selected( $filter, 'year', false ).'>This year</option><option value="custom_date" '.selected( $filter, 'custom_date', false ).'>Custom date</option><option value="custom_range" '.selected( $filter, 'custom_range', false ).'>Custom date range</option></select> ';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter values are escaped on output and validated above.
		$date_value = isset( $_GET['sact_date'] ) && is_scalar( $_GET['sact_date'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_date'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters; values are escaped on output.
		$from_value = isset( $_GET['sact_from'] ) && is_scalar( $_GET['sact_from'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_from'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters; values are escaped on output.
		$to_value = isset( $_GET['sact_to'] ) && is_scalar( $_GET['sact_to'] ) ? sanitize_text_field( wp_unslash( $_GET['sact_to'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters; values are escaped on output.
		echo '<label for="sact_date">Date </label> <input type="date" id="sact_date" name="sact_date" value="'.esc_attr( $date_value ).'"> <label for="sact_from">From </label> <input type="date" id="sact_from" name="sact_from" value="'.esc_attr( $from_value ).'"> <label for="sact_to">To </label> <input type="date" id="sact_to" name="sact_to" value="'.esc_attr( $to_value ).'"> ';
		submit_button( 'Apply filter', 'primary', '', false ); echo ' <a class="button" href="'.esc_url( admin_url( 'admin.php?page=ta-sact-activity' ) ).'">Reset</a></form>';
		echo '<p><strong>'.esc_html( count( $sites ) ).'</strong> unique website(s) audited from <strong>'.esc_html( $start_date ).'</strong> to <strong>'.esc_html( $end_date ).'</strong>.</p><table class="widefat striped"><thead><tr><th>Website / Domain</th><th>Last audit date</th></tr></thead><tbody>';
		if ( empty( $sites ) ) { echo '<tr><td colspan="2">No websites found for this date filter.</td></tr>'; }
		foreach ( $sites as $site => $stamp ) { echo '<tr><td>'.esc_html( $site ).'</td><td>'.esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $stamp, wp_timezone() ) ).'</td></tr>'; }
		echo '</tbody></table></div>';
	}
	private static function normalize_audited_host( $url ) {
		$host = wp_parse_url( trim( (string) $url ), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) { return ''; }
		$host = strtolower( rtrim( $host, '.' ) );
		if ( 0 === strpos( $host, 'www.' ) ) { $host = substr( $host, 4 ); }
		return sanitize_text_field( $host );
	}
	private static function prune_audited_sites( $records ) {
		$cutoff = current_time( 'timestamp' ) - YEAR_IN_SECONDS;
		$records = array_values( array_filter( $records, static function( $record ) use ( $cutoff ) { return isset( $record['site'], $record['time'] ) && is_string( $record['site'] ) && absint( $record['time'] ) >= $cutoff; } ) );
		return array_slice( $records, 0, 2000 );
	}
	private static function log_activity( $url, $action = 'seo-audit', $status = 'completed' ) {
		if ( 'completed' !== $status ) { return; }
		$site = self::normalize_audited_host( $url ); if ( '' === $site ) { return; }
		$records = get_option( 'ta_sact_audited_sites', array() ); if ( ! is_array( $records ) ) { $records = array(); }
		$now = current_time( 'timestamp' ); $day = wp_date( 'Y-m-d', $now, wp_timezone() );
		foreach ( $records as $record ) { if ( isset( $record['site'], $record['time'] ) && $record['site'] === $site && wp_date( 'Y-m-d', absint( $record['time'] ), wp_timezone() ) === $day ) { return; } }
		array_unshift( $records, array( 'site' => $site, 'time' => $now ) );
		update_option( 'ta_sact_audited_sites', self::prune_audited_sites( $records ), false );
	}
	private static function migrate_legacy_activity() {
		if ( get_option( 'ta_sact_activity_migrated', false ) ) { return; }
		$legacy = get_option( 'ta_sact_activity_log', array() ); $records = get_option( 'ta_sact_audited_sites', array() ); if ( ! is_array( $records ) ) { $records = array(); }
		if ( is_array( $legacy ) ) { foreach ( $legacy as $row ) { if ( ! isset( $row['url'], $row['time'] ) || ( isset( $row['status'] ) && 'completed' !== $row['status'] ) ) { continue; } $site = self::normalize_audited_host( $row['url'] ); if ( '' === $site ) { continue; } try { $dt = new DateTimeImmutable( $row['time'], wp_timezone() ); $stamp = $dt->getTimestamp(); } catch ( Exception $e ) { continue; } $records[] = array( 'site' => $site, 'time' => $stamp ); } }
		update_option( 'ta_sact_audited_sites', self::prune_audited_sites( $records ), false );
		delete_option( 'ta_sact_activity_log' ); update_option( 'ta_sact_activity_migrated', 1, false );
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'tech-akhil-seo-audit-tool' ) ); }
		self::register_assets();
		wp_enqueue_style( 'ta-sact-front', TA_SACT_URL . 'assets/front.css', array(), TA_SACT_VERSION );
		?>
		<div class="wrap ta-sact-admin"><section class="sact-page-hero"><span class="sact-kicker">BUILT-IN SEO CHECKS</span><h1>Run an SEO Audit</h1><p>Enter a public website URL below to run the plugin's built-in audit. No third-party API is needed for these checks.</p></section>
		<?php echo do_shortcode( '[seo_audit_checklist_tool]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin shortcode output. ?>
		<section class="sact-panel"><h2>Audit scope and limitations</h2><ul class="ul-disc"><li>Up to 30 same-host pages and a bounded time budget; selected extra internal link targets are checked.</li><li>JavaScript-rendered content is not executed, and server response time is not a Core Web Vitals measurement.</li><li>Actual Google indexing, backlinks, competitor data and real Core Web Vitals require external data sources and are not fabricated by this built-in audit.</li></ul></section></div>
		<?php
	}

	public static function shortcode() {
		wp_enqueue_style( 'ta-sact-front' );
		wp_enqueue_script( 'ta-sact-front' );
		$accent_color = sanitize_hex_color( get_option( 'ta_sact_accent_color', '#6d3df5' ) );
		if ( ! $accent_color ) { $accent_color = '#6d3df5'; }
		wp_add_inline_style( 'ta-sact-front', '.ta-sact-front{--ta-sact-accent:' . $accent_color . ';}' );

		$report = null; $error = ''; $focus_keyword = ''; $related_keywords = '';
		$input = home_url( '/' );
		if ( isset( $_POST['ta_sact_action'] ) ) {
			$action = is_scalar( $_POST['ta_sact_action'] ) ? sanitize_key( wp_unslash( $_POST['ta_sact_action'] ) ) : '';
			$nonce  = isset( $_POST['ta_sact_nonce'] ) && is_scalar( $_POST['ta_sact_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ta_sact_nonce'] ) ) : '';

			// Verify the form token before processing any request data or applying rate limits.
			if ( ! wp_verify_nonce( $nonce, 'ta_sact_run_audit' ) ) {
				$error = __( 'Security check failed because the form token is missing or expired. Refresh the page and try again.', 'tech-akhil-seo-audit-tool' );
			} elseif ( ! isset( $_POST['ta_sact_rate_limit_consent'] ) || ! is_scalar( $_POST['ta_sact_rate_limit_consent'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['ta_sact_rate_limit_consent'] ) ) ) {
				$error = __( 'Please confirm the temporary, on-site abuse-prevention rate limit before running an audit.', 'tech-akhil-seo-audit-tool' );
			} else {
				$ip       = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
				$rate_key = 'ta_sact_public_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
				if ( ! current_user_can( 'manage_options' ) && get_transient( $rate_key ) ) {
					$error = __( 'Please wait 60 seconds before running another public audit.', 'tech-akhil-seo-audit-tool' );
				} else {
					if ( ! current_user_can( 'manage_options' ) ) { set_transient( $rate_key, 1, 60 ); }
					$url_raw = '';
					if ( isset( $_POST['ta_sact_url'] ) && is_scalar( $_POST['ta_sact_url'] ) ) {
						// URL input is unslashed, then restricted to HTTP(S) by esc_url_raw() before use.
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized as an HTTP(S) URL on the next line before it is used.
						$url_raw = wp_unslash( $_POST['ta_sact_url'] );
					}
					$input = esc_url_raw( trim( $url_raw ), array( 'http', 'https' ) );
					$focus_keyword = isset( $_POST['ta_sact_focus_keyword'] ) && is_scalar( $_POST['ta_sact_focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['ta_sact_focus_keyword'] ) ) : '';
					$related_keywords = isset( $_POST['ta_sact_related_keywords'] ) && is_scalar( $_POST['ta_sact_related_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['ta_sact_related_keywords'] ) ) : '';
					$related_keywords = implode( ', ', array_slice( array_filter( array_map( 'trim', explode( ',', $related_keywords ) ) ), 0, 10 ) );
					if ( 'audit' === $action ) {
						$report = TA_SACT_Crawler::audit( $input );
						if ( ! is_wp_error( $report ) && isset( $_POST['ta_sact_store_activity'] ) && is_scalar( $_POST['ta_sact_store_activity'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ta_sact_store_activity'] ) ) ) { self::log_activity( $input ); }
						if ( ! is_wp_error( $report ) ) {
							if ( '' !== $focus_keyword && get_option( 'ta_sact_enable_keyword', true ) ) { $report['_keyword_analysis'] = self::analyze_focus_keyword( isset( $report['pages'][0] ) ? $report['pages'][0] : array(), $focus_keyword, $related_keywords ); }
							// Keep extracted page text only during analysis; do not carry it into the rendered report/export transient.
							foreach ( $report['pages'] as &$page_row ) { unset( $page_row['text_content'], $page_row['image_alts'], $page_row['anchor_texts'], $page_row['h2'], $page_row['h3'] ); } unset( $page_row );
						}
						if ( is_wp_error( $report ) ) { $error = $report->get_error_message(); $report = null; }
					}
				}
			}
		}
		ob_start();
		?>
		<div class="ta-sact-front ta-sact-theme-<?php echo esc_attr( get_option( 'ta_sact_theme_mode', 'dark' ) ); ?>">
			<section class="ta-sact-hero"><p class="ta-sact-eyebrow"><?php esc_html_e( 'TECH AKHIL · WEBSITE INTELLIGENCE', 'tech-akhil-seo-audit-tool' ); ?></p><h2><?php esc_html_e( 'SEO Audit Studio', 'tech-akhil-seo-audit-tool' ); ?></h2><p><?php esc_html_e( 'Find on-page and technical issues, see exactly which URL is affected, and get practical next steps.', 'tech-akhil-seo-audit-tool' ); ?></p></section>
			<form class="ta-sact-form" method="post">
				<input type="hidden" name="ta_sact_action" value="audit">
				<label for="ta-sact-url"><?php esc_html_e( 'Website URL to audit', 'tech-akhil-seo-audit-tool' ); ?></label>
				<input type="url" id="ta-sact-url" name="ta_sact_url" value="<?php echo esc_attr( $input ); ?>" required>
				<?php if ( get_option( 'ta_sact_enable_keyword', true ) ) : ?><div class="ta-sact-keyword-fields"><h3><?php esc_html_e( 'Focus Keyword Analyzer (optional)', 'tech-akhil-seo-audit-tool' ); ?></h3><label for="ta-sact-focus-keyword"><?php esc_html_e( 'Focus keyword', 'tech-akhil-seo-audit-tool' ); ?></label><input type="text" id="ta-sact-focus-keyword" name="ta_sact_focus_keyword" maxlength="100" value="<?php echo esc_attr( $focus_keyword ); ?>" placeholder="e.g. wooden key holder"><label for="ta-sact-related-keywords"><?php esc_html_e( 'Related keywords (optional, comma-separated)', 'tech-akhil-seo-audit-tool' ); ?></label><input type="text" id="ta-sact-related-keywords" name="ta_sact_related_keywords" maxlength="500" value="<?php echo esc_attr( $related_keywords ); ?>" placeholder="e.g. wall key organizer, wooden wall decor"><p class="ta-sact-help"><?php esc_html_e( 'Checks the entered page HTML. No search volume, ranking or keyword difficulty is estimated.', 'tech-akhil-seo-audit-tool' ); ?></p></div><?php endif; ?>
				<label class="ta-sact-activity-consent"><input type="checkbox" name="ta_sact_rate_limit_consent" value="1" required> <?php esc_html_e( 'Required: I agree that this site may temporarily use a one-way hash of my IP address for a 60-second abuse-prevention cooldown. The temporary value stays on this WordPress site and is not sent to Tech Akhil.', 'tech-akhil-seo-audit-tool' ); ?></label>
				<label class="ta-sact-activity-consent"><input type="checkbox" name="ta_sact_store_activity" value="1"> <?php esc_html_e( 'Optional: save this website’s domain and audit date in this site’s Audit Activity list for up to one year. This information stays in this WordPress database and is not sent to Tech Akhil.', 'tech-akhil-seo-audit-tool' ); ?></label>
				<?php wp_nonce_field( 'ta_sact_run_audit', 'ta_sact_nonce' ); ?>
				<div class="ta-sact-buttons"><button name="ta_sact_action" value="audit"><?php esc_html_e( 'Run SEO Audit', 'tech-akhil-seo-audit-tool' ); ?></button></div>
				<p class="ta-sact-help"><?php esc_html_e( 'Audit is limited to 30 pages on the entered host. Please audit only websites you own or have permission to test. A basic per-IP cooldown applies. Speed analysis opens Google PageSpeed Insights separately and does not use this server API.', 'tech-akhil-seo-audit-tool' ); ?></p>
			</form>
			<?php if ( $error ) : ?><div class="ta-sact-alert ta-sact-alert-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php if ( is_array( $report ) ) { self::render_report( $report ); } ?>
			<?php if ( ! is_admin() && get_option( 'ta_sact_public_branding_enabled', false ) ) : ?><footer class="ta-sact-brand-footer"><span><?php esc_html_e( 'Powered by', 'tech-akhil-seo-audit-tool' ); ?></span> <a href="https://techakhil.com/" target="_blank" rel="noopener noreferrer">Tech Akhil</a></footer><?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}
	private static function analyze_focus_keyword( $page, $keyword, $related_csv ) {
		$keyword = trim( wp_strip_all_tags( (string) $keyword ) );
		if ( '' === $keyword || empty( $page['url'] ) || empty( $page['status_code'] ) || (int) $page['status_code'] >= 400 ) { return array( 'keyword'=>$keyword, 'related'=>array(), 'score'=>null, 'complete'=>false, 'checks'=>array( array( 'label'=>'Page content unavailable', 'status'=>'info', 'detail'=>'The entered page could not be fetched successfully, so keyword checks were not completed.', 'fix'=>'Check the URL and make sure the page is publicly accessible. Run the audit again when the page responds.' ) ) ); }
		$related = array_slice( array_filter( array_map( 'trim', explode( ',', (string) $related_csv ) ) ), 0, 10 );
		$text = isset( $page['text_content'] ) ? (string) $page['text_content'] : '';
		$lower_text = function_exists('mb_strtolower') ? mb_strtolower($text,'UTF-8') : strtolower($text);
		$lower_keyword = function_exists('mb_strtolower') ? mb_strtolower($keyword,'UTF-8') : strtolower($keyword);
		$contains = static function($haystack,$needle) { $h=function_exists('mb_strtolower')?mb_strtolower((string)$haystack,'UTF-8'):strtolower((string)$haystack); $n=function_exists('mb_strtolower')?mb_strtolower((string)$needle,'UTF-8'):strtolower((string)$needle); return ''!==trim($n) && false!==strpos($h,$n); };
		$checks = array(); $score = 0; $max_score = 0;
		$add = static function($label,$ok,$weight,$detail,$fix='') use (&$checks,&$score,&$max_score) { $max_score += $weight; if($ok){$score += $weight;} $checks[] = array('label'=>$label,'status'=>$ok?'pass':'improve','detail'=>$detail,'fix'=>$fix); };
		$title_ok = $contains($page['title'] ?? '',$keyword);
		$add('SEO title', $title_ok, 20, $title_ok ? 'The focus keyword appears in the detected title.' : 'The exact focus keyword was not detected in the title.', 'Write a descriptive title that accurately matches the page topic. Include the keyword naturally if it reads well.');
		$meta_ok = $contains($page['description'] ?? '',$keyword);
		$add('Meta description', $meta_ok, 10, $meta_ok ? 'The focus keyword appears in the meta description.' : 'The exact focus keyword was not detected in the meta description.', 'Write a unique, useful summary of the page. Include the keyword only when natural; search engines may choose a different snippet.');
		$h1_text = implode(' ', $page['h1'] ?? array()); $h1_ok = $contains($h1_text,$keyword);
		$add('H1 heading', $h1_ok, 15, $h1_ok ? 'The focus keyword appears in an H1 heading.' : 'The exact focus keyword was not detected in the H1 headings.', 'Make the main heading clear and relevant to the page. Do not force an awkward exact-match phrase.');
		$content_ok = $contains($text,$keyword);
		$add('Main page content', $content_ok, 15, $content_ok ? 'The focus keyword appears in the text extracted from the returned HTML.' : 'The focus keyword was not detected in the text extracted from the returned HTML.', 'Explain the page topic clearly in useful, original content. JavaScript-rendered text may not be visible to this lightweight crawler.');
		$word_parts = preg_split('/\s+/u', trim($text)); if ( ! is_array( $word_parts ) ) { $word_parts = preg_split('/\s+/', trim($text)); } $first_words = implode(' ', array_slice( is_array($word_parts) ? $word_parts : array(), 0, 100 )); $intro_ok = $contains($first_words,$keyword);
		$add('Opening content', $intro_ok, 10, $intro_ok ? 'The focus keyword appears in the first 100 extracted words.' : 'The focus keyword was not detected in the first 100 extracted words.', 'Make the page purpose clear early where appropriate. This is a writing suggestion, not a strict ranking requirement.');
		$slug = (string) ( $page['slug'] ?? '' ); $slug_ok = $contains(str_replace(array('-','_'),' ',$slug),$keyword);
		$add('URL slug', $slug_ok, 10, $slug_ok ? 'The URL slug contains the focus keyword phrase.' : 'The URL slug does not contain the exact focus keyword phrase.', 'Use a short, readable URL when creating a new page. Do not change an established URL without a redirect plan.');
		$related_found = array(); foreach($related as $term){ if($contains($page['title']??'',$term)||$contains($page['description']??'',$term)||$contains($h1_text,$term)||$contains($text,$term)){ $related_found[]=$term; } }
		$related_ok = empty($related) || count($related_found) > 0;
		$related_detail = empty($related) ? 'No related keywords were provided; this check is informational and is excluded from the score.' : (count($related_found).' of '.count($related).' related keywords were found in title, description, H1 or extracted page text'.( $related_found ? ': '.implode(', ',$related_found) : '' ).'.');
		if ( empty($related) ) { $checks[] = array('label'=>'Related keyword coverage','status'=>'info','detail'=>$related_detail,'fix'=>'Add optional related phrases if you want to check topical coverage.'); } else { $add('Related keyword coverage', $related_ok, 10, $related_detail, 'Cover related subtopics only when they help the reader. Do not add a phrase merely to increase keyword counts.'); }
		$alts = implode(' ', $page['image_alts'] ?? array()); $anchors = implode(' ', $page['anchor_texts'] ?? array()); $support_ok = $contains($alts,$keyword)||$contains($anchors,$keyword); foreach($related as $term){ if($contains($alts,$term)||$contains($anchors,$term)){ $support_ok=true; break; } }
		$support_applicable = !empty($page['images']) || !empty($page['links']);
		if(!$support_applicable){ $checks[] = array('label'=>'Image alt and link text','status'=>'info','detail'=>'No image or link text was available for this check; this item is excluded from the score.','fix'=>'When relevant, use descriptive image alt text and meaningful internal-link text.'); }
		else { $add('Image alt and link text', $support_ok, 10, $support_ok ? 'A focus or related phrase was found in image alt text or link text.' : 'No focus or related keyword was detected in image alt text or link text.', 'Use descriptive alt text for informative images and meaningful anchor text where it helps users. Do not keyword-stuff alt attributes.'); }
		return array('keyword'=>$keyword,'related'=>$related,'score'=>$max_score ? (int) round(($score / $max_score) * 100) : null,'complete'=>true,'checks'=>$checks,'related_found'=>$related_found);
	}
	private static function render_report( $r ) {
		?>
		<section class="ta-sact-report" aria-live="polite">
			<div class="ta-sact-report-heading"><div><p class="ta-sact-eyebrow"><?php esc_html_e( 'CRAWL REPORT', 'tech-akhil-seo-audit-tool' ); ?></p><h3><?php echo esc_html( $r['site'] ); ?></h3><p><?php /* translators: 1: Number of pages scanned. 2: Time taken for the crawl, in seconds. */ echo esc_html( sprintf( __( '%1$d pages scanned · %2$s seconds', 'tech-akhil-seo-audit-tool' ), count( $r['pages'] ), $r['duration'] ) ); ?></p></div><div class="ta-sact-report-actions"><?php if ( get_option( 'ta_sact_enable_print', true ) ) : ?><button type="button" class="ta-sact-print" data-sact-print><?php esc_html_e( 'Print / Save PDF', 'tech-akhil-seo-audit-tool' ); ?></button><?php endif; ?><?php if ( get_option( 'ta_sact_enable_csv', true ) && class_exists( 'TA_SACT_History' ) ) : $csv_url = TA_SACT_History::export_url( $r ); if ( $csv_url ) : ?><a class="ta-sact-print" href="<?php echo esc_url( $csv_url ); ?>"><?php esc_html_e( 'Export CSV', 'tech-akhil-seo-audit-tool' ); ?></a><?php endif; endif; ?></div></div>
			<div class="ta-sact-metrics"><div><span><?php esc_html_e( 'Issues', 'tech-akhil-seo-audit-tool' ); ?></span><strong><?php echo esc_html( count( $r['issues'] ) ); ?></strong></div><div><span><?php esc_html_e( 'Errors', 'tech-akhil-seo-audit-tool' ); ?></span><strong><?php echo esc_html( $r['counts']['error'] ); ?></strong></div><div><span><?php esc_html_e( 'Warnings', 'tech-akhil-seo-audit-tool' ); ?></span><strong><?php echo esc_html( $r['counts']['warning'] ); ?></strong></div><div><span><?php esc_html_e( 'Notices', 'tech-akhil-seo-audit-tool' ); ?></span><strong><?php echo esc_html( $r['counts']['notice'] ); ?></strong></div></div>
			<?php if ( ! empty( $r['_keyword_analysis'] ) ) : $ka = $r['_keyword_analysis']; ?>
			<section class="ta-sact-keyword-report"><div class="ta-sact-keyword-heading"><div><p class="ta-sact-eyebrow">FOCUS KEYWORD ANALYZER</p><h3><?php echo esc_html( $ka['keyword'] ); ?></h3><p>On-page checks for the first entered URL only. This is an internal checklist score, not a Google ranking score.</p></div><div class="ta-sact-keyword-score"><strong><?php echo esc_html( null === $ka['score'] ? 'N/A' : $ka['score'] ); ?></strong><span><?php echo null === $ka['score'] ? '' : '/100'; ?></span><small><?php echo null === $ka['score'] ? 'Analysis incomplete' : 'On-page checklist'; ?></small></div></div>
			<?php if ( ! empty( $ka['related'] ) ) : ?><p><strong>Related keywords:</strong> <?php echo esc_html( implode( ', ', $ka['related'] ) ); ?></p><?php endif; ?>
			<div class="ta-sact-keyword-checks"><?php foreach ( $ka['checks'] as $check ) : ?><details class="ta-sact-keyword-check"><summary><span class="ta-sact-keyword-status ta-sact-keyword-<?php echo esc_attr( $check['status'] ); ?>"><?php echo esc_html( 'pass' === $check['status'] ? 'FOUND' : ( 'info' === $check['status'] ? 'INFO' : 'IMPROVE' ) ); ?></span><strong><?php echo esc_html( $check['label'] ); ?></strong><span class="ta-sact-issue-chevron" aria-hidden="true">＋</span></summary><div class="ta-sact-issue-body"><p><?php echo esc_html( $check['detail'] ); ?></p><?php if ( ! empty( $check['fix'] ) ) : ?><div class="ta-sact-fix"><strong>Recommended action</strong><p><?php echo esc_html( $check['fix'] ); ?></p></div><?php endif; ?></div></details><?php endforeach; ?></div>
			</section><?php endif; ?>
			<?php if ( ! empty( $r['issues'] ) ) : $action_items = $r['issues']; usort( $action_items, static function( $a, $b ) { $rank = array( 'error' => 0, 'warning' => 1, 'notice' => 2 ); $ra = $rank[ $a['severity'] ] ?? 3; $rb = $rank[ $b['severity'] ] ?? 3; return $ra === $rb ? 0 : ( $ra < $rb ? -1 : 1 ); } ); ?>
			<?php if ( get_option( 'ta_sact_show_action_plan', true ) ) : ?><section class="ta-sact-action-plan"><h3><?php esc_html_e( 'Prioritized action plan', 'tech-akhil-seo-audit-tool' ); ?></h3><p><?php esc_html_e( 'Start with critical technical issues, then review warnings. Priority is a practical triage guide, not a prediction of ranking impact.', 'tech-akhil-seo-audit-tool' ); ?></p><ol><?php foreach ( array_slice( $action_items, 0, 8 ) as $action_item ) : ?><li><strong><?php echo esc_html( $action_item['title'] ); ?></strong> <span class="ta-sact-priority"><?php echo esc_html( ( $action_item['priority'] ?? 'Low' ) . ' priority · ' . ( $action_item['effort'] ?? 'Low' ) . ' effort' ); ?></span><p><?php echo esc_html( $action_item['fix'] ); ?></p></li><?php endforeach; ?></ol><?php if ( count( $action_items ) > 8 ) : ?><p><?php /* translators: %d: Total number of findings in the action plan. */ echo esc_html( sprintf( __( 'Showing the first 8 of %d findings in the action plan; use the issue tabs below for all findings.', 'tech-akhil-seo-audit-tool' ), count( $action_items ) ) ); ?></p><?php endif; ?></section><?php endif; ?>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Issues & recommended fixes', 'tech-akhil-seo-audit-tool' ); ?></h3>
			<?php if ( ! $r['issues'] ) : ?><div class="ta-sact-alert"><?php esc_html_e( 'No issue from the current rules was detected. This is not a guarantee that the site has no SEO problems.', 'tech-akhil-seo-audit-tool' ); ?></div><?php endif; ?>
			<?php $severity_tabs = array( 'error' => 'Critical', 'warning' => 'Warnings', 'notice' => 'Notices' ); ?>
			<div class="ta-sact-issue-tabs" role="tablist" aria-label="Filter issues">
			<?php foreach ( $severity_tabs as $severity_key => $severity_label ) : $severity_count = 0; foreach ( $r['issues'] as $issue_item ) { if ( $issue_item['severity'] === $severity_key ) { $severity_count++; } } ?>
			<button type="button" class="ta-sact-issue-tab<?php echo 'error' === $severity_key ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo 'error' === $severity_key ? 'true' : 'false'; ?>" data-sact-filter="<?php echo esc_attr( $severity_key ); ?>"><?php echo esc_html( $severity_label ); ?> <span><?php echo esc_html( $severity_count ); ?></span></button>
			<?php endforeach; ?></div>
			<div class="ta-sact-issue-list">
			<?php foreach ( $r['issues'] as $issue_index => $i ) : ?><details class="ta-sact-issue ta-sact-severity-<?php echo esc_attr( $i['severity'] ); ?>" data-sact-severity="<?php echo esc_attr( $i['severity'] ); ?>"><summary><span class="ta-sact-pill"><?php echo esc_html( 'error' === $i['severity'] ? 'CRITICAL' : strtoupper( $i['severity'] ) ); ?></span><strong><?php echo esc_html( $i['title'] ); ?></strong><span class="ta-sact-issue-code"><?php echo esc_html( $i['code'] ); ?></span><span class="ta-sact-issue-chevron" aria-hidden="true">＋</span></summary><div class="ta-sact-issue-body"><p><strong><?php esc_html_e( 'Affected URL:', 'tech-akhil-seo-audit-tool' ); ?></strong> <a href="<?php echo esc_url( $i['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $i['url'] ); ?></a></p><p><?php echo esc_html( $i['detail'] ); ?></p><div class="ta-sact-fix"><strong><?php esc_html_e( 'How to fix:', 'tech-akhil-seo-audit-tool' ); ?></strong><p><?php echo esc_html( $i['fix'] ); ?></p></div></div></details><?php endforeach; ?>
			</div>
			
			<?php if ( get_option( 'ta_sact_show_signal_summary', true ) ) : ?><section class="ta-sact-signal-summary"><h3><?php esc_html_e( 'Technical, mobile, UX & security signals', 'tech-akhil-seo-audit-tool' ); ?></h3><p><?php esc_html_e( 'Lightweight HTML/response checks only. These signals do not replace browser-based mobile testing, a professional security scan, analytics debugging or a full accessibility audit.', 'tech-akhil-seo-audit-tool' ); ?></p><div class="ta-sact-signal-grid"><div><strong>robots.txt</strong><span><?php echo esc_html( ! empty( $r['robots_status'] ) ? 'HTTP ' . $r['robots_status'] : 'Not confirmed' ); ?></span></div><div><strong>Sitemap</strong><span><?php echo esc_html( ! empty( $r['sitemap_url'] ) ? $r['sitemap_url'] : 'Not confirmed' ); ?></span></div><div><strong>JSON-LD blocks</strong><span><?php echo esc_html( $r['signals']['jsonld_blocks'] ?? 0 ); ?><?php if ( ! empty( $r['signals']['schema_types'] ) ) : ?> · <?php echo esc_html( implode( ', ', array_slice( $r['signals']['schema_types'], 0, 4 ) ) ); ?><?php endif; ?></span></div><div><strong>Analytics signal</strong><span><?php echo ! empty( $r['signals']['analytics_detected'] ) ? 'Detected' : 'Not detected'; ?></span></div><div><strong>Forms in HTML</strong><span><?php echo ! empty( $r['signals']['forms_detected'] ) ? 'Detected' : 'Not detected'; ?></span></div><div><strong>Pages scanned</strong><span><?php echo esc_html( count( $r['pages'] ) . ' / up to ' . (int) $r['page_limit'] ); ?></span></div></div></section><?php endif; ?>
			<?php if ( get_option( 'ta_sact_show_page_table', true ) ) : ?>
			<section class="ta-sact-page-data-section">
				<div class="ta-sact-page-section-heading"><div><p class="ta-sact-eyebrow">PAGE LEVEL ANALYSIS</p><h3><?php esc_html_e( 'Page-by-page reports', 'tech-akhil-seo-audit-tool' ); ?></h3><p><?php esc_html_e( 'Open any page to view its technical data and the issues with recommended fixes for that page.', 'tech-akhil-seo-audit-tool' ); ?></p></div><span class="ta-sact-page-count"><?php echo esc_html( count( $r['pages'] ) ); ?> <?php esc_html_e( 'pages', 'tech-akhil-seo-audit-tool' ); ?></span></div>
				<div class="ta-sact-page-list">
				<?php foreach ( $r['pages'] as $page_index => $p ) : $page_issues = array(); foreach ( $r['issues'] as $page_issue ) { if ( isset( $page_issue['url'] ) && untrailingslashit( $page_issue['url'] ) === untrailingslashit( $p['url'] ) ) { $page_issues[] = $page_issue; } } $page_errors = 0; $page_warnings = 0; $page_notices = 0; foreach ( $page_issues as $page_issue_count ) { if ( 'error' === $page_issue_count['severity'] ) { $page_errors++; } elseif ( 'warning' === $page_issue_count['severity'] ) { $page_warnings++; } else { $page_notices++; } } ?>
				<details class="ta-sact-page-card">
					<summary><span class="ta-sact-page-number"><?php echo esc_html( $page_index + 1 ); ?></span><span class="ta-sact-page-summary"><strong><?php echo esc_html( $p['slug'] ? $p['slug'] : $p['url'] ); ?></strong><small><?php echo esc_html( $p['url'] ); ?></small><span class="ta-sact-page-badges"><em class="ta-sact-page-status <?php echo (int) $p['status_code'] >= 200 && (int) $p['status_code'] < 400 ? 'is-good' : 'is-bad'; ?>">HTTP <?php echo esc_html( $p['status_code'] ? $p['status_code'] : 'N/A' ); ?></em><em class="is-critical"><?php echo esc_html( $page_errors ); ?> <?php esc_html_e( 'Critical', 'tech-akhil-seo-audit-tool' ); ?></em><em class="is-warning"><?php echo esc_html( $page_warnings ); ?> <?php esc_html_e( 'Warnings', 'tech-akhil-seo-audit-tool' ); ?></em><em class="is-notice"><?php echo esc_html( $page_notices ); ?> <?php esc_html_e( 'Notices', 'tech-akhil-seo-audit-tool' ); ?></em></span></span><span class="ta-sact-page-open-label"><?php esc_html_e( 'View report', 'tech-akhil-seo-audit-tool' ); ?> <b aria-hidden="true">＋</b></span></summary>
					<div class="ta-sact-page-card-body">
						<div class="ta-sact-page-metrics"><div><small>Title length</small><strong><?php echo esc_html( $p['title_length'] ); ?> chars</strong></div><div><small>Meta description</small><strong><?php echo esc_html( $p['description_length'] ); ?> chars</strong></div><div><small>Headings</small><strong><?php echo esc_html( count( $p['h1'] ) . ' H1 / ' . $p['h2_count'] . ' H2' ); ?></strong></div><div><small>Images missing alt</small><strong><?php echo esc_html( $p['missing_alt'] . ' / ' . $p['images'] ); ?></strong></div></div>
						<div class="ta-sact-page-detail-grid">
							<div class="ta-sact-page-detail"><span>Page title</span><strong><?php echo esc_html( $p['title'] ? $p['title'] : 'Not detected' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Meta description</span><strong><?php echo esc_html( $p['description'] ? $p['description'] : 'Not detected' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>H1 headings</span><strong><?php echo esc_html( $p['h1'] ? implode( ' · ', $p['h1'] ) : 'Not detected' ); ?></strong></div><div class="ta-sact-page-detail"><span>Heading counts</span><strong><?php echo esc_html( count( $p['h1'] ) . ' H1 · ' . (int) ( $p['h2_count'] ?? 0 ) . ' H2 · ' . (int) ( $p['h3_count'] ?? 0 ) . ' H3' ); ?></strong></div><div class="ta-sact-page-detail"><span>Link counts</span><strong><?php echo esc_html( (int) ( $p['internal_links'] ?? 0 ) . ' internal · ' . (int) ( $p['external_links'] ?? 0 ) . ' external' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Canonical URL</span><strong><?php echo esc_html( $p['canonical'] ? $p['canonical'] : 'Not detected' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Indexability signal</span><strong><?php echo esc_html( $p['noindex'] ? 'Noindex detected' : 'No noindex detected' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Mobile viewport</span><strong><?php echo esc_html( ! empty( $p['viewport'] ) ? 'Detected' : 'Missing' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Structured data</span><strong><?php echo esc_html( ! empty( $p['schema_count'] ) ? $p['schema_count'] . ' block(s)' : 'Not detected' ); ?></strong></div>
							<div class="ta-sact-page-detail"><span>Content / response</span><strong><?php echo esc_html( ( $p['word_count'] ?? 0 ) . ' words · ' . $p['response_ms'] . ' ms server response' ); ?></strong></div>
						</div>
						<h4 class="ta-sact-page-issues-title"><?php esc_html_e( 'Issues & recommended fixes for this page', 'tech-akhil-seo-audit-tool' ); ?></h4>
						<?php if ( empty( $page_issues ) ) : ?><div class="ta-sact-page-no-issues">✓ <?php esc_html_e( 'No issue from the current rules was linked to this page.', 'tech-akhil-seo-audit-tool' ); ?></div><?php else : ?><div class="ta-sact-page-issue-list"><?php foreach ( $page_issues as $page_issue ) : ?><details class="ta-sact-issue ta-sact-severity-<?php echo esc_attr( $page_issue['severity'] ); ?>"><summary><span class="ta-sact-pill"><?php echo esc_html( 'error' === $page_issue['severity'] ? 'CRITICAL' : strtoupper( $page_issue['severity'] ) ); ?></span><strong><?php echo esc_html( $page_issue['title'] ); ?></strong><span class="ta-sact-issue-chevron" aria-hidden="true">＋</span></summary><div class="ta-sact-issue-body"><p><?php echo esc_html( $page_issue['detail'] ); ?></p><div class="ta-sact-fix"><strong><?php esc_html_e( 'Recommended fix', 'tech-akhil-seo-audit-tool' ); ?></strong><p><?php echo esc_html( $page_issue['fix'] ); ?></p></div></div></details><?php endforeach; ?></div><?php endif; ?>
						<p class="ta-sact-page-footer"><a href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener noreferrer">Open this page in a new tab ↗</a></p>
					</div>
				</details>
				<?php endforeach; ?>
				</div><p class="ta-sact-disclaimer">*<?php esc_html_e( 'Server-side request timing is not a browser speed test or Core Web Vitals. Noindex detection does not prove Google index status. JavaScript-rendered content may be missed.', 'tech-akhil-seo-audit-tool' ); ?></p>
			</section>
			<?php endif; ?>
			<div class="ta-sact-external-tools"><strong><?php esc_html_e( 'Free external SEO tools', 'tech-akhil-seo-audit-tool' ); ?></strong><p><?php esc_html_e( 'Speed tests run on Google PageSpeed Insights, outside this server. Actual Google index status and Search Console data require property authorization. This audit does not invent ranking or backlink metrics.', 'tech-akhil-seo-audit-tool' ); ?></p><a href="https://pagespeed.web.dev/" target="_blank" rel="noopener noreferrer">Test website speed on PageSpeed Insights</a> · <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Open Search Console</a> · <a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer">Schema Markup Validator</a></div>
		</section><?php
	}

}
