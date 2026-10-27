<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class TA_SACT_History {
	public static function init() {
		add_action( 'admin_post_ta_sact_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_nopriv_ta_sact_export_csv', array( __CLASS__, 'export_csv' ) );
	}
	public static function save_report( $url, $report ) {
		$key = 'ta_sact_history_' . hash( 'sha256', strtolower( untrailingslashit( $url ) ) );
		$old = get_transient( $key );
		$entry = array( 'created'=>current_time( 'mysql' ), 'report'=>$report );
		$history = is_array( $old ) ? $old : array();
		array_unshift( $history, $entry );
		$history = array_slice( $history, 0, 5 );
		set_transient( $key, $history, 30 * DAY_IN_SECONDS );
		return array( 'previous' => isset( $history[1]['report'] ) ? $history[1]['report'] : null, 'history_count'=>count( $history ) );
	}
	public static function export_url( $report ) {
		if ( ! is_array( $report ) || ! isset( $report['issues'], $report['pages'] ) || ! is_array( $report['issues'] ) || ! is_array( $report['pages'] ) ) {
			return '';
		}
		$token = wp_generate_password( 24, false, false );
		set_transient( 'ta_sact_csv_' . $token, $report, 2 * HOUR_IN_SECONDS );
		return add_query_arg( array( 'action'=>'ta_sact_export_csv', 'token'=>$token ), admin_url( 'admin-post.php' ) );
	}
	/**
	 * Prevent spreadsheet formula execution from untrusted text fields in CSV exports.
	 *
	 * @param mixed $value Cell value.
	 * @return mixed
	 */
	public static function csv_safe_value( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[\\x00-\\x20]*[=+@\\-]/u', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	public static function export_csv() {
		$token = isset( $_GET['token'] ) && is_scalar( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A short-lived, random, single-use token authorizes this CSV download.
		if ( ! preg_match( '/^[a-zA-Z0-9]{24}$/', $token ) ) { wp_die( 'Invalid or expired export link.' ); }
		$r = get_transient( 'ta_sact_csv_' . $token );
		if ( ! is_array( $r ) ) { wp_die( esc_html__( 'This CSV export link has expired or has already been used. Return to the audit page and run a new audit to create a fresh export link.', 'tech-akhil-seo-audit-tool' ), esc_html__( 'Export link unavailable', 'tech-akhil-seo-audit-tool' ), array( 'response' => 410, 'back_link' => true ) ); }
		delete_transient( 'ta_sact_csv_' . $token );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="seo-audit-report-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'The CSV export could not be started. Please run the audit again.', 'tech-akhil-seo-audit-tool' ) );
		}
		fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
		fputcsv( $out, array( 'Severity','Issue code','Issue','Affected URL','Details','Recommended fix' ), ',', '"', '' );
		foreach ( $r['issues'] ?? array() as $i ) {
			if ( ! is_array( $i ) ) { continue; }
			$row = array( $i['severity'] ?? '', $i['code'] ?? '', $i['title'] ?? '', $i['url'] ?? '', $i['detail'] ?? '', $i['fix'] ?? '' );
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe_value' ), $row ), ',', '"', '' );
		}
		fputcsv( $out, array(), ',', '"', '' );
		fputcsv( $out, array( 'URL','HTTP status','Title','Title length','Meta description','Description length','H1 count','H2 count','Noindex','Canonical','Images','Missing alt','Response ms','HTML bytes','CSS resources','JS resources','Resource bytes' ), ',', '"', '' );
		foreach ( $r['pages'] ?? array() as $p ) {
			if ( ! is_array( $p ) ) { continue; }
			$row = array( $p['url']??'', $p['status_code']??'', $p['title']??'', $p['title_length']??0, $p['description']??'', $p['description_length']??0, count($p['h1']??array()), $p['h2_count']??0, !empty($p['noindex'])?'Yes':'No', $p['canonical']??'', $p['images']??0, $p['missing_alt']??0, $p['response_ms']??0, $p['bytes']??0, $p['css_count']??0, $p['js_count']??0, $p['resource_bytes']??0 );
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe_value' ), $row ), ',', '"', '' );
		}
		/* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- This is the php://output response stream for a downloadable CSV, not a filesystem file. */
		fclose( $out );
		exit;
	}
}
