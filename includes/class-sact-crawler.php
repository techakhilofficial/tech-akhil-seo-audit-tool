<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * A bounded, same-host HTML crawler. This is not a browser-rendering crawler.
 */
final class TA_SACT_Crawler {
	const MAX_PAGES = 30;
	const MAX_HTML_BYTES = 1200000;
	const MAX_SECONDS = 25;

	public static function audit( $input_url ) {
		$home = home_url( '/' );
		$url = esc_url_raw( trim( (string) $input_url ), array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		$host = is_array( $parts ) && isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || ! $host || preg_match( '/[^a-z0-9.:-]/i', $host ) ) {
			return new WP_Error( 'sact_invalid_url', __( 'Enter a valid public HTTP/HTTPS website URL.', 'tech-akhil-seo-audit-tool' ) );
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		if ( ! in_array( $port, array( 80, 443 ), true ) || ( 'https' === $scheme && 80 === $port ) || ( 'http' === $scheme && 443 === $port ) ) {
			return new WP_Error( 'sact_invalid_port', __( 'For safety, only standard HTTP (80) and HTTPS (443) ports are supported.', 'tech-akhil-seo-audit-tool' ) );
		}
		// Reject localhost and literal private/reserved IP addresses for public-tool SSRF protection.
		if ( 'localhost' === $host || substr( $host, -6 ) === '.local' || filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return new WP_Error( 'sact_invalid_url', __( 'Private, local, and reserved network addresses cannot be audited.', 'tech-akhil-seo-audit-tool' ) );
		}
		$home_host = $host;
		$target_origin = $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
		$queue = array( self::normalize_url( $url ) );
		$seen = array();
		$pages = array();
		$issues = array();
		$start = microtime( true );
		while ( $queue && count( $pages ) < self::MAX_PAGES && microtime( true ) - $start < self::MAX_SECONDS ) {
			$current = array_shift( $queue );
			if ( ! $current || isset( $seen[ $current ] ) ) { continue; }
			$seen[ $current ] = true;
			$req_start = microtime( true );
			$response = wp_safe_remote_get( $current, array(
				'timeout' => 7, 'redirection' => 3, 'limit_response_size' => self::MAX_HTML_BYTES,
				'user-agent' => 'TECH-AKHIL-SEO-Audit/' . TA_SACT_VERSION,
				'headers' => array( 'Accept' => 'text/html,application/xhtml+xml' ),
			) );
			$ms = (int) round( ( microtime( true ) - $req_start ) * 1000 );
			if ( is_wp_error( $response ) ) {
				$page = self::empty_page( $current );
				$page['error'] = $response->get_error_message();
				$page['response_ms'] = $ms;
				$pages[] = $page;
				$issues[] = self::issue( 'error', 'request-failed', 'Page could not be fetched', $current, $page['error'], 'Check the URL, hosting logs, firewall rules, and server availability. Restore the page or redirect it to a relevant replacement if it has moved.' );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );
			$xrobots = '';
			if ( is_object( $headers ) && isset( $headers['x-robots-tag'] ) ) { $xrobots = (string) $headers['x-robots-tag']; }
			elseif ( is_array( $headers ) && isset( $headers['x-robots-tag'] ) ) { $xrobots = (string) $headers['x-robots-tag']; }
			$data = self::parse_html( $body );
			$data['robots'] = trim( $data['robots'] . ' ' . $xrobots );
			$data['noindex'] = (bool) preg_match( '/\bnoindex\b/i', $data['robots'] );
			$data['internal_links'] = 0; $data['external_links'] = 0;
			foreach ( $data['links'] as $link_href ) { $absolute_link = self::absolute_url( $link_href, $current ); if ( $absolute_link ) { if ( self::same_host( $absolute_link, $home_host ) ) { $data['internal_links']++; } else { $data['external_links']++; } } }
			$page = array_merge( self::empty_page( $current ), $data );
			$page['status_code'] = $code;
			$page['response_ms'] = $ms;
			$page['bytes'] = strlen( $body );
			$page['slug'] = self::slug( $current );
			$page['error'] = '';
			$page['response_headers'] = array(
				'x-content-type-options' => (string) ( is_object( $headers ) || is_array( $headers ) ? ( $headers['x-content-type-options'] ?? '' ) : '' ),
				'referrer-policy' => (string) ( is_object( $headers ) || is_array( $headers ) ? ( $headers['referrer-policy'] ?? '' ) : '' ),
				'content-security-policy' => (string) ( is_object( $headers ) || is_array( $headers ) ? ( $headers['content-security-policy'] ?? '' ) : '' ),
				'strict-transport-security' => (string) ( is_object( $headers ) || is_array( $headers ) ? ( $headers['strict-transport-security'] ?? '' ) : '' ),
			);
			$pages[] = $page;
			$issues = array_merge( $issues, self::page_issues( $page ) );
			if ( $code >= 200 && $code < 300 ) {
				foreach ( $data['links'] as $href ) {
					$target = self::absolute_url( $href, $current );
					if ( $target && self::same_host( $target, $home_host ) && ! isset( $seen[ $target ] ) && ! in_array( $target, $queue, true ) ) {
						$queue[] = $target;
					}
				}
			}
		}

		// Detect duplicate titles/descriptions and exact duplicate extracted body text within the crawled sample.
		$title_map = array(); $desc_map = array(); $content_map = array();
		foreach ( $pages as $p ) {
			if ( ! empty( $p['title'] ) ) { $k = md5( strtolower( trim( $p['title'] ) ) ); $title_map[ $k ][] = $p['url']; }
			if ( ! empty( $p['description'] ) ) { $k = md5( strtolower( trim( $p['description'] ) ) ); $desc_map[ $k ][] = $p['url']; }
			if ( ! empty( $p['text_content'] ) && (int) $p['word_count'] >= 100 ) { $normalized_text = strtolower( preg_replace( '/\s+/u', ' ', trim( $p['text_content'] ) ) ); $k = md5( $normalized_text ); $content_map[ $k ][] = $p['url']; }
		}
		foreach ( $title_map as $urls ) { if ( count( $urls ) > 1 ) { foreach ( $urls as $u ) { $issues[] = self::issue( 'warning', 'duplicate-title', 'Duplicate title detected in crawl', $u, 'The same title appears on ' . count( $urls ) . ' crawled URLs.', 'Write a unique, descriptive title for each important page. Review whether these pages serve different search intents.' ); } } }
		foreach ( $desc_map as $urls ) { if ( count( $urls ) > 1 ) { foreach ( $urls as $u ) { $issues[] = self::issue( 'notice', 'duplicate-description', 'Duplicate meta description detected', $u, 'The same description appears on ' . count( $urls ) . ' crawled URLs.', 'Consider writing page-specific descriptions that summarize each page accurately.' ); } } }
		foreach ( $content_map as $urls ) { if ( count( $urls ) > 1 ) { foreach ( $urls as $u ) { $issues[] = self::issue( 'warning', 'duplicate-body-content', 'Identical visible page content detected', $u, 'The extracted visible text exactly matches ' . ( count( $urls ) - 1 ) . ' other crawled URL(s). This is an exact-text signal, not a full semantic similarity test.', 'Check whether these URLs should contain distinct useful content or use a consistent canonical/redirect strategy when they are true duplicates. Do not remove pages solely because this automated signal matched.' ); } } }
		// Crawl a bounded sample of internal links and report broken targets.
		$targets = array();
		foreach ( $pages as $p ) { foreach ( $p['links'] as $href ) { $target = self::absolute_url( $href, $p['url'] ); if ( $target && self::same_host( $target, $home_host ) && ! isset( $seen[ $target ] ) ) { $targets[ $target ] = true; } } }
		$checked = 0;
		foreach ( array_keys( $targets ) as $target ) {
			if ( $checked >= 20 || microtime( true ) - $start > self::MAX_SECONDS ) { break; }
			$checked++;
			$link_check = self::url_status_details( $target, $home_host );
			$status = (int) $link_check['status'];
			if ( ! empty( $link_check['redirects'] ) ) {
				$hops = count( $link_check['redirects'] );
				$chain_urls = array();
				foreach ( $link_check['redirects'] as $redirect ) { $chain_urls[] = $redirect['from'] . ' → ' . $redirect['to'] . ' (HTTP ' . $redirect['status'] . ')'; }
				if ( $hops > 1 ) {
					$issues[] = self::issue( 'warning', 'redirect-chain', 'Internal link uses a redirect chain', $target, 'The sampled link required ' . $hops . ' redirect hops: ' . implode( '; ', $chain_urls ) . '. Final status: ' . ( $status ? $status : 'unknown' ) . '.', 'Where practical, update the source link to point directly to the final relevant URL. Keep redirects that are needed for old URLs; do not remove them without checking external links and indexing.' );
				} else {
					$issues[] = self::issue( 'notice', 'redirected-internal-link', 'Internal link redirects', $target, 'The sampled link redirects: ' . implode( '; ', $chain_urls ) . '. Final status: ' . ( $status ? $status : 'unknown' ) . '.', 'Update the source link to the final URL when appropriate to avoid an unnecessary redirect. Keep the redirect itself if it serves a useful migration or canonicalization purpose.' );
				}
			}
			if ( $status >= 400 ) { $issues[] = self::issue( 'error', 'broken-internal-link', 'Broken internal link target', ! empty( $link_check['final_url'] ) ? $link_check['final_url'] : $target, 'The target returned HTTP ' . $status . ' when checked.' . ( ! empty( $link_check['error'] ) ? ' ' . $link_check['error'] : '' ), 'Update the source link to a working relevant URL, restore the target, or use a relevant permanent redirect if the page moved.' ); }
			elseif ( 0 === $status ) { $issues[] = self::issue( 'notice', 'link-check-inconclusive', 'Internal link could not be verified', $target, 'The link status could not be determined reliably.' . ( ! empty( $link_check['error'] ) ? ' ' . $link_check['error'] : '' ), 'Check this URL manually. Network restrictions, rate limits, or hosting rules may block automated checks.' ); }
		}
		// Global checks apply to the audited domain, not the WordPress installation hosting this tool.
		if ( 'https' !== $scheme ) {
			$issues[] = self::issue( 'warning', 'https', 'Audited URL uses HTTP', $url, 'The URL supplied for this audit uses HTTP rather than HTTPS.', 'If this is your website, configure TLS/SSL and redirect HTTP to HTTPS after checking for mixed-content issues.');
		}
		$wp_home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( $host === $wp_home_host ) {
			if ( 1 !== (int) get_option( 'blog_public', 1 ) ) { $issues[] = self::issue( 'warning', 'search-visibility', 'WordPress discourages search engines', home_url( '/' ), 'This WordPress installation is configured to discourage search engines.', 'If this is a public production site, open Settings → Reading and disable “Discourage search engines from indexing this site”. Keep it enabled on private/staging sites.'); }
			if ( '' === (string) get_option( 'permalink_structure', '' ) ) { $issues[] = self::issue( 'notice', 'permalinks', 'Default permalink structure detected', home_url( '/' ), 'This WordPress installation uses the default permalink structure.', 'Review Settings → Permalinks if readable URLs are appropriate. Check redirects before changing established URLs.'); }
		}
		$resource_report = self::check_site_resources( $target_origin, $start );
		$issues = array_merge( $issues, $resource_report['issues'] );
		if ( ! empty( $pages[0]['response_headers'] ) && 'https' === $scheme ) {
			$h = $pages[0]['response_headers'];
			if ( empty( $h['x-content-type-options'] ) ) { $issues[] = self::issue( 'notice', 'security-x-content-type-options', 'Security header not detected: X-Content-Type-Options', $pages[0]['url'], 'The response did not expose the X-Content-Type-Options header.', 'Ask the site administrator or host whether nosniff is appropriate. Missing this header alone does not prove the site is vulnerable.'); }
			if ( empty( $h['referrer-policy'] ) ) { $issues[] = self::issue( 'notice', 'security-referrer-policy', 'Security header not detected: Referrer-Policy', $pages[0]['url'], 'The response did not expose a Referrer-Policy header.', 'Consider a policy appropriate to the site, such as strict-origin-when-cross-origin, after reviewing analytics and integration needs.'); }
			if ( empty( $h['content-security-policy'] ) ) { $issues[] = self::issue( 'notice', 'security-csp', 'Content Security Policy not detected', $pages[0]['url'], 'No Content-Security-Policy response header was detected on the tested page.', 'Review whether a CSP can be safely configured. A strict policy needs testing because it can break scripts and embeds; this check is not a vulnerability scan.'); }
		}
		$signals = self::site_signals( $pages );
		if ( empty( $signals['analytics_detected'] ) ) { $issues[] = self::issue( 'notice', 'analytics-signal-not-detected', 'Analytics tracking signal not detected', $url, 'Common Google Analytics, Google Tag Manager and Meta Pixel markers were not found in the fetched HTML. Scripts injected later or through a consent manager may be missed.', 'If you need conversion reporting, verify analytics installation and test events in the provider’s own debug tools. This crawler cannot confirm that events actually fire.'); }
		if ( empty( $signals['forms_detected'] ) ) { $issues[] = self::issue( 'notice', 'conversion-form-not-detected', 'No form detected in scanned HTML', $url, 'No HTML form was detected in the scanned pages. Conversion paths may use phone links, checkout widgets or JavaScript-rendered forms.', 'If lead generation is a goal, manually test contact, quote and checkout journeys. Absence of a form is not automatically a problem.'); }
		foreach ( $pages as &$page_for_cleanup ) { unset( $page_for_cleanup['response_headers'] ); } unset( $page_for_cleanup );
		$counts = array( 'error' => 0, 'warning' => 0, 'notice' => 0 );
		foreach ( $issues as $issue ) { if ( isset( $counts[ $issue['severity'] ] ) ) { $counts[ $issue['severity'] ]++; } }
		return array(
			'site' => $url, 'created' => current_time( 'mysql' ), 'pages' => $pages, 'issues' => $issues,
			'counts' => $counts, 'duration' => round( microtime( true ) - $start, 2 ),
			'page_limit' => self::MAX_PAGES, 'robots_status' => $resource_report['robots_status'], 'sitemap_status' => $resource_report['sitemap_status'], 'sitemap_url' => $resource_report['sitemap_url'], 'signals' => $signals,
		);
	}

	private static function empty_page( $url ) {
		return array( 'url' => $url, 'slug' => self::slug( $url ), 'status_code' => 0, 'title' => '', 'title_length' => 0, 'description' => '', 'description_length' => 0, 'h1' => array(), 'h2' => array(), 'h2_count' => 0, 'h3' => array(), 'h3_count' => 0, 'canonical' => '', 'robots' => '', 'noindex' => false, 'images' => 0, 'missing_alt' => 0, 'image_alts' => array(), 'anchor_texts' => array(), 'text_content' => '', 'links' => array(), 'css_count'=>0, 'js_count'=>0, 'resource_bytes'=>0, 'viewport' => '', 'html_lang' => '', 'schema_count' => 0, 'schema_invalid' => 0, 'schema_types' => array(), 'empty_anchor_count' => 0, 'unlabelled_controls' => 0, 'mixed_content_count' => 0, 'word_count' => 0, 'forms' => 0, 'tracking_detected' => false, 'response_headers' => array(), 'response_ms' => 0, 'bytes' => 0, 'error' => '' );
	}

	private static function parse_html( $html ) {
		$out = array( 'title' => '', 'title_length' => 0, 'description' => '', 'description_length' => 0, 'h1' => array(), 'h2' => array(), 'h2_count' => 0, 'h3' => array(), 'h3_count' => 0, 'canonical' => '', 'robots' => '', 'noindex' => false, 'images' => 0, 'missing_alt' => 0, 'image_alts' => array(), 'anchor_texts' => array(), 'text_content' => '', 'links' => array(), 'css_count'=>0, 'js_count'=>0, 'resource_bytes'=>0, 'viewport' => '', 'html_lang' => '', 'schema_count' => 0, 'schema_invalid' => 0, 'schema_types' => array(), 'empty_anchor_count' => 0, 'unlabelled_controls' => 0, 'mixed_content_count' => 0, 'word_count' => 0, 'forms' => 0, 'tracking_detected' => false );
		if ( '' === trim( $html ) ) { return $out; }
		if ( ! class_exists( 'DOMDocument' ) ) { return self::fallback_parse_html( $html, $out ); }
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors(); libxml_use_internal_errors( $previous );
		if ( ! $loaded ) { return self::fallback_parse_html( $html, $out ); }
		$x = new DOMXPath( $dom );
		$title = $x->query( '//title' );
		if ( $title && $title->length ) { $out['title'] = trim( preg_replace( '/\s+/u', ' ', $title->item( 0 )->textContent ) ); }
		$out['title_length'] = function_exists( 'mb_strlen' ) ? mb_strlen( $out['title'] ) : strlen( $out['title'] );
		$desc = $x->query( '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]' );
		if ( $desc && $desc->length ) { $out['description'] = trim( $desc->item( 0 )->getAttribute( 'content' ) ); }
		$out['description_length'] = function_exists( 'mb_strlen' ) ? mb_strlen( $out['description'] ) : strlen( $out['description'] );
		$robots = $x->query( '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots" or translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="googlebot"]' );
		if ( $robots ) { foreach ( $robots as $node ) { $out['robots'] .= ' ' . $node->getAttribute( 'content' ); } }
		$canonical = $x->query( '//link[contains(concat(" ", normalize-space(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]' );
		if ( $canonical && $canonical->length ) { $out['canonical'] = trim( $canonical->item( 0 )->getAttribute( 'href' ) ); }
		$h1 = $x->query( '//h1' );
		if ( $h1 ) { foreach ( $h1 as $node ) { $v = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ); if ( '' !== $v ) { $out['h1'][] = $v; } } }
		$h2 = $x->query( '//h2' ); $out['h2_count'] = $h2 ? $h2->length : 0; if ( $h2 ) { foreach ( $h2 as $node ) { $v = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ); if ( '' !== $v ) { $out['h2'][] = $v; } } }
		$h3 = $x->query( '//h3' ); $out['h3_count'] = $h3 ? $h3->length : 0; if ( $h3 ) { foreach ( $h3 as $node ) { $v = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ); if ( '' !== $v ) { $out['h3'][] = $v; } } }
		$body = $x->query( '//body' ); if ( $body && $body->length ) { $out['text_content'] = trim( preg_replace( '/\s+/u', ' ', $body->item( 0 )->textContent ) ); }
		$imgs = $x->query( '//img' );
		if ( $imgs ) { $out['images'] = $imgs->length; foreach ( $imgs as $img ) { $alt = trim( $img->getAttribute( 'alt' ) ); $out['image_alts'][] = $alt; if ( ! $img->hasAttribute( 'alt' ) ) { $out['missing_alt']++; } } }
		$css = $x->query( '//link[@href and (contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"stylesheet") or contains(translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"text/css"))]' ); $out['css_count'] = $css ? $css->length : 0;
		$scripts = $x->query( '//script[@src]' ); $out['js_count'] = $scripts ? $scripts->length : 0;
		$links = $x->query( '//a[@href]' );
		if ( $links ) { foreach ( $links as $a ) { $href = trim( $a->getAttribute( 'href' ) ); if ( $href && ! preg_match( '~^(?:#|mailto:|tel:|javascript:|data:)~i', $href ) ) { $out['links'][] = $href; $anchor = trim( preg_replace( '/\s+/u', ' ', $a->textContent ) ); $out['anchor_texts'][] = $anchor; if ( '' === $anchor && ! $a->getElementsByTagName( 'img' )->length && ! $a->hasAttribute( 'aria-label' ) && ! $a->hasAttribute( 'title' ) ) { $out['empty_anchor_count']++; } } } }
		$html_nodes = $x->query( '/html' ); if ( $html_nodes && $html_nodes->length ) { $out['html_lang'] = trim( $html_nodes->item( 0 )->getAttribute( 'lang' ) ); }
		$viewport = $x->query( '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]' ); if ( $viewport && $viewport->length ) { $out['viewport'] = trim( $viewport->item( 0 )->getAttribute( 'content' ) ); }
		$schemas = $x->query( '//script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]' ); if ( $schemas ) { foreach ( $schemas as $schema ) { $out['schema_count']++; $json = json_decode( trim( $schema->textContent ), true ); if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $json ) ) { $out['schema_invalid']++; } else { $types = isset( $json['@type'] ) ? (array) $json['@type'] : array(); if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) { foreach ( $json['@graph'] as $node ) { if ( isset( $node['@type'] ) ) { $types = array_merge( $types, (array) $node['@type'] ); } } } foreach ( $types as $type ) { if ( is_string( $type ) ) { $out['schema_types'][] = $type; } } } } }
		$forms = $x->query( '//form' ); $out['forms'] = $forms ? $forms->length : 0;
		$controls = $x->query( '//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@aria-label) and not(@aria-labelledby) and not(@id = //label/@for)] | //textarea[not(@aria-label) and not(@aria-labelledby) and not(@id = //label/@for)] | //select[not(@aria-label) and not(@aria-labelledby) and not(@id = //label/@for)]' ); $out['unlabelled_controls'] = $controls ? $controls->length : 0;
		$out['mixed_content_count'] = (int) preg_match_all( '#(?:src|href)\s*=\s*["\']http://#i', $html );
		$out['tracking_detected'] = (bool) preg_match( '/google-analytics\.com|googletagmanager\.com|gtag\s*\(|fbq\s*\(/i', $html );
		preg_match_all( '/[\p{L}\p{N}]+/u', $out['text_content'], $word_matches ); $out['word_count'] = isset( $word_matches[0] ) ? count( $word_matches[0] ) : str_word_count( $out['text_content'] );
		return $out;
	}


	/** Conservative regex fallback for hosts without PHP's DOM extension. */
	private static function fallback_parse_html( $html, $out ) {
		$decode = static function( $value ) { return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) ); };
		if ( preg_match( '~<title\b[^>]*>(.*?)</title>~is', $html, $m ) ) { $out['title'] = $decode( $m[1] ); }
		$out['title_length'] = function_exists( 'mb_strlen' ) ? mb_strlen( $out['title'] ) : strlen( $out['title'] );
		if ( preg_match_all( '~<meta\b[^>]*>~is', $html, $metas ) ) {
			foreach ( $metas[0] as $tag ) {
				if ( preg_match( '~\bname\s*=\s*["\']description["\']~i', $tag ) && preg_match( '~\bcontent\s*=\s*(["\'])(.*?)\1~is', $tag, $m ) ) { $out['description'] = trim( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) ); }
				if ( preg_match( '~\bname\s*=\s*["\'](?:robots|googlebot)["\']~i', $tag ) && preg_match( '~\bcontent\s*=\s*(["\'])(.*?)\1~is', $tag, $m ) ) { $out['robots'] .= ' ' . $m[2]; }
				if ( preg_match( '~\bname\s*=\s*["\']viewport["\']~i', $tag ) && preg_match( '~\bcontent\s*=\s*(["\'])(.*?)\1~is', $tag, $m ) ) { $out['viewport'] = trim( $m[2] ); }
			}
		}
		$out['description_length'] = function_exists( 'mb_strlen' ) ? mb_strlen( $out['description'] ) : strlen( $out['description'] );
		if ( preg_match( '~<html\b[^>]*\blang\s*=\s*["\']([^"\']*)["\']~i', $html, $m ) ) { $out['html_lang'] = trim( $m[1] ); }
		if ( preg_match( '~<link\b(?=[^>]*\brel\s*=\s*["\'][^"\']*canonical[^"\']*["\'])(?=[^>]*\bhref\s*=\s*["\']([^"\']+)["\'])[^>]*>~i', $html, $m ) ) { $out['canonical'] = trim( $m[1] ); }
		foreach ( array( 'h1', 'h2' ) as $tag ) { if ( preg_match_all( '~<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>~is', $html, $matches ) ) { foreach ( $matches[1] as $text ) { $text = $decode( $text ); if ( '' !== $text ) { $out[ $tag ][] = $text; } } } }
		$out['h2_count'] = count( $out['h2'] );
		if ( preg_match_all( '~<h3\b[^>]*>(.*?)</h3>~is', $html, $h3_matches ) ) { foreach ( $h3_matches[1] as $heading_text ) { $heading_text = $decode( $heading_text ); if ( '' !== $heading_text ) { $out['h3'][] = $heading_text; } } }
		$out['h3_count'] = count( $out['h3'] );
		if ( preg_match_all( '~<img\b[^>]*>~is', $html, $imgs ) ) { $out['images'] = count( $imgs[0] ); foreach ( $imgs[0] as $img ) { if ( preg_match( '~\balt\s*=\s*["\']([^"\']*)["\']~i', $img, $m ) ) { $out['image_alts'][] = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ); } else { $out['image_alts'][] = ''; $out['missing_alt']++; } } }
		if ( preg_match_all( '~<link\b[^>]*\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\'][^>]*>~i', $html, $matches ) ) { $out['css_count'] = count( $matches[0] ); }
		if ( preg_match_all( '~<script\b[^>]*\bsrc\s*=~i', $html, $matches ) ) { $out['js_count'] = count( $matches[0] ); }
		if ( preg_match_all( '~<a\b([^>]*)>(.*?)</a>~is', $html, $anchors, PREG_SET_ORDER ) ) { foreach ( $anchors as $a ) { if ( preg_match( '~\bhref\s*=\s*["\']([^"\']+)["\']~i', $a[1], $hm ) ) { $href = trim( $hm[1] ); if ( '' !== $href && ! preg_match( '~^(?:#|mailto:|tel:|javascript:|data:)~i', $href ) ) { $out['links'][] = $href; $text = $decode( $a[2] ); $out['anchor_texts'][] = $text; if ( '' === $text && false === stripos( $a[2], '<img' ) && false === stripos( $a[1], 'aria-label=' ) && false === stripos( $a[1], 'title=' ) ) { $out['empty_anchor_count']++; } } } } }
		if ( preg_match_all( '~<script\b[^>]*\btype\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $schemas ) ) { foreach ( $schemas[1] as $json_text ) { $out['schema_count']++; $json = json_decode( trim( $json_text ), true ); if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $json ) ) { $out['schema_invalid']++; } else { $types = isset( $json['@type'] ) ? (array) $json['@type'] : array(); if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) { foreach ( $json['@graph'] as $node ) { if ( isset( $node['@type'] ) ) { $types = array_merge( $types, (array) $node['@type'] ); } } } foreach ( $types as $type ) { if ( is_string( $type ) ) { $out['schema_types'][] = $type; } } } } }
		$out['forms'] = preg_match_all( '~<form\b~i', $html, $matches );
		if ( preg_match_all( '~<(input|textarea|select)\b([^>]*)>~i', $html, $control_matches, PREG_SET_ORDER ) ) { foreach ( $control_matches as $control ) { $attrs = $control[2]; if ( preg_match( '~\btype\s*=\s*["\'](?:hidden|submit|button|reset)["\']~i', $attrs ) || preg_match( '~\baria-label\s*=|\baria-labelledby\s*=~i', $attrs ) ) { continue; } $id = ''; if ( preg_match( '~\bid\s*=\s*["\']([^"\']+)["\']~i', $attrs, $id_match ) ) { $id = $id_match[1]; } if ( '' === $id || ! preg_match( '~<label\b[^>]*\bfor\s*=\s*["\']' . preg_quote( $id, '~' ) . '["\']~i', $html ) ) { $out['unlabelled_controls']++; } } }
		$out['mixed_content_count'] = (int) preg_match_all( '#(?:src|href)\s*=\s*["\']http://#i', $html );
		$out['tracking_detected'] = (bool) preg_match( '/google-analytics\.com|googletagmanager\.com|gtag\s*\(|fbq\s*\(/i', $html );
		$body = $html; if ( preg_match( '~<body\b[^>]*>(.*?)</body>~is', $html, $body_match ) ) { $body = $body_match[1]; } $body = preg_replace( '~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $body ); $out['text_content'] = $decode( $body );
		$out['word_count'] = str_word_count( $out['text_content'] );
		return $out;
	}

	private static function page_issues( $p ) {
		$out = array(); $url = $p['url'];
		if ( $p['status_code'] >= 400 ) { $out[] = self::issue( 'error', 'http-' . $p['status_code'], 'HTTP error response', $url, 'This URL returned HTTP ' . $p['status_code'] . '.', 'Check whether the page should exist. Fix the server/configuration issue or redirect the URL to the most relevant replacement. Avoid blanket redirects to the homepage.' ); }
		if ( '' === $p['title'] ) { $out[] = self::issue( 'error', 'missing-title', 'Missing title tag', $url, 'No non-empty title tag was detected.', 'Edit the page in WordPress or your SEO plugin and add a unique, descriptive title that accurately reflects the page.' ); }
		elseif ( $p['title_length'] < 20 || $p['title_length'] > 60 ) { $out[] = self::issue( 'warning', 'title-length', 'Review title length', $url, 'Detected length: ' . $p['title_length'] . ' characters.', 'Review the title for clarity and uniqueness. Length is a guideline, not a strict ranking rule; search engines may rewrite displayed titles.' ); }
		if ( '' === $p['description'] ) { $out[] = self::issue( 'warning', 'missing-description', 'Missing meta description', $url, 'No non-empty meta description was detected.', 'Add a page-specific summary in the SEO plugin or editor. Search engines may choose another snippet.' ); }
		elseif ( $p['description_length'] < 70 || $p['description_length'] > 160 ) { $out[] = self::issue( 'notice', 'description-length', 'Review meta description length', $url, 'Detected length: ' . $p['description_length'] . ' characters.', 'Check usefulness and clarity. Character length is a guideline, not a guarantee of how the snippet will appear.' ); }
		if ( ! empty( $p['h3_count'] ) && empty( $p['h2_count'] ) ) { $out[] = self::issue( 'notice', 'heading-level-gap', 'H3 headings found without H2 headings', $url, 'The page contains H3 headings but no H2 headings in the fetched HTML. This can indicate a skipped section level, though context matters.', 'Review the heading outline and use headings to represent the content hierarchy. Do not change heading levels purely for visual styling.' ); }
		if ( 0 === count( $p['h1'] ) ) { $out[] = self::issue( 'warning', 'missing-h1', 'No H1 heading detected', $url, 'No non-empty H1 element was found in the returned HTML.', 'Add a clear main heading in the editor or page builder. If JavaScript renders the heading, this crawler may not see it.' ); }
		elseif ( count( $p['h1'] ) > 1 ) { $out[] = self::issue( 'notice', 'multiple-h1', 'Multiple H1 headings detected', $url, 'Found ' . count( $p['h1'] ) . ' H1 elements.', 'Review the page outline so the main topic is clear. Multiple H1s are not automatically a penalty; inspect context.' ); }
		if ( ! $p['canonical'] ) { $out[] = self::issue( 'notice', 'missing-canonical', 'Canonical tag not detected', $url, 'No rel=canonical link was found in returned HTML.', 'Check whether the page needs an explicit canonical, particularly if similar/duplicate URLs exist. Not every page necessarily needs a manually added canonical.' ); }
		if ( $p['noindex'] ) { $out[] = self::issue( 'warning', 'noindex', 'Noindex directive detected', $url, 'A meta robots or X-Robots-Tag directive contains noindex.', 'If the page should appear in search, remove unintended noindex from SEO plugin/page settings or HTTP headers. Keep it for private or intentionally excluded pages.' ); }
		if ( ( $p['css_count'] + $p['js_count'] ) > 30 ) { $out[] = self::issue( 'warning', 'many-render-resources', 'Many CSS/JavaScript resources detected', $url, 'Detected ' . $p['css_count'] . ' stylesheet links and ' . $p['js_count'] . ' external scripts in returned HTML. File sizes and render impact were not measured by this crawler.', 'Use PageSpeed Insights to identify render-blocking resources and unused CSS/JavaScript. Remove unused assets, defer non-critical scripts, and avoid combining files blindly when HTTP/2 or caching changes the trade-off.' ); }
		if ( $p['missing_alt'] ) { $out[] = self::issue( 'warning', 'missing-alt', 'Images missing alt attributes', $url, $p['missing_alt'] . ' of ' . $p['images'] . ' images have no alt attribute.', 'Add meaningful alt text to informative images; use empty alt for decorative images. Edit images in Media Library or the page editor. Avoid keyword stuffing.' ); }
		if ( empty( $p['viewport'] ) ) { $out[] = self::issue( 'warning', 'mobile-viewport', 'Mobile viewport meta tag not detected', $url, 'No viewport meta tag was detected in the returned HTML. This is a basic mobile-readiness signal, not a full mobile usability test.', 'Add a suitable viewport meta tag in the page template and test the page on real mobile devices.'); }
		if ( empty( $p['html_lang'] ) ) { $out[] = self::issue( 'notice', 'html-lang', 'HTML language attribute missing', $url, 'The root HTML element has no lang attribute.', 'Set the correct page language on the HTML element to help assistive technology and language processing.'); }
		if ( ! empty( $p['empty_anchor_count'] ) ) { $out[] = self::issue( 'warning', 'empty-link-text', 'Links with no accessible text detected', $url, $p['empty_anchor_count'] . ' links have no visible text, image alt, aria-label or title signal.', 'Give links descriptive visible text or an accessible name so users and assistive technologies can understand their purpose.'); }
		if ( ! empty( $p['unlabelled_controls'] ) ) { $out[] = self::issue( 'warning', 'unlabelled-form-controls', 'Form controls may lack labels', $url, $p['unlabelled_controls'] . ' form controls did not have a matching label or accessible-name attribute detectable by this check.', 'Associate each input, select and textarea with a visible label or a suitable accessible name. This is a basic heuristic and may produce false positives.'); }
		if ( (int) $p['word_count'] > 0 && (int) $p['word_count'] < 100 ) { $out[] = self::issue( 'notice', 'short-visible-content', 'Very little visible text detected', $url, 'Approximately ' . (int) $p['word_count'] . ' words were extracted from returned HTML. This does not assess content quality and may miss JavaScript-rendered content or image-based pages.', 'Check whether the page gives visitors enough useful information for its purpose. Do not add filler text just to reach a word count.'); }
		if ( ! empty( $p['schema_invalid'] ) ) { $out[] = self::issue( 'warning', 'invalid-jsonld', 'JSON-LD structured data could not be parsed', $url, $p['schema_invalid'] . ' JSON-LD script block(s) contained invalid JSON or an unsupported structure.', 'Validate the structured data with the Schema Markup Validator or Rich Results Test and fix syntax errors.'); }
		elseif ( empty( $p['schema_count'] ) ) { $out[] = self::issue( 'notice', 'schema-not-detected', 'JSON-LD structured data not detected', $url, 'No JSON-LD script block was detected. Microdata or RDFa may still be present; structured data is not required on every page.', 'If appropriate for this page type, consider valid structured data and validate it with a supported testing tool.'); }
		if ( ! empty( $p['mixed_content_count'] ) && 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) { $out[] = self::issue( 'warning', 'mixed-content-signals', 'HTTP resources referenced by an HTTPS page', $url, $p['mixed_content_count'] . ' src/href references appear to use HTTP in the returned HTML. This simple scan may include harmless links and does not prove browser-blocked mixed content.', 'Review HTTP image, script, stylesheet and iframe references and use HTTPS versions where available.'); }
		if ( $p['canonical'] ) {
			$canon = self::absolute_url( $p['canonical'], $url );
			$chost = strtolower( (string) wp_parse_url( $canon, PHP_URL_HOST ) );
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( $chost && $chost !== $host ) { $out[] = self::issue( 'warning', 'external-canonical', 'Canonical points to another host', $url, 'Canonical host: ' . $chost, 'Verify that this cross-domain canonical is intentional. Otherwise update the canonical in your SEO plugin or theme.' ); }
		}
		return $out;
	}
	private static function issue( $severity, $code, $title, $url, $detail, $fix ) {
		$priority = 'error' === $severity ? 'High' : ( 'warning' === $severity ? 'Medium' : 'Low' );
		$effort = ( false !== strpos( $code, 'https' ) || false !== strpos( $code, 'invalid-jsonld' ) ) ? 'Medium' : ( 'error' === $severity ? 'Medium' : 'Low' );
		return array( 'severity' => $severity, 'priority' => $priority, 'effort' => $effort, 'code' => $code, 'title' => $title, 'url' => $url, 'detail' => $detail, 'fix' => $fix );
	}
	private static function check_site_resources( $origin, $start ) {
		$issues = array(); $robots_url = trailingslashit( $origin ) . 'robots.txt'; $robots = self::fetch_small( $robots_url, 24000 ); $robots_status = is_wp_error( $robots ) ? 0 : $robots['status']; $robots_body = is_wp_error( $robots ) ? '' : $robots['body'];
		if ( 200 !== $robots_status ) { $issues[] = self::issue( 'notice', 'robots-txt', 'robots.txt endpoint not confirmed', $robots_url, 'The endpoint did not return HTTP 200 in this test. A missing robots.txt is not automatically an SEO error.', 'Open robots.txt manually and check whether crawl rules are needed. Avoid unintentionally blocking important pages.'); }
		elseif ( self::robots_blocks_all( $robots_body ) ) { $issues[] = self::issue( 'warning', 'robots-block-all', 'robots.txt may block the whole site', $robots_url, 'A broad Disallow: / rule appears near a wildcard user-agent. Rule grouping can affect interpretation, so confirm the exact robots.txt semantics manually.', 'Review robots.txt and remove broad blocking only if public pages are intended to be crawlable.'); }
		$sitemap_candidates = array(); if ( preg_match_all( '/^\s*Sitemap:\s*(https?:\/\/\S+)/mi', $robots_body, $matches ) ) { foreach ( $matches[1] as $candidate ) { $candidate = esc_url_raw( $candidate, array( 'http', 'https' ) ); if ( $candidate && self::same_host( $candidate, strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) ) ) ) { $sitemap_candidates[] = $candidate; } } }
		$sitemap_candidates[] = trailingslashit( $origin ) . 'sitemap_index.xml'; $sitemap_candidates[] = trailingslashit( $origin ) . 'sitemap.xml'; $sitemap_candidates[] = trailingslashit( $origin ) . 'wp-sitemap.xml';
		$sitemap_candidates = array_values( array_unique( array_filter( $sitemap_candidates ) ) ); $sitemap_status = 0; $sitemap_url = ''; $valid_sitemap = false;
		foreach ( array_slice( $sitemap_candidates, 0, 4 ) as $candidate ) { if ( microtime( true ) - $start > self::MAX_SECONDS ) { break; } $res = self::fetch_small( $candidate, 64000 ); if ( is_wp_error( $res ) ) { continue; } if ( 200 === $res['status'] ) { $sitemap_status = 200; $sitemap_url = $candidate; if ( preg_match( '/<(?:urlset|sitemapindex)\b/i', $res['body'] ) ) { $valid_sitemap = true; break; } } }
		if ( ! $sitemap_url ) { $issues[] = self::issue( 'notice', 'sitemap-not-confirmed', 'Sitemap not confirmed at common locations', trailingslashit( $origin ), 'No sitemap was confirmed at the common URLs or Sitemap directives found in robots.txt. A CMS or SEO plugin may use a different path.', 'Check your SEO plugin or CMS for the actual sitemap URL and submit it in Search Console if appropriate.'); }
		elseif ( ! $valid_sitemap ) { $issues[] = self::issue( 'warning', 'sitemap-format', 'Sitemap response did not look like a standard XML sitemap', $sitemap_url, 'The endpoint returned HTTP 200 but no urlset or sitemapindex element was found in the limited response sample.', 'Open the sitemap URL and confirm it returns valid XML sitemap content rather than an HTML page or access-denied screen.'); }
		return array( 'issues' => $issues, 'robots_status' => $robots_status, 'sitemap_status' => $sitemap_status, 'sitemap_url' => $sitemap_url );
	}
	private static function robots_blocks_all( $robots_body ) {
		$groups = preg_split( '/(?=^\s*User-agent:)/mi', (string) $robots_body );
		foreach ( $groups as $group ) {
			if ( preg_match( '/^\s*User-agent:\s*\*\s*$/mi', $group ) && preg_match( '/^\s*Disallow:\s*\/\s*$/mi', $group ) ) { return true; }
		}
		return false;
	}
	private static function fetch_small( $url, $limit ) {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 4, 'redirection' => 2, 'limit_response_size' => (int) $limit, 'user-agent' => 'TECH-AKHIL-SEO-Audit/' . TA_SACT_VERSION, 'headers' => array( 'Accept' => 'text/plain,application/xml,text/xml,*/*;q=0.5' ) ) );
		if ( is_wp_error( $response ) ) { return $response; }
		return array( 'status' => (int) wp_remote_retrieve_response_code( $response ), 'body' => (string) wp_remote_retrieve_body( $response ) );
	}
	private static function site_signals( $pages ) {
		$analytics = false; $forms = false; $controls = 0; $words = 0; $schema = 0; $types = array();
		foreach ( $pages as $page ) { $analytics = $analytics || ! empty( $page['tracking_detected'] ); $forms = $forms || ! empty( $page['forms'] ); $controls += (int) ( $page['unlabelled_controls'] ?? 0 ); $words += (int) ( $page['word_count'] ?? 0 ); $schema += (int) ( $page['schema_count'] ?? 0 ); $types = array_merge( $types, $page['schema_types'] ?? array() ); }
		return array( 'analytics_detected' => $analytics, 'forms_detected' => $forms, 'unlabelled_controls' => $controls, 'approx_words' => $words, 'jsonld_blocks' => $schema, 'schema_types' => array_values( array_unique( $types ) ) );
	}
	/**
	 * Check a sampled internal URL without automatically following redirects.
	 * Only same-host redirects are followed; cross-host redirects are reported
	 * as a single hop and are not fetched by this checker.
	 */
	private static function url_status_details( $url, $expected_host ) {
		$current = $url; $redirects = array(); $visited = array(); $status = 0; $error = '';
		for ( $hop = 0; $hop <= 4; $hop++ ) {
			$current = self::normalize_url( $current );
			if ( ! $current || isset( $visited[ $current ] ) ) { $error = 'A redirect loop or invalid redirect target was detected.'; break; }
			$visited[ $current ] = true;
			$r = wp_safe_remote_head( $current, array( 'timeout' => 3, 'redirection' => 0, 'reject_unsafe_urls' => true, 'limit_response_size' => 1024, 'user-agent' => 'TECH-AKHIL-SEO-Audit/' . TA_SACT_VERSION ) );
			if ( is_wp_error( $r ) ) { $r = wp_safe_remote_get( $current, array( 'timeout' => 3, 'redirection' => 0, 'reject_unsafe_urls' => true, 'limit_response_size' => 1024, 'user-agent' => 'TECH-AKHIL-SEO-Audit/' . TA_SACT_VERSION, 'headers' => array( 'Accept' => 'text/html,*/*;q=0.5' ) ) ); }
			if ( is_wp_error( $r ) ) { $error = 'Request failed: ' . $r->get_error_message(); break; }
			$status = (int) wp_remote_retrieve_response_code( $r );
			if ( ! in_array( $status, array( 301, 302, 303, 307, 308 ), true ) ) { break; }
			$headers = wp_remote_retrieve_headers( $r ); $location = '';
			if ( is_object( $headers ) && isset( $headers['location'] ) ) { $location = (string) $headers['location']; }
			elseif ( is_array( $headers ) && isset( $headers['location'] ) ) { $location = (string) $headers['location']; }
			if ( '' === trim( $location ) ) { $error = 'The server returned a redirect status without a readable Location header.'; break; }
			$next = self::absolute_url( $location, $current );
			if ( ! $next ) { $error = 'The redirect target was not a valid HTTP/HTTPS URL.'; break; }
			$redirects[] = array( 'from' => $current, 'to' => $next, 'status' => $status );
			if ( ! self::same_host( $next, $expected_host ) ) { $current = $next; $status = 0; $error = 'Redirect leaves the audited host; destination was not fetched.'; break; }
			if ( $hop >= 3 ) { $status = 0; $error = 'Redirect limit reached; remaining chain was not followed.'; break; }
			$current = $next;
		}
		return array( 'status' => $status, 'redirects' => $redirects, 'final_url' => $current, 'error' => $error );
	}
	private static function normalize_url( $url ) {
		$url = strtok( (string) $url, '#' ); $p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['host'] ) ) { return ''; }
		return strtolower( $p['scheme'] ) . '://' . strtolower( $p['host'] ) . ( isset( $p['path'] ) && $p['path'] ? $p['path'] : '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
	}
	private static function absolute_url( $href, $base ) {
		$href = trim( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ) );
		if ( ! $href || preg_match( '~^(?:#|mailto:|tel:|javascript:|data:)~i', $href ) ) { return ''; }
		$b = wp_parse_url( $base ); if ( ! is_array( $b ) || empty( $b['host'] ) ) { return ''; }
		if ( 0 === strpos( $href, '//' ) ) { $href = $b['scheme'] . ':' . $href; }
		elseif ( ! preg_match( '#^https?://#i', $href ) ) {
			if ( 0 === strpos( $href, '/' ) ) { $href = $b['scheme'] . '://' . $b['host'] . $href; }
			else { $path = isset( $b['path'] ) ? $b['path'] : '/'; $href = $b['scheme'] . '://' . $b['host'] . trailingslashit( dirname( $path ) ) . $href; }
		}
		$href = esc_url_raw( $href, array( 'http', 'https' ) );
		return $href ? self::normalize_url( $href ) : '';
	}
	private static function same_host( $url, $host ) {
		$p = wp_parse_url( $url );
		return is_array( $p ) && isset( $p['host'] ) && strtolower( $p['host'] ) === $host && in_array( strtolower( (string) $p['scheme'] ), array( 'http', 'https' ), true );
	}
	private static function slug( $url ) { $p = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ); if ( '' === $p ) { return '(homepage)'; } $parts = explode( '/', $p ); return (string) end( $parts ); }
}
