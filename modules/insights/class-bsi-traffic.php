<?php
defined( 'ABSPATH' ) || exit;

/**
 * Copies each listing's estimated monthly visits (from its Tranco rank) into post meta
 * `_bsi_traffic_score`, so listings can be ORDERED by website traffic (Popular).
 *
 * The number itself stays admin-only (see BSI_Store::ADMIN_ONLY_KEYS): it is used for sorting,
 * never printed on the front end.
 */
class BSI_Traffic {

	const META  = '_bsi_traffic_score';
	const BATCH = 200;

	public static function init() {
		add_action( 'bsi_traffic_sync', array( __CLASS__, 'sync' ) );
		add_action( 'bsi_daily', array( __CLASS__, 'schedule_sync' ) );
	}

	/** Runs the sync shortly (once), e.g. after new data was collected. */
	public static function schedule_sync() {
		if ( ! wp_next_scheduled( 'bsi_traffic_sync', array( 0 ) ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'bsi_traffic_sync', array( 0 ) );
		}
	}

	/** Score of one listing: estimated monthly visits, 0 when unknown. */
	public static function score_for_post( $post_id ) {
		$domain = BSI_Domain::for_post( (int) $post_id, false );
		if ( '' === $domain ) {
			return 0;
		}
		$site = BSI_Store::get_site( $domain );
		if ( ! $site ) {
			return 0;
		}
		$visits = isset( $site['data']['visits'] ) ? (int) $site['data']['visits'] : 0;
		if ( $visits <= 0 && ! empty( $site['data']['tranco']['rank'] ) ) {
			$visits = (int) BSI_Estimator::monthly_visits( (int) $site['data']['tranco']['rank'] );
		}
		return max( 0, $visits );
	}

	/** Updates the meta for a batch of listings and reschedules itself until all are done. */
	public static function sync( $offset = 0 ) {
		$ids = get_posts(
			array(
				'post_type'        => BSI_Cron::post_types(),
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'posts_per_page'   => self::BATCH,
				'offset'           => (int) $offset,
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, self::META, self::score_for_post( $id ) );
		}
		if ( count( $ids ) === self::BATCH ) {
			wp_schedule_single_event( time() + 30, 'bsi_traffic_sync', array( (int) $offset + self::BATCH ) );
		} else {
			update_option( 'bsi_traffic_synced_at', current_time( 'mysql' ), false );
		}
	}
}
