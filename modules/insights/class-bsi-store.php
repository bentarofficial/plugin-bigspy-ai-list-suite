<?php
defined( 'ABSPATH' ) || exit;

/**
 * Two custom tables:
 *   bsi_sites  — one row per domain with the collected insights as JSON.
 *   bsi_checks — uptime/response-time samples from the monitor.
 */
class BSI_Store {

	/**
	 * Collected for the site owner but never printed on the front end, because the terms do not
	 * clearly allow public display on a commercial site: Tranco rank and the visit estimate
	 * derived from it, RDAP registration data and Open PageRank.
	 */
	const ADMIN_ONLY_KEYS = array( 'tranco', 'visits', 'rdap', 'authority' );

	/** Never collected or stored: Cloudflare Radar data is CC BY-NC 4.0 (no commercial use). */
	const FORBIDDEN_KEYS = array( 'cloudflare' );

	public static function drop_forbidden( array $data ) {
		return self::without( $data, self::FORBIDDEN_KEYS );
	}

	/** Data that may appear on public listing pages. */
	public static function public_data( array $data ) {
		return self::without( $data, array_merge( self::FORBIDDEN_KEYS, self::ADMIN_ONLY_KEYS ) );
	}

	private static function without( array $data, array $keys ) {
		foreach ( $keys as $key ) {
			unset( $data[ $key ] );
			if ( isset( $data['sources'] ) && is_array( $data['sources'] ) ) {
				unset( $data['sources'][ $key ] );
			}
		}
		return $data;
	}

	/** One-time removal of Cloudflare Radar data and its token stored by versions before 0.3.0. */
	public static function purge_forbidden() {
		if ( get_option( 'bsi_purged_forbidden' ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT domain, data FROM ' . self::sites_table() . ' WHERE data IS NOT NULL', ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$data = json_decode( (string) $row['data'], true );
			if ( ! is_array( $data ) || ! isset( $data['cloudflare'] ) ) {
				continue;
			}
			$wpdb->update( self::sites_table(), array( 'data' => wp_json_encode( self::drop_forbidden( $data ) ) ), array( 'domain' => $row['domain'] ) );
		}
		$settings = get_option( BSI_Settings::OPTION, array() );
		if ( is_array( $settings ) && isset( $settings['cloudflare_token'] ) ) {
			unset( $settings['cloudflare_token'] );
			update_option( BSI_Settings::OPTION, $settings );
		}
		update_option( 'bsi_purged_forbidden', 1, false );
	}

	public static function sites_table() {
		global $wpdb;
		return $wpdb->prefix . 'bsi_sites';
	}

	public static function checks_table() {
		global $wpdb;
		return $wpdb->prefix . 'bsi_checks';
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bsi_db_version' ) === BSI_DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$sites   = self::sites_table();
		$checks  = self::checks_table();

		dbDelta(
			"CREATE TABLE {$sites} (
  domain varchar(191) NOT NULL,
  data longtext NULL,
  requested_at datetime NOT NULL,
  fetched_at datetime NULL,
  checked_at datetime NULL,
  PRIMARY KEY  (domain),
  KEY fetched_at (fetched_at),
  KEY checked_at (checked_at)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$checks} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  domain varchar(191) NOT NULL,
  checked_at datetime NOT NULL,
  http_code smallint(5) unsigned NOT NULL DEFAULT 0,
  response_ms int(10) unsigned NOT NULL DEFAULT 0,
  ok tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY domain_time (domain, checked_at)
) {$charset};"
		);
		update_option( 'bsi_db_version', BSI_DB_VERSION );
	}

	/** Registers a domain so the collector and monitor pick it up. */
	public static function ensure_site( $domain ) {
		global $wpdb;
		if ( '' === $domain ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::sites_table() . ' (domain, requested_at) VALUES (%s, %s)',
				$domain,
				current_time( 'mysql', true )
			)
		);
	}

	public static function get_site( $domain ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::sites_table() . ' WHERE domain = %s', $domain ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['data'] = $row['data'] ? json_decode( $row['data'], true ) : array();
		if ( ! is_array( $row['data'] ) ) {
			$row['data'] = array();
		}
		return $row;
	}

	public static function save_data( $domain, array $data ) {
		global $wpdb;
		self::ensure_site( $domain );
		$wpdb->update(
			self::sites_table(),
			array(
				'data'       => wp_json_encode( $data ),
				'fetched_at' => current_time( 'mysql', true ),
			),
			array( 'domain' => $domain )
		);
	}

	/** Domains never fetched first, then the stalest ones. */
	public static function due_for_refresh( $limit, $days ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT domain FROM ' . self::sites_table() . ' WHERE fetched_at IS NULL OR fetched_at < %s ORDER BY fetched_at IS NOT NULL, fetched_at ASC LIMIT %d',
				$cutoff,
				$limit
			)
		);
	}

	public static function due_for_check( $limit, $minutes ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS );
		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT domain FROM ' . self::sites_table() . ' WHERE checked_at IS NULL OR checked_at < %s ORDER BY checked_at IS NOT NULL, checked_at ASC LIMIT %d',
				$cutoff,
				$limit
			)
		);
	}

	public static function add_check( $domain, $http_code, $response_ms, $ok ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			self::checks_table(),
			array(
				'domain'      => $domain,
				'checked_at'  => $now,
				'http_code'   => (int) $http_code,
				'response_ms' => (int) $response_ms,
				'ok'          => $ok ? 1 : 0,
			),
			array( '%s', '%s', '%d', '%d', '%d' )
		);
		$wpdb->update( self::sites_table(), array( 'checked_at' => $now ), array( 'domain' => $domain ) );
	}

	public static function checks( $domain, $days = 30 ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT checked_at, http_code, response_ms, ok FROM ' . self::checks_table() . ' WHERE domain = %s AND checked_at >= %s ORDER BY checked_at ASC',
				$domain,
				$cutoff
			),
			ARRAY_A
		);
	}

	public static function prune_checks( $days = 90 ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::checks_table() . ' WHERE checked_at < %s', $cutoff ) );
	}

	public static function counts() {
		global $wpdb;
		$sites = self::sites_table();
		return array(
			'sites'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sites}" ),
			'fetched' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sites} WHERE fetched_at IS NOT NULL" ),
			'checks'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::checks_table() ),
		);
	}

	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::sites_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::checks_table() );
		delete_option( 'bsi_db_version' );
	}
}
