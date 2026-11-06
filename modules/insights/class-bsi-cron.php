<?php
defined( 'ABSPATH' ) || exit;

/**
 * Background work. Visitors never wait for an external API:
 *   bsi_refresh     hourly      — refresh the stalest domains (a few per run).
 *   bsi_monitor     15 minutes  — uptime checks for domains that are due.
 *   bsi_daily       daily       — prune old uptime samples.
 *   bsi_resolve_post (single)   — find the domain behind one listing and fetch it the first time.
 *   bsi_queue_all    (single)   — walk every listing in batches (after activation or on request).
 */
class BSI_Cron {

	const REFRESH_PER_RUN = 5;
	const QUEUE_BATCH     = 20;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'bsi_refresh', array( __CLASS__, 'refresh_due' ) );
		add_action( 'bsi_monitor', array( 'BSI_Monitor', 'run' ) );
		add_action( 'bsi_daily', array( __CLASS__, 'daily' ) );
		add_action( 'bsi_resolve_post', array( __CLASS__, 'resolve_post' ) );
		add_action( 'bsi_queue_all', array( __CLASS__, 'queue_all' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );

		// Recreate missing events (e.g. after a migration that dropped the cron option).
		if ( ! wp_next_scheduled( 'bsi_refresh' ) ) {
			self::schedule();
		}
	}

	public static function schedules( $schedules ) {
		$schedules['bsi_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 minutes (AI List Suite)',
		);
		return $schedules;
	}

	public static function activate() {
		BSI_Store::maybe_upgrade();
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		self::schedule();
		self::start_queue_all();
	}

	public static function deactivate() {
		foreach ( array( 'bsi_refresh', 'bsi_monitor', 'bsi_daily', 'bsi_queue_all' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( 'bsi_refresh' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'bsi_refresh' );
		}
		if ( ! wp_next_scheduled( 'bsi_monitor' ) ) {
			wp_schedule_event( time() + 120, 'bsi_15min', 'bsi_monitor' );
		}
		if ( ! wp_next_scheduled( 'bsi_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bsi_daily' );
		}
	}

	public static function post_types() {
		return BSI_Settings::list_value( 'post_types' );
	}

	public static function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::post_types(), true ) || 'publish' !== $post->post_status ) {
			return;
		}
		self::queue_post( $post_id );
	}

	public static function queue_post( $post_id ) {
		$args = array( (int) $post_id );
		if ( ! wp_next_scheduled( 'bsi_resolve_post', $args ) ) {
			wp_schedule_single_event( time(), 'bsi_resolve_post', $args );
		}
	}

	public static function resolve_post( $post_id ) {
		$domain = BSI_Domain::for_post( (int) $post_id, true );
		if ( '' === $domain ) {
			return;
		}
		BSI_Store::ensure_site( $domain );
		$site = BSI_Store::get_site( $domain );
		if ( $site && empty( $site['fetched_at'] ) ) {
			BSI_Collector::refresh( $domain );
			BSI_Monitor::check( $domain );
		}
	}

	public static function refresh_due() {
		if ( get_transient( 'bsi_refresh_lock' ) ) {
			return 0;
		}
		set_transient( 'bsi_refresh_lock', 1, 15 * MINUTE_IN_SECONDS );
		$domains = BSI_Store::due_for_refresh( self::REFRESH_PER_RUN, (int) BSI_Settings::get( 'refresh_days' ) );
		foreach ( $domains as $domain ) {
			BSI_Collector::refresh( $domain );
		}
		delete_transient( 'bsi_refresh_lock' );
		// A full batch means more are waiting (first fill): continue soon instead of next hour.
		if ( count( $domains ) === self::REFRESH_PER_RUN ) {
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'bsi_refresh' );
		}
		return count( $domains );
	}

	public static function daily() {
		BSI_Store::prune_checks( 90 );
	}

	public static function start_queue_all() {
		wp_clear_scheduled_hook( 'bsi_queue_all' );
		wp_schedule_single_event( time(), 'bsi_queue_all', array( 0 ) );
	}

	/** Resolves listings in batches and reschedules itself until every listing has a domain. */
	public static function queue_all( $offset = 0 ) {
		$ids = get_posts(
			array(
				'post_type'        => self::post_types(),
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'posts_per_page'   => self::QUEUE_BATCH,
				'offset'           => (int) $offset,
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			$domain = BSI_Domain::for_post( $id, true );
			if ( '' !== $domain ) {
				BSI_Store::ensure_site( $domain );
			}
		}
		if ( count( $ids ) === self::QUEUE_BATCH ) {
			wp_schedule_single_event( time() + 30, 'bsi_queue_all', array( (int) $offset + self::QUEUE_BATCH ) );
		} else {
			// Everything is registered; start fetching now instead of waiting for the hourly run.
			wp_schedule_single_event( time() + 5, 'bsi_refresh' );
		}
		return count( $ids );
	}
}
