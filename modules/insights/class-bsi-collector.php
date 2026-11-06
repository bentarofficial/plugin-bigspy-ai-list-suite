<?php
defined( 'ABSPATH' ) || exit;

/**
 * Collects insights for one domain. Runs from WP-Cron only, never while a visitor waits.
 * A source that fails keeps its previous value.
 *
 * Public (shown on listings): homepage probe, SSL certificate, Chrome UX Report (CC BY 4.0).
 * Admin only (see BSI_Store::ADMIN_ONLY_KEYS): Tranco rank and the visit estimate derived
 * from it, RDAP registration data, Open PageRank. They are never printed on the front end.
 * Cloudflare Radar (CC BY-NC 4.0) is not used at all.
 */
class BSI_Collector {

	const HISTORY_DAYS = 730;

	private static $last_tranco_call = 0.0;

	public static function refresh( $domain ) {
		$site = BSI_Store::get_site( $domain );
		$data = $site ? $site['data'] : array();

		$data['domain']  = $domain;
		$data['sources'] = isset( $data['sources'] ) && is_array( $data['sources'] ) ? $data['sources'] : array();

		self::run( $data, 'tranco', array( __CLASS__, 'tranco' ), $domain );
		self::run( $data, 'rdap', array( __CLASS__, 'rdap' ), $domain );
		self::run( $data, 'site', array( __CLASS__, 'homepage' ), $domain );

		$host = ! empty( $data['site']['final_url'] ) ? wp_parse_url( $data['site']['final_url'], PHP_URL_HOST ) : $domain;
		self::run( $data, 'ssl', array( __CLASS__, 'ssl' ), $host ? $host : $domain );

		if ( BSI_Settings::get( 'google_api_key' ) ) {
			$origin = ! empty( $data['site']['final_url'] ) ? self::origin( $data['site']['final_url'] ) : 'https://' . $domain;
			self::run( $data, 'vitals', array( __CLASS__, 'crux' ), $origin );
		}
		if ( BSI_Settings::get( 'openpagerank_key' ) ) {
			self::run( $data, 'authority', array( __CLASS__, 'openpagerank' ), $domain );
		}

		$rank           = isset( $data['tranco']['rank'] ) ? (int) $data['tranco']['rank'] : 0;
		$data['visits'] = $rank > 0 ? BSI_Estimator::monthly_visits( $rank ) : null;

		$data = BSI_Store::drop_forbidden( apply_filters( 'bsi_collected_data', $data, $domain ) );
		BSI_Store::save_data( $domain, $data );
		return $data;
	}

	/** Calls one source; on success stores its result under $key, on failure keeps the old value. */
	private static function run( array &$data, $key, $callback, $arg ) {
		$result = call_user_func( $callback, $arg, isset( $data[ $key ] ) ? $data[ $key ] : null );
		$now    = gmdate( 'c' );
		if ( is_wp_error( $result ) ) {
			$data['sources'][ $key ] = array( 'ok' => false, 'at' => $now, 'error' => $result->get_error_message() );
			return;
		}
		$data[ $key ]            = $result;
		$data['sources'][ $key ] = array( 'ok' => true, 'at' => $now );
	}

