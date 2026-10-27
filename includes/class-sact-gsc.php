<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Free Google Search Console API integration.
 * Site owner supplies their own OAuth client credentials; API usage is free but quota-limited.
 */
final class TA_SACT_GSC {
	const OPTION = 'ta_sact_gsc';
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_ta_sact_gsc_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_ta_sact_gsc_callback', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_ta_sact_gsc_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_ta_sact_gsc_refresh', array( __CLASS__, 'refresh_dashboard' ) );
		add_action( 'admin_post_ta_sact_gsc_inspect', array( __CLASS__, 'inspect_url' ) );
	}
	private static function settings() { $s = get_option( self::OPTION, array() ); return is_array( $s ) ? $s : array(); }
	private static function save( $s ) { update_option( self::OPTION, $s, false ); }
	public static function menu() {
		add_submenu_page( null, 'Google Search Console', 'Search Console', 'manage_options', 'ta-sact-gsc', array( __CLASS__, 'page' ) );
	}
	private static function redirect_uri() { return admin_url( 'admin-post.php?action=ta_sact_gsc_callback' ); }
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = self::settings(); $notice = isset( $_GET['sact_gsc_notice'] ) && is_scalar( $_GET['sact_gsc_notice'] ) ? sanitize_key( wp_unslash( $_GET['sact_gsc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only OAuth status notice; value is sanitized and allow-listed by comparison.
		echo '<div class="wrap"><h1>Google Search Console Integration</h1>';
		echo '<p>Google Search Console API is free to use, subject to Google quotas. You do not need a paid Search Console subscription. You do need a free Google Cloud project and OAuth client credentials, and your Google account must have access to the property.</p>';
		if ( 'disconnected' === $notice ) { echo '<div class="notice notice-success"><p>Google account disconnected and saved tokens removed.</p></div>'; }
		if ( 'connected' === $notice ) { echo '<div class="notice notice-success"><p>Google authorization completed.</p></div>'; }
		if ( 'error' === $notice ) { echo '<div class="notice notice-error"><p>Connection could not be completed. Check OAuth credentials, redirect URI and Google account permissions.</p></div>'; }
		echo '<h2>1. OAuth credentials</h2><p>Create an OAuth 2.0 Web application client in Google Cloud Console, enable Search Console API, and add this exact authorized redirect URI:</p><code>' . esc_html( self::redirect_uri() ) . '</code>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'ta_sact_gsc_settings' );
		echo '<table class="form-table"><tr><th>OAuth Client ID</th><td><input class="regular-text" name="ta_sact_gsc_client_id" value="' . esc_attr( get_option( 'ta_sact_gsc_client_id', '' ) ) . '" autocomplete="off"></td></tr>';
		echo '<tr><th>OAuth Client Secret</th><td><input type="password" class="regular-text" name="ta_sact_gsc_client_secret" value="' . esc_attr( get_option( 'ta_sact_gsc_client_secret', '' ) ) . '" autocomplete="new-password"></td></tr></table>';
		submit_button( 'Save credentials' ); echo '</form>';
		if ( empty( get_option( 'ta_sact_gsc_client_id' ) ) || empty( get_option( 'ta_sact_gsc_client_secret' ) ) ) {
			echo '<p><strong>Next:</strong> save your credentials to enable the Connect button.</p>';
		} elseif ( empty( $s['access_token'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ta_sact_gsc_connect' ); echo '<input type="hidden" name="action" value="ta_sact_gsc_connect">'; submit_button( 'Connect Google Search Console', 'primary' ); echo '</form>';
		} else {
			echo '<p><strong>Status:</strong> Connected.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'ta_sact_gsc_disconnect' ); echo '<input type="hidden" name="action" value="ta_sact_gsc_disconnect">'; submit_button( 'Disconnect Google account', 'delete' ); echo '</form>';
			self::dashboard( $s );
			if ( ! empty( $s['last_inspection'] ) ) { echo '<h3>Last URL inspection result</h3><pre class="ta-sact-gsc-inspection-result">' . esc_html( wp_json_encode( $s['last_inspection'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>'; }
		}
		echo '<hr><p><strong>Privacy:</strong> tokens are stored in this WordPress site database. Keep WordPress and administrator accounts secure. Disconnect to remove saved tokens. Data is requested from Google only for features you run.</p></div>';
	}
	public static function register_settings() {
		register_setting( 'ta_sact_gsc_settings', 'ta_sact_gsc_client_id', array( 'type'=>'string', 'sanitize_callback'=>'sanitize_text_field', 'default'=>'' ) );
		register_setting( 'ta_sact_gsc_settings', 'ta_sact_gsc_client_secret', array( 'type'=>'string', 'sanitize_callback'=>'sanitize_text_field', 'default'=>'' ) );
	}
	public static function connect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'ta_sact_gsc_connect' );
		$id = get_option( 'ta_sact_gsc_client_id', '' ); $secret = get_option( 'ta_sact_gsc_client_secret', '' );
		if ( ! $id || ! $secret ) { self::go( 'error' ); }
		$state = wp_generate_password( 32, false, false );
		set_transient( 'ta_sact_gsc_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );
		$url = add_query_arg( array(
			'client_id'=>$id, 'redirect_uri'=>self::redirect_uri(), 'response_type'=>'code',
			'scope'=>'https://www.googleapis.com/auth/webmasters.readonly',
			'access_type'=>'offline', 'prompt'=>'consent', 'include_granted_scopes'=>'true', 'state'=>$state
		), 'https://accounts.google.com/o/oauth2/v2/auth' );
		// Allow only Google's fixed OAuth host for this redirect, then use wp_safe_redirect().
		$google_host = 'accounts.google.com';
		add_filter( 'allowed_redirect_hosts', static function ( $hosts ) use ( $google_host ) {
			$hosts[] = $google_host;
			return array_values( array_unique( $hosts ) );
		} );
		wp_safe_redirect( esc_url_raw( $url ), 302, 'Tech Akhil SEO Audit Tool' );
		exit;
	}
	public static function callback() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback is protected by the single-use state token validated below.
		$state = isset( $_GET['state'] ) && is_scalar( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$expected = get_transient( 'ta_sact_gsc_state_' . get_current_user_id() ); delete_transient( 'ta_sact_gsc_state_' . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Google redirects back with the authorization code; the single-use OAuth state is validated above.
		// Google returns an authorization code by redirecting to this callback.
		// Sanitize it once before validation/use; the single-use OAuth state above
		// protects this callback against forged authorization responses.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback validates a single-use state token before accepting the code.
		$code = isset( $_GET['code'] ) && is_scalar( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $state || ! $expected || ! hash_equals( $expected, $state ) || '' === $code ) { self::go( 'error' ); }
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout'=>20, 'body'=>array(
			'code' => $code, 'client_id'=>get_option( 'ta_sact_gsc_client_id', '' ),
			'client_secret'=>get_option( 'ta_sact_gsc_client_secret', '' ), 'redirect_uri'=>self::redirect_uri(), 'grant_type'=>'authorization_code'
		) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { self::go( 'error' ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) { self::go( 'error' ); }
		$s = self::settings(); $s['access_token'] = sanitize_text_field( $data['access_token'] );
		if ( ! empty( $data['refresh_token'] ) ) { $s['refresh_token'] = sanitize_text_field( $data['refresh_token'] ); }
		$s['expires_at'] = time() + absint( $data['expires_in'] ?? 3600 ); self::save( $s ); self::go( 'connected' );
	}
	private static function go( $notice ) { wp_safe_redirect( add_query_arg( 'sact_gsc_notice', $notice, admin_url( 'admin.php?page=ta-sact-gsc' ) ) ); exit; }
	private static function token() {
		$s = self::settings();
		if ( empty( $s['access_token'] ) ) { return new WP_Error( 'sact_gsc_not_connected', 'Connect Google Search Console first.' ); }
		if ( ! empty( $s['expires_at'] ) && time() > (int) $s['expires_at'] - 60 && ! empty( $s['refresh_token'] ) ) {
			$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout'=>20, 'body'=>array(
				'client_id'=>get_option( 'ta_sact_gsc_client_id', '' ), 'client_secret'=>get_option( 'ta_sact_gsc_client_secret', '' ),
				'refresh_token'=>$s['refresh_token'], 'grant_type'=>'refresh_token'
			) ) );
			if ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) ) {
				$d = json_decode( wp_remote_retrieve_body( $r ), true );
				if ( ! empty( $d['access_token'] ) ) { $s['access_token'] = sanitize_text_field( $d['access_token'] ); $s['expires_at'] = time() + absint( $d['expires_in'] ?? 3600 ); self::save( $s ); }
			}
		}
		return $s['access_token'];
	}
	private static function api( $url, $body = null ) {
		$t = self::token(); if ( is_wp_error( $t ) ) { return $t; }
		$args = array( 'timeout'=>25, 'headers'=>array( 'Authorization'=>'Bearer ' . $t, 'Accept'=>'application/json' ) );
		if ( null !== $body ) { $args['headers']['Content-Type'] = 'application/json'; $args['body'] = wp_json_encode( $body ); $r = wp_remote_post( $url, $args ); }
		else { $r = wp_remote_get( $url, $args ); }
		if ( is_wp_error( $r ) ) { return $r; }
		$code = wp_remote_retrieve_response_code( $r ); $data = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $code < 200 || $code >= 300 ) { $message = isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : 'Google API request failed (HTTP ' . $code . ').'; return new WP_Error( 'sact_gsc_api', $message ); }
		return is_array( $data ) ? $data : array();
	}
	private static function properties() { return self::api( 'https://www.googleapis.com/webmasters/v3/sites' ); }

	/** Check that a property is in the currently authorized Google account's property list. */
	private static function is_authorized_property( $property ) {
		if ( ! is_string( $property ) || '' === $property ) { return false; }
		$result = self::properties();
		if ( is_wp_error( $result ) ) { return false; }
		$allowed = array();
		foreach ( $result['siteEntry'] ?? array() as $site ) {
			if ( isset( $site['siteUrl'] ) && is_string( $site['siteUrl'] ) ) { $allowed[] = $site['siteUrl']; }
		}
		return in_array( $property, $allowed, true );
	}

	/** Ensure an inspected URL belongs to the selected URL-prefix or domain property. */
	private static function url_matches_property( $url, $property ) {
		$url_parts = wp_parse_url( $url );
		if ( ! is_array( $url_parts ) || empty( $url_parts['host'] ) || empty( $url_parts['scheme'] ) || ! in_array( strtolower( $url_parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $url_parts['user'] ) || isset( $url_parts['pass'] ) ) { return false; }
		if ( 0 === strpos( $property, 'sc-domain:' ) ) {
			$domain = strtolower( trim( substr( $property, strlen( 'sc-domain:' ) ), '.' ) );
			$host = strtolower( rtrim( $url_parts['host'], '.' ) );
			return '' !== $domain && ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain );
		}
		$prefix = wp_parse_url( $property );
		if ( ! is_array( $prefix ) || empty( $prefix['host'] ) || empty( $prefix['scheme'] ) ) { return false; }
		if ( strtolower( $url_parts['scheme'] ) !== strtolower( $prefix['scheme'] ) || strtolower( rtrim( $url_parts['host'], '.' ) ) !== strtolower( rtrim( $prefix['host'], '.' ) ) ) { return false; }
		$url_port = isset( $url_parts['port'] ) ? (int) $url_parts['port'] : ( 'https' === strtolower( $url_parts['scheme'] ) ? 443 : 80 );
		$prefix_port = isset( $prefix['port'] ) ? (int) $prefix['port'] : ( 'https' === strtolower( $prefix['scheme'] ) ? 443 : 80 );
		if ( $url_port !== $prefix_port ) { return false; }
		$path_prefix = isset( $prefix['path'] ) ? $prefix['path'] : '/';
		$url_path = isset( $url_parts['path'] ) ? $url_parts['path'] : '/';
		return 0 === strpos( $url_path, $path_prefix );
	}
	private static function dashboard( $s ) {
		$sites = self::properties();
		if ( is_wp_error( $sites ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $sites->get_error_message() ) . '</p></div>'; return; }
		$list = $sites['siteEntry'] ?? array(); if ( ! $list ) { echo '<p>No Search Console properties were returned for this Google account.</p>'; return; }
		$selected = $s['property'] ?? $list[0]['siteUrl'];
		if ( isset( $_POST['ta_sact_gsc_property'] ) && is_scalar( $_POST['ta_sact_gsc_property'] ) ) {
			check_admin_referer( 'ta_sact_gsc_refresh' );
			$selected = sanitize_text_field( wp_unslash( $_POST['ta_sact_gsc_property'] ) );
		}
		$allowed = array_column( $list, 'siteUrl' ); if ( ! in_array( $selected, $allowed, true ) ) { $selected = $list[0]['siteUrl']; }
		echo '<h2>2. Search Console data</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ta_sact_gsc_refresh' ); echo '<input type="hidden" name="action" value="ta_sact_gsc_refresh"><label for="ta-sact-gsc-property">Property </label><select id="ta-sact-gsc-property" name="property">';
		foreach ( $list as $site ) { echo '<option value="' . esc_attr( $site['siteUrl'] ) . '"' . selected( $selected, $site['siteUrl'], false ) . '>' . esc_html( $site['siteUrl'] ) . ' (' . esc_html( $site['permissionLevel'] ?? 'authorized' ) . ')</option>'; }
		echo '</select> <button class="button button-primary">Load last 28 days</button></form>';
		$s['property'] = $selected; self::save( $s );
		$end = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); $start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$q = self::api( 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $selected ) . '/searchAnalytics/query', array( 'startDate'=>$start, 'endDate'=>$end, 'dimensions'=>array( 'query' ), 'rowLimit'=>10 ) );
		if ( is_wp_error( $q ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $q->get_error_message() ) . '</p></div>'; return; }
		echo '<h3>Top search queries (recent finalized data)</h3><table class="widefat striped"><thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg. position</th></tr></thead><tbody>';
		foreach ( $q['rows'] ?? array() as $row ) { echo '<tr><td>' . esc_html( $row['keys'][0] ?? '' ) . '</td><td>' . esc_html( number_format_i18n( $row['clicks'] ?? 0 ) ) . '</td><td>' . esc_html( number_format_i18n( $row['impressions'] ?? 0 ) ) . '</td><td>' . esc_html( round( 100 * ( $row['ctr'] ?? 0 ), 2 ) ) . '%</td><td>' . esc_html( round( $row['position'] ?? 0, 1 ) ) . '</td></tr>'; }
		if ( empty( $q['rows'] ) ) { echo '<tr><td colspan="5">No query rows returned for this date range/property.</td></tr>'; }
		echo '</tbody></table><h3>Inspect a URL in Google index</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ta_sact_gsc_inspect' ); echo '<input type="hidden" name="action" value="ta_sact_gsc_inspect"><input type="hidden" name="property" value="' . esc_attr( $selected ) . '"><input class="regular-text" type="url" name="inspection_url" placeholder="https://example.com/page/" required><button class="button">Inspect URL</button></form>';
	}
	public static function refresh_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); } check_admin_referer( 'ta_sact_gsc_refresh' );
		$property = isset( $_POST['property'] ) && is_scalar( $_POST['property'] ) ? sanitize_text_field( wp_unslash( $_POST['property'] ) ) : '';
		if ( ! self::is_authorized_property( $property ) ) { self::go( 'error' ); }
		$s = self::settings(); $s['property'] = $property; self::save( $s );
		wp_safe_redirect( admin_url( 'admin.php?page=ta-sact-gsc' ) ); exit;
	}
	public static function inspect_url() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); } check_admin_referer( 'ta_sact_gsc_inspect' );
		$url = isset( $_POST['inspection_url'] ) && is_scalar( $_POST['inspection_url'] ) ? esc_url_raw( wp_unslash( $_POST['inspection_url'] ), array( 'http','https' ) ) : '';
		$property = isset( $_POST['property'] ) && is_scalar( $_POST['property'] ) ? sanitize_text_field( wp_unslash( $_POST['property'] ) ) : '';
		$s = self::settings(); $s['last_inspection'] = array();
		if ( ! $url || ! $property || ! self::is_authorized_property( $property ) || ! self::url_matches_property( $url, $property ) ) {
			$s['last_inspection'] = array( 'error' => 'Choose an authorized Search Console property and enter a URL that belongs to that property.' );
		} else {
			$r = self::api( 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', array( 'inspectionUrl'=>$url, 'siteUrl'=>$property, 'languageCode'=>'en-US' ) );
			if ( is_wp_error( $r ) ) { $s['last_inspection'] = array( 'error'=>$r->get_error_message() ); }
			else { $s['last_inspection'] = array( 'url'=>$url, 'result'=>$r['inspectionResult'] ?? array() ); }
		}
		self::save( $s ); wp_safe_redirect( admin_url( 'admin.php?page=ta-sact-gsc' ) ); exit;
	}
	public static function disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); } check_admin_referer( 'ta_sact_gsc_disconnect' );
		delete_option( self::OPTION ); self::go( 'disconnected' );
	}
}
