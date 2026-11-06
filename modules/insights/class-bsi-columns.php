<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin-only analytics columns on the listings screen (Products → All Products).
 *
 * These show the data that may not be published on the front end (Tranco rank, the visit
 * estimate, RDAP and Open PageRank) plus uptime. They are hidden by default and switched on
 * under Screen Options, placed right after the "Website Opens" column when it exists.
 */
class BSI_Columns {

	private static $cache = array();

	public static function init() {
		foreach ( BSI_Cron::post_types() as $type ) {
			// After the Generator's "Website Opens" column (priority 30).
			add_filter( 'manage_edit-' . $type . '_columns', array( __CLASS__, 'columns' ), 40 );
			add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		}
		add_filter( 'default_hidden_columns', array( __CLASS__, 'hidden_by_default' ), 10, 2 );
		add_action( 'admin_head', array( __CLASS__, 'style' ) );
	}

	private static function labels() {
		return array(
			'bsi_rank'      => 'Global Rank',
			'bsi_visits'    => 'Est. Visits',
			'bsi_uptime'    => 'Uptime (30d)',
			'bsi_age'       => 'Domain Age',
			'bsi_expires'   => 'Domain Expires',
			'bsi_registrar' => 'Registrar',
			'bsi_opr'       => 'OPR Score',
		);
	}

	public static function columns( $columns ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $columns;
		}
		$after  = isset( $columns['bigspy_website_opens'] ) ? 'bigspy_website_opens' : 'name';
		$output = array();
		foreach ( $columns as $key => $label ) {
			$output[ $key ] = $label;
			if ( $after === $key ) {
				$output = array_merge( $output, self::labels() );
			}
		}
		// Fallback when neither anchor column exists.
		return array_merge( $output, array_diff_key( self::labels(), $output ) );
	}

	public static function hidden_by_default( $hidden, $screen ) {
		if ( $screen && in_array( $screen->post_type, BSI_Cron::post_types(), true ) && 'edit' === $screen->base ) {
			$hidden = array_merge( (array) $hidden, array_keys( self::labels() ) );
		}
		return $hidden;
	}

	public static function cell( $column, $post_id ) {
		if ( ! isset( self::labels()[ $column ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$row = self::row( $post_id );
		if ( ! $row ) {
			echo '<span class="bsi-col-muted">—</span>';
			return;
		}
		$data = $row['data'];

		switch ( $column ) {
			case 'bsi_rank':
				if ( empty( $data['tranco']['rank'] ) ) {
					self::empty_cell( $row );
					break;
				}
				echo '<strong>#' . esc_html( number_format( (int) $data['tranco']['rank'] ) ) . '</strong>';
				break;

			case 'bsi_visits':
				if ( empty( $data['visits']['low'] ) ) {
					self::empty_cell( $row );
					break;
				}
				echo esc_html( BSI_Estimator::short_number( $data['visits']['low'] ) . '–' . BSI_Estimator::short_number( $data['visits']['high'] ) );
				echo '<span class="bsi-col-muted">estimate / month</span>';
				break;

			case 'bsi_uptime':
				$status = BSI_Monitor::summary( $row['domain'] );
				if ( ! $status ) {
					self::empty_cell( $row );
					break;
				}
				$state = $status['last_ok'] ? 'Online' : ( $status['last_code'] ? 'Error ' . $status['last_code'] : 'Unreachable' );
				echo '<strong class="' . ( $status['last_ok'] ? 'bsi-col-up' : 'bsi-col-down' ) . '">' . esc_html( number_format( $status['uptime'], 2 ) . '%' ) . '</strong>';
				echo '<span class="bsi-col-muted">' . esc_html( $state . ( null !== $status['avg_ms'] ? ' · ' . number_format( $status['avg_ms'] ) . ' ms' : '' ) ) . '</span>';
				break;

			case 'bsi_age':
				if ( empty( $data['rdap']['registered'] ) ) {
					self::empty_cell( $row );
					break;
				}
				$years = ( time() - strtotime( $data['rdap']['registered'] ) ) / YEAR_IN_SECONDS;
				echo esc_html( $years >= 1 ? sprintf( '%d yrs', floor( $years ) ) : '< 1 yr' );
				echo '<span class="bsi-col-muted">' . esc_html( 'since ' . gmdate( 'Y', strtotime( $data['rdap']['registered'] ) ) ) . '</span>';
				break;

			case 'bsi_expires':
				if ( empty( $data['rdap']['expires'] ) ) {
					self::empty_cell( $row );
					break;
				}
				$days = (int) floor( ( strtotime( $data['rdap']['expires'] ) - time() ) / DAY_IN_SECONDS );
				echo '<span class="' . ( $days < 60 ? 'bsi-col-down' : '' ) . '">' . esc_html( $data['rdap']['expires'] ) . '</span>';
				break;

			case 'bsi_registrar':
				if ( empty( $data['rdap']['registrar'] ) ) {
					self::empty_cell( $row );
					break;
				}
				echo esc_html( $data['rdap']['registrar'] );
				break;

			case 'bsi_opr':
				if ( ! isset( $data['authority']['score'] ) || null === $data['authority']['score'] ) {
					echo '<span class="bsi-col-muted">' . esc_html( BSI_Settings::get( 'openpagerank_key' ) ? 'waiting' : 'no key' ) . '</span>';
					break;
				}
				echo esc_html( number_format( (float) $data['authority']['score'], 2 ) ) . '<span class="bsi-col-muted">/ 10</span>';
				break;
		}
	}

	/** Domain and stored data for one listing, looked up once per request. */
	private static function row( $post_id ) {
		if ( array_key_exists( $post_id, self::$cache ) ) {
			return self::$cache[ $post_id ];
		}
		$domain = BSI_Domain::for_post( $post_id, false );
		$site   = '' !== $domain ? BSI_Store::get_site( $domain ) : null;
		self::$cache[ $post_id ] = $site ? array(
			'domain'  => $domain,
			'data'    => BSI_Store::drop_forbidden( is_array( $site['data'] ) ? $site['data'] : array() ),
			'fetched' => ! empty( $site['fetched_at'] ),
		) : null;
		return self::$cache[ $post_id ];
	}

	private static function empty_cell( array $row ) {
		echo '<span class="bsi-col-muted">' . esc_html( $row['fetched'] ? 'no data' : 'waiting' ) . '</span>';
	}

	public static function style() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, BSI_Cron::post_types(), true ) ) {
			return;
		}
		echo '<style>[class*="column-bsi_"]{width:110px}.column-bsi_registrar{width:140px}.bsi-col-muted{display:block;margin-top:2px;color:#646970;font-size:11px}.bsi-col-up{color:#008a20}.bsi-col-down{color:#b32d2e}</style>';
	}
}