	private static function get_json( $url, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout'    => 20,
				'user-agent' => BSI_Domain::USER_AGENT,
			)
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'bsi_http', sprintf( 'HTTP %d from %s', $code, wp_parse_url( $url, PHP_URL_HOST ) ) );
		}
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bsi_json', 'Invalid JSON from ' . wp_parse_url( $url, PHP_URL_HOST ) );
		}
		return $body;
	}

	/* ---------------------------------------------------------------- Tranco */

	public static function tranco( $domain, $previous ) {
		// The public API allows about one request per second.
		$wait = 1.1 - ( microtime( true ) - self::$last_tranco_call );
		if ( $wait > 0 ) {
			usleep( (int) ( $wait * 1e6 ) );
		}
		self::$last_tranco_call = microtime( true );

		$body = self::get_json( 'https://tranco-list.eu/api/ranks/domain/' . rawurlencode( $domain ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		// The API only returns about 30 days, so we keep our own longer history.
		$history = isset( $previous['history'] ) && is_array( $previous['history'] ) ? $previous['history'] : array();
		foreach ( isset( $body['ranks'] ) && is_array( $body['ranks'] ) ? $body['ranks'] : array() as $row ) {
			if ( isset( $row['date'], $row['rank'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['date'] ) ) {
				$history[ $row['date'] ] = (int) $row['rank'];
			}
		}
		ksort( $history );
		$history = array_slice( $history, -self::HISTORY_DAYS, null, true );

		$rank = null;
		if ( $history ) {
			$rank = (int) end( $history );
		}
		return array(
			'rank'    => $rank,
			'history' => $history,
		);
	}

	/* ------------------------------------------------------------------ RDAP */

	public static function rdap( $domain ) {
		$labels = explode( '.', $domain );
		$tld    = end( $labels );
		$base   = self::rdap_base( $tld );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$body = self::get_json( rtrim( $base, '/' ) . '/domain/' . rawurlencode( $domain ), array( 'headers' => array( 'Accept' => 'application/rdap+json' ) ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$info = array(
			'registered' => null,
			'expires'    => null,
			'updated'    => null,
			'registrar'  => null,
		);
		foreach ( isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array() as $event ) {
			if ( empty( $event['eventAction'] ) || empty( $event['eventDate'] ) ) {
				continue;
			}
			$date = substr( (string) $event['eventDate'], 0, 10 );
			if ( 'registration' === $event['eventAction'] ) {
				$info['registered'] = $date;
			} elseif ( 'expiration' === $event['eventAction'] ) {
				$info['expires'] = $date;
			} elseif ( 'last changed' === $event['eventAction'] ) {
				$info['updated'] = $date;
			}
		}
		foreach ( isset( $body['entities'] ) && is_array( $body['entities'] ) ? $body['entities'] : array() as $entity ) {
			if ( empty( $entity['roles'] ) || ! in_array( 'registrar', (array) $entity['roles'], true ) ) {
				continue;
			}
			foreach ( isset( $entity['vcardArray'][1] ) ? (array) $entity['vcardArray'][1] : array() as $field ) {
				if ( isset( $field[0], $field[3] ) && 'fn' === $field[0] && is_string( $field[3] ) ) {
					$info['registrar'] = $field[3];
				}
			}
		}
		return $info;
	}

	private static function rdap_base( $tld ) {
		$bootstrap = get_transient( 'bsi_rdap_bootstrap' );
		if ( ! is_array( $bootstrap ) ) {
			$body = self::get_json( 'https://data.iana.org/rdap/dns.json' );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$bootstrap = array();
			foreach ( isset( $body['services'] ) ? (array) $body['services'] : array() as $service ) {
				if ( empty( $service[0] ) || empty( $service[1][0] ) ) {
					continue;
				}
				foreach ( (array) $service[0] as $suffix ) {
					$bootstrap[ strtolower( $suffix ) ] = $service[1][0];
				}
			}
			set_transient( 'bsi_rdap_bootstrap', $bootstrap, WEEK_IN_SECONDS );
		}
		if ( empty( $bootstrap[ $tld ] ) ) {
			return new WP_Error( 'bsi_rdap', 'No RDAP server for .' . $tld );
		}
		return $bootstrap[ $tld ];
	}

	/* -------------------------------------------------------- Homepage probe */

	private static $tech_rules = array(
		'Platform'  => array(
			'WordPress'   => '#/wp-content/|/wp-includes/|content="WordPress#i',
			'Shopify'     => '#cdn\.shopify\.com|Shopify\.theme#i',
			'Wix'         => '#static\.wixstatic\.com|wix-warmup-data#i',
			'Webflow'     => '#data-wf-page|webflow\.js|assets\.website-files\.com#i',
			'Squarespace' => '#static1\.squarespace\.com#i',
			'Framer'      => '#framerusercontent\.com|framer-motion#i',
			'Ghost'       => '#content="Ghost#i',
			'HubSpot CMS' => '#hs-sites\.com|hubspot-cms#i',
		),
		'Framework' => array(
			'Next.js' => '#__NEXT_DATA__|/_next/static/#',
			'Nuxt'    => '#__NUXT__|/_nuxt/#',
			'Gatsby'  => '#___gatsby#',
			'Angular' => '#ng-version=#',
			'Vue.js'  => '#data-v-[0-9a-f]{6,}#',
			'Svelte'  => '#svelte-[a-z0-9]{5,}#',
			'Astro'   => '#astro-island|/_astro/#',
		),
		'Analytics' => array(
			'Google Analytics'   => '#googletagmanager\.com/gtag|google-analytics\.com#i',
			'Google Tag Manager' => '#googletagmanager\.com/gtm\.js|GTM-[A-Z0-9]{4,}#',
			'Hotjar'             => '#static\.hotjar\.com#i',
			'Microsoft Clarity'  => '#clarity\.ms#i',
			'Mixpanel'           => '#cdn\.mxpnl\.com|mixpanel#i',
			'Segment'            => '#cdn\.segment\.com#i',
			'Plausible'          => '#plausible\.io/js#i',
		),
		'Marketing' => array(
			'Meta Pixel'     => '#connect\.facebook\.net/[^"\']*fbevents#i',
			'TikTok Pixel'   => '#analytics\.tiktok\.com#i',
			'LinkedIn Insight' => '#snap\.licdn\.com#i',
			'HubSpot'        => '#js\.hs-scripts\.com|js\.hsforms\.net#i',
			'Intercom'       => '#widget\.intercom\.io|intercomcdn#i',
			'Crisp'          => '#client\.crisp\.chat#i',
			'Zendesk'        => '#static\.zdassets\.com#i',
		),
		'Payments'  => array(
			'Stripe' => '#js\.stripe\.com#i',
			'PayPal' => '#paypal\.com/sdk|paypalobjects\.com#i',
			'Paddle' => '#cdn\.paddle\.com#i',
		),
	);

	private static $header_rules = array(
		'cf-ray'           => 'Cloudflare',
		'x-vercel-id'      => 'Vercel',
		'x-nf-request-id'  => 'Netlify',
		'x-amz-cf-id'      => 'Amazon CloudFront',
		'x-github-request-id' => 'GitHub Pages',
		'x-fastly-request-id' => 'Fastly',
		'x-akamai-transformed' => 'Akamai',
		'x-wix-request-id' => 'Wix',
		'x-shopid'         => 'Shopify',
	);

	private static $social_rules = array(
		'facebook'  => '#^https?://(www\.)?facebook\.com/(?!sharer|share|dialog|plugins|tr\b)[^"\'\s?]+#i',
		'x'         => '#^https?://(www\.)?(twitter|x)\.com/(?!intent|share|home)[A-Za-z0-9_]+/?$#i',
		'linkedin'  => '#^https?://([a-z]+\.)?linkedin\.com/(company|in|school)/[^"\'\s?]+#i',
		'youtube'   => '#^https?://(www\.)?youtube\.com/(@|c/|channel/|user/)[^"\'\s?]+#i',
		'instagram' => '#^https?://(www\.)?instagram\.com/[A-Za-z0-9_.]+/?$#i',
		'tiktok'    => '#^https?://(www\.)?tiktok\.com/@[^"\'\s?]+#i',
		'github'    => '#^https?://(www\.)?github\.com/[A-Za-z0-9-]+/?$#i',
		'discord'   => '#^https?://(www\.)?(discord\.gg|discord\.com/invite)/[^"\'\s?]+#i',
		'telegram'  => '#^https?://(www\.)?t\.me/[^"\'\s?]+#i',
	);

	public static function homepage( $domain ) {
		$response = null;
		foreach ( array( 'https://' . $domain . '/', 'https://www.' . $domain . '/' ) as $url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 20,
					'redirection'         => 5,
					'user-agent'          => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 BigSpySiteInsights/0.1',
					'limit_response_size' => 1572864,
				)
			);
			if ( ! is_wp_error( $response ) ) {
				break;
			}
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$final = $url;
		if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$object = $response['http_response']->get_response_object();
			if ( is_object( $object ) && ! empty( $object->url ) ) {
				$final = $object->url;
			}
		}

		$headers = wp_remote_retrieve_headers( $response );
		$html    = (string) wp_remote_retrieve_body( $response );

		$site = array(
			'final_url'   => esc_url_raw( $final ),
			'status'      => (int) wp_remote_retrieve_response_code( $response ),
			'https'       => 0 === strpos( $final, 'https://' ),
			'hsts'        => ! empty( $headers['strict-transport-security'] ),
			'server'      => self::header_text( $headers, 'server' ),
			'title'       => '',
			'description' => '',
			'lang'        => '',
			'tech'        => array(),
			'social'      => array(),
		);

		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			$site['title'] = self::clean_text( $m[1], 160 );
		}
		$description = self::meta_content( $html, 'description' );
		if ( '' === $description ) {
			$description = self::meta_content( $html, 'og:description' );
		}
		$site['description'] = self::clean_text( $description, 300 );
		if ( preg_match( '#<html[^>]+lang=["\']?([a-zA-Z-]{2,10})#i', $html, $m ) ) {
			$site['lang'] = strtolower( $m[1] );
		}

		foreach ( self::$tech_rules as $category => $rules ) {
			foreach ( $rules as $name => $pattern ) {
				if ( preg_match( $pattern, $html ) ) {
					$site['tech'][ $category ][] = $name;
				}
			}
		}
		foreach ( self::$header_rules as $header => $name ) {
			if ( ! empty( $headers[ $header ] ) ) {
				$site['tech']['Hosting & CDN'][] = $name;
			}
		}
		$powered = self::header_text( $headers, 'x-powered-by' );
		$known = array();
		foreach ( $site['tech'] as $names ) {
			$known = array_merge( $known, $names );
		}
		if ( '' !== $powered && ! in_array( $powered, $known, true ) ) {
			$site['tech']['Hosting & CDN'][] = $powered;
		}
		foreach ( $site['tech'] as $category => $names ) {
			$site['tech'][ $category ] = array_values( array_unique( $names ) );
		}

		if ( preg_match_all( '#href=["\']([^"\']+)["\']#i', $html, $links ) ) {
			foreach ( $links[1] as $href ) {
				$href = html_entity_decode( $href, ENT_QUOTES );
				foreach ( self::$social_rules as $network => $pattern ) {
					if ( empty( $site['social'][ $network ] ) && preg_match( $pattern, $href ) ) {
						$site['social'][ $network ] = esc_url_raw( $href );
					}
				}
			}
		}
		return $site;
	}

	private static function header_text( $headers, $name ) {
		if ( empty( $headers[ $name ] ) ) {
			return '';
		}
		$value = $headers[ $name ];
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		return self::clean_text( (string) $value, 80 );
	}

	private static function meta_content( $html, $name ) {
		$quoted = preg_quote( $name, '#' );
		if ( preg_match( '#<meta[^>]+(?:name|property)=["\']' . $quoted . '["\'][^>]*content=["\']([^"\']*)#i', $html, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:name|property)=["\']' . $quoted . '["\']#i', $html, $m ) ) {
			return $m[1];
		}
		return '';
	}

	private static function clean_text( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > $max ) {
			return rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…';
		}
		return $text;
	}

	private static function origin( $url ) {
		$parts = wp_parse_url( $url );
		return ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . ( isset( $parts['host'] ) ? $parts['host'] : '' );
	}

	/* ------------------------------------------------------------------- SSL */

	public static function ssl( $host ) {
		if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
			return new WP_Error( 'bsi_ssl', 'OpenSSL is not available on this server' );
		}
		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'SNI_enabled'       => true,
					'peer_name'         => $host,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
				),
			)
		);
		$errno  = 0;
		$errstr = '';
		$socket = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $socket ) {
			return new WP_Error( 'bsi_ssl', $errstr ? $errstr : 'TLS connection failed' );
		}
		$params = stream_context_get_params( $socket );
		fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return new WP_Error( 'bsi_ssl', 'No certificate returned' );
		}
		$cert = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		if ( ! is_array( $cert ) ) {
			return new WP_Error( 'bsi_ssl', 'Unreadable certificate' );
		}
		$issuer = '';
		if ( ! empty( $cert['issuer']['O'] ) ) {
			$issuer = is_array( $cert['issuer']['O'] ) ? reset( $cert['issuer']['O'] ) : $cert['issuer']['O'];
		} elseif ( ! empty( $cert['issuer']['CN'] ) ) {
			$issuer = is_array( $cert['issuer']['CN'] ) ? reset( $cert['issuer']['CN'] ) : $cert['issuer']['CN'];
		}
		return array(
			'issuer'     => self::clean_text( $issuer, 80 ),
			'valid_from' => isset( $cert['validFrom_time_t'] ) ? gmdate( 'Y-m-d', (int) $cert['validFrom_time_t'] ) : null,
			'valid_to'   => isset( $cert['validTo_time_t'] ) ? gmdate( 'Y-m-d', (int) $cert['validTo_time_t'] ) : null,
		);
	}

	/* ------------------------------------------------------ Chrome UX Report */

	public static function crux( $origin ) {
		$key      = BSI_Settings::get( 'google_api_key' );
		$response = wp_remote_post(
			'https://chromeuxreport.googleapis.com/v1/records:queryRecord?key=' . rawurlencode( $key ),
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'origin' => $origin ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) {
			return new WP_Error( 'bsi_crux', 'Not enough Chrome users for field data' );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || empty( $body['record']['metrics'] ) ) {
			return new WP_Error( 'bsi_crux', 'HTTP ' . $code . ' from Chrome UX Report' );
		}
		$names  = array(
			'lcp'  => 'largest_contentful_paint',
			'inp'  => 'interaction_to_next_paint',
			'cls'  => 'cumulative_layout_shift',
			'fcp'  => 'first_contentful_paint',
			'ttfb' => 'experimental_time_to_first_byte',
		);
		$vitals = array( 'origin' => $origin );
		foreach ( $names as $short => $metric ) {
			$m = isset( $body['record']['metrics'][ $metric ] ) ? $body['record']['metrics'][ $metric ] : null;
			if ( ! $m || ! isset( $m['percentiles']['p75'] ) ) {
				continue;
			}
			$good = isset( $m['histogram'][0]['density'] ) ? (float) $m['histogram'][0]['density'] : null;
			$vitals[ $short ] = array(
				'p75'  => (float) $m['percentiles']['p75'],
				'good' => $good,
			);
		}
		if ( ! empty( $body['record']['collectionPeriod']['lastDate'] ) ) {
			$last             = $body['record']['collectionPeriod']['lastDate'];
			$vitals['period'] = sprintf( '%04d-%02d-%02d', $last['year'], $last['month'], $last['day'] );
		}
		return $vitals;
	}

	/* --------------------------------------------------------- Open PageRank */

	public static function openpagerank( $domain ) {
		$body = self::get_json(
			'https://openpagerank.com/api/v1.0/getPageRank?' . http_build_query( array( 'domains' => array( $domain ) ) ),
			array( 'headers' => array( 'API-OPR' => BSI_Settings::get( 'openpagerank_key' ) ) )
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$row = isset( $body['response'][0] ) ? $body['response'][0] : null;
		if ( ! $row || ( isset( $row['status_code'] ) && 200 !== (int) $row['status_code'] ) ) {
			return new WP_Error( 'bsi_opr', 'Domain not found in Open PageRank' );
		}
		return array(
			'score' => isset( $row['page_rank_decimal'] ) ? round( (float) $row['page_rank_decimal'], 2 ) : null,
			'rank'  => isset( $row['rank'] ) && '' !== $row['rank'] ? (int) $row['rank'] : null,
		);
	}
}
