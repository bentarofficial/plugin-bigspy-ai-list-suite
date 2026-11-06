<?php
defined( 'ABSPATH' ) || exit;

/**
 * Our own uptime monitor: every listing's website is requested on a schedule and
 * the status code and response time are stored. This is measured by us, so it is exact.
 */
class BSI_Monitor {

	const PER_RUN = 20;

	public static function run() {
		$domains = BSI_Store::due_for_check( self::PER_RUN, (int) BSI_Settings::get( 'monitor_minutes' ) );
		foreach ( $domains as $domain ) {
			self::check( $domain );
		}
		return count( $domains );
	}

	public static function check( $domain ) {
		$site = BSI_Store::get_site( $domain );
		$url  = ! empty( $site['data']['site']['final_url'] ) ? $site['data']['site']['final_url'] : 'https://' . $domain . '/';

		$start    = microtime( true );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 5,
				'user-agent'          => BSI_Domain::USER_AGENT,
				'limit_response_size' => 32768,
			)
		);
		$ms   = (int) round( ( microtime( true ) - $start ) * 1000 );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		// Bot protection (403/429/503 challenge pages) still means the server answered.
		$ok = ( $code >= 200 && $code < 400 ) || in_array( $code, array( 401, 403, 429 ), true );

		BSI_Store::add_check( $domain, $code, $ms, $ok );
		return array(
			'code' => $code,
			'ms'   => $ms,
			'ok'   => $ok,
		);
	}

	/** Summary used by the front end: uptime %, average response, last state, hourly series. */
	public static function summary( $domain, $days = 30 ) {
		$rows = BSI_Store::checks( $domain, $days );
		if ( ! $rows ) {
			return null;
		}
		$ok_count = 0;
		$ok_ms    = array();
		$series   = array();
		foreach ( $rows as $row ) {
			if ( (int) $row['ok'] ) {
				$ok_count++;
				$ok_ms[] = (int) $row['response_ms'];
			}
			$series[ $row['checked_at'] ] = (int) $row['ok'] ? (int) $row['response_ms'] : null;
		}
		$last = end( $rows );
		return array(
			'uptime'     => round( 100 * $ok_count / count( $rows ), 2 ),
			'avg_ms'     => $ok_ms ? (int) round( array_sum( $ok_ms ) / count( $ok_ms ) ) : null,
			'checks'     => count( $rows ),
			'last_ok'    => (bool) (int) $last['ok'],
			'last_code'  => (int) $last['http_code'],
			'last_at'    => $last['checked_at'],
			'series'     => $series,
		);
	}
}
